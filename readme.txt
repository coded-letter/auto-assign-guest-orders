=== Auto Assign Guest Orders ===
Contributors: coded-letter
Tags: woocommerce, guest orders, customers, accounts, hpos
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Links WooCommerce guest orders to customer accounts with matching email addresses.

== Description ==

Auto Assign Guest Orders keeps WooCommerce order history connected when customers
check out before creating an account.

After a successful customer login, the plugin uses WooCommerce's native
exact-email relinker to attach matching guest orders. Registration-time and
new-checkout assignment continue to respect WooCommerce email verification.

New guest checkouts are assigned to an existing account when the billing email
matches a verified account. Classic checkout, Checkout Blocks, HPOS,
downloadable products, and headless WordPress authentication are supported.

The plugin has no settings, tracking, external requests, or build dependencies.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Auto Assign Guest Orders.
3. Keep WooCommerce active. The plugin works automatically.

== Frequently Asked Questions ==

= Does this support HPOS? =

Yes. The plugin uses WooCommerce order APIs and declares HPOS compatibility.

= Does it support Checkout Blocks? =

Yes. Both classic checkout and the Store API event used by Checkout Blocks are
supported.

= Does it store any data? =

It stores an expiring hashed verification request and the verified address in
WordPress user metadata. It also updates the native customer ID on matching
WooCommerce orders.

= How are customers matched? =

Past guest orders are linked by exact billing-email and account-email matching
after a successful login. Registration-time and new-checkout assignment require
the account email to be verified.

== Changelog ==

= 1.1.2 =
* Relinks exact-email guest orders immediately after successful classic or headless login.
* Keeps registration-time and new-checkout assignment behind email verification.

= 1.1.1 =
* Fixed past guest orders not appearing for customers already verified by WooCommerce.
* Requests WooCommerce's native verification email when linking is blocked.
* Added a public verification-status API for headless account pages.

= 1.1.0 =
* Replaced custom past-order queries with WooCommerce's native relinking API.
* Preserved downloadable-product permissions and customer statistics.
* Added Checkout Block and Store API support.
* Added HPOS compatibility and safe WooCommerce dependency handling.
* Added signed-in, expiring email verification for older WooCommerce versions.
* Respected WooCommerce's native customer email verification flow when present.
* Added public documentation and GPL licensing.

= 1.0.0 =
* Initial legacy prototype.
