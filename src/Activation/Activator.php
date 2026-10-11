<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Activation;

use WPL\ProductBundles\Migration\MigrationRunner;

/**
 * Runs on plugin activation.
 *
 * Checks hard requirements, writes initial options, seeds the schema version,
 * and flushes rewrite rules so the bundle product type is registered cleanly.
 */
final class Activator {

    /**
     * Entry point for register_activation_hook().
     */
    public static function activate(): void {
        self::checkRequirements();
        self::seedOptions();
        (new MigrationRunner())->run();
        flush_rewrite_rules();
    }

    private static function checkRequirements(): void {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            wp_die(
                esc_html__('WPL Product Bundles requires PHP 8.1 or higher.', 'wpl-product-bundles'),
                esc_html__('Plugin Activation Error', 'wpl-product-bundles'),
                ['back_link' => true]
            );
        }

        if (version_compare(get_bloginfo('version'), '6.4', '<')) {
            wp_die(
                esc_html__('WPL Product Bundles requires WordPress 6.4 or higher.', 'wpl-product-bundles'),
                esc_html__('Plugin Activation Error', 'wpl-product-bundles'),
                ['back_link' => true]
            );
        }
    }

    private static function seedOptions(): void {
        // add_option is a no-op when the option already exists — safe to call on re-activation.
        add_option('wpl_product_bundles_version', WPL_PRODUCT_BUNDLES_VERSION);
        add_option('wpl_product_bundles_schema_version', '0');
        add_option('wpl_product_bundles_installed', (string) time());
    }
}
