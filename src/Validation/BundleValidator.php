<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Validation;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\ComponentConfig;
use WPL\ProductBundles\Config\ProductResolverInterface;

/**
 * Validates a complete BundleConfig, including product existence and
 * variation-parent checks via an injected ProductResolverInterface.
 */
final class BundleValidator {

    private const VALID_MODES       = ['fixed', 'mix_and_match'];
    private const VALID_PRICE_MODES = ['per_bundle', 'per_component', 'tiered'];

    public function __construct(
        private readonly ProductResolverInterface $resolver
    ) {}

    /**
     * Validates a bundle configuration.
     *
     * @param int|null $bundleProductId When provided, prevents a component from referencing
     *                                   the bundle itself (self-reference guard).
     */
    public function validate(BundleConfig $config, ?int $bundleProductId = null): ValidationResult {
        $result = new ValidationResult();

        $this->validateMode($config, $result);
        $this->validatePriceMode($config, $result);
        $this->validateComponents($config, $result, $bundleProductId);
        $this->validateTiers($config, $result);
        $this->validateUniqueComponentIds($config, $result);

        return $result;
    }

    private function validateMode(BundleConfig $config, ValidationResult $result): void {
        if (!in_array($config->mode, self::VALID_MODES, true)) {
            $result->addError(
                'invalid_mode',
                sprintf('Bundle mode "%s" is invalid. Valid values: fixed, mix_and_match.', $config->mode),
                'mode'
            );
        }
    }

    private function validatePriceMode(BundleConfig $config, ValidationResult $result): void {
        if (!in_array($config->priceMode, self::VALID_PRICE_MODES, true)) {
            $result->addError(
                'invalid_price_mode',
                sprintf(
                    'Bundle price_mode "%s" is invalid. Valid values: %s.',
                    $config->priceMode,
                    implode(', ', self::VALID_PRICE_MODES)
                ),
                'price_mode'
            );
        }
    }

    private function validateComponents(
        BundleConfig $config,
        ValidationResult $result,
        ?int $bundleProductId
    ): void {
        if (empty($config->items)) {
            $result->addError(
                'no_components',
                'A bundle must have at least one component.',
                'items'
            );
            return;
        }

        $componentValidator = new ComponentValidator();

        foreach ($config->items as $index => $component) {
            $field = "items[{$index}]";

            // Self-reference: component cannot be the bundle itself.
            if ($bundleProductId !== null && $component->productId === $bundleProductId) {
                $result->addError(
                    'self_reference',
                    'A bundle cannot contain itself as a component.',
                    $field
                );
                continue;
            }

            // Resolve the component product.
            $productInfo = $this->resolver->resolve($component->productId);
            if ($productInfo === null) {
                $result->addError(
                    'product_not_found',
                    "Component product ID {$component->productId} was not found.",
                    $field
                );
                continue;
            }

            // Nested bundles are not allowed.
            if ($productInfo->type === 'bundle') {
                $result->addError(
                    'nested_bundle',
                    'Bundle products cannot be used as bundle components.',
                    $field
                );
                continue;
            }

            // Variation validation (when a specific variation is pinned).
            if ($component->variationId !== null) {
                $this->validateVariation($component, $field, $result);
            }

            // Per-component value validation (quantities, discount, etc.).
            $result->merge($componentValidator->validate($component));
        }
    }

    private function validateVariation(
        ComponentConfig $component,
        string $field,
        ValidationResult $result
    ): void {
        /** @var int $variationId Guarded by caller */
        $variationId = $component->variationId;

        $variationInfo = $this->resolver->resolve($variationId);

        if ($variationInfo === null || !$variationInfo->isVariation()) {
            $result->addError(
                'variation_not_found',
                "Variation ID {$variationId} was not found or is not a product variation.",
                $field
            );
            return;
        }

        if ($variationInfo->parentId !== $component->productId) {
            $result->addError(
                'variation_mismatch',
                "Variation {$variationId} does not belong to product {$component->productId}.",
                $field
            );
        }
    }

    private function validateTiers(BundleConfig $config, ValidationResult $result): void {
        if ($config->priceMode !== 'tiered') {
            return;
        }

        if (empty($config->priceTiers)) {
            $result->addError(
                'missing_tiers',
                'Tiered price mode requires at least one price tier.',
                'price_tiers'
            );
            return;
        }

        $prevMaxQty = 0;

        foreach ($config->priceTiers as $i => $tier) {
            $field = "price_tiers[{$i}]";

            if (!isset($tier['min_qty'], $tier['price'])) {
                $result->addError('invalid_tier', "Tier {$i} is missing required fields (min_qty, price).", $field);
                continue;
            }

            $minQty = (int) $tier['min_qty'];
            $maxQty = (isset($tier['max_qty']) && $tier['max_qty'] !== null)
                ? (int) $tier['max_qty']
                : null;

            if ($minQty < 1) {
                $result->addError('invalid_tier', "Tier {$i}: min_qty must be ≥ 1.", $field);
            }

            if ($minQty <= $prevMaxQty) {
                $result->addError(
                    'overlapping_tiers',
                    "Tier {$i}: min_qty must be greater than the previous tier's max_qty.",
                    $field
                );
            }

            if ($maxQty !== null && $maxQty < $minQty) {
                $result->addError('invalid_tier', "Tier {$i}: max_qty must be ≥ min_qty.", $field);
            }

            $prevMaxQty = $maxQty ?? PHP_INT_MAX;
        }
    }

    private function validateUniqueComponentIds(BundleConfig $config, ValidationResult $result): void {
        $seen = [];
        foreach ($config->items as $component) {
            if (isset($seen[$component->id])) {
                $result->addError(
                    'duplicate_component_id',
                    "Duplicate component ID \"{$component->id}\" found.",
                    'items'
                );
            }
            $seen[$component->id] = true;
        }
    }
}
