<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Admin;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\BundleConfigRepositoryInterface;
use WPL\ProductBundles\Config\ComponentConfig;

/**
 * Saves bundle configuration from the WooCommerce product editor.
 *
 * Hooked to woocommerce_process_product_meta.
 *
 * SECURITY:
 *   – Verifies our own nonce (wpl_save_bundle_{productId}) before reading any POST data.
 *   – Checks edit_product capability for the specific product.
 *   – All incoming values are sanitized with typed helpers; no raw strings pass through.
 *   – Numeric enums (mode, price_mode, stock_mode) are validated against an allow-list.
 */
final class BundleProductSaver {

    public function __construct(
        private readonly BundleConfigRepositoryInterface $repository,
    ) {}

    /**
     * Entry point — hooked to woocommerce_process_product_meta (priority 10).
     */
    public function save(int $productId): void {
        if (!current_user_can('edit_product', $productId)) {
            return;
        }

        $nonce = (string) wp_unslash($_POST['_wpl_bundle_nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'wpl_save_bundle_' . $productId)) {
            return;
        }

        if (!isset($_POST['wpl_bundle']) || !is_array($_POST['wpl_bundle'])) {
            return;
        }

        /** @var array<string, mixed> $raw */
        $raw = (array) wp_unslash($_POST['wpl_bundle']);

        $allowedModes = ['fixed', 'mix_and_match'];
        $mode         = in_array($raw['mode'] ?? '', $allowedModes, true)
            ? (string) $raw['mode'] : 'fixed';

        $allowedPriceModes = ['per_bundle', 'per_component', 'tiered'];
        $priceMode         = in_array($raw['price_mode'] ?? '', $allowedPriceModes, true)
            ? (string) $raw['price_mode'] : 'per_bundle';

        $discount = $this->sanitizeDecimal((string) ($raw['discount'] ?? '0'));
        $minQty   = max(1, absint($raw['min_qty'] ?? 1));
        $maxQty   = absint($raw['max_qty'] ?? 0);

        $priceTiers = $this->sanitizePriceTiers((array) ($raw['price_tiers'] ?? []));
        $components = $this->sanitizeComponents((array) ($raw['components'] ?? []));

        $config = BundleConfig::fromArray([
            'mode'        => $mode,
            'price_mode'  => $priceMode,
            'discount'    => $discount,
            'min_qty'     => $minQty,
            'max_qty'     => $maxQty,
            'items'       => $components,
            'price_tiers' => $priceTiers,
        ]);

        $product = wc_get_product($productId);
        if ($product instanceof \WC_Product) {
            $this->repository->save($product, $config);
        }
    }

    // ── Private sanitisers ───────────────────────────────────────────────────

    /**
     * Sanitises and sorts the components sub-array from POST.
     *
     * Components keyed by UUID; sorted by the sort_order field submitted by JS.
     *
     * @param array<mixed> $raw
     * @return array<string, mixed>[]
     */
    private function sanitizeComponents(array $raw): array {
        $components = [];

        foreach ($raw as $compKey => $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = absint($item['product_id'] ?? 0);
            if ($productId === 0) {
                continue;
            }

            $id = sanitize_key((string) ($item['id'] ?? $compKey));
            if ($id === '') {
                $id = ComponentConfig::generateId();
            }

            $variationId = absint($item['variation_id'] ?? 0);

            $allowedCompPriceModes = ['component_default', 'custom_price', 'free'];
            $compPriceMode         = in_array($item['price_mode'] ?? '', $allowedCompPriceModes, true)
                ? (string) $item['price_mode'] : 'component_default';

            $allowedStockModes = ['inherit', 'shared', 'independent'];
            $stockMode         = in_array($item['stock_mode'] ?? '', $allowedStockModes, true)
                ? (string) $item['stock_mode'] : 'inherit';

            $components[] = [
                '_sort' => (int) ($item['sort_order'] ?? 0),
                'data'  => [
                    'id'                   => $id,
                    'sort_order'           => 0,
                    'product_id'           => $productId,
                    'variation_id'         => $variationId > 0 ? $variationId : null,
                    'variation_attributes' => [],
                    'slot_title'           => sanitize_text_field((string) ($item['slot_title'] ?? '')),
                    'quantity_min'         => max(0, absint($item['quantity_min'] ?? 1)),
                    'quantity_max'         => absint($item['quantity_max'] ?? 1),
                    'quantity_default'     => max(0, absint($item['quantity_default'] ?? 1)),
                    'optional'             => !empty($item['optional']),
                    'price_mode'           => $compPriceMode,
                    'discount'             => $this->sanitizeDecimal((string) ($item['discount'] ?? '0')),
                    'override_price'       => $this->sanitizeNullableDecimal((string) ($item['override_price'] ?? '')),
                    'allowed_products'     => array_map('absint', (array) ($item['allowed_products'] ?? [])),
                    'allowed_categories'   => array_map('absint', (array) ($item['allowed_categories'] ?? [])),
                    'allowed_variations'   => array_map('absint', (array) ($item['allowed_variations'] ?? [])),
                    'stock_mode'           => $stockMode,
                ],
            ];
        }

        // Sort by the JS-submitted sort_order value.
        usort($components, fn(array $a, array $b) => $a['_sort'] <=> $b['_sort']);

        // Re-assign sequential sort_order values (0, 1, 2…).
        $result = [];
        foreach ($components as $i => $entry) {
            $entry['data']['sort_order'] = $i;
            $result[]                    = $entry['data'];
        }

        return $result;
    }

    /**
     * Sanitises the price_tiers sub-array from POST.
     *
     * @param array<mixed> $raw
     * @return array<int, array{min_qty: int, max_qty: int|null, price: string}>
     */
    private function sanitizePriceTiers(array $raw): array {
        $tiers = [];

        foreach ($raw as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $minQty = absint($tier['min_qty'] ?? 0);
            if ($minQty === 0) {
                continue;
            }
            $maxRaw = (string) ($tier['max_qty'] ?? '');
            $tiers[] = [
                'min_qty' => $minQty,
                'max_qty' => $maxRaw !== '' ? absint($maxRaw) : null,
                'price'   => $this->sanitizeDecimal((string) ($tier['price'] ?? '0')),
            ];
        }

        return $tiers;
    }

    private function sanitizeDecimal(string $value): string {
        $clean = (string) preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($clean) && $clean !== '' ? $clean : '0.00';
    }

    private function sanitizeNullableDecimal(string $value): ?string {
        if ($value === '') {
            return null;
        }
        $result = $this->sanitizeDecimal($value);
        return $result === '0.00' && !is_numeric($value) ? null : $result;
    }
}
