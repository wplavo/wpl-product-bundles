<?php

declare(strict_types=1);

namespace WPL\ProductBundles\ProductType;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Pricing\BundleProductPriceService;
use WPL\ProductBundles\Pricing\TierResolver;

/**
 * WooCommerce product class for the 'bundle' product type.
 *
 * Extends WC_Product so WooCommerce can manage standard product fields
 * (price, stock, images, etc.). Bundle-specific data is stored in separate
 * meta keys managed by BundleConfigRepository.
 *
 * Pricing modes:
 *   per_bundle    — delegates to WC's own stored price (no override needed).
 *   per_component — get_price() returns the sum of resolved component prices.
 *   tiered        — get_price() returns the tier price for qty=1;
 *                   get_price_html() prepends "From ".
 *
 * Cart/purchasability overrides are added in Phase 6.
 */
class BundleProduct extends \WC_Product {

    /** @var array<int, BundleConfig|null> Request-scoped config cache. */
    private static array $configCache = [];

    public function get_type(): string {
        return 'bundle';
    }

    /**
     * Returns the effective selling price for this bundle.
     *
     * For per_bundle mode this delegates to WC_Product::get_price() (the standard
     * WC price field). For other modes it runs the PricingService.
     *
     * @param string $context 'view' or 'edit'.
     */
    public function get_price( $context = 'view' ): string {
        $config = $this->getBundleConfig();

        if ( $config === null || $config->priceMode === 'per_bundle' ) {
            return (string) parent::get_price( $context );
        }

        $service = BundleProductPriceService::getPricingService();
        if ( $service === null ) {
            return (string) parent::get_price( $context );
        }

        return $service->calculateDefault( $config )->total;
    }

    /**
     * Returns the formatted price HTML.
     *
     * For tiered mode this prepends "From" and shows the cheapest tier price.
     * All other modes delegate to WC_Product::get_price_html() which calls
     * our get_price() override and formats the result.
     *
     * @param string $price Optional raw price string (ignored — always recalculated).
     */
    public function get_price_html( $price = '' ): string {
        $config = $this->getBundleConfig();

        if ( $config === null || $config->priceMode !== 'tiered' ) {
            return parent::get_price_html( $price );
        }

        $minPrice = ( new TierResolver() )->getMinimumPrice( $config->priceTiers );

        if ( $minPrice === '0.00' && empty( $config->priceTiers ) ) {
            return parent::get_price_html( $price );
        }

        return sprintf(
            /* translators: %s: lowest tier price HTML */
            __( 'From %s', 'wpl-product-bundles' ),
            wc_price( (float) $minPrice )
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getBundleConfig(): ?BundleConfig {
        $id = $this->get_id();
        if ( $id <= 0 ) {
            return null;
        }

        if ( ! array_key_exists( $id, self::$configCache ) ) {
            $repo = BundleProductPriceService::getConfigRepository();
            self::$configCache[ $id ] = $repo !== null ? $repo->get( $id ) : null;
        }

        return self::$configCache[ $id ];
    }

    /** @internal For testing only. */
    public static function clearConfigCache(): void {
        self::$configCache = [];
    }
}
