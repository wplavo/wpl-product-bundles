=== WPL Product Bundles ===
Contributors: wpl
Tags: woocommerce, bundles, product bundles, mix and match, composite products
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fixed and mix-and-match product bundles for WooCommerce with per-bundle, per-component, and tiered pricing.

== Description ==

WPL Product Bundles adds a `bundle` product type to WooCommerce. Store owners can create:

* **Fixed bundles** — a curated set of products sold together at a defined price.
* **Mix-and-match bundles** — customers choose from a list of allowed products within configurable quantity limits.

= Pricing modes =

* **Per-bundle** — a single price set on the WooCommerce product price field.
* **Per-component** — the bundle price is the sum of component prices, with optional percentage discounts at the component and bundle levels.
* **Tiered** — the bundle price is looked up from a quantity-based price table.

= Key features =

* Server-authoritative pricing — browser-submitted prices are never trusted.
* HPOS compatible — all order operations use the WC_Order API.
* Optional components, quantity controls per component.
* Live price estimation in the browser (per-component mode).
* WooCommerce template override support — themes can customize the add-to-cart form.
* REST API for headless and third-party integration (`wpl/v1`).
* Five documented extension hooks for developers.
* No runtime external dependencies — a single autoloader is the only runtime requirement.

= Documentation =

Developer documentation is included in the `docs/` directory of the plugin source:

* REST API reference — `docs/REST-API.md`
* Hooks and filters — `docs/HOOKS-FILTERS.md`
* Pricing specification — `docs/PRICING-SPEC.md`
* Security audit — `docs/SECURITY-AUDIT.md`
* Compatibility matrix — `docs/COMPATIBILITY.md`

== Installation ==

= Minimum requirements =

* PHP 8.1 or higher
* WordPress 6.4 or higher
* WooCommerce 8.0 or higher

= Installing from a ZIP file =

1. Log in to your WordPress admin dashboard.
2. Navigate to **Plugins → Add New → Upload Plugin**.
3. Choose the `wpl-product-bundles-1.0.0.zip` file and click **Install Now**.
4. Click **Activate Plugin**.

= Creating your first bundle =

1. Go to **Products → Add New**.
2. In the **Product data** dropdown, select **Bundle**.
3. The **Bundle** tab appears in the product data panel.
4. Configure the bundle mode, pricing mode, and add component slots.
5. Publish the product.

= Upgrading =

Before upgrading:

1. Back up your database (especially `wp_postmeta` and `wp_woocommerce_order_items`).
2. Test the upgrade on a staging site first.

To upgrade, install the new ZIP over the existing plugin (WordPress handles the file replacement). The plugin's migration runner detects the schema version and applies any new migrations automatically on the first page load after activation.

== Frequently Asked Questions ==

= Does this plugin require any other plugins? =

WooCommerce 8.0 or higher is required. No other plugins are needed.

= Is it compatible with WooCommerce Blocks checkout? =

The bundle add-to-cart form on the single-product page works with both classic and Blocks themes. However, adding a bundle directly from a catalog page using the Blocks add-to-cart button bypasses component selection. Full Blocks Store API support is planned for a future release.

= Can themes customize the bundle form? =

Yes. Copy `templates/single-product/add-to-cart/bundle.php` or `templates/partials/bundle-component.php` to `{your-theme}/woocommerce/single-product/add-to-cart/` or `{your-theme}/woocommerce/partials/` respectively.

= How do I remove plugin data on uninstall? =

By default the plugin retains all bundle configuration and order meta on uninstall. To enable cleanup, set the option `wpl_product_bundles_uninstall_cleanup` to `1` before uninstalling (via WP-CLI: `wp option update wpl_product_bundles_uninstall_cleanup 1`). The plugin then removes its own options on the next uninstall. Bundle product meta and order item meta are intentionally preserved to protect order history.

= Can I use this alongside the official WooCommerce Product Bundles extension? =

No. Both plugins register the `bundle` product type, which causes a conflict. Do not install both.

== Screenshots ==

1. Bundle product editor tab — configure mode, pricing, and component slots.
2. Fixed bundle on the single-product page.
3. Mix-and-match bundle with product selector and progress bar.

== Changelog ==

See CHANGELOG.md in the plugin directory for the full changelog.

= 1.0.0 =
* Initial release. Fixed and mix-and-match bundles with per-bundle, per-component, and tiered pricing. REST API. Five developer extension hooks. HPOS compatible.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade path from a previous version.
