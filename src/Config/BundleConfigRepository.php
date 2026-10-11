<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Config;

use WPL\ProductBundles\Migration\MigrationRunner;

/**
 * Reads and writes bundle configuration to WooCommerce product meta.
 *
 * All meta I/O goes through the WC_Product object API — no direct post-meta calls.
 */
final class BundleConfigRepository implements BundleConfigRepositoryInterface {

    private const MODE_KEY         = '_wpl_bundle_mode';
    private const PRICE_MODE_KEY   = '_wpl_bundle_price_mode';
    private const ITEMS_KEY        = '_wpl_bundle_items';
    private const TIERS_KEY        = '_wpl_bundle_price_tiers';
    private const DISCOUNT_KEY     = '_wpl_bundle_discount';
    private const MIN_QTY_KEY      = '_wpl_bundle_min_qty';
    private const MAX_QTY_KEY      = '_wpl_bundle_max_qty';
    private const SCHEMA_KEY       = '_wpl_bundle_schema_version';

    /**
     * Returns the bundle configuration for a product, or null if none is stored.
     */
    public function get(int $productId): ?BundleConfig {
        $product = wc_get_product($productId);

        if (!$product instanceof \WC_Product) {
            return null;
        }

        $schemaVersion = (int) $product->get_meta(self::SCHEMA_KEY, true);
        if ($schemaVersion === 0) {
            return null;
        }

        $mode = (string) $product->get_meta(self::MODE_KEY, true);
        if ('' === $mode) {
            return null;
        }

        $itemsJson = (string) $product->get_meta(self::ITEMS_KEY, true);
        $items     = [];

        if ('' !== $itemsJson) {
            $decoded = json_decode($itemsJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $raw) {
                    $items[] = ComponentConfig::fromArray((array) $raw);
                }
            }
        }

        $tiersJson = (string) $product->get_meta(self::TIERS_KEY, true);
        $tiers     = [];

        if ('' !== $tiersJson) {
            $decoded = json_decode($tiersJson, true);
            if (is_array($decoded)) {
                $tiers = $decoded;
            }
        }

        $config = BundleConfig::fromArray([
            'mode'        => $mode,
            'price_mode'  => (string) $product->get_meta(self::PRICE_MODE_KEY, true),
            'discount'    => (string) $product->get_meta(self::DISCOUNT_KEY, true) ?: '0.00',
            'min_qty'     => (int) $product->get_meta(self::MIN_QTY_KEY, true) ?: 1,
            'max_qty'     => (int) $product->get_meta(self::MAX_QTY_KEY, true),
            'items'       => $items,
            'price_tiers' => $tiers,
        ]);

        /**
         * Filters the bundle configuration after it is loaded from product meta.
         *
         * @param BundleConfig $config     Loaded configuration object.
         * @param int          $productId  Product ID the config belongs to.
         */
        $filtered = apply_filters('wpl_bundle_config', $config, $productId);
        return $filtered instanceof BundleConfig ? $filtered : $config;
    }

    /**
     * Persists a bundle configuration onto a WC product.
     *
     * Caller must ensure $product is of type 'bundle' before calling.
     * Calls save_meta_data() but not save() — the caller controls when the product saves.
     */
    public function save(\WC_Product $product, BundleConfig $config): void {
        $product->update_meta_data(self::SCHEMA_KEY, (string) MigrationRunner::CURRENT_VERSION);
        $product->update_meta_data(self::MODE_KEY, $config->mode);
        $product->update_meta_data(self::PRICE_MODE_KEY, $config->priceMode);
        $product->update_meta_data(self::DISCOUNT_KEY, $config->discount);
        $product->update_meta_data(self::MIN_QTY_KEY, (string) $config->minQty);
        $product->update_meta_data(self::MAX_QTY_KEY, (string) $config->maxQty);

        $items = array_map(fn(ComponentConfig $c) => $c->toArray(), $config->items);
        $product->update_meta_data(self::ITEMS_KEY, (string) wp_json_encode($items));

        if ($config->priceMode === 'tiered' && !empty($config->priceTiers)) {
            $product->update_meta_data(self::TIERS_KEY, (string) wp_json_encode($config->priceTiers));
        } else {
            $product->delete_meta_data(self::TIERS_KEY);
        }

        $product->save_meta_data();
    }

    /**
     * Removes all bundle configuration meta from a product.
     */
    public function delete(\WC_Product $product): void {
        foreach ([
            self::SCHEMA_KEY,
            self::MODE_KEY,
            self::PRICE_MODE_KEY,
            self::ITEMS_KEY,
            self::TIERS_KEY,
            self::DISCOUNT_KEY,
            self::MIN_QTY_KEY,
            self::MAX_QTY_KEY,
        ] as $key) {
            $product->delete_meta_data($key);
        }
        $product->save_meta_data();
    }
}
