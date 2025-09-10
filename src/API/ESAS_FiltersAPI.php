<?php

namespace ESAdvSearch\API;

use ESAdvSearch\API\ESAS_BatchesAPI;
use WP_REST_Request;

class ESAS_FiltersAPI {

    public static function init() {

        add_action('rest_api_init', [__CLASS__, 'register_routes']);

        // Clear cache on relevant post changes
        add_action('save_post_batch', [__CLASS__, 'clear_cache']);
        add_action('trashed_post', [__CLASS__, 'clear_cache']);
        add_action('deleted_post', [__CLASS__, 'clear_cache']);
    }

    public static function register_routes() {
        register_rest_route('custom/v1', '/es-advanced-search-filters', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_filters'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function clear_cache($post_id = null) {
        // Delete all category/effect-specific transients
        global $wpdb;
        $keys = $wpdb->get_col("
            SELECT option_name FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_esas_products_json%'
        ");
        foreach ($keys as $key) {
            $transient = str_replace('_transient_', '', $key);
            delete_transient($transient);
        }
        delete_transient('esas_filters_json'); // optional main cache
    }

    public static function get_filters(WP_REST_Request $request) {
        $category = $request->get_param('category') ?? '';
        $effect   = $request->get_param('effect') ?? '';

        $transient_key = 'esas_filters_json_' . ($category ?: 'all') . '_' . ($effect ?: 'all');
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        // --- Pull from batches cache instead of remote GET ---
        $batches_key = ESAS_BatchesAPI::get_cache_key($category, $effect);
        $tiles = get_transient($batches_key);

        // If no cache, build fresh
        if ($tiles === false) {
            $tiles = ESAS_BatchesAPI::build_batches($category, $effect);
            set_transient($batches_key, $tiles, defined('WP_DEBUG') && WP_DEBUG ? 600 : 30 * DAY_IN_SECONDS);
        }

        $filters = [];
        $filter_keys = ['category', 'effects', 'colour', 'finish', 'sizes', 'thickness'];

        foreach ($tiles as $tile) {
            foreach ($filter_keys as $key) {
                if (!empty($tile[$key])) {
                    $filters[$key] = array_merge($filters[$key] ?? [], (array) $tile[$key]);
                }
            }
        }

        foreach ($filters as $key => $values) {
            $unique = array_unique(array_map('strtolower', $values));
            $filters[$key] = array_values(array_map(fn($v) => [
                'id'   => sanitize_title($v),
                'name' => $v,
            ], $unique));
        }

        set_transient($transient_key, $filters, defined('WP_DEBUG') && WP_DEBUG ? 600 : 30 * DAY_IN_SECONDS);

        return rest_ensure_response($filters);
    }

}