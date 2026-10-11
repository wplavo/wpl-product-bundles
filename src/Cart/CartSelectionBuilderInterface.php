<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

use WPL\ProductBundles\Selection\BundleSelection;

/**
 * Contract for building a BundleSelection from POST data or cart contents.
 */
interface CartSelectionBuilderInterface {

    /**
     * Parses submitted form POST data into a BundleSelection.
     *
     * @param int   $bundleProductId  Product ID of the parent bundle.
     * @param array<string, mixed> $post  Typically $_POST.
     */
    public function fromPost(int $bundleProductId, array $post): BundleSelection;

    /**
     * Rebuilds a BundleSelection from existing child items in the cart.
     *
     * @param int    $bundleProductId  Product ID of the parent bundle.
     * @param string $parentCartKey    Cart item key of the parent bundle item.
     * @param array<string, array<string, mixed>> $cartContents
     */
    public function fromCart(int $bundleProductId, string $parentCartKey, array $cartContents): BundleSelection;
}
