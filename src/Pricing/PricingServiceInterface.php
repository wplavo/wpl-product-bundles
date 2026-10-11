<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Selection\BundleSelection;

/**
 * Contract for the bundle pricing engine.
 *
 * Implementations must apply the same authoritative server-side price logic as
 * PricingService — never trusting browser-submitted values.
 */
interface PricingServiceInterface {

    /**
     * Calculates the display price using the bundle's configured default component quantities.
     */
    public function calculateDefault(BundleConfig $config): PriceResult;

    /**
     * Calculates the price from a customer's validated selection.
     * Used at add-to-cart time, on cart recalculation, and by the REST estimate endpoint.
     *
     * @param int $bundleQty Quantity of the bundle item (for tiered mode).
     */
    public function calculateFromSelection(
        BundleConfig $config,
        BundleSelection $selection,
        int $bundleQty = 1
    ): PriceResult;
}
