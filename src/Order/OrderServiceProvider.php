<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Order;

/**
 * Registers all WooCommerce order hooks for bundle product support.
 *
 * Hooks registered here:
 *
 *  woocommerce_checkout_create_order_line_item — write bundle meta to order items
 *  woocommerce_checkout_order_created          — link parent/child order item IDs
 *  woocommerce_prevent_adjust_line_item_product_stock — prevent parent stock reduction
 *  woocommerce_can_restore_order_item_stock    — prevent parent stock restoration
 */
final class OrderServiceProvider {

    public function __construct(
        private readonly OrderIntegration $integration,
    ) {}

    public function register(): void {
        add_action(
            'woocommerce_checkout_create_order_line_item',
            [$this->integration, 'writeLineItemMeta'],
            10,
            4
        );

        add_action(
            'woocommerce_checkout_order_created',
            [$this->integration, 'linkParentChildItems'],
            10
        );

        add_filter(
            'woocommerce_prevent_adjust_line_item_product_stock',
            [$this->integration, 'preventParentStockAdjust'],
            10,
            3
        );

        add_filter(
            'woocommerce_can_restore_order_item_stock',
            [$this->integration, 'preventParentStockRestore'],
            10,
            3
        );
    }
}
