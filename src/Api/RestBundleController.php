<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Api;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\BundleConfigRepositoryInterface;
use WPL\ProductBundles\Config\ComponentConfig;
use WPL\ProductBundles\Pricing\PricingServiceInterface;
use WPL\ProductBundles\Selection\BundleSelection;
use WPL\ProductBundles\Selection\ComponentSelection;

/**
 * REST API controller for bundle configuration and price estimation.
 *
 * Routes registered:
 *
 *   GET  /wpl/v1/bundles                       — list bundle products (edit_products)
 *   GET  /wpl/v1/bundles/{product_id}          — get bundle config (public for published)
 *   PUT  /wpl/v1/bundles/{product_id}          — update bundle config (edit_products)
 *   POST /wpl/v1/bundles/{product_id}/estimate — price estimate (public)
 *
 * SECURITY:
 *   – Write endpoints require edit_products capability.
 *   – The estimate endpoint never accepts submitted prices; PricingService
 *     recalculates from authoritative product meta.
 *   – Unpublished bundle configs are only readable by users with edit_products.
 *   – All output is escaped; all input is sanitized before use.
 *
 * HPOS: No WP_Query on post_type=shop_order; product queries via WP_Query are
 * unaffected by HPOS.
 */
final class RestBundleController {

    public const NAMESPACE = 'wpl/v1';
    public const BASE      = 'bundles';

    public function __construct(
        private readonly BundleConfigRepositoryInterface $repository,
        private readonly PricingServiceInterface $pricingService,
    ) {}

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_list_permission'],
                'args'                => [
                    'per_page' => [
                        'type'    => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'page'     => [
                        'type'    => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'search'   => [
                        'type'              => 'string',
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/(?P<product_id>[\d]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args'                => [
                    'context' => [
                        'type'    => 'string',
                        'default' => 'view',
                        'enum'    => ['view', 'edit'],
                    ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/(?P<product_id>[\d]+)/estimate', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'estimate_price'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    // ── Permission callbacks ─────────────────────────────────────────────────

    public function check_list_permission(\WP_REST_Request $request): bool|\WP_Error {
        return current_user_can('edit_products');
    }

    public function check_read_permission(\WP_REST_Request $request): bool|\WP_Error {
        // The handler itself restricts access to unpublished products.
        return true;
    }

    public function check_write_permission(\WP_REST_Request $request): bool|\WP_Error {
        return current_user_can('edit_products');
    }

    // ── GET /wpl/v1/bundles ──────────────────────────────────────────────────

    public function get_items(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));
        $page    = max(1, (int) ($request->get_param('page') ?? 1));
        $search  = sanitize_text_field((string) ($request->get_param('search') ?? ''));

        $queryArgs = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'no_found_rows'  => false,
            'fields'         => 'ids',
            'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['bundle'],
                ],
            ],
        ];

        if ($search !== '') {
            $queryArgs['s'] = $search;
        }

        $query = new \WP_Query($queryArgs);

        $items = [];
        foreach ((array) $query->posts as $postId) {
            $productId = (int) $postId;
            $product   = wc_get_product($productId);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $config  = $this->repository->get($productId);
            $items[] = [
                'product_id'      => $productId,
                'name'            => esc_html($product->get_name()),
                'status'          => esc_html((string) get_post_status($productId)),
                'mode'            => $config !== null ? esc_html($config->mode) : '',
                'price_mode'      => $config !== null ? esc_html($config->priceMode) : '',
                'component_count' => $config !== null ? count($config->items) : 0,
                'link'            => rest_url(self::NAMESPACE . '/' . self::BASE . '/' . $productId),
            ];
        }

        $response = new \WP_REST_Response($items, 200);
        $response->header('X-WP-Total', (string) $query->found_posts);
        $response->header('X-WP-TotalPages', (string) $query->max_num_pages);

        return $response;
    }

    // ── GET /wpl/v1/bundles/{product_id} ────────────────────────────────────

    public function get_item(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $productId = (int) $request->get_param('product_id');
        $context   = (string) ($request->get_param('context') ?? 'view');
        $context   = in_array($context, ['view', 'edit'], true) ? $context : 'view';

        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product) {
            return new \WP_Error(
                'wpl_not_found',
                esc_html__('Bundle product not found.', 'wpl-product-bundles'),
                ['status' => 404]
            );
        }

        $status = (string) get_post_status($productId);
        if ($status !== 'publish' && !current_user_can('edit_products')) {
            return new \WP_Error(
                'wpl_forbidden',
                esc_html__('You are not allowed to view this bundle.', 'wpl-product-bundles'),
                ['status' => 403]
            );
        }

        $config = $this->repository->get($productId);
        if ($config === null) {
            return new \WP_Error(
                'wpl_not_configured',
                esc_html__('This product has no bundle configuration.', 'wpl-product-bundles'),
                ['status' => 404]
            );
        }

        return new \WP_REST_Response($this->prepareConfig($productId, $config, $context), 200);
    }

    // ── PUT /wpl/v1/bundles/{product_id} ────────────────────────────────────

