<?php

namespace ESAdvSearch\API;

use WP_REST_Request;
use WP_Query;

class ESAS_BatchesAPI {

    public static function init() {
        // Register API routes on init
        add_action('rest_api_init', [__CLASS__, 'register_route']);

        // Clear cache when relevant posts change
        add_action('save_post_batch', [__CLASS__, 'clear_cache']);
        // add_action('save_post_product', [__CLASS__, 'clear_cache']);
        add_action('trashed_post', [__CLASS__, 'clear_cache']);
        add_action('deleted_post', [__CLASS__, 'clear_cache']);
    }

    public static function clear_cache($post_id = null) {
        delete_transient('esas_products_json');
    }

    public static function register_route() {
        // Route returning all batches
        register_rest_route('custom/v1', '/es-advanced-search', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_all_batches'],
        ]);
    }

    public static function get_all_batches(WP_REST_Request $request) {
        // Check for params in request
        $category = $request->get_param('category');
        $effect   = $request->get_param('effect');

        $cache_key = self::get_cache_key($category, $effect);

        // Check if cached values are set and return if they are
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        // Build products array
        $batches = self::build_batches($category, $effect);

        // Cache it (10 min dev, 30 days prod)
        set_transient($cache_key, $batches, defined('WP_DEBUG') && WP_DEBUG ? 10 * MINUTE_IN_SECONDS : 30 * DAY_IN_SECONDS);

        // Return JSON response
        return rest_ensure_response($batches);
    }

    public static function get_cache_key($category = null, $effect = null) {
        $cache_key = 'esas_products_json';

        // Include params in transient cache key if set
        if ($category) {
            $cache_key .= '_cat_' . sanitize_key($category);
        }
        if ($effect) {
            $cache_key .= '_effect_' . sanitize_key($effect);
        }

        return $cache_key;
    }

    public static function build_batches($category = null, $effect = null) {
        // Populate args for current query
        $args = self::get_query_args($category, $effect);

        // New WP_Query
        $query = new WP_Query($args);

        // Array to hold batch results
        $batches = [];

        // Loop over returned IDs and fetch batch data
        foreach ($query->posts as $batch_id) {
            $batches[] = self::get_batch_data($batch_id);
        }

        return $batches;
    }

    private static function get_query_args($category = null, $effect = null) {
        $args = [
            'post_type'      => 'batch',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_stock',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                ],
            ],
        ];

        // Array to hold tax query
        $tax_query = [];

        // Add category to query if set
        if ($category) {
            $tax_query[] = [
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($category),
            ];
        }

        // Add effect to query if set
        if ($effect) {
            $tax_query[] = [
                'taxonomy' => 'effect',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($effect),
            ];
        }

        // If tax query data is set, add to query args
        if (!empty($tax_query)) {
            $args['tax_query'] = [
                'relation' => 'AND',
                ...$tax_query,
            ];
        }

        return $args;
    }

    private static function get_batch_data($batch_id) {
        // Get WooCommerce data for batch
        $product = wc_get_product($batch_id);

        // Get all ACF fields for batch (single DB call)
        $acf_fields = get_fields($batch_id);

        // Get effects data (returns array, we only want one entry)
        $effects = wp_get_post_terms($batch_id, 'effect', ['fields' => 'names']);

        // Add only the fields MixItUp needs
        $data = [
            'id'        => $product->get_id(),
            'title'     => $product->get_name(),
            'price'     => $product->get_price(),
            'quantity'  => $product->get_stock_quantity(),
            'image'     => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'effects'   => !empty($effects) ? $effects[0] : null,
            'colour'    => $acf_fields['colour'] ?? null,
            'finish'    => $acf_fields['finish'] ?? null,
            'thickness' => !empty($acf_fields['thickness']) ? 'thickness-' . $acf_fields['thickness'] : null,
            'sizes'     => !empty($acf_fields['dimensions']) ? 'size-' . strtolower(str_replace(' ', '', $acf_fields['dimensions'])) : null,
            'factory'   => $acf_fields['factory_name'] ?? null,
            'menu_order'=> get_post_field('menu_order', $batch_id),
        ];

        // Lowercase string fields
        foreach (['effects', 'colour', 'finish', 'thickness', 'sizes', 'title', 'factory'] as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtolower($data[$field]);
            }
        }

        return $data;
    }
}