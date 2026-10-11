<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Immutable value object representing one component slot in a bundle.
 *
 * All monetary values (discount, override_price) are stored as numeric strings
 * to avoid floating-point precision issues.
 */
final class ComponentConfig {

    /**
     * @param int[]    $allowedProducts   Simple/variable product IDs allowed for M&M slots.
     * @param int[]    $allowedCategories Category IDs allowed for M&M slots.
     * @param int[]    $allowedVariations Specific variation IDs allowed for M&M variable slots.
     * @param array<string, string> $variationAttributes Attribute slug → value pairs.
     */
    public function __construct(
        public readonly string $id,
        public readonly int $sortOrder,
        public readonly int $productId,
        public readonly ?int $variationId,
        public readonly array $variationAttributes,
        public readonly string $slotTitle,
        public readonly int $quantityMin,
        public readonly int $quantityMax,
        public readonly int $quantityDefault,
        public readonly bool $optional,
        public readonly string $priceMode,
        public readonly string $discount,
        public readonly ?string $overridePrice,
        public readonly array $allowedProducts,
        public readonly array $allowedCategories,
        public readonly array $allowedVariations,
        public readonly string $stockMode,
    ) {}

    /**
     * Constructs an instance from a raw associative array (e.g. decoded from JSON meta).
     * Missing optional fields receive safe defaults.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            id: (string) ($data['id'] ?? ''),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            productId: (int) ($data['product_id'] ?? 0),
            variationId: isset($data['variation_id']) && $data['variation_id'] !== null
                ? (int) $data['variation_id']
                : null,
            variationAttributes: (array) ($data['variation_attributes'] ?? []),
            slotTitle: (string) ($data['slot_title'] ?? ''),
            quantityMin: (int) ($data['quantity_min'] ?? 1),
            quantityMax: (int) ($data['quantity_max'] ?? 1),
            quantityDefault: (int) ($data['quantity_default'] ?? 1),
            optional: (bool) ($data['optional'] ?? false),
            priceMode: (string) ($data['price_mode'] ?? 'component_default'),
            discount: (string) ($data['discount'] ?? '0.00'),
            overridePrice: isset($data['override_price']) && $data['override_price'] !== null
                ? (string) $data['override_price']
                : null,
            allowedProducts: array_map('intval', (array) ($data['allowed_products'] ?? [])),
            allowedCategories: array_map('intval', (array) ($data['allowed_categories'] ?? [])),
            allowedVariations: array_map('intval', (array) ($data['allowed_variations'] ?? [])),
            stockMode: (string) ($data['stock_mode'] ?? 'inherit'),
        );
    }

    /**
     * Serialises back to an associative array suitable for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'id'                   => $this->id,
            'sort_order'           => $this->sortOrder,
            'product_id'           => $this->productId,
            'variation_id'         => $this->variationId,
            'variation_attributes' => $this->variationAttributes,
            'slot_title'           => $this->slotTitle,
            'quantity_min'         => $this->quantityMin,
            'quantity_max'         => $this->quantityMax,
            'quantity_default'     => $this->quantityDefault,
            'optional'             => $this->optional,
            'price_mode'           => $this->priceMode,
            'discount'             => $this->discount,
            'override_price'       => $this->overridePrice,
            'allowed_products'     => $this->allowedProducts,
            'allowed_categories'   => $this->allowedCategories,
            'allowed_variations'   => $this->allowedVariations,
            'stock_mode'           => $this->stockMode,
        ];
    }

    /**
     * Generates a new UUID v4 suitable for use as a component ID.
     * Uses random_bytes — no WordPress dependency.
     */
    public static function generateId(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
