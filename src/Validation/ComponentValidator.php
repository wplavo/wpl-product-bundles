<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Validation;

use WPL\ProductBundles\Config\ComponentConfig;

/**
 * Validates a single ComponentConfig in isolation (no WooCommerce calls).
 *
 * Product existence and variation-parent checks are the responsibility of
 * BundleValidator, which has access to a ProductResolverInterface.
 */
final class ComponentValidator {

    private const VALID_PRICE_MODES = ['component_default', 'override', 'free'];
    private const VALID_STOCK_MODES = ['inherit', 'independent'];

    public function validate(ComponentConfig $component): ValidationResult {
        $result = new ValidationResult();

        $this->validateProductId($component, $result);
        $this->validateQuantities($component, $result);
        $this->validateDiscount($component, $result);
        $this->validateOverridePrice($component, $result);
        $this->validatePriceMode($component, $result);
        $this->validateStockMode($component, $result);

        return $result;
    }

    private function validateProductId(ComponentConfig $component, ValidationResult $result): void {
        if ($component->productId <= 0) {
            $result->addError(
                'invalid_product_id',
                'Component product_id must be a positive integer.',
                'product_id'
            );
        }
    }

    private function validateQuantities(ComponentConfig $component, ValidationResult $result): void {
        if ($component->quantityMin < 0) {
            $result->addError(
                'invalid_qty_min',
                'quantity_min cannot be negative.',
                'quantity_min'
            );
        }

        // A required component must have a minimum quantity of at least 1.
        if (!$component->optional && $component->quantityMin < 1) {
            $result->addError(
                'required_qty_zero',
                'A required (non-optional) component must have quantity_min ≥ 1.',
                'quantity_min'
            );
        }

        // quantity_max = 0 means unlimited; if positive it must be ≥ quantity_min.
        if ($component->quantityMax > 0 && $component->quantityMax < $component->quantityMin) {
            $result->addError(
                'invalid_qty_range',
                'quantity_max must be ≥ quantity_min (or 0 for unlimited).',
                'quantity_max'
            );
        }

        if ($component->quantityDefault < $component->quantityMin) {
            $result->addError(
                'invalid_qty_default',
                'quantity_default must be ≥ quantity_min.',
                'quantity_default'
            );
        }

        if (
            $component->quantityMax > 0
            && $component->quantityDefault > $component->quantityMax
        ) {
            $result->addError(
                'invalid_qty_default',
                'quantity_default must be ≤ quantity_max.',
                'quantity_default'
            );
        }
    }

    private function validateDiscount(ComponentConfig $component, ValidationResult $result): void {
        if (!is_numeric($component->discount)) {
            $result->addError(
                'invalid_discount',
                'Discount must be a numeric string.',
                'discount'
            );
            return;
        }

        $value = (float) $component->discount;
        if ($value < 0.0 || $value > 100.0) {
            $result->addError(
                'discount_out_of_range',
                'Discount must be between 0.00 and 100.00.',
                'discount'
            );
        }
    }

    private function validateOverridePrice(ComponentConfig $component, ValidationResult $result): void {
        if ($component->overridePrice === null) {
            return;
        }

        if (!is_numeric($component->overridePrice) || (float) $component->overridePrice < 0.0) {
            $result->addError(
                'negative_override_price',
                'override_price must be a non-negative number.',
                'override_price'
            );
        }
    }

    private function validatePriceMode(ComponentConfig $component, ValidationResult $result): void {
        if (!in_array($component->priceMode, self::VALID_PRICE_MODES, true)) {
            $result->addError(
                'invalid_price_mode',
                sprintf(
                    'price_mode "%s" is invalid. Valid values: %s.',
                    $component->priceMode,
                    implode(', ', self::VALID_PRICE_MODES)
                ),
                'price_mode'
            );
        }

        if ($component->priceMode === 'override' && $component->overridePrice === null) {
            $result->addError(
                'missing_override_price',
                'price_mode "override" requires override_price to be set.',
                'override_price'
            );
        }
    }

    private function validateStockMode(ComponentConfig $component, ValidationResult $result): void {
        if (!in_array($component->stockMode, self::VALID_STOCK_MODES, true)) {
            $result->addError(
                'invalid_stock_mode',
                sprintf(
                    'stock_mode "%s" is invalid. Valid values: %s.',
                    $component->stockMode,
                    implode(', ', self::VALID_STOCK_MODES)
                ),
                'stock_mode'
            );
        }
    }
}
