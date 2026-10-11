<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Pricing;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\ComponentConfig;
use WPL\ProductBundles\Selection\BundleSelection;

/**
 * Central pricing engine.
 *
 * Implements the three pricing modes defined in PRICING-SPEC.md:
 *
 *   per_bundle    — WC product price, no component arithmetic.
 *   per_component — sum of component prices with component- and bundle-level discounts.
 *   tiered        — fixed unit price looked up from a quantity-based tier table.
 *
 * SECURITY: This service never accepts browser-submitted prices.  It always
 * recalculates from authoritative product meta retrieved server-side.
 *
 * TAX: Taxes are never calculated here.  Component products retain their own
 * tax class; WooCommerce applies tax per line item using
 * wc_get_price_including_tax() / wc_get_price_excluding_tax() as configured.
 *
 * COUPONS: Coupon interaction is documented in PRICING-SPEC.md §5.3 and is not
 * enforced here.  A coupon targeted at a component product that is also sold
 * standalone may apply inside a bundle unexpectedly — this is a known edge case
 * deferred to a later admin setting.
 *
 * ROUNDING: Applied once per component unit price AFTER discount multiplication,
 * per PRICING-SPEC.md §3.2:
 *   $unitPrice = round($base * (1 - $discount / 100), $decimals, PHP_ROUND_HALF_UP)
 * The bundle-level discount is applied to the already-rounded component sum and
 * rounded once more.  This matches WooCommerce's own price-rounding pattern.
 */
final class PricingService implements PricingServiceInterface {

    public function __construct(
        private readonly ProductPriceResolverInterface $priceResolver,
        private readonly TierResolver $tierResolver,
        private readonly ?int $priceDecimals = null,
    ) {}

    /**
     * Calculates the display price using the bundle's configured default
     * component quantities.  Used for product-page display.
     */
    public function calculateDefault(BundleConfig $config): PriceResult {
        return match ($config->priceMode) {
            'per_bundle' => $this->perBundleResult(),
            'tiered'     => $this->tieredResult($config, 1),
            default      => $this->perComponentFromDefaults($config),
        };
    }

    /**
     * Calculates the price from a customer's validated selection.
     * Used at add-to-cart time and on every cart-totals recalculation.
     *
     * @param int $bundleQty  Quantity of the bundle item in the cart (for tiered mode).
     */
    public function calculateFromSelection(
        BundleConfig $config,
        BundleSelection $selection,
        int $bundleQty = 1
    ): PriceResult {
        $result = match ($config->priceMode) {
            'per_bundle' => $this->perBundleResult(),
            'tiered'     => $this->tieredResult($config, $bundleQty),
            default      => $this->perComponentFromSelection($config, $selection),
        };

        /**
         * Filters the computed bundle price result.
         *
         * Allows third-party code to modify the final price before it is
         * stored on the cart item or returned via the REST estimate endpoint.
         * Return a PriceResult instance; any other value is ignored.
         *
         * @param PriceResult     $result    The computed price result.
         * @param BundleConfig    $config    Bundle configuration.
         * @param BundleSelection $selection Customer's selection.
         * @param int             $bundleQty Quantity of the bundle being purchased.
         */
        $filtered = apply_filters('wpl_bundle_price_result', $result, $config, $selection, $bundleQty);
        return $filtered instanceof PriceResult ? $filtered : $result;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function perBundleResult(): PriceResult {
        // Per-bundle mode delegates entirely to WC's own price field.
        // Callers must use WC_Product::get_price() when they receive this result.
        return new PriceResult('per_bundle', '', '0.00', '', []);
    }

    private function tieredResult(BundleConfig $config, int $bundleQty): PriceResult {
        $unitPrice = $this->tierResolver->resolve($config->priceTiers, $bundleQty);
        $decimals  = $this->getDecimals();
        $total     = PriceRounder::round((float) $unitPrice, $decimals);

        return new PriceResult(
            mode:           'tiered',
            subtotal:       $total,
            bundleDiscount: '0.00',
            total:          $total,
            components:     [],
        );
    }

    private function perComponentFromDefaults(BundleConfig $config): PriceResult {
        $inputs = [];
        foreach ($config->items as $component) {
            // Skip optional components where the configured default is zero.
            if ($component->optional && $component->quantityDefault <= 0) {
                continue;
            }
            $inputs[] = [
                'component'   => $component,
                'productId'   => $component->productId,
                'variationId' => $component->variationId,
                'qty'         => $component->quantityDefault,
            ];
        }
        return $this->perComponentCalculation($config, $inputs);
    }

    private function perComponentFromSelection(BundleConfig $config, BundleSelection $selection): PriceResult {
        $inputs = [];
        foreach ($config->items as $component) {
            $sel = $selection->getSelection($component->id);
            if ($sel === null || $sel->quantity <= 0) {
                continue; // Optional component not selected, or zero quantity.
            }
            $inputs[] = [
                'component'   => $component,
                'productId'   => $sel->productId,
                'variationId' => $sel->variationId,
                'qty'         => $sel->quantity,
            ];
        }
        return $this->perComponentCalculation($config, $inputs);
    }

    /**
     * @param array<int, array{component: ComponentConfig, productId: int, variationId: int|null, qty: int}> $inputs
     */
    private function perComponentCalculation(BundleConfig $config, array $inputs): PriceResult {
        $decimals      = $this->getDecimals();
        $components    = [];
        $subtotalFloat = 0.0;

        foreach ($inputs as $input) {
            $component   = $input['component'];
            $productId   = $input['productId'];
            $variationId = $input['variationId'];
            $qty         = $input['qty'];

            $basePrice = $this->resolveBasePrice($component, $productId, $variationId);

            // Apply component-level discount and round (per spec §3.2).
            $unitPrice = PriceRounder::round(
                (float) $basePrice * (1.0 - (float) $component->discount / 100.0),
                $decimals
            );

            // Line price: unit × qty, rounded once.
            $linePrice = PriceRounder::round((float) $unitPrice * $qty, $decimals);

            $components[] = new ComponentPrice(
                componentId: $component->id,
                basePrice:   $basePrice,
                discountPct: $component->discount,
                unitPrice:   $unitPrice,
                quantity:    $qty,
                linePrice:   $linePrice,
            );

            $subtotalFloat += (float) $linePrice;
        }

        $subtotal = PriceRounder::round($subtotalFloat, $decimals);

        // Apply bundle-level discount to the rounded subtotal (spec §3.2).
        $bundleDiscountPct = (float) $config->discount;
        $totalFloat        = $subtotalFloat * (1.0 - $bundleDiscountPct / 100.0);
        $total             = PriceRounder::round($totalFloat, $decimals);
        $total             = PriceRounder::clampToZero($total);

        return new PriceResult(
            mode:           'per_component',
            subtotal:       $subtotal,
            bundleDiscount: $config->discount,
            total:          $total,
            components:     $components,
        );
    }

    private function resolveBasePrice(ComponentConfig $component, int $productId, ?int $variationId): string {
        if ($component->priceMode === 'free') {
            return '0.00';
        }

        if ($component->priceMode === 'override' && $component->overridePrice !== null) {
            return $component->overridePrice;
        }

        // component_default: delegate to WC (returns sale price when active).
        return $this->priceResolver->getPrice($productId, $variationId);
    }

    private function getDecimals(): int {
        if ($this->priceDecimals !== null) {
            return $this->priceDecimals;
        }
        return function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
    }
}