    public function update_item(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $productId = (int) $request->get_param('product_id');

        if (!current_user_can('edit_product', $productId)) {
            return new \WP_Error(
                'wpl_forbidden',
                esc_html__('You cannot edit this product.', 'wpl-product-bundles'),
                ['status' => 403]
            );
        }

        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product || $product->get_type() !== 'bundle') {
            return new \WP_Error(
                'wpl_invalid_product',
                esc_html__('Product is not a bundle.', 'wpl-product-bundles'),
                ['status' => 400]
            );
        }

        $body          = (array) $request->get_json_params();
        $configOrError = $this->parseConfigFromBody($body);
        if ($configOrError instanceof \WP_Error) {
            return $configOrError;
        }

        $this->repository->save($product, $configOrError);

        return new \WP_REST_Response($this->prepareConfig($productId, $configOrError, 'edit'), 200);
    }

    // ── POST /wpl/v1/bundles/{product_id}/estimate ───────────────────────────

    public function estimate_price(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $productId = (int) $request->get_param('product_id');
        $body      = (array) $request->get_json_params();
        $bundleQty = isset($body['bundle_quantity']) ? max(1, (int) $body['bundle_quantity']) : 1;

        $config = $this->repository->get($productId);
        if ($config === null) {
            return new \WP_Error(
                'wpl_not_configured',
                esc_html__('Bundle product not found or not configured.', 'wpl-product-bundles'),
                ['status' => 404]
            );
        }

        $rawComponents    = is_array($body['components'] ?? null)
            ? (array) $body['components']
            : [];
        $selectionOrError = $this->buildSelectionFromInput($productId, $config, $rawComponents);
        if ($selectionOrError instanceof \WP_Error) {
            return $selectionOrError;
        }

        $priceResult = $this->pricingService->calculateFromSelection($config, $selectionOrError, $bundleQty);

        $components = [];
        foreach ($priceResult->components as $cp) {
            $components[] = [
                'component_id' => $cp->componentId,
                'unit_price'   => $cp->unitPrice,
                'quantity'     => $cp->quantity,
                'line_total'   => $cp->linePrice,
            ];
        }

        return new \WP_REST_Response([
            'product_id'      => $productId,
            'bundle_quantity' => $bundleQty,
            'price_mode'      => $priceResult->mode,
            'subtotal'        => $priceResult->subtotal,
            'bundle_discount' => $priceResult->bundleDiscount,
            'total'           => $priceResult->total,
            'components'      => $components,
        ], 200);
    }

    // ── Public helpers (testable without WP_REST_Request) ───────────────────

    /**
     * Transforms a BundleConfig into a REST-safe array.
     * In 'view' context, internal pricing details (discount, override_price,
     * stock_mode) are omitted to avoid exposing merchant strategy to the public.
     *
     * @return array<string, mixed>
     */
    public function prepareConfig(int $productId, BundleConfig $config, string $context): array {
        $data = [
            'product_id'  => $productId,
            'mode'        => $config->mode,
            'price_mode'  => $config->priceMode,
            'min_qty'     => $config->minQty,
            'max_qty'     => $config->maxQty,
            'components'  => array_map(
                fn(ComponentConfig $c) => $this->prepareComponent($c, $context),
                $config->items
            ),
            'price_tiers' => $config->priceTiers,
        ];

        if ($context === 'edit') {
            $data['discount'] = $config->discount;
        }

        return $data;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function prepareComponent(ComponentConfig $component, string $context): array {
        $data = [
            'id'                 => $component->id,
            'sort_order'         => $component->sortOrder,
            'product_id'         => $component->productId,
            'variation_id'       => $component->variationId,
            'slot_title'         => esc_html($component->slotTitle),
            'quantity_min'       => $component->quantityMin,
            'quantity_max'       => $component->quantityMax,
            'quantity_default'   => $component->quantityDefault,
            'optional'           => $component->optional,
            'price_mode'         => $component->priceMode,
            'allowed_products'   => $component->allowedProducts,
            'allowed_categories' => $component->allowedCategories,
            'allowed_variations' => $component->allowedVariations,
        ];

        if ($context === 'edit') {
            $data['discount']       = $component->discount;
            $data['override_price'] = $component->overridePrice;
            $data['stock_mode']     = $component->stockMode;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function parseConfigFromBody(array $body): BundleConfig|\WP_Error {
        $allowedModes = ['fixed', 'mix_and_match'];
        $modeRaw      = (string) ($body['mode'] ?? '');

        if (!in_array($modeRaw, $allowedModes, true)) {
            return new \WP_Error(
                'wpl_invalid_mode',
                sprintf(
                    /* translators: %s: comma-separated list of allowed mode values */
                    esc_html__('Invalid bundle mode. Allowed values: %s.', 'wpl-product-bundles'),
                    implode(', ', $allowedModes)
                ),
                ['status' => 400]
            );
        }

        $allowedPriceModes = ['per_bundle', 'per_component', 'tiered'];
        $priceMode         = in_array($body['price_mode'] ?? '', $allowedPriceModes, true)
            ? (string) $body['price_mode']
            : 'per_bundle';

        $discount = $this->sanitizeDecimal((string) ($body['discount'] ?? '0'));
        $minQty   = max(1, absint($body['min_qty'] ?? 1));
        $maxQty   = absint($body['max_qty'] ?? 0);

        $rawTiers = is_array($body['price_tiers'] ?? null) ? (array) $body['price_tiers'] : [];
        $rawItems = is_array($body['components'] ?? null) ? (array) $body['components'] : [];

        return BundleConfig::fromArray([
            'mode'        => $modeRaw,
            'price_mode'  => $priceMode,
            'discount'    => $discount,
            'min_qty'     => $minQty,
            'max_qty'     => $maxQty,
            'items'       => $this->sanitizeComponentsFromBody($rawItems),
            'price_tiers' => $this->sanitizePriceTiers($rawTiers),
        ]);
    }

    /**
     * @param array<mixed> $raw
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeComponentsFromBody(array $raw): array {
        $components = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = absint($item['product_id'] ?? 0);
            if ($productId === 0) {
                continue;
            }

            $allowedCompPriceModes = ['component_default', 'custom_price', 'free'];
            $compPriceMode         = in_array($item['price_mode'] ?? '', $allowedCompPriceModes, true)
                ? (string) $item['price_mode']
                : 'component_default';

            $allowedStockModes = ['inherit', 'shared', 'independent'];
            $stockMode         = in_array($item['stock_mode'] ?? '', $allowedStockModes, true)
                ? (string) $item['stock_mode']
                : 'inherit';

            $variationIdRaw = isset($item['variation_id']) && $item['variation_id'] !== null
                ? absint($item['variation_id'])
                : null;

            $overridePriceRaw = isset($item['override_price'])
                && $item['override_price'] !== null
                && $item['override_price'] !== ''
                ? $this->sanitizeDecimal((string) $item['override_price'])
                : null;

            $components[] = [
                'id'                   => sanitize_key((string) ($item['id'] ?? '')),
                'sort_order'           => (int) ($item['sort_order'] ?? 0),
                'product_id'           => $productId,
                'variation_id'         => $variationIdRaw,
                'variation_attributes' => [],
                'slot_title'           => sanitize_text_field((string) ($item['slot_title'] ?? '')),
                'quantity_min'         => max(0, absint($item['quantity_min'] ?? 1)),
                'quantity_max'         => absint($item['quantity_max'] ?? 1),
                'quantity_default'     => max(0, absint($item['quantity_default'] ?? 1)),
                'optional'             => !empty($item['optional']),
                'price_mode'           => $compPriceMode,
                'discount'             => $this->sanitizeDecimal((string) ($item['discount'] ?? '0')),
                'override_price'       => $overridePriceRaw,
                'allowed_products'     => array_map('absint', (array) ($item['allowed_products'] ?? [])),
                'allowed_categories'   => array_map('absint', (array) ($item['allowed_categories'] ?? [])),
                'allowed_variations'   => array_map('absint', (array) ($item['allowed_variations'] ?? [])),
                'stock_mode'           => $stockMode,
            ];
        }

        return $components;
    }

    /**
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
            $maxRaw  = (string) ($tier['max_qty'] ?? '');
            $tiers[] = [
                'min_qty' => $minQty,
                'max_qty' => $maxRaw !== '' ? absint($maxRaw) : null,
                'price'   => $this->sanitizeDecimal((string) ($tier['price'] ?? '0')),
            ];
        }

        return $tiers;
    }

    /**
     * Builds a BundleSelection from raw estimate input, validating that every
     * component_id matches a slot defined in the bundle config.
     *
     * @param array<mixed> $rawComponents
     */
    private function buildSelectionFromInput(
        int $productId,
        BundleConfig $config,
        array $rawComponents
    ): BundleSelection|\WP_Error {
        $knownIds   = array_flip(array_map(fn(ComponentConfig $c) => $c->id, $config->items));
        $selections = [];

        foreach ($rawComponents as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $componentId = sanitize_key((string) ($raw['component_id'] ?? ''));

            if ($componentId === '' || !array_key_exists($componentId, $knownIds)) {
                return new \WP_Error(
                    'wpl_invalid_component',
                    sprintf(
                        /* translators: %s: the submitted component_id value */
                        esc_html__('Unknown component_id: %s.', 'wpl-product-bundles'),
                        esc_html($componentId)
                    ),
                    ['status' => 400]
                );
            }

            $selections[] = ComponentSelection::fromArray([
                'component_id' => $componentId,
                'product_id'   => absint($raw['product_id'] ?? 0),
                'variation_id' => isset($raw['variation_id']) ? absint($raw['variation_id']) : null,
                'quantity'     => max(0, (int) ($raw['quantity'] ?? 0)),
            ]);
        }

        return new BundleSelection($productId, $selections);
    }

    private function sanitizeDecimal(string $value): string {
        $clean = (string) preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($clean) && $clean !== '' ? $clean : '0.00';
    }
}
