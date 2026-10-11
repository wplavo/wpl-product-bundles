<?php
/**
 * Plugin Name:       WPL Product Bundles
 * Plugin URI:        https://example.com/wpl-product-bundles
 * Description:       Fixed and mix-and-match product bundles for WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WPL
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpl-product-bundles
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package WPL\ProductBundles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail early on PHP version mismatch — no autoloader needed for this check.
if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'WPL Product Bundles requires PHP 8.1 or higher. Please upgrade PHP.',
					'wpl-product-bundles'
				)
			);
		}
	);
	return;
}

// Bail if Composer autoloader is missing (plugin not built correctly).
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'WPL Product Bundles: vendor dependencies are missing. Run `composer install` in the plugin directory.',
					'wpl-product-bundles'
				)
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Plugin constants — defined once at load time.
define( 'WPL_PRODUCT_BUNDLES_VERSION', '1.0.0' );
define( 'WPL_PRODUCT_BUNDLES_FILE', __FILE__ );
define( 'WPL_PRODUCT_BUNDLES_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPL_PRODUCT_BUNDLES_URL', plugin_dir_url( __FILE__ ) );
define( 'WPL_PRODUCT_BUNDLES_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Declare HPOS and block-checkout compatibility before WooCommerce initializes.
 * This hook fires from inside WooCommerce's own plugins_loaded handler, so it
 * must be registered here at include time — not inside our plugins_loaded callback.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPL_PRODUCT_BUNDLES_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WPL_PRODUCT_BUNDLES_FILE,
				true
			);
		}
	}
);

// Lifecycle hooks — registered at include time so WordPress can call them.
register_activation_hook( __FILE__, array( \WPL\ProductBundles\Activation\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPL\ProductBundles\Activation\Deactivator::class, 'deactivate' ) );

/*
 * Bootstrap the plugin after WooCommerce has loaded (priority 11 > WC default 10).
 * Dependency checks run first; if they fail, notices are shown and we bail.
 */
add_action(
	'plugins_loaded',
	function () {
		$checker = new \WPL\ProductBundles\Checks\DependencyChecker();

		if ( ! $checker->check() ) {
			add_action( 'admin_notices', array( $checker, 'displayNotices' ) );
			return;
		}

		\WPL\ProductBundles\Plugin::instance( WPL_PRODUCT_BUNDLES_FILE )->init();
	},
	11
);
