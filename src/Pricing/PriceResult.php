<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Immutable result returned by PricingService for one bundle calculation pass.
 *
 * For `per_bundle` mode, $total is an empty string — the calling code must
 * fall back to WC_Product::get_price().  For all other modes it is a
 * non-negative numeric string ready to pass to wc_price() or set on a cart item.
 *
 * Tax is never included here; WooCommerce applies tax per line item using
 * wc_get_price_including_tax() / wc_get_price_excluding_tax() as appropriate
 * for the store's display settings.
 */
final class PriceResult {

    /**
     * @param ComponentPrice[] $components  Empty for per_bundle and tiered modes.
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $subtotal,
        public readonly string $bundleDiscount,
        public readonly string $total,
        public readonly array $components,
    ) {}

    public function isPerBundle(): bool {
        return $this->mode === 'per_bundle';
    }

    public function isPerComponent(): bool {
        return $this->mode === 'per_component';
    }

    public function isTiered(): bool {
        return $this->mode === 'tiered';
    }
}
