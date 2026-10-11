<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Frontend;

/**
 * Registers all front-end hooks: the add-to-cart form, asset enqueueing,
 * and the WooCommerce template-path override.
 */
final class FrontendServiceProvider {

    public function __construct(
        private readonly BundleRenderer $renderer,
    ) {}

    public function register(): void {
        add_action('woocommerce_bundle_add_to_cart', [$this, 'renderForm']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('woocommerce_locate_template', [$this, 'locateTemplate'], 10, 3);
    }

    /**
     * Renders the bundle add-to-cart form on the single-product page.
     */
    public function renderForm(): void {
        global $product;
        if (!$product instanceof \WC_Product) {
            return;
        }
        $this->renderer->render($product);
    }

    /**
     * Enqueues bundle UI assets, but only on bundle product pages.
     */
    public function enqueueAssets(): void {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product instanceof \WC_Product || $product->get_type() !== 'bundle') {
            return;
        }

        wp_enqueue_style(
            'wpl-bundle-ui',
            WPL_PRODUCT_BUNDLES_URL . 'assets/css/bundle-ui.css',
            [],
            WPL_PRODUCT_BUNDLES_VERSION
        );

        wp_enqueue_script(
            'wpl-bundle-ui',
            WPL_PRODUCT_BUNDLES_URL . 'assets/js/bundle-ui.js',
            [],
            WPL_PRODUCT_BUNDLES_VERSION,
            true
        );
    }

    /**
     * Points WooCommerce's template loader to our templates/ directory so that
     * themes can still override via {theme}/woocommerce/single-product/….
     *
     * @param string $template      Resolved path from previous filter / theme lookup.
     * @param string $templateName  Relative name, e.g. single-product/add-to-cart/bundle.php.
     * @param string $templatePath  Theme-level search path (may be empty).
     */
    public function locateTemplate(string $template, string $templateName, string $templatePath): string {
        $pluginTemplate = WPL_PRODUCT_BUNDLES_DIR . 'templates/' . $templateName;

        if (file_exists($pluginTemplate)) {
            return $pluginTemplate;
        }

        return $template;
    }
}
