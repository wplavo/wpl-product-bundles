<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Resolves a WooCommerce product ID to a ProductInfo summary.
 *
 * The interface exists so validators can be tested without WooCommerce.
 * The production implementation calls wc_get_product() under the hood.
 */
interface ProductResolverInterface {

    /**
     * Returns a summary of the product with the given ID, or null if it does not exist.
     */
    public function resolve(int $id): ?ProductInfo;
}
