<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Admin;

/**
 * AJAX handlers for the bundle admin UI's product and variation search.
 *
 * wp_ajax_wpl_product_search   — searches publishable non-bundle products.
 * wp_ajax_wpl_get_variations   — returns available variations for a variable product.
 *
 * Both actions share the same nonce: 'wpl_product_search'.
 *
 * Response format for both: JSON array [{id: int, text: string, type?: string}]
 */
final class ProductSearchController {

    /**
     * Handles wp_ajax_wpl_product_search.
     * Returns a JSON array of products matching the search term, excluding bundle types.
     */
    public function handle(): void {
        check_ajax_referer('wpl_product_search', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error([], 403);
        }

        $term = sanitize_text_field(wp_unslash((string) ($_GET['q'] ?? '')));

        if (strlen($term) < 2) {
            wp_send_json([]);
        }

        $query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => 20,
            'no_found_rows'  => true,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['bundle'],
                    'operator' => 'NOT IN',
                ],
            ],
        ]);

        $results = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $product = wc_get_product($post->ID);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $sku    = $product->get_sku();
            $text   = $product->get_name() . ' (#' . $product->get_id() . ')';
            if ($sku !== '') {
                $text .= ' [' . $sku . ']';
            }
            $results[] = [
                'id'   => $product->get_id(),
                'text' => $text,
                'type' => $product->get_type(),
            ];
        }

        wp_send_json($results);
    }

    /**
     * Handles wp_ajax_wpl_get_variations.
     * Returns a JSON array of available variations for the given variable product.
     */
    public function getVariations(): void {
        check_ajax_referer('wpl_product_search', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error([], 403);
        }

        $productId = absint($_GET['product_id'] ?? 0);
        if ($productId === 0) {
            wp_send_json([]);
        }

        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product_Variable) {
            wp_send_json([]);
        }

        $results = [];

        foreach ($product->get_available_variations() as $variation) {
            $variationId = (int) ($variation['variation_id'] ?? 0);
            if ($variationId === 0) {
                continue;
            }
            $attrs = array_filter(
                array_values((array) ($variation['attributes'] ?? [])),
                fn(mixed $v) => $v !== ''
            );
            $label = !empty($attrs)
                ? implode(' / ', $attrs)
                : '#' . $variationId;

            $varProduct = wc_get_product($variationId);
            if ($varProduct instanceof \WC_Product_Variation) {
                $sku = $varProduct->get_sku();
                if ($sku !== '') {
                    $label .= ' [' . $sku . ']';
                }
            }

            $results[] = [
                'id'   => $variationId,
                'text' => $label,
            ];
        }

        wp_send_json($results);
    }
}
