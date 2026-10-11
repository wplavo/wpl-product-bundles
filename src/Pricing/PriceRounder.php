<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Centralised monetary rounding following the PRICING-SPEC.md rules:
 *
 *   - PHP_ROUND_HALF_UP (matches WooCommerce's own rounding behaviour).
 *   - Rounding is applied AFTER discount multiplication, not before.
 *   - Results are formatted as fixed-decimal strings so downstream code
 *     never needs to guess the decimal count.
 *   - Prices are clamped to zero; negative prices are never produced.
 */
final class PriceRounder {

    /**
     * Rounds a float to $decimals places using ROUND_HALF_UP and returns a
     * zero-padded decimal string, e.g. "10.50" for decimals=2.
     */
    public static function round(float $value, int $decimals): string {
        $rounded = round($value, $decimals, PHP_ROUND_HALF_UP);
        return number_format($rounded, $decimals, '.', '');
    }

    /**
     * Clamps a price string to zero if it would be negative.
     * Logs to the WC logger when clamping occurs, so the admin can investigate.
     */
    public static function clampToZero(string $price): string {
        if ((float) $price >= 0.0) {
            return $price;
        }

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->warning(
                sprintf(
                    'WPL Product Bundles: calculated price %s was negative and has been clamped to 0.00. Check bundle/component discounts.',
                    $price
                ),
                ['source' => 'wpl-product-bundles']
            );
        }

        // Return a zero string with the same decimal count as the input.
        $decimals = strlen((string) strrchr($price, '.')) - 1;
        return number_format(0.0, max(0, $decimals), '.', '');
    }
}
