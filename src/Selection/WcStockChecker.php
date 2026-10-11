<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

/**
 * WooCommerce implementation of StockCheckerInterface.
 *
 * Resolves the most-specific product object (variation > simple) and delegates
 * to WooCommerce's own stock management methods.
 */
final class WcStockChecker implements StockCheckerInterface {

    public function isAvailable(int $productId, ?int $variationId, int $quantity): bool {
        $id      = $variationId ?? $productId;
        $product = wc_get_product($id);

        if (!$product instanceof \WC_Product) {
            return false;
        }

        if (!$product->is_in_stock()) {
            return false;
        }

        if ($product->managing_stock()) {
            $available = $product->get_stock_quantity();
            if ($available !== null && $available < $quantity) {
                return false;
            }
        }

        return true;
    }
}
