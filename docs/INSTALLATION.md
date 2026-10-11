# Installation Guide — WPL Product Bundles

**Plugin version:** 1.0.0

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.1 |
| WordPress | 6.4 |
| WooCommerce | 8.0 |

---

## Fresh installation

### Option A — Upload ZIP via WordPress admin

1. Download `wpl-product-bundles-1.0.0.zip`.
2. Log in to your WordPress admin dashboard.
3. Go to **Plugins → Add New → Upload Plugin**.
4. Select the ZIP file and click **Install Now**.
5. Click **Activate Plugin**.

On activation the plugin:
- Verifies PHP ≥ 8.1 and WordPress ≥ 6.4; calls `wp_die()` with a message if the check fails.
- Seeds the database options `wpl_product_bundles_version`, `wpl_product_bundles_schema_version`, and `wpl_product_bundles_installed`.
- Runs the migration runner to set up the schema version tracking.
- Flushes rewrite rules.

### Option B — WP-CLI

```bash
wp plugin install wpl-product-bundles-1.0.0.zip --activate
```

### Option C — Manual FTP

1. Unzip `wpl-product-bundles-1.0.0.zip`.
2. Upload the `wpl-product-bundles/` directory to `wp-content/plugins/`.
3. Activate via **Plugins → Installed Plugins → Activate**.

---

## Upgrade procedure

### Before every upgrade

1. **Back up your database** — at minimum, export the `wp_postmeta`, `wp_options`, `wp_woocommerce_order_items`, and `wp_woocommerce_order_itemmeta` tables.
2. **Test on staging** — apply the upgrade on a staging copy of your site before production.
3. **Check the changelog** — review `CHANGELOG.md` for breaking changes or migration notes.
4. **Note active bundles** — if you have bundle products with active pricing configurations, verify pricing is correct after the upgrade.

### Upgrade steps

1. Deactivate the current version via **Plugins → Installed Plugins → Deactivate**.
2. Install the new version ZIP via **Plugins → Add New → Upload Plugin** (WordPress prompts to overwrite the existing plugin files).
3. Activate the new version.
4. The migration runner automatically detects the schema version and applies any new migrations. Check **Tools → Site Health** for errors if the migration fails.

### WP-CLI upgrade

```bash
wp plugin deactivate wpl-product-bundles
wp plugin install wpl-product-bundles-1.0.0.zip --force
wp plugin activate wpl-product-bundles
```

---

## Backup recommendations

| Data | Location | Backup method |
|------|----------|--------------|
| Bundle configs | `wp_postmeta` with key `_wpl_bundle_config` | Full DB backup or targeted meta export |
| Plugin options | `wp_options` with prefix `wpl_product_bundles_` | Included in full DB backup |
| Order bundle meta | `wp_woocommerce_order_itemmeta` | Included in full DB backup |
| Plugin files | `wp-content/plugins/wpl-product-bundles/` | File system backup or version control |

**Recommended backup schedule:**
- Full database backup before any plugin upgrade.
- Daily automated backups covering the database and `uploads/` directory.

---

## Configuration

### Creating a bundle product

1. Go to **Products → Add New** (or edit an existing product).
2. Set the **Product type** dropdown to **Bundle**.
3. Click the **Bundle** tab in the product data panel.

#### Bundle settings

| Field | Description |
|-------|-------------|
| **Mode** | `Fixed` — fixed set of components. `Mix & Match` — customer selects from allowed products. |
| **Price mode** | `Per bundle` — uses WC product price. `Per component` — sums component prices. `Tiered` — price from quantity table. |
| **Bundle discount** | Percentage discount applied after component subtotal (per-component and per-bundle modes). |
| **Min / Max qty** | Applies to M&M bundles; total selected component quantity must fall within this range. |

#### Component settings

Each component slot configures:

