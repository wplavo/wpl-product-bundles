<?php
/**
 * Uninstall handler for WPL Product Bundles.
 *
 * Data-retention policy: By default this plugin retains all bundle product
 * meta, options, and order item meta on uninstall. Deleting plugin data is
 * destructive and irreversible; store owners may reinstall the plugin and
 * expect their data to be intact.
 *
 * To enable data cleanup on uninstall, store owners must explicitly set the
 * option `wpl_product_bundles_uninstall_cleanup` to `1` via WP-CLI or direct
 * database update BEFORE uninstalling. This prevents accidental data loss.
 *
 * @package WPL\ProductBundles
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Bail if cleanup has not been explicitly opted into.
if ( ! get_option( 'wpl_product_bundles_uninstall_cleanup', false ) ) {
	return;
}

/*
 * Cleanup path — only reached when the store owner has explicitly opted in.
 * Autoloader may not be available (plugin deactivated), so we load it safely.
 */
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	return;
}
require_once $autoloader;

// Remove plugin-level options.
delete_option( 'wpl_product_bundles_version' );
delete_option( 'wpl_product_bundles_schema_version' );
delete_option( 'wpl_product_bundles_installed' );
delete_option( 'wpl_product_bundles_uninstall_cleanup' );

/*
 * Bundle product meta (_wpl_bundle_*, _wpl_bundle_items, etc.) is intentionally
 * left on the post/product records. Removing it would corrupt existing orders
 * that reference bundle item meta for display and refund logic.
 * A future dedicated cleanup tool should handle this with explicit confirmation.
 */
