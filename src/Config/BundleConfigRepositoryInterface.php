<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Bundle config persistence contract.
 * Implemented by BundleConfigRepository; interfaces allow cart, order, and admin
 * handlers to be unit-tested without a running WooCommerce installation.
 */
interface BundleConfigRepositoryInterface {

    /**
     * Returns the BundleConfig for the given product ID, or null if none exists.
     */
    public function get(int $productId): ?BundleConfig;

    /**
     * Persists a bundle configuration onto a WC product.
     * Caller must ensure $product is of type 'bundle'.
     * Calls save_meta_data() but not save() — the caller controls when the product saves.
     */
    public function save(\WC_Product $product, BundleConfig $config): void;
}
