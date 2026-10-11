<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

/**
 * Keeps child component cart items in sync with their parent bundle item.
 *
 * Quantity sync: when a customer changes the bundle quantity, all child item
 * quantities are scaled proportionally (child_qty = component_qty_per_bundle × bundle_qty).
 *
 * Removal: when a bundle parent is removed from the cart, all its children
 * are removed too. Removing a child item directly is not handled — it leaves
 * the bundle in a potentially invalid state, which is an accepted limitation.
 * A future phase may add re-validation of orphaned bundles.
 */
final class CartSyncManager {

    /**
     * Hooked to woocommerce_after_cart_item_quantity_update (priority 10).
     *
     * @param string   $cartItemKey  Key of the item whose quantity changed.
     * @param int      $newQty       New quantity value.
     * @param int      $oldQty       Previous quantity value.
     * @param \WC_Cart $cart         The cart instance.
     */
    public function syncChildQuantities(string $cartItemKey, int $newQty, int $oldQty, \WC_Cart $cart): void {
        $item = $cart->get_cart_item($cartItemKey);
        if (!$item || !isset($item[BundleCartMeta::PARENT_KEY]) || !is_array($item[BundleCartMeta::PARENT_KEY])) {
            return;
        }

        $bundleCartId = (string) ($item[BundleCartMeta::PARENT_KEY][BundleCartMeta::BUNDLE_CART_ID] ?? '');
        if ($bundleCartId === '' || $newQty <= 0) {
            return;
        }

        foreach ($cart->get_cart() as $key => $ci) {
            if (!isset($ci[BundleCartMeta::CHILD_KEY]) || !is_array($ci[BundleCartMeta::CHILD_KEY])) {
                continue;
            }
            if (($ci[BundleCartMeta::CHILD_KEY][BundleCartMeta::BUNDLE_CART_ID] ?? '') !== $bundleCartId) {
                continue;
            }
            $compQty = max(1, (int) ($ci[BundleCartMeta::CHILD_KEY][BundleCartMeta::COMPONENT_QTY] ?? 1));
            $cart->set_quantity($key, $compQty * $newQty, false);
        }
    }

    /**
     * Hooked to woocommerce_remove_cart_item (priority 10).
     *
     * When WC fires this action the item has already been moved to
     * removed_cart_contents, so we read from there to identify whether the
     * removed item is a bundle parent.
     *
     * @param string   $cartItemKey  Key of the item that was just removed.
     * @param \WC_Cart $cart         The cart instance.
     */
    public function removeChildItems(string $cartItemKey, \WC_Cart $cart): void {
        $removedContents = $cart->get_removed_cart_contents();
        $item = $removedContents[$cartItemKey] ?? null;

        if (!$item || !isset($item[BundleCartMeta::PARENT_KEY]) || !is_array($item[BundleCartMeta::PARENT_KEY])) {
            return;
        }

        $bundleCartId = (string) ($item[BundleCartMeta::PARENT_KEY][BundleCartMeta::BUNDLE_CART_ID] ?? '');
        if ($bundleCartId === '') {
            return;
        }

        // Collect keys first to avoid modifying the array while iterating.
        $toRemove = [];
        foreach ($cart->get_cart() as $key => $ci) {
            if (!isset($ci[BundleCartMeta::CHILD_KEY]) || !is_array($ci[BundleCartMeta::CHILD_KEY])) {
                continue;
            }
            if (($ci[BundleCartMeta::CHILD_KEY][BundleCartMeta::BUNDLE_CART_ID] ?? '') === $bundleCartId) {
                $toRemove[] = $key;
            }
        }

        foreach ($toRemove as $key) {
            $cart->remove_cart_item($key);
        }
    }
}
