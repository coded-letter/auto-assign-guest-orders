# Auto Assign Guest Orders

Auto Assign Guest Orders is a free WordPress plugin that links WooCommerce guest
orders to customer accounts with matching email addresses.

## Features

- Verifies account email ownership before linking any guest order
- Links matching past guest orders after verification
- Assigns new guest checkouts to a verified account with the same email
- Supports classic checkout and the Checkout Block/Store API
- Uses WooCommerce CRUD APIs and supports High-Performance Order Storage (HPOS)
- Preserves WooCommerce download permissions and customer statistics
- Defers to WooCommerce's native customer email verification when available
- Supports standard and headless WordPress authentication flows
- Adds no settings, tracking, external requests, or build step

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- WooCommerce 7.0 or newer

## Installation

1. Download `auto-assign-guest-orders-1.1.0.zip` from the latest GitHub release.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Select the ZIP, install it, and activate **Auto Assign Guest Orders**.
4. Keep WooCommerce active. The plugin works automatically.

You can also clone this repository into
`wp-content/plugins/auto-assign-guest-orders`.

## How it works

On WooCommerce versions without native customer email verification, the plugin
sends a one-time link after registration or login. The customer must open that
link while signed in to the same account, proving control of both the account and
its email address. It then uses WooCommerce's own past-order relinking API. That
API keeps downloadable-product permissions, order counts, spending totals, and
compatibility hooks correct.

WooCommerce 11 and newer link past orders after the customer verifies their
email. This plugin detects that flow and does not bypass it.

Headless sites with a separate, trusted email-verification flow can integrate
with the verification decision:

```php
add_filter(
	'auto_assign_guest_orders_is_email_verified',
	function ( $verified, $user ) {
		return $verified || my_app_has_verified_email( $user->ID );
	},
	10,
	2
);
```

Return true only after the external flow has proved ownership of the account's
current email address.

For new checkouts, the plugin assigns an otherwise guest order when its billing
email belongs to a verified WordPress account. Both classic checkout and the
Checkout Block are supported.

## Security and privacy

Order ownership is determined by exact email matching only after account email
ownership is verified. Verification links are hashed at rest, expire after one
day, and work only while signed in as the target account.

The plugin stores only temporary verification data and the verified email in
WordPress user metadata. It does not add database tables, collect analytics, or
contact external services.

## Development

The production source is maintained in the
[`coded-letter-monorepo`](https://github.com/coded-letter/coded-letter-monorepo)
and released to this public repository. The plugin has no build step.

Validate PHP changes with:

```bash
php -l auto-assign-guest-orders.php
```

## License

Auto Assign Guest Orders is free software licensed under the
[GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).
