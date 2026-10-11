<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

/**
 * Immutable value object representing the complete configuration of a bundle product.
 */
final class BundleConfig {

    /**
     * @param ComponentConfig[]                                               $items
     * @param array<int, array{min_qty: int, max_qty: int|null, price: string}> $priceTiers
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $priceMode,
        public readonly string $discount,
        public readonly int $minQty,
        public readonly int $maxQty,
        public readonly array $items,
        public readonly array $priceTiers,
    ) {}

    /**
     * Constructs an instance from a raw associative array.
     * Items may be raw arrays (deserialized from JSON) or ComponentConfig objects.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $items = [];
        foreach ((array) ($data['items'] ?? []) as $item) {
            if ($item instanceof ComponentConfig) {
                $items[] = $item;
            } else {
                $items[] = ComponentConfig::fromArray((array) $item);
            }
        }

        return new self(
            mode: (string) ($data['mode'] ?? ''),
            priceMode: (string) ($data['price_mode'] ?? ''),
            discount: (string) ($data['discount'] ?? '0.00'),
            minQty: max(1, (int) ($data['min_qty'] ?? 1)),
            maxQty: (int) ($data['max_qty'] ?? 0),
            items: $items,
            priceTiers: (array) ($data['price_tiers'] ?? []),
        );
    }

    /**
     * Serialises back to an associative array suitable for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'mode'        => $this->mode,
            'price_mode'  => $this->priceMode,
            'discount'    => $this->discount,
            'min_qty'     => $this->minQty,
            'max_qty'     => $this->maxQty,
            'items'       => array_map(fn(ComponentConfig $c) => $c->toArray(), $this->items),
            'price_tiers' => $this->priceTiers,
        ];
    }

    /**
     * @return ComponentConfig[]
     */
    public function getComponents(): array {
        return $this->items;
    }
}