| Field | Description |
|-------|-------------|
| **Slot title** | Customer-facing label for this slot. Defaults to the product name if left blank. |
| **Product** | The product assigned to this slot (required). |
| **Variation** | For variable products, the specific variation to include. |
| **Qty min / max / default** | Quantity range and default. Set min = max for a fixed quantity. |
| **Optional** | When checked, the customer can exclude this component. |
| **Component price mode** | `Product default` — uses WC product price. `Custom price` — enter a custom override price. `Free` — zero component price. |
| **Component discount** | Percentage discount on this component's price (per-component mode only). |
| **Allowed products** | M&M only: the products a customer may choose for this slot. |
| **Stock mode** | `Inherit` — uses WooCommerce's default stock behavior. `Shared` / `Independent` — advanced stock handling (see DATA-MODEL.md). |

#### Price tiers (tiered mode only)

Add rows to the price tier table. Each row defines a quantity range and fixed price per bundle unit. Rows must not overlap; the resolver picks the first matching tier.

---

## Troubleshooting

### Bundle tab not visible in product editor

- Confirm WooCommerce 8.0+ is active.
- Go to **WooCommerce → Status → Tools → Clear transients** then hard-refresh the browser cache.
- Check that the product type dropdown shows "Bundle" as an option. If it does not, deactivate and reactivate the plugin to re-flush rewrite rules.

### Bundle form not appearing on the product page

- Confirm the product type is set to "Bundle" and the product is published.
- Confirm WooCommerce template hooks are intact. A theme that calls `remove_all_actions('woocommerce_single_product_summary')` or similar will suppress the form.
- Check the browser console for JavaScript errors on the product page.

### Cart totals incorrect

- WPL Product Bundles recalculates component prices on every cart totals calculation. If a third-party pricing plugin conflicts, check the priority of hooks on `woocommerce_before_calculate_totals`. The plugin uses priority 10; a conflicting plugin at priority < 10 runs first and may be overwritten.
- Clear the WooCommerce cart session (WP-CLI: `wp wc cart empty --user=<id>`) and re-add the bundle.

### PHP 8.1 error on activation

The plugin checks PHP version before loading its autoloader. If you see a PHP error rather than the admin notice, your server is running a PHP version that does not support the version check code — upgrade to PHP 8.1 or higher.

### "vendor/autoload.php not found" admin notice

The plugin was installed without its Composer dependencies. This happens if the ZIP was built incorrectly (e.g., from a raw `git clone` without running `composer install`). Install the correctly built ZIP from the release package, or run `composer install --no-dev --optimize-autoloader` in the plugin directory.

### Migrations not running

Check `wp_options` for `wpl_product_bundles_schema_version`. If the value is not updated after activation, there may be a database permissions issue. Run `wp option get wpl_product_bundles_schema_version` via WP-CLI; if the command fails, check your database user permissions.

---

## Uninstalling

**Default behavior — data retained:**

1. Go to **Plugins → Installed Plugins**.
2. Deactivate WPL Product Bundles.
3. Click **Delete**.

Plugin options and bundle configuration meta are retained. Bundle product meta remains on your products and order records.

**With data cleanup (irreversible):**

Before uninstalling, set the cleanup option:

```bash
wp option update wpl_product_bundles_uninstall_cleanup 1
```

Then deactivate and delete the plugin. This removes the `wpl_product_bundles_*` options. Bundle product meta on `wp_postmeta` and order item meta are intentionally not removed to preserve order history.

---

## Licensing and update server

WPL Product Bundles 1.0.0 does not include a built-in update server or license validation mechanism. Updates must be applied manually by downloading a new ZIP and installing it as described in the **Upgrade procedure** section above.

A future release will optionally integrate with a hosted update server (e.g., a self-hosted update API or a marketplace-provided update channel). That integration will be a separate, optional component and will not affect the core commerce engine.

Do not implement license validation that sends credentials to a third-party server without explicit store owner consent and disclosure.

---

## Support

For bug reports and feature requests, open an issue in the project repository.

For questions about bundle configuration, see the FAQ in `readme.txt` and the developer documentation in the `docs/` directory.
