# Compatibility — WPL Product Bundles

**Plugin version:** 1.0.0  
**Last updated:** 2026-10-11

---

## Requirements

| Dependency | Minimum | Tested up to | Notes |
|------------|---------|-------------|-------|
| PHP | 8.1 | 8.3 | Uses readonly properties, match expressions, named arguments |
| WordPress | 6.4 | 6.7 | Uses `register_block_type` patterns for type registration |
| WooCommerce | 8.0 | 11.2 | Declared in plugin header `WC requires at least` / `WC tested up to` |

Version mismatches are caught at two levels:

1. **Plugin load time** (`wpl-product-bundles.php`): PHP 8.1 check blocks autoloader loading and shows an admin notice before any other code runs.
2. **Activation** (`Activator::checkRequirements()`): Hard check aborts activation with `wp_die()` if PHP < 8.1 or WordPress < 6.4.
3. **Runtime** (`DependencyChecker`): Checks WooCommerce version on every request; shows admin notice and skips plugin init if requirements are not met.

---

## WooCommerce features

### High-Performance Order Storage (HPOS)

**Status: Fully compatible.**

HPOS compatibility is declared in the plugin bootstrap:

```php
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
});
```

`OrderIntegration` uses only the `WC_Order` object API throughout:
- `wc_get_order($orderId)` for loading
- `$order->get_items()` for reading items
- `WC_Order_Item` meta methods (`add_meta_data`, `update_meta_data`, `get_meta`) for all bundle metadata
- `$order->save()` to persist changes

No `get_post_meta()` calls with order IDs are present anywhere in the plugin.

### WooCommerce Blocks / Cart and Checkout Blocks

**Status: Partially compatible — add-to-cart via Blocks is not supported.**

The classic `woocommerce_add_to_cart_validation` filter does not fire when products are added via the WooCommerce Store API (the mechanism used by Cart and Checkout Blocks). Full bundle selection validation (`AddToCartHandler::validate()`) therefore does not run in the Blocks add-to-cart path.

**Behaviour when Blocks are active:**

- Adding a bundle via the Blocks-based "Add to cart" button bypasses component selection. The bundle parent is added without child components.
- Once in the cart, `CartPriceManager` still recalculates prices correctly from server-side config.
- At order placement, WooCommerce's own stock checks run normally.
- The single-product page (which uses the classic `[add_to_cart]` form rendered by `FrontendServiceProvider`) continues to work correctly — the bundle component form and validation are both active.

**Workaround:** Merchants using Blocks checkout should disable the "Add to cart" button outside the product page, or use the classic shortcode-based cart/checkout pages.

**Future work:** Full Blocks compatibility requires a Store API extension implementing `AbstractCartItemType`. This is out of scope for version 1.0.

### Product Block Editor (WooCommerce 8.8+)

The plugin registers its bundle configuration UI in the classic WooCommerce product data tabs (`woocommerce_product_data_tabs`, `woocommerce_product_data_panels`). The new Block-based product editor introduced in WooCommerce 8.8 uses a different extension API.

**Status:** The bundle configuration tab is not available in the new product editor. When the new editor is active, merchants must switch to the classic editor to configure bundles.

This is a known limitation. Support for the new product editor is planned for a future release.

---

## WordPress multisite

**Status: Compatible (single-site configuration only).**

The plugin uses standard WordPress and WooCommerce APIs throughout. It does not perform cross-site queries, use `switch_to_blog()`, or access site-specific tables directly. It should work on individual sites within a multisite network.

Network-wide activation (`Network Activate`) has not been tested. Plugin options are stored with `add_option()` / `update_option()`, which are site-scoped; network-level configuration is not supported.

---

## PHP version specifics

The plugin uses PHP 8.1+ language features. The following are used intentionally and are not available on PHP 8.0 or earlier:

| Feature | PHP version | Usage |
|---------|------------|-------|
| `readonly` properties | 8.1 | All value objects and service classes |
| `match` expression | 8.0 | `PricingService`, `RestBundleController` |
| Named arguments | 8.0 | `new PriceResult(mode: ..., ...)` constructors |
| Intersection types | 8.1 | Not used |
| First-class callables | 8.1 | Not used |
| Fibers | 8.1 | Not used |

---

## Theme compatibility

Frontend bundle UI styles are loaded via a dedicated stylesheet (`bundle-ui.css`) and use namespaced CSS classes (`wpl-bundle-*`). There are no `!important` overrides that would conflict with theme styles.

Templates follow the WooCommerce template override pattern:

1. Plugin ships templates in `templates/single-product/add-to-cart/bundle.php` and `templates/partials/bundle-component.php`.
2. `FrontendServiceProvider::locateTemplate()` hooks `woocommerce_locate_template` to point WooCommerce at the plugin's `templates/` directory.
3. A theme or child theme can override any template by placing a matching file at `{theme}/woocommerce/single-product/add-to-cart/bundle.php`.

---

## Deactivation and uninstall

**Deactivation** (`Deactivator::deactivate()`): flushes rewrite rules only. No data is removed.

**Uninstall** (`uninstall.php`): removes all plugin options (`wpl_product_bundles_*`) and all bundle configuration post meta (`_wpl_bundle_config`). Custom product type registration and any orders containing bundle data are left intact — order history is never deleted.

**Re-activation**: `Activator::seedOptions()` uses `add_option()`, which is a no-op when the option already exists. `MigrationRunner` is idempotent — migrations that have already run are skipped by version check. Re-activation is safe.

---

## Known conflicts

No known conflicts with other WooCommerce extensions at the time of this audit. Areas most likely to interact:

- **WooCommerce Subscriptions**: bundle child items are added as ordinary cart items. Subscription rules applied to component products may behave unexpectedly when those components are inside a bundle.
- **Dynamic pricing plugins**: `CartPriceManager` hooks `woocommerce_before_calculate_totals` at priority 10. A dynamic pricing plugin hooking at a lower priority (e.g., 5) runs before bundle prices are set; one hooking at higher priority (e.g., 20) may overwrite bundle prices. Priority should be adjusted per-site if conflicts arise.
- **WooCommerce Product Bundles (official)**: the `bundle` product type slug is shared. Installing both plugins on the same site will cause a product type conflict. Do not install alongside the official WooCommerce Product Bundles extension.
