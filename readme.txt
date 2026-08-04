=== Auto Assign Guest Orders ===
Contributors: coded-letter
Tags: woocommerce, guest orders, customers, accounts, hpos
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Links WooCommerce guest orders to customer accounts with matching email addresses.

== Description ==

Auto Assign Guest Orders keeps WooCommerce order history connected when customers
check out before creating an account.

The plugin verifies account email ownership before linking orders. On
WooCommerce versions without native verification, the customer receives a
one-time link that must be opened while signed in to the same account. On newer
WooCommerce versions, the plugin respects the built-in verification flow.

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

Orders are linked by exact billing-email and account-email matching after the
account email has been verified.

== Changelog ==

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
