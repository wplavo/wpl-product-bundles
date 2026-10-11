<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

use WPL\ProductBundles\Config\BundleConfigRepository;

/**
 * Static registry that allows BundleProduct to reach the PricingService and
 * BundleConfigRepository without accessing the DI container directly.
 *
 * Plugin::registerServices() calls boot() once during initialisation.
 * BundleProduct uses the getters, which return null when not yet booted
 * (safe fallback: BundleProduct delegates to WC_Product).
 */
final class BundleProductPriceService {

    private static ?PricingService $pricingService       = null;
    private static ?BundleConfigRepository $configRepository = null;

    public static function boot(PricingService $pricingService, BundleConfigRepository $configRepository): void {
        self::$pricingService    = $pricingService;
        self::$configRepository  = $configRepository;
    }

    public static function getPricingService(): ?PricingService {
        return self::$pricingService;
    }

    public static function getConfigRepository(): ?BundleConfigRepository {
        return self::$configRepository;
    }

    /** @internal For testing only. */
    public static function reset(): void {
        self::$pricingService   = null;
        self::$configRepository = null;
    }
}
