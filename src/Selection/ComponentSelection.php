<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

/**
 * Immutable value object representing a customer's choice for one component slot.
 */
final class ComponentSelection {

    public function __construct(
        public readonly string $componentId,
        public readonly int $productId,
        public readonly ?int $variationId,
        public readonly int $quantity,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self(
            componentId: (string) ($data['component_id'] ?? ''),
            productId: (int) ($data['product_id'] ?? 0),
            variationId: isset($data['variation_id']) && $data['variation_id'] !== null
                ? (int) $data['variation_id']
                : null,
            quantity: max(0, (int) ($data['quantity'] ?? 0)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'component_id' => $this->componentId,
            'product_id'   => $this->productId,
            'variation_id' => $this->variationId,
            'quantity'     => $this->quantity,
        ];
    }
}
