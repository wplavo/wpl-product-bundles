<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

/**
 * Registers all WooCommerce cart hooks for bundle product support.
 *
 * Hooks registered here:
 *
 *  woocommerce_add_to_cart_validation      — validate bundle selection + nonce
 *  woocommerce_add_to_cart                 — add child component items
 *  woocommerce_before_calculate_totals     — re-inject server-calculated prices
 *  woocommerce_after_cart_item_quantity_update — sync child quantities
 *  woocommerce_remove_cart_item            — remove children with parent
 *  woocommerce_cart_item_visible           — hide child items in cart table
 *  woocommerce_widget_cart_item_visible    — hide child items in mini-cart
 *  woocommerce_checkout_cart_item_visible  — hide child items at checkout
 *  woocommerce_order_item_visible          — hide child items in order emails/pages
 *  woocommerce_product_add_to_cart_url     — redirect archive button to product page
 *  woocommerce_product_add_to_cart_text    — change archive button text
 */
final class CartServiceProvider {

    public function __construct(
        private readonly AddToCartHandler $addToCartHandler,
        private readonly CartPriceManager $priceManager,
        private readonly CartSyncManager $syncManager,
    ) {}

    public function register(): void {
        add_filter('woocommerce_add_to_cart_validation', [$this->addToCartHandler, 'validate'], 10, 6);
        add_action('woocommerce_add_to_cart', [$this->addToCartHandler, 'addChildItems'], 20, 6);
        add_action('woocommerce_before_calculate_totals', [$this->priceManager, 'injectPrices'], 20);
        add_action('woocommerce_after_cart_item_quantity_update', [$this->syncManager, 'syncChildQuantities'], 10, 4);
        add_action('woocommerce_remove_cart_item', [$this->syncManager, 'removeChildItems'], 10, 2);

        add_filter('woocommerce_cart_item_visible', [$this, 'isCartItemVisible'], 10, 3);
        add_filter('woocommerce_widget_cart_item_visible', [$this, 'isCartItemVisible'], 10, 3);
        add_filter('woocommerce_checkout_cart_item_visible', [$this, 'isCartItemVisible'], 10, 3);
        add_filter('woocommerce_order_item_visible', [$this, 'isOrderItemVisible'], 10, 2);

        add_filter('woocommerce_product_add_to_cart_url', [$this, 'archiveAddToCartUrl'], 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'archiveAddToCartText'], 10, 2);
    }

    /**
     * Hides child component items in all cart display contexts.
     *
     * @param bool   $visible     Current visibility state.
     * @param array<string, mixed> $cartItem  Cart item array.
     * @param string $cartItemKey Cart item key (unused).
     */
    public function isCartItemVisible(bool $visible, array $cartItem, string $cartItemKey): bool {
        if (!isset($cartItem[BundleCartMeta::CHILD_KEY])) {
            return $visible;
        }

        /**
         * Filters whether a bundle component item is visible in cart/checkout display.
         *
         * Return true to show the component row, false (default) to hide it.
         * This filter fires only for child component items, not the parent bundle.
         *
         * @param bool   $visible     Current visibility (always false by default).
         * @param array<string, mixed> $cartItem  The child cart item array.
         * @param string $cartItemKey The cart item key.
         */
        return (bool) apply_filters('wpl_bundle_component_visible', false, $cartItem, $cartItemKey);
    }

    /**
     * Hides child component items in order views (emails, account pages, admin).
     *
     * @param bool          $visible  Current visibility state.
     * @param \WC_Order_Item $item    Order line item.
     */
    public function isOrderItemVisible(bool $visible, \WC_Order_Item $item): bool {
        return $item->get_meta(BundleCartMeta::ORDER_BUNDLE_CHILD) === '1' ? false : $visible;
    }

    /**
     * On archive/shop pages, redirects the "Add to cart" button to the product
     * page so customers must fill in the bundle form.
     */
    public function archiveAddToCartUrl(string $url, \WC_Product $product): string {
        return $product->get_type() === 'bundle' ? (string) $product->get_permalink() : $url;
    }

    /**
     * Changes the archive "Add to cart" button label for bundle products.
     */
    public function archiveAddToCartText(string $text, \WC_Product $product): string {
        return $product->get_type() === 'bundle'
            ? esc_html__('Select options', 'wpl-product-bundles')
            : $text;
    }
}
