<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Admin;

use WPL\ProductBundles\Logging\LoggerInterface;

/**
 * Registers all admin-facing hooks.
 */
final class AdminServiceProvider {

    private string $pluginFile;

    /**
     * Logger is accepted now so future phases can inject it without changing the constructor signature.
     * @phpstan-ignore-next-line property.onlyWritten
     */
    private LoggerInterface $logger;

    private BundleProductTab $productTab;
    private BundleProductSaver $productSaver;
    private ProductSearchController $searchController;

    public function __construct(
        LoggerInterface $logger,
        string $pluginFile,
        BundleProductTab $productTab,
        BundleProductSaver $productSaver,
        ProductSearchController $searchController
    ) {
        $this->logger           = $logger;
        $this->pluginFile       = $pluginFile;
        $this->productTab       = $productTab;
        $this->productSaver     = $productSaver;
        $this->searchController = $searchController;
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'registerMenuPages']);
        add_filter('plugin_action_links_' . plugin_basename($this->pluginFile), [$this, 'addActionLinks']);

        // Bundle product data tab.
        add_filter('woocommerce_product_data_tabs', [$this->productTab, 'registerTab']);
        add_action('woocommerce_product_data_panels', [$this->productTab, 'renderPanel']);
        add_action('admin_enqueue_scripts', [$this->productTab, 'enqueueAssets']);

        // Save bundle config when product is saved.
        add_action('woocommerce_process_product_meta', [$this->productSaver, 'save'], 10, 1);

        // AJAX handlers (logged-in users only).
        add_action('wp_ajax_wpl_product_search', [$this->searchController, 'handle']);
        add_action('wp_ajax_wpl_get_variations', [$this->searchController, 'getVariations']);
    }

    public function registerMenuPages(): void {
        add_submenu_page(
            'woocommerce',
            __('Product Bundles Status', 'wpl-product-bundles'),
            __('Product Bundles', 'wpl-product-bundles'),
            'manage_woocommerce',
            'wpl-product-bundles-status',
            [$this, 'renderStatusPage']
        );
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function addActionLinks(array $links): array {
        $statusLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wpl-product-bundles-status')),
            esc_html__('Status', 'wpl-product-bundles')
        );
        array_unshift($links, $statusLink);
        return $links;
    }

    public function renderStatusPage(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wpl-product-bundles'));
        }

        $schemaVersion = (int) get_option('wpl_product_bundles_schema_version', 0);
        $installedOn   = (string) get_option('wpl_product_bundles_installed', '');
        $wcVersion     = defined('WC_VERSION') ? WC_VERSION : __('unknown', 'wpl-product-bundles');
        $hposEnabled   = $this->isHposEnabled() ? __('Enabled', 'wpl-product-bundles') : __('Disabled', 'wpl-product-bundles');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WPL Product Bundles — Status', 'wpl-product-bundles'); ?></h1>
            <table class="widefat striped" style="max-width:600px">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Plugin version', 'wpl-product-bundles'); ?></strong></td>
                        <td><?php echo esc_html(WPL_PRODUCT_BUNDLES_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Schema version', 'wpl-product-bundles'); ?></strong></td>
                        <td><?php echo esc_html((string) $schemaVersion); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('PHP version', 'wpl-product-bundles'); ?></strong></td>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('WooCommerce version', 'wpl-product-bundles'); ?></strong></td>
                        <td><?php echo esc_html((string) $wcVersion); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('HPOS (Custom Order Tables)', 'wpl-product-bundles'); ?></strong></td>
                        <td><?php echo esc_html($hposEnabled); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Installed on', 'wpl-product-bundles'); ?></strong></td>
                        <td>
                            <?php
                            echo '' !== $installedOn
                                ? esc_html(wp_date(get_option('date_format'), (int) $installedOn))
                                : esc_html__('unknown', 'wpl-product-bundles');
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function isHposEnabled(): bool {
        if (!class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)) {
            return false;
        }
        try {
            return wc_get_container()
                ->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
                ->custom_orders_table_usage_is_enabled();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
