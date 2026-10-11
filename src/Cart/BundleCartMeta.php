<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

/**
 * Meta key constants for bundle cart and order items.
 *
 * Cart structure:
 *   Parent bundle item: $cartItem[PARENT_KEY] = [ BUNDLE_CART_ID, PRICE_MODE ]
 *   Child component item: $cartItem[CHILD_KEY]  = [ BUNDLE_CART_ID, PARENT_CART_KEY, COMPONENT_ID, COMPONENT_QTY ]
 *
 * BUNDLE_CART_ID is a UUID shared between the parent and all its children so they
 * can be linked after the cart session is loaded or after order creation.
 */
final class BundleCartMeta {

    // ── Cart item array keys ────────────────────────────────────────────────

    /** Top-level key added to the parent bundle cart item array. */
    const PARENT_KEY = 'wpl_bundle';

    /** Top-level key added to each child component cart item array. */
    const CHILD_KEY  = 'wpl_bundle_child';

    // ── Fields inside the PARENT_KEY sub-array ─────────────────────────────

    /** UUID shared between the parent and all its children. */
    const BUNDLE_CART_ID = 'bundle_cart_id';

    /** Pricing mode from BundleConfig (per_bundle | per_component | tiered). */
    const PRICE_MODE = 'price_mode';

    // ── Fields inside the CHILD_KEY sub-array (also shares BUNDLE_CART_ID) ─

    /** Cart item key of the parent bundle item. */
    const PARENT_CART_KEY = 'parent_cart_key';

    /** Component ID from BundleConfig. */
    const COMPONENT_ID = 'component_id';

    /** Component quantity per one bundle unit (not the total cart quantity). */
    const COMPONENT_QTY = 'component_qty';

    // ── Order item meta keys ────────────────────────────────────────────────

    /** Stored on the parent order line item: value '1'. */
    const ORDER_BUNDLE_PARENT = '_wpl_bundle_parent';

    /** Stored on each child order line item: value '1'. */
    const ORDER_BUNDLE_CHILD  = '_wpl_bundle_child';

    /** UUID linking parent and child order items (mirrors BUNDLE_CART_ID). */
    const ORDER_CART_ID = '_wpl_bundle_cart_id';

    /** Component ID stored on child order items. */
    const ORDER_COMP_ID = '_wpl_component_id';

    /** Per-bundle-unit component quantity stored on child order items. */
    const ORDER_COMP_QTY = '_wpl_component_qty';

    /** Comma-separated child order item IDs stored on the parent item. */
    const ORDER_CHILD_ITEM_IDS = '_wpl_child_item_ids';

    /** Parent order item ID stored on each child item. */
    const ORDER_PARENT_ITEM_ID = '_wpl_parent_item_id';
}
