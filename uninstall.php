<?php
/**
 * Removes Auto Assign Guest Orders verification metadata.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, '_aago_verified_email', '', true );
delete_metadata( 'user', 0, '_aago_email_verification', '', true );
