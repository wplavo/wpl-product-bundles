<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Returns the effective selling price for a product or variation.
 *
 * Implementations must return the WooCommerce "current price" — the sale price
 * when a scheduled sale is active, otherwise the regular price. This mirrors
 * what WC_Product::get_price() returns.
 */
interface ProductPriceResolverInterface {

    /**
     * @param int      $productId   Simple or variable product ID.
     * @param int|null $variationId Specific variation ID, or null for the parent.
     * @return string  Numeric price string, e.g. "19.99"; "0.00" when not found or unpublished.
     */
    public function getPrice(int $productId, ?int $variationId): string;
}
