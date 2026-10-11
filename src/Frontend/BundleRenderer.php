<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Frontend;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\BundleConfigRepository;
use WPL\ProductBundles\Config\ComponentConfig;

/**
 * Builds the data arrays used by templates and the JS bundle-ui script,
 * then delegates rendering to WooCommerce's template loader.
 */
final class BundleRenderer {

    public function __construct(
        private readonly BundleConfigRepository $repository,
    ) {}

    public function render(\WC_Product $product): void {
        $config = $this->repository->get($product->get_id());

        if ($config === null) {
            return;
        }

        $components = $this->buildComponentData($config);

        wp_localize_script('wpl-bundle-ui', 'wplBundleConfig', $this->buildJsConfig($config, $components));

        wc_get_template(
            'single-product/add-to-cart/bundle.php',
            [
                'product'           => $product,
                'bundle_config'     => $config,
                'bundle_components' => $components,
            ],
            '',
            WPL_PRODUCT_BUNDLES_DIR . 'templates/'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildComponentData(BundleConfig $config): array {
        $components = [];

        foreach ($config->items as $component) {
            $product = wc_get_product($component->productId);

            $data = [
                'config'                => $component,
                'product'               => $product,
                'name'                  => $component->slotTitle !== ''
                    ? $component->slotTitle
                    : ($product instanceof \WC_Product ? $product->get_name() : ''),
                'image'                 => $product instanceof \WC_Product
                    ? $product->get_image('woocommerce_thumbnail')
                    : '',
                'price_html'            => $product instanceof \WC_Product
                    ? $product->get_price_html()
                    : '',
                'is_in_stock'           => $product instanceof \WC_Product
                    ? $product->is_in_stock()
                    : false,
                'variation'             => null,
                'allowed_products_data' => [],
            ];

            if ($component->variationId !== null) {
                $variation = wc_get_product($component->variationId);
                $data['variation'] = $variation instanceof \WC_Product_Variation ? $variation : null;
            }

            if ($config->mode === 'mix_and_match' && !empty($component->allowedProducts)) {
                $data['allowed_products_data'] = $this->fetchAllowedProductsData($component->allowedProducts);
            }

            $components[] = $data;
        }

        return $components;
    }

    /**
     * @param int[] $productIds
     * @return array<int, array{id: int, name: string, price_html: string, is_in_stock: bool}>
     */
    private function fetchAllowedProductsData(array $productIds): array {
        $data = [];
        foreach ($productIds as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $data[] = [
                'id'          => $product->get_id(),
                'name'        => $product->get_name(),
                'price_html'  => $product->get_price_html(),
                'is_in_stock' => $product->is_in_stock(),
            ];
        }
        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $components
     * @return array<string, mixed>
     */
    private function buildJsConfig(BundleConfig $config, array $components): array {
        $jsComponents = [];
        foreach ($config->items as $i => $component) {
            $compData       = $components[$i];
            /** @var \WC_Product|null $compProduct */
            $compProduct    = $compData['product'] instanceof \WC_Product ? $compData['product'] : null;
            $rawUnitPrice   = $compProduct !== null ? (string) $compProduct->get_price() : '0';

            $jsComponents[] = [
                'id'                  => $component->id,
                'productId'           => $component->productId,
                'productName'         => $compData['name'],
                'priceHtml'           => $compData['price_html'],
                'unitPrice'           => $rawUnitPrice,
                'componentDiscount'   => $component->discount,
                'isInStock'           => $compData['is_in_stock'],
                'variationId'         => $component->variationId,
                'slotTitle'           => $component->slotTitle,
                'quantityMin'         => $component->quantityMin,
                'quantityMax'         => $component->quantityMax,
                'quantityDefault'     => $component->quantityDefault,
                'optional'            => $component->optional,
                'priceMode'           => $component->priceMode,
                'allowedProducts'     => $component->allowedProducts,
                'allowedVariations'   => $component->allowedVariations,
                'allowedProductsData' => $compData['allowed_products_data'],
            ];
        }

        return [
            'mode'         => $config->mode,
            'priceMode'    => $config->priceMode,
            'discount'     => $config->discount,
            'minQty'       => $config->minQty,
            'maxQty'       => $config->maxQty,
            'components'   => $jsComponents,
            'currency'     => [
                'symbol'   => get_woocommerce_currency_symbol(),
                'position' => (string) get_option('woocommerce_currency_pos', 'left'),
                'decimals' => (int) get_option('woocommerce_price_decimals', 2),
                'decSep'   => (string) get_option('woocommerce_price_decimal_sep', '.'),
                'thouSep'  => (string) get_option('woocommerce_price_thousand_sep', ','),
            ],
            'i18n'         => [
                'required'       => __('Required', 'wpl-product-bundles'),
                'optional'       => __('Optional', 'wpl-product-bundles'),
                'outOfStock'     => __('Out of stock', 'wpl-product-bundles'),
                'selectProduct'  => __('— Select a product —', 'wpl-product-bundles'),
                'items'          => __('items', 'wpl-product-bundles'),
                'addToCart'      => __('Add to cart', 'wpl-product-bundles'),
                'qty'            => __('Qty', 'wpl-product-bundles'),
                'qtyTooLow'      => __('Minimum quantity: %d.', 'wpl-product-bundles'),
                'qtyTooHigh'     => __('Maximum quantity: %d.', 'wpl-product-bundles'),
                'totalBelowMin'  => __('Please select at least %d item(s) in total.', 'wpl-product-bundles'),
                'totalAboveMax'  => __('You may select at most %d item(s) in total.', 'wpl-product-bundles'),
                'estTotal'       => __('Estimated total:', 'wpl-product-bundles'),
                'savings'        => __('You save:', 'wpl-product-bundles'),
            ],
        ];
    }
}
