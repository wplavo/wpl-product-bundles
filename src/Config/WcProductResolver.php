<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Resolves product IDs by calling wc_get_product().
 *
 * This is the production implementation of ProductResolverInterface.
 * Tests inject a mock instead.
 */
final class WcProductResolver implements ProductResolverInterface {

    public function resolve(int $id): ?ProductInfo {
        $product = wc_get_product($id);

        if (!$product instanceof \WC_Product) {
            return null;
        }

        $parentId = $product instanceof \WC_Product_Variation
            ? $product->get_parent_id()
            : 0;

        return new ProductInfo(
            id: $id,
            type: $product->get_type(),
            parentId: $parentId,
        );
    }
}
