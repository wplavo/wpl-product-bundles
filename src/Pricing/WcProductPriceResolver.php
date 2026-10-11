<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Resolves a product's effective selling price via WooCommerce.
 *
 * wc_get_product(id)->get_price() returns the sale price when a WC scheduled
 * sale is active, otherwise the regular price. This is intentional — the
 * plugin always honours current WC pricing without manual sale-price detection.
 */
final class WcProductPriceResolver implements ProductPriceResolverInterface {

    public function getPrice(int $productId, ?int $variationId): string {
        $id      = $variationId ?? $productId;
        $product = wc_get_product($id);

        if (!$product instanceof \WC_Product) {
            return '0.00';
        }

        $price = $product->get_price();
        return is_numeric($price) ? (string) $price : '0.00';
    }
}
