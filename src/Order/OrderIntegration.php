<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Order;

use WPL\ProductBundles\Cart\BundleCartMeta;

/**
 * Manages bundle order item metadata and stock adjustment guards.
 *
 * Responsibilities:
 *
 *  1. writeLineItemMeta   — stores bundle/component metadata on order items during
 *                           checkout so the parent-child relationship survives
 *                           independently of cart session data.
 *
 *  2. linkParentChildItems — after the full order is created, cross-links parent
 *                            order items to their children (and vice versa) using
 *                            the shared bundle_cart_id UUID.
 *
 *  3. preventParentStockAdjust  — prevents the bundle parent order item from
 *                                 reducing stock (only children reduce stock).
 *
 *  4. preventParentStockRestore — prevents the bundle parent order item from
 *                                 restoring stock on order cancellation / refund
 *                                 (only children restore stock).
 *
 * HPOS COMPATIBILITY:
 *   All order reads/writes use the WC_Order object API exclusively — no
 *   get_post_meta(), no WP_Query with post_type=shop_order.  This satisfies
 *   the WooCommerce HPOS (High-Performance Order Storage) requirement.
 *
 * STOCK RACE CONDITIONS:
 *   WooCommerce does not provide true atomic stock reservation between
 *   add-to-cart and checkout.  The stock is checked at add-to-cart time via
 *   SelectionValidator, and again by WooCommerce's own woocommerce_check_cart_items
 *   at checkout.  A narrow window exists between these two checks in which
 *   concurrent purchases may oversell a component.  This is a known WooCommerce
 *   limitation; implementing row-level locking would require custom DB transactions
 *   outside the scope of this phase.
 *
 * INTEGRATION TESTS REQUIRED:
 *   – Successful checkout: order items carry correct meta; parent/child IDs linked.
 *   – Stock reduced for children only (parent meta prevents double-reduction).
 *   – Cancelled/refunded order: children restocked, parent not restocked.
 *   – HPOS mode enabled: order meta written and readable via order API only.
 */
final class OrderIntegration {

    /**
     * Hooked to woocommerce_checkout_create_order_line_item (priority 10).
     * Writes bundle metadata from the cart item onto the order line item.
     *
     * @param \WC_Order_Item_Product $item        The order line item being created.
     * @param string                 $cartItemKey  The source cart item key.
     * @param array<string, mixed>   $values       The source cart item array.
     * @param \WC_Order              $order        The order being created.
     */
    public function writeLineItemMeta(
        \WC_Order_Item_Product $item,
        string $cartItemKey,
        array $values,
        \WC_Order $order
    ): void {
        if (isset($values[BundleCartMeta::PARENT_KEY]) && is_array($values[BundleCartMeta::PARENT_KEY])) {
            $meta = $values[BundleCartMeta::PARENT_KEY];
            $item->add_meta_data(BundleCartMeta::ORDER_BUNDLE_PARENT, '1', true);
            $item->add_meta_data(
                BundleCartMeta::ORDER_CART_ID,
                (string) ($meta[BundleCartMeta::BUNDLE_CART_ID] ?? ''),
                true
            );
        }

        if (isset($values[BundleCartMeta::CHILD_KEY]) && is_array($values[BundleCartMeta::CHILD_KEY])) {
            $meta = $values[BundleCartMeta::CHILD_KEY];
            $item->add_meta_data(BundleCartMeta::ORDER_BUNDLE_CHILD, '1', true);
            $item->add_meta_data(
                BundleCartMeta::ORDER_CART_ID,
                (string) ($meta[BundleCartMeta::BUNDLE_CART_ID] ?? ''),
                true
            );
            $item->add_meta_data(
                BundleCartMeta::ORDER_COMP_ID,
                (string) ($meta[BundleCartMeta::COMPONENT_ID] ?? ''),
                true
            );
            $item->add_meta_data(
                BundleCartMeta::ORDER_COMP_QTY,
                (string) ($meta[BundleCartMeta::COMPONENT_QTY] ?? '1'),
                true
            );
        }
    }

    /**
     * Hooked to woocommerce_checkout_order_created (priority 10).
     * Links parent order items to their children and vice versa.
     *
     * Uses the shared BUNDLE_CART_ID UUID written during writeLineItemMeta to
     * reconstruct the parent-child relationship inside the order.
     *
     * @param \WC_Order $order  The newly created order.
     */
    public function linkParentChildItems(\WC_Order $order): void {
        /** @var array<string, int> $parentByCartId  cartId → order item ID */
        $parentByCartId = [];
        /** @var array<string, list<int>> $childrenByCartId  cartId → list of child item IDs */
        $childrenByCartId = [];

        /** @var int $itemId */
        /** @var \WC_Order_Item $item */
        foreach ($order->get_items() as $itemId => $item) {
            $cartId = (string) $item->get_meta(BundleCartMeta::ORDER_CART_ID);
            if ($cartId === '') {
                continue;
            }
            if ($item->get_meta(BundleCartMeta::ORDER_BUNDLE_PARENT) === '1') {
                $parentByCartId[$cartId] = (int) $itemId;
            } elseif ($item->get_meta(BundleCartMeta::ORDER_BUNDLE_CHILD) === '1') {
                $childrenByCartId[$cartId][] = (int) $itemId;
            }
        }

        foreach ($parentByCartId as $cartId => $parentId) {
            $childIds = $childrenByCartId[$cartId] ?? [];
            if (empty($childIds)) {
                continue;
            }

            $parentItem = $order->get_item($parentId);
            if ($parentItem instanceof \WC_Order_Item) {
                $parentItem->add_meta_data(
                    BundleCartMeta::ORDER_CHILD_ITEM_IDS,
                    implode(',', $childIds),
                    true
                );
                $parentItem->save();
            }

            foreach ($childIds as $childId) {
                $childItem = $order->get_item($childId);
                if ($childItem instanceof \WC_Order_Item) {
                    $childItem->add_meta_data(
                        BundleCartMeta::ORDER_PARENT_ITEM_ID,
                        (string) $parentId,
                        true
                    );
                    $childItem->save();
                }
            }
        }
    }

    /**
     * Hooked to woocommerce_prevent_adjust_line_item_product_stock (priority 10).
     *
     * Prevents the bundle parent order item from reducing stock.  Only the
     * child component items (which are real WC products) reduce stock.
     * This avoids double-reduction when the bundle product itself has stock
     * management enabled.
     *
     * @param bool          $prevent  Current value (true = skip adjustment).
     * @param \WC_Order_Item $item    Order line item.
     * @param \WC_Order      $order   The order.
     */
    public function preventParentStockAdjust(bool $prevent, \WC_Order_Item $item, \WC_Order $order): bool {
        if ($item->get_meta(BundleCartMeta::ORDER_BUNDLE_PARENT) === '1') {
            return true;
        }
        return $prevent;
    }

    /**
     * Hooked to woocommerce_can_restore_order_item_stock (priority 10).
     *
     * Prevents the bundle parent item's stock from being restored when the
     * order is cancelled or refunded.  Child items restore their stock normally.
     *
     * @param bool          $can   Current value (true = allow restore).
     * @param \WC_Order_Item $item  Order line item.
     * @param \WC_Order      $order The order.
     */
    public function preventParentStockRestore(bool $can, \WC_Order_Item $item, \WC_Order $order): bool {
        if ($item->get_meta(BundleCartMeta::ORDER_BUNDLE_PARENT) === '1') {
            return false;
        }
        return $can;
    }
}
