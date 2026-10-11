<?php

declare(strict_types=1);

namespace WPL\ProductBundles\ProductType;

/**
 * Registers the 'bundle' WooCommerce product type.
 *
 * Hooks are registered here; the actual class is resolved lazily by WooCommerce
 * when it instantiates a product of type 'bundle'.
 */
final class ProductTypeRegistrar {

    public function register(): void {
        add_filter('woocommerce_product_class', [$this, 'getProductClass'], 10, 2);
        add_filter('product_type_selector', [$this, 'addProductTypeOption']);
    }

    /**
     * Returns the FQCN for our BundleProduct when the product type is 'bundle'.
     */
    public function getProductClass(string $classname, string $productType): string {
        if ('bundle' === $productType) {
            return BundleProduct::class;
        }
        return $classname;
    }

    /**
     * Adds 'Bundle' to the product type selector in the WC product editor.
     *
     * @param array<string, string> $types
     * @return array<string, string>
     */
    public function addProductTypeOption(array $types): array {
        $types['bundle'] = __('Bundle', 'wpl-product-bundles');
        return $types;
    }
}
