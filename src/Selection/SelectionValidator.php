<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\ComponentConfig;
use WPL\ProductBundles\Config\ProductResolverInterface;
use WPL\ProductBundles\Validation\ValidationResult;

/**
 * Validates a customer's BundleSelection against the stored BundleConfig.
 *
 * Enforces quantity bounds, allowed-product constraints, variation-parent
 * consistency, and stock availability.  Pure server-side — never trusts
 * browser-submitted data.
 */
final class SelectionValidator implements SelectionValidatorInterface {

    public function __construct(
        private readonly ProductResolverInterface $productResolver,
        private readonly StockCheckerInterface $stockChecker,
    ) {}

    public function validate(BundleConfig $config, BundleSelection $selection): ValidationResult {
        $result = new ValidationResult();

        if ($config->mode === 'fixed') {
            $this->validateFixed($config, $selection, $result);
        } else {
            $this->validateMixAndMatch($config, $selection, $result);
        }

        /**
         * Filters the bundle validation result.
         *
         * Return a ValidationResult with no errors to force-pass validation,
         * or one with additional errors to block add-to-cart.
         *
         * @param ValidationResult $result    The computed validation result.
         * @param BundleConfig     $config    Bundle configuration.
         * @param BundleSelection  $selection Customer's selection.
         */
        $filtered = apply_filters('wpl_bundle_validation_result', $result, $config, $selection);
        return $filtered instanceof ValidationResult ? $filtered : $result;
    }

    private function validateFixed(
        BundleConfig $config,
        BundleSelection $selection,
        ValidationResult $result
    ): void {
        foreach ($config->items as $index => $component) {
            $field = "components[{$index}]";
            $sel   = $selection->getSelection($component->id);

            if ($sel === null) {
                if (!$component->optional) {
                    $result->addError(
                        'missing_component',
                        "Component \"{$component->id}\" is required.",
                        $field
                    );
                }
                continue;
            }

            // In a fixed bundle the product cannot be changed by the customer.
            if ($sel->productId !== $component->productId) {
                $result->addError(
                    'product_mismatch',
                    "Component \"{$component->id}\" must use product {$component->productId}.",
                    $field
                );
            }

            $this->validateQuantity($sel, $component, $field, $result);
            $this->validateVariationFixed($sel, $component, $field, $result);
            $this->validateStock($sel->productId, $sel->variationId, $sel->quantity, $field, $result);
        }
    }

    private function validateMixAndMatch(
        BundleConfig $config,
        BundleSelection $selection,
        ValidationResult $result
    ): void {
        $totalQty = 0;

        foreach ($config->items as $index => $component) {
            $field = "components[{$index}]";
            $sel   = $selection->getSelection($component->id);

            if ($sel === null) {
                if (!$component->optional) {
                    $result->addError(
                        'missing_component',
                        "Component slot \"{$component->id}\" is required.",
                        $field
                    );
                }
                continue;
            }

            // Product must exist and must not itself be a bundle.
            $productInfo = $this->productResolver->resolve($sel->productId);
            if ($productInfo === null) {
                $result->addError(
                    'product_not_found',
                    "Product {$sel->productId} was not found.",
                    $field
                );
                continue;
            }
            if ($productInfo->type === 'bundle') {
                $result->addError(
                    'nested_bundle',
                    'Bundle products cannot be used as bundle components.',
                    $field
                );
                continue;
            }

            // Check allowed-products constraint (when non-empty).
            if (!empty($component->allowedProducts)
                && !in_array($sel->productId, $component->allowedProducts, true)
            ) {
                $result->addError(
                    'product_not_allowed',
                    "Product {$sel->productId} is not allowed for slot \"{$component->id}\".",
                    $field
                );
            }

            $this->validateQuantity($sel, $component, $field, $result);
            $this->validateVariationMixAndMatch($sel, $component, $field, $result);
            $this->validateStock($sel->productId, $sel->variationId, $sel->quantity, $field, $result);

            $totalQty += $sel->quantity;
        }

        // Bundle-level total-quantity constraints.
        if ($totalQty < $config->minQty) {
            $result->addError(
                'total_qty_below_min',
                "Bundle requires at least {$config->minQty} item(s) in total.",
                'total_quantity'
            );
        }
        if ($config->maxQty > 0 && $totalQty > $config->maxQty) {
            $result->addError(
                'total_qty_above_max',
                "Bundle allows at most {$config->maxQty} item(s) in total.",
                'total_quantity'
            );
        }
    }

    private function validateQuantity(
        ComponentSelection $sel,
        ComponentConfig $component,
        string $field,
        ValidationResult $result
    ): void {
        if ($sel->quantity < $component->quantityMin) {
            $result->addError(
                'quantity_below_min',
                "Minimum quantity for component \"{$component->id}\" is {$component->quantityMin}.",
                $field
            );
        }

        if ($component->quantityMax > 0 && $sel->quantity > $component->quantityMax) {
            $result->addError(
                'quantity_above_max',
                "Maximum quantity for component \"{$component->id}\" is {$component->quantityMax}.",
                $field
            );
        }
    }

    private function validateVariationFixed(
        ComponentSelection $sel,
        ComponentConfig $component,
        string $field,
        ValidationResult $result
    ): void {
        // Pinned variation: customer must use the configured one.
        if ($component->variationId !== null && $sel->variationId !== $component->variationId) {
            $result->addError(
                'invalid_variation',
                "Component \"{$component->id}\" requires variation {$component->variationId}.",
                $field
            );
            return;
        }

        // Allowed-variations list check.
        if (!empty($component->allowedVariations)
            && $sel->variationId !== null
            && !in_array($sel->variationId, $component->allowedVariations, true)
        ) {
            $result->addError(
                'variation_not_allowed',
                "Variation {$sel->variationId} is not allowed for component \"{$component->id}\".",
                $field
            );
        }
    }

    private function validateVariationMixAndMatch(
        ComponentSelection $sel,
        ComponentConfig $component,
        string $field,
        ValidationResult $result
    ): void {
        if ($sel->variationId === null) {
            return;
        }

        // Allowed-variations list check.
        if (!empty($component->allowedVariations)
            && !in_array($sel->variationId, $component->allowedVariations, true)
        ) {
            $result->addError(
                'variation_not_allowed',
                "Variation {$sel->variationId} is not allowed for slot \"{$component->id}\".",
                $field
            );
            return;
        }

        // Variation must exist and must belong to the selected product.
        $varInfo = $this->productResolver->resolve($sel->variationId);
        if ($varInfo === null || !$varInfo->isVariation()) {
            $result->addError(
                'invalid_variation',
                "Variation {$sel->variationId} was not found or is not a product variation.",
                $field
            );
            return;
        }
        if ($varInfo->parentId !== $sel->productId) {
            $result->addError(
                'variation_mismatch',
                "Variation {$sel->variationId} does not belong to product {$sel->productId}.",
                $field
            );
        }
    }

    private function validateStock(
        int $productId,
        ?int $variationId,
        int $qty,
        string $field,
        ValidationResult $result
    ): void {
        if (!$this->stockChecker->isAvailable($productId, $variationId, $qty)) {
            $id = $variationId ?? $productId;
            $result->addError(
                'out_of_stock',
                "Insufficient stock for product {$id}.",
                $field
            );
        }
    }
}
