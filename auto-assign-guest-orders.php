<?php
/**
 * Plugin Name: Auto Assign Guest Orders
 * Plugin URI: https://github.com/coded-letter/auto-assign-guest-orders
 * Description: Securely links WooCommerce guest orders to verified customer accounts with matching email addresses.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Coded Letter
 * Author URI: https://codedletter.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-assign-guest-orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links guest orders to matching WooCommerce customer accounts.
 */
final class Auto_Assign_Guest_Orders {
	/**
	 * User meta containing the email address verified by this plugin.
	 */
	const VERIFIED_EMAIL_META = '_aago_verified_email';

	/**
	 * User meta containing a hashed, expiring verification request.
	 */
	const VERIFICATION_META = '_aago_email_verification';

	/**
	 * Verification links expire after one day.
	 */
	const VERIFICATION_TTL = DAY_IN_SECONDS;

	/**
	 * Registers hooks that must be available before WooCommerce initializes.
	 */
	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'register_runtime_hooks' ), 20 );
	}

	/**
	 * Declares compatibility with High-Performance Order Storage.
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}

	/**
	 * Registers WooCommerce-dependent hooks after every plugin has loaded.
	 */
	public static function register_runtime_hooks() {
		add_action( 'admin_notices', array( __CLASS__, 'show_dependency_notice' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action(
			'woocommerce_checkout_order_created',
			array( __CLASS__, 'assign_new_guest_order' )
		);
		add_action(
			'woocommerce_store_api_checkout_order_processed',
			array( __CLASS__, 'assign_new_guest_order' )
		);
		add_action(
			'template_redirect',
			array( __CLASS__, 'maybe_process_verification' )
		);

		if ( function_exists( 'wc_update_new_customer_past_orders' ) ) {
			add_action( 'user_register', array( __CLASS__, 'assign_past_orders' ) );
			add_action( 'wp_login', array( __CLASS__, 'assign_past_orders_on_login' ), 10, 2 );
		}
	}

	/**
	 * Returns whether WooCommerce provides native customer email verification.
	 *
	 * @return bool
	 */
	private static function woocommerce_verifies_customer_email() {
		return class_exists(
			'\Automattic\WooCommerce\Internal\CustomerEmailVerification\CustomerEmailVerification'
		);
	}

	/**
	 * Links guest orders after registration on WooCommerce versions that do not
	 * provide native customer email verification.
	 *
	 * WooCommerce's API also refreshes download permissions and customer totals.
	 *
	 * @param int $user_id Registered user ID.
	 */
	public static function assign_past_orders( $user_id ) {
		if ( ! function_exists( 'wc_update_new_customer_past_orders' ) ) {
			return;
		}

		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
			return;
		}

		if ( self::woocommerce_verifies_customer_email() ) {
			return;
		}

		if ( ! self::is_account_email_verified( $user ) ) {
			self::send_verification_email( $user );
			return;
		}

		wc_update_new_customer_past_orders( $user_id );
	}

	/**
	 * Links guest orders after a successful login.
	 *
	 * @param string  $user_login User login.
	 * @param WP_User $user       Authenticated user.
	 */
	public static function assign_past_orders_on_login( $user_login, $user ) {
		unset( $user_login );

		if ( $user instanceof WP_User ) {
			self::assign_past_orders( $user->ID );
		}
	}

	/**
	 * Returns whether the user has proved ownership of the current account email.
	 *
	 * @param WP_User $user Customer account.
	 * @return bool
	 */
	private static function is_account_email_verified( $user ) {
		$verified = false;

		if (
			self::woocommerce_verifies_customer_email()
			&& function_exists( 'wc_get_container' )
			&& class_exists(
				'\Automattic\WooCommerce\Internal\CustomerEmailVerification\EmailVerificationService'
			)
		) {
			$service  = wc_get_container()->get(
				'\Automattic\WooCommerce\Internal\CustomerEmailVerification\EmailVerificationService'
			);
			$verified = $service->is_verified( $user->ID );
		} else {
			$verified_email = strtolower(
				(string) get_user_meta( $user->ID, self::VERIFIED_EMAIL_META, true )
			);
			$verified       = '' !== $verified_email
				&& $verified_email === strtolower( $user->user_email );
		}

		/**
		 * Filters whether a customer has verified the current account email.
		 *
		 * Integrations may return true only after an equivalent trusted
		 * verification flow has proved email ownership.
		 *
		 * @param bool    $verified Whether the email is verified.
		 * @param WP_User $user     Customer account.
		 */
		return (bool) apply_filters(
			'auto_assign_guest_orders_is_email_verified',
			$verified,
			$user
		);
	}

	/**
	 * Sends an expiring verification link when a recent one is not pending.
	 *
	 * @param WP_User $user Customer account.
	 */
	private static function send_verification_email( $user ) {
		$email_hash = self::get_email_hash( $user->user_email );
		$pending    = get_user_meta( $user->ID, self::VERIFICATION_META, true );

		if (
			is_array( $pending )
			&& isset( $pending['email_hash'], $pending['expires'] )
			&& hash_equals( $email_hash, (string) $pending['email_hash'] )
			&& time() < (int) $pending['expires']
		) {
			return;
		}

		$key  = wp_generate_password( 32, false, false );
		$data = array(
			'email_hash' => $email_hash,
			'key_hash'   => wp_hash_password( $key ),
			'expires'    => time() + self::VERIFICATION_TTL,
		);

		if ( false === update_user_meta( $user->ID, self::VERIFICATION_META, $data ) ) {
			do_action( 'auto_assign_guest_orders_verification_storage_failed', $user );
			return;
		}

		$url = add_query_arg(
			array(
				'aago_verify_email' => '1',
				'aago_user'         => $user->ID,
				'aago_key'          => $key,
			),
			home_url( '/' )
		);
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject   = sprintf(
			/* translators: %s: Site name. */
			__( '[%s] Confirm your email to link guest orders', 'auto-assign-guest-orders' ),
			$site_name
		);
		$message   = sprintf(
			/* translators: 1: Site name, 2: Verification URL. */
			__(
				"Confirm that this email belongs to your %1\$s account before guest orders are linked.\n\nSign in and confirm your email:\n%2\$s\n\nIf you did not create this account, ignore this email.",
				'auto-assign-guest-orders'
			),
			$site_name,
			$url
		);

		if ( ! wp_mail( $user->user_email, $subject, $message ) ) {
			delete_user_meta( $user->ID, self::VERIFICATION_META );
			do_action( 'auto_assign_guest_orders_verification_email_failed', $user );
		}
	}

	/**
	 * Verifies an email link only while signed in as the target account.
	 */
	public static function maybe_process_verification() {
		if ( ! isset( $_GET['aago_verify_email'], $_GET['aago_user'], $_GET['aago_key'] ) ) {
			return;
		}

		$user_id = absint( wp_unslash( $_GET['aago_user'] ) );
		$key     = sanitize_text_field( wp_unslash( $_GET['aago_key'] ) );
		$url     = add_query_arg(
			array(
				'aago_verify_email' => '1',
				'aago_user'         => $user_id,
				'aago_key'          => $key,
			),
			home_url( '/' )
		);

		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $url ) );
			exit;
		}

		if ( get_current_user_id() !== $user_id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$user    = get_userdata( $user_id );
		$pending = get_user_meta( $user_id, self::VERIFICATION_META, true );

		if (
			! $user instanceof WP_User
			|| ! is_array( $pending )
			|| ! isset( $pending['email_hash'], $pending['key_hash'], $pending['expires'] )
			|| time() > (int) $pending['expires']
			|| ! hash_equals(
				self::get_email_hash( $user->user_email ),
				(string) $pending['email_hash']
			)
			|| ! wp_check_password( $key, (string) $pending['key_hash'] )
		) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		update_user_meta(
			$user_id,
			self::VERIFIED_EMAIL_META,
			strtolower( $user->user_email )
		);
		delete_user_meta( $user_id, self::VERIFICATION_META );
		self::assign_past_orders( $user_id );

		$destination = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( 'orders' )
			: home_url( '/' );

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				__( 'Your email is verified and matching guest orders are now linked.', 'auto-assign-guest-orders' ),
				'success'
			);
		}

		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * Returns a site-bound hash of an account email.
	 *
	 * @param string $email Account email.
	 * @return string
	 */
	private static function get_email_hash( $email ) {
		return hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) );
	}

	/**
	 * Assigns a newly created guest checkout order to an existing account.
	 *
	 * @param WC_Order $order Checkout order.
	 */
	public static function assign_new_guest_order( $order ) {
		if ( ! $order instanceof WC_Order || 0 < (int) $order->get_customer_id() ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );

		if ( ! is_email( $email ) ) {
			return;
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! self::is_account_email_verified( $user ) ) {
			return;
		}

		$order->set_customer_id( $user->ID );
		$order->save();

		/**
		 * Fires after a new guest order is assigned to an existing account.
		 *
		 * @param WC_Order $order Assigned order.
		 * @param WP_User  $user  Matching customer.
		 */
		do_action( 'auto_assign_guest_orders_order_assigned', $order, $user );
	}

	/**
	 * Shows an actionable dependency notice on older WordPress versions.
	 */
	public static function show_dependency_notice() {
		if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Auto Assign Guest Orders requires WooCommerce to be installed and active.',
				'auto-assign-guest-orders'
			)
		);
	}
}

Auto_Assign_Guest_Orders::init();
