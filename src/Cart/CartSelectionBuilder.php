<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

use WPL\ProductBundles\Selection\BundleSelection;
use WPL\ProductBundles\Selection\ComponentSelection;

/**
 * Builds a BundleSelection from either HTTP POST data or existing cart contents.
 *
 * POST form field names:
 *   wpl_component[{componentId}][product_id]  = int
 *   wpl_component[{componentId}][variation_id] = int  (optional)
 *   wpl_component[{componentId}][quantity]     = int
 *
 * All user-supplied values are sanitised here. The resulting BundleSelection is
 * then validated by SelectionValidator before any cart operation proceeds.
 */
final class CartSelectionBuilder implements CartSelectionBuilderInterface {

    /**
     * Parses the submitted add-to-cart form data into a BundleSelection.
     *
     * @param int   $bundleProductId  Product ID of the parent bundle.
     * @param array<string, mixed> $post  Typically $_POST.
     */
    public function fromPost(int $bundleProductId, array $post): BundleSelection {
        $raw = isset($post['wpl_component']) && is_array($post['wpl_component'])
            ? $post['wpl_component']
            : [];

        $selections = [];
        foreach ($raw as $rawId => $data) {
            if (!is_array($data)) {
                continue;
            }

            $componentId = sanitize_key((string) $rawId);
            $productId   = absint($data['product_id'] ?? 0);
            $variationId = !empty($data['variation_id']) ? absint($data['variation_id']) : null;
            $qty         = max(0, (int) ($data['quantity'] ?? 0));

            if ($componentId === '' || $productId <= 0 || $qty <= 0) {
                continue;
            }

            $selections[] = new ComponentSelection($componentId, $productId, $variationId, $qty);
        }

        return new BundleSelection($bundleProductId, $selections);
    }

    /**
     * Rebuilds a BundleSelection from the child items that are already in the cart.
     * Used by CartPriceManager to recalculate prices on every page load without
     * trusting the prices stored in the session.
     *
     * Returns per-bundle-unit quantities (not the total cart quantities).
     *
     * @param int    $bundleProductId  Product ID of the parent bundle.
     * @param string $parentCartKey    Cart item key of the parent bundle item.
     * @param array<string, array<string, mixed>> $cartContents  From WC_Cart::get_cart().
     */
    public function fromCart(int $bundleProductId, string $parentCartKey, array $cartContents): BundleSelection {
        $selections = [];

        foreach ($cartContents as $item) {
            if (!isset($item[BundleCartMeta::CHILD_KEY]) || !is_array($item[BundleCartMeta::CHILD_KEY])) {
                continue;
            }
            $meta = $item[BundleCartMeta::CHILD_KEY];

            if (($meta[BundleCartMeta::PARENT_CART_KEY] ?? '') !== $parentCartKey) {
                continue;
            }

            $componentId  = (string) ($meta[BundleCartMeta::COMPONENT_ID] ?? '');
            $productId    = (int) ($item['product_id'] ?? 0);
            $variationId  = !empty($item['variation_id']) ? (int) $item['variation_id'] : null;
            $qtyPerBundle = max(1, (int) ($meta[BundleCartMeta::COMPONENT_QTY] ?? 1));

            if ($componentId === '' || $productId <= 0) {
                continue;
            }

            $selections[] = new ComponentSelection($componentId, $productId, $variationId, $qtyPerBundle);
        }

        return new BundleSelection($bundleProductId, $selections);
    }
}
