<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

use WPL\ProductBundles\Config\BundleConfigRepositoryInterface;
use WPL\ProductBundles\Config\ProductResolverInterface;
use WPL\ProductBundles\Selection\SelectionValidatorInterface;

/**
 * Handles add-to-cart for bundle products.
 *
 * Two hooks are registered by CartServiceProvider:
 *
 *  1. woocommerce_add_to_cart_validation (priority 10) — validate()
 *     Runs before WC adds the parent item.  Verifies the nonce, builds
 *     a BundleSelection from POST, runs SelectionValidator, and adds WC
 *     error notices on failure.
 *
 *  2. woocommerce_add_to_cart (priority 20) — addChildItems()
 *     Runs after the parent item is already in the cart.  Adds a child
 *     cart item for every component in the validated selection.
 *
 * SECURITY:
 *   – Nonce verified before any processing.
 *   – Product type confirmed via ProductResolverInterface (never trusting POST).
 *   – Component prices are never read from POST; PricingService recalculates
 *     them on every woocommerce_before_calculate_totals call.
 *
 * LIMITATION — WC Blocks / Store API:
 *   The woocommerce_add_to_cart_validation filter does not fire for REST API
 *   add-to-cart requests used by WC Blocks.  Full Blocks compatibility requires
 *   a separate Store API extension (out of scope for Phase 6).
 */
final class AddToCartHandler {

    public function __construct(
        private readonly BundleConfigRepositoryInterface $repository,
        private readonly SelectionValidatorInterface $validator,
        private readonly ProductResolverInterface $productResolver,
        private readonly CartSelectionBuilderInterface $selectionBuilder,
    ) {}

    // ── Hook: woocommerce_add_to_cart_validation ────────────────────────────

    /**
     * @param bool  $valid       Current validation state from prior filters.
     * @param int   $productId   The product being added.
     * @param int   $qty         Bundle quantity.
     * @param int   $variationId 0 for non-variable (unused for bundles).
     * @param array<string, mixed> $variations  Unused for bundles.
     * @param array<string, mixed> $cartItemData  Extra cart item data from prior filters.
     */
    public function validate(
        bool $valid,
        int $productId,
        int $qty,
        int $variationId = 0,
        array $variations = [],
        array $cartItemData = []
    ): bool {
        // Only process bundle products.
        $productInfo = $this->productResolver->resolve($productId);
        if ($productInfo === null || $productInfo->type !== 'bundle') {
            return $valid;
        }

        // Programmatic adds (no wpl_component POST data) skip our validation.
        if (!isset($_POST['wpl_component'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $valid;
        }

        // Nonce verification — prevents CSRF on form submissions.
        $nonce = isset($_POST['_wpl_bundle_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['_wpl_bundle_nonce'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            : '';
        if (!wp_verify_nonce($nonce, 'wpl_add_bundle_to_cart')) {
            wc_add_notice(
                esc_html__('Security check failed. Please try again.', 'wpl-product-bundles'),
                'error'
            );
            return false;
        }

        $config = $this->repository->get($productId);
        if ($config === null) {
            wc_add_notice(
                esc_html__('Bundle configuration not found.', 'wpl-product-bundles'),
                'error'
            );
            return false;
        }

        $selection = $this->selectionBuilder->fromPost($productId, $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $result    = $this->validator->validate($config, $selection);

        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                wc_add_notice(esc_html($error['message']), 'error');
            }
            return false;
        }

        return true;
    }

    // ── Hook: woocommerce_add_to_cart ───────────────────────────────────────

    /**
     * Adds a child cart item for each component after the parent bundle is added.
     *
     * Calling WC()->cart->add_to_cart() for each child triggers
     * woocommerce_add_to_cart again, but children are not bundle type so the
     * early-return guards prevent infinite recursion.
     *
     * @param string $cartItemKey  Key of the parent item just added.
     * @param int    $productId    Parent bundle product ID.
     * @param int    $qty          Number of bundle units added.
     * @param int    $variationId  0 for bundles.
     * @param array<string, mixed> $variation
     * @param array<string, mixed> $cartItemData
     */
    public function addChildItems(
        string $cartItemKey,
        int $productId,
        int $qty,
        int $variationId,
        array $variation,
        array $cartItemData
    ): void {
        $productInfo = $this->productResolver->resolve($productId);
        if ($productInfo === null || $productInfo->type !== 'bundle') {
            return;
        }

        if (!isset($_POST['wpl_component']) || !is_array($_POST['wpl_component'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $config = $this->repository->get($productId);
        if ($config === null) {
            return;
        }

        $selection    = $this->selectionBuilder->fromPost($productId, $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $bundleCartId = wp_generate_uuid4();

        // Attach bundle metadata to the parent cart item (already in cart_contents).
        WC()->cart->cart_contents[$cartItemKey][BundleCartMeta::PARENT_KEY] = [
            BundleCartMeta::BUNDLE_CART_ID => $bundleCartId,
            BundleCartMeta::PRICE_MODE     => $config->priceMode,
        ];

        foreach ($selection->getSelections() as $sel) {
            if ($sel->quantity <= 0) {
                continue;
            }
            WC()->cart->add_to_cart(
                $sel->productId,
                $sel->quantity * $qty,
                $sel->variationId ?? 0,
                [],
                [
                    BundleCartMeta::CHILD_KEY => [
                        BundleCartMeta::BUNDLE_CART_ID  => $bundleCartId,
                        BundleCartMeta::PARENT_CART_KEY => $cartItemKey,
                        BundleCartMeta::COMPONENT_ID    => $sel->componentId,
                        BundleCartMeta::COMPONENT_QTY   => $sel->quantity,
                    ],
                ]
            );
        }

        /**
         * Fires after all child component cart items have been added for a bundle.
         *
         * @param string $cartItemKey Parent bundle cart item key.
         * @param int    $productId   Parent bundle product ID.
         * @param int    $qty         Quantity of bundle units added.
         * @param \WPL\ProductBundles\Config\BundleConfig    $config    Bundle configuration.
         * @param \WPL\ProductBundles\Selection\BundleSelection $selection Customer's validated selection.
         */
        do_action('wpl_bundle_after_add_to_cart', $cartItemKey, $productId, $qty, $config, $selection);
    }
}
