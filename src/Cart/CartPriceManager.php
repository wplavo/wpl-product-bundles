<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Cart;

use WPL\ProductBundles\Config\BundleConfigRepositoryInterface;
use WPL\ProductBundles\Pricing\PriceRounder;
use WPL\ProductBundles\Pricing\PricingService;

/**
 * Recalculates all bundle-related cart item prices before WooCommerce computes totals.
 *
 * Hooked to woocommerce_before_calculate_totals at priority 20 so it runs after
 * WooCommerce's own setup but before line totals are computed.
 *
 * SECURITY: Prices are ALWAYS recalculated from the authoritative product meta
 * via PricingService. Prices stored in the cart session are never trusted.
 * This prevents a customer from manipulating stored cart data to get a lower price.
 *
 * Pricing mode behaviour:
 *   per_bundle   — parent retains its WC product price; children are zeroed.
 *   per_component — parent is zeroed; each child carries its adjusted unit price
 *                   (component discount + bundle-level discount distributed
 *                   multiplicatively across components).
 *   tiered       — parent carries the tier-resolved unit price for the current
 *                  bundle quantity; children are zeroed.
 *
 * TAX NOTE: Prices set here are exclusive of tax. WooCommerce applies tax per
 * line item using each product's configured tax class and the store's display
 * settings — no change is required here.
 *
 * INTEGRATION TESTS REQUIRED:
 *   This class interacts directly with WC_Cart and WC_Product objects which
 *   require a running WooCommerce installation. The following scenarios must be
 *   covered by integration tests (wp-browser / Codeception):
 *     – per_component: correct line totals per child with no bundle discount
 *     – per_component: bundle-level discount distributed proportionally
 *     – tiered: correct parent price at tier boundary quantities
 *     – per_bundle: children zeroed, parent price unchanged
 *     – price injection idempotency (multiple totals recalculations)
 */
final class CartPriceManager {

    public function __construct(
        private readonly BundleConfigRepositoryInterface $repository,
        private readonly PricingService $pricingService,
        private readonly CartSelectionBuilderInterface $selectionBuilder,
    ) {}

    /**
     * Injects server-calculated prices for all bundle items in the cart.
     */
    public function injectPrices(\WC_Cart $cart): void {
        $contents = $cart->get_cart();
        $decimals = (int) wc_get_price_decimals();

        foreach ($contents as $parentKey => $parentItem) {
            if (!isset($parentItem[BundleCartMeta::PARENT_KEY])
                || !is_array($parentItem[BundleCartMeta::PARENT_KEY])
            ) {
                continue;
            }

            $parentMeta = $parentItem[BundleCartMeta::PARENT_KEY];
            $priceMode  = (string) ($parentMeta[BundleCartMeta::PRICE_MODE] ?? 'per_bundle');
            $bundleId   = (int) ($parentItem['product_id'] ?? 0);
            $bundleQty  = (int) ($parentItem['quantity'] ?? 1);
            $bundleCartId = (string) ($parentMeta[BundleCartMeta::BUNDLE_CART_ID] ?? '');

            $config = $this->repository->get($bundleId);
            if ($config === null) {
                continue;
            }

            /** @var \WC_Product|null $parentProduct */
            $parentProduct = $parentItem['data'] instanceof \WC_Product ? $parentItem['data'] : null;

            if ($priceMode === 'per_bundle') {
                // Parent keeps its WC price; zero all children.
                $this->zeroChildPrices($bundleCartId, $contents);
                continue;
            }

            if ($priceMode === 'tiered') {
                $selection = $this->selectionBuilder->fromCart($bundleId, $parentKey, $contents);
                $result    = $this->pricingService->calculateFromSelection($config, $selection, $bundleQty);
                if ($parentProduct !== null) {
                    $parentProduct->set_price($result->total);
                }
                $this->zeroChildPrices($bundleCartId, $contents);
                continue;
            }

            // per_component: parent = 0, children = adjusted unit prices.
            if ($parentProduct !== null) {
                $parentProduct->set_price('0');
            }

            $selection   = $this->selectionBuilder->fromCart($bundleId, $parentKey, $contents);
            $result      = $this->pricingService->calculateFromSelection($config, $selection, $bundleQty);
            $discountPct = (float) $result->bundleDiscount;
            $factor      = $discountPct > 0.0 ? (1.0 - $discountPct / 100.0) : 1.0;

            foreach ($result->components as $compPrice) {
                $childKey = $this->findChildKey($parentKey, $compPrice->componentId, $contents);
                if ($childKey === null) {
                    continue;
                }
                /** @var \WC_Product|null $childProduct */
                $childProduct = $contents[$childKey]['data'] instanceof \WC_Product
                    ? $contents[$childKey]['data']
                    : null;
                if ($childProduct === null) {
                    continue;
                }
                // Distribute bundle-level discount into the per-unit price.
                $effectivePrice = PriceRounder::round(
                    (float) $compPrice->unitPrice * $factor,
                    $decimals
                );
                $childProduct->set_price($effectivePrice);
            }
        }
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Sets the price to 0 on all child items belonging to the given bundle.
     *
     * @param array<string, array<string, mixed>> $contents
     */
    private function zeroChildPrices(string $bundleCartId, array $contents): void {
        foreach ($contents as $item) {
            if (!isset($item[BundleCartMeta::CHILD_KEY]) || !is_array($item[BundleCartMeta::CHILD_KEY])) {
                continue;
            }
            if (($item[BundleCartMeta::CHILD_KEY][BundleCartMeta::BUNDLE_CART_ID] ?? '') !== $bundleCartId) {
                continue;
            }
            if ($item['data'] instanceof \WC_Product) {
                $item['data']->set_price('0');
            }
        }
    }

    /**
     * Returns the cart item key of the child that matches a given parent key
     * and component ID, or null if not found.
     *
     * @param array<string, array<string, mixed>> $contents
     */
    private function findChildKey(string $parentKey, string $componentId, array $contents): ?string {
        foreach ($contents as $key => $item) {
            if (!isset($item[BundleCartMeta::CHILD_KEY]) || !is_array($item[BundleCartMeta::CHILD_KEY])) {
                continue;
            }
            $meta = $item[BundleCartMeta::CHILD_KEY];
            if (($meta[BundleCartMeta::PARENT_CART_KEY] ?? '') === $parentKey
                && ($meta[BundleCartMeta::COMPONENT_ID] ?? '') === $componentId
            ) {
                return (string) $key;
            }
        }
        return null;
    }
}
