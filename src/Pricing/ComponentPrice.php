<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

/**
 * Immutable value object representing the resolved price for one component slot.
 *
 * All monetary fields are numeric strings to avoid float precision loss
 * when serialising to JSON or passing to WooCommerce APIs.
 */
final class ComponentPrice {

    public function __construct(
        public readonly string $componentId,
        public readonly string $basePrice,
        public readonly string $discountPct,
        public readonly string $unitPrice,
        public readonly int $quantity,
        public readonly string $linePrice,
    ) {}
}
