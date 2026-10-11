<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Immutable summary of a WooCommerce product used by domain validators.
 *
 * Decouples the validator layer from WC_Product so validation logic
 * can be unit-tested without a running WooCommerce installation.
 */
final class ProductInfo {

    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly int $parentId,
    ) {}

    /**
     * Returns true when this product is a variation (i.e. a child of a variable product).
     */
    public function isVariation(): bool {
        return $this->type === 'variation';
    }
}
