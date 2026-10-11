<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

/**
 * Immutable value object representing a customer's complete bundle selection.
 */
final class BundleSelection {

    /** @param ComponentSelection[] $selections */
    public function __construct(
        public readonly int $bundleProductId,
        public readonly array $selections,
    ) {}

    /**
     * @param array<string, mixed> $data
     *   'bundle_product_id' => int
     *   'selections'        => array of raw arrays or ComponentSelection objects
     */
    public static function fromArray(array $data): self {
        $selections = [];
        foreach ((array) ($data['selections'] ?? []) as $item) {
            if ($item instanceof ComponentSelection) {
                $selections[] = $item;
            } else {
                $selections[] = ComponentSelection::fromArray((array) $item);
            }
        }

        return new self(
            bundleProductId: (int) ($data['bundle_product_id'] ?? 0),
            selections: $selections,
        );
    }

    /**
     * Returns the selection for a given component slot ID, or null if absent.
     */
    public function getSelection(string $componentId): ?ComponentSelection {
        foreach ($this->selections as $sel) {
            if ($sel->componentId === $componentId) {
                return $sel;
            }
        }
        return null;
    }

    /** @return ComponentSelection[] */
    public function getSelections(): array {
        return $this->selections;
    }
}
