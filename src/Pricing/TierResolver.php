<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Resolves the applicable unit price from a bundle's tiered pricing table.
 *
 * Tier selection algorithm (from PRICING-SPEC.md §4.2):
 *   For each tier in order:
 *     if qty >= min_qty AND (max_qty is null OR qty <= max_qty) → use this tier
 *   If no tier matches → fall back to the first tier.
 *
 * "Lowest price" for the product page "From" display is the cheapest price
 * across all tiers (typically the highest-quantity tier).
 */
final class TierResolver {

    /**
     * Returns the unit price for the given bundle quantity.
     *
     * @param array<int, array<string, mixed>> $tiers  From BundleConfig::priceTiers.
     * @param int                              $qty    Number of bundle units in cart.
     * @return string  Numeric price string; '0.00' when $tiers is empty.
     */
    public function resolve(array $tiers, int $qty): string {
        if (empty($tiers)) {
            return '0.00';
        }

        foreach ($tiers as $tier) {
            if (!isset($tier['min_qty'], $tier['price'])) {
                continue;
            }

            $minQty = (int) $tier['min_qty'];
            $maxQty = isset($tier['max_qty']) && $tier['max_qty'] !== null
                ? (int) $tier['max_qty']
                : null;

            if ($qty >= $minQty && ($maxQty === null || $qty <= $maxQty)) {
                return (string) $tier['price'];
            }
        }

        // Fallback: first valid tier. BundleValidator ensures no gaps, so this
        // should only occur when qty is somehow below all tiers.
        foreach ($tiers as $tier) {
            if (isset($tier['price'])) {
                return (string) $tier['price'];
            }
        }

        return '0.00';
    }

    /**
     * Returns the minimum (cheapest) price across all tiers.
     * Used for the "From $X.XX" display on the product page.
     *
     * @param array<int, array<string, mixed>> $tiers
     */
    public function getMinimumPrice(array $tiers): string {
        if (empty($tiers)) {
            return '0.00';
        }

        $minFloat  = null;
        $minString = '0.00';

        foreach ($tiers as $tier) {
            if (!isset($tier['price']) || !is_numeric($tier['price'])) {
                continue;
            }
            $float = (float) $tier['price'];
            if ($minFloat === null || $float < $minFloat) {
                $minFloat  = $float;
                $minString = (string) $tier['price'];
            }
        }

        return $minString;
    }
}
