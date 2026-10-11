<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

/**
 * Checks whether a product (or specific variation) has sufficient stock.
 */
interface StockCheckerInterface {

    /**
     * Returns true if $quantity units of the product / variation are available.
     *
     * @param int      $productId   Parent product ID (or variation's own ID when $variationId is null).
     * @param int|null $variationId Variation ID, or null when $productId is a simple / variable product.
     * @param int      $quantity    Number of units the customer wants.
     */
    public function isAvailable(int $productId, ?int $variationId, int $quantity): bool;
}
