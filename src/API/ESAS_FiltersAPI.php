<?php

namespace ESAdvSearch\API;

use WP_REST_Request;

/**
 * Class ESAS_FiltersAPI
 * Handles fetching and formatting tile filter data for the front-end.
 */
class ESAS_FiltersAPI {

    /**
     * Initialize REST API route and cache clearing hooks.
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('save_post_batch', [__CLASS__, 'clear_cache']);
        add_action('trashed_post', [__CLASS__, 'clear_cache']);
        add_action('deleted_post', [__CLASS__, 'clear_cache']);
    }

    /**
     * Register REST route for filters.
     */
    public static function register_routes() {
        register_rest_route('custom/v1', '/es-advanced-search-filters', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_filters'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Clear filter cache.
     *
     * @param int|null $post_id
     */
    public static function clear_cache($post_id = null) {
        delete_transient('esas_filters_json');
    }

    /**
     * Main REST API callback to get filters.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_filters(WP_REST_Request $request, $category = "") {

        //$category = $request->get_param('category') ?? '';

        $transient_key = self::get_transient_key($category);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $tiles = self::get_tiles($category);
        $filters = self::build_filters($tiles);

        set_transient($transient_key, $filters, defined('WP_DEBUG') && WP_DEBUG ? 600 : 30 * DAY_IN_SECONDS);

        return rest_ensure_response($filters);
    }

    /**
     * Generate transient key for caching.
     */
    private static function get_transient_key($category = '') {

        if ($category) {
            return 'esas_filters_json_' . $category;
        } else {
            return 'esas_filters_json_all';
        }
        
    }

    /**
     * Get tile data from batches cache or rebuild if missing.
     */
    private static function get_tiles($category) {
        $batches_key = ESAS_BatchesAPI::get_cache_key($category);
        $tiles = get_transient($batches_key);

        if ($tiles === false) {
            $tiles = ESAS_BatchesAPI::build_batches($category);
            set_transient($batches_key, $tiles, defined('WP_DEBUG') && WP_DEBUG ? 600 : 30 * DAY_IN_SECONDS);
        }

        return $tiles;
    }

    /**
     * Build filters array from tile data.
     */
    private static function build_filters(array $tiles) {
        $filters = [];
        $filter_keys = ['category', 'effects', 'colour', 'finish', 'sizes', 'thickness', 'quantity'];

        foreach ($tiles as $tile) {
            foreach ($filter_keys as $key) {
                if (!empty($tile[$key])) {
                    $filters[$key] = array_merge($filters[$key] ?? [], (array) $tile[$key]);
                }
            }
        }

        foreach ($filters as $key => $values) {
            $unique = array_unique(array_map('strtolower', $values));
            $filters[$key] = $key === 'sizes'
                ? self::format_and_sort_sizes($unique)
                : self::format_generic_filter($unique, $key);
        }

        // // After collecting $filters['quantity'] from tiles
        $filters['quantity'] = array_map(
            fn($id, $data) => [ 'id' => $id, 'name' => $data['name'] ],
            array_keys( SQM_BANDS ),
            SQM_BANDS
        );

        return $filters;
    }

    /**
     * Format and sort size filters.
     *
     * @param array $sizes Raw size strings (e.g., size-1200x1200)
     * @return array Formatted size array with id and label
     */
    private static function format_and_sort_sizes(array $sizes) {
        $sizes_map = [];

        foreach ($sizes as $size) {
            $size_clean = str_replace('size-', '', $size);
            $dims = array_map('intval', explode('x', $size_clean));
            sort($dims);
            $sizes_map[] = [
                'max' => $dims[1],
                'min' => $dims[0],
                'original' => $size_clean
            ];
        }

        usort($sizes_map, function($a, $b) {
            return $b['max'] <=> $a['max'] ?: $b['min'] <=> $a['min'];
        });

        return array_map(function($s) {
            return [
                'id'   => 'size-' . $s['original'],
                'name' => str_replace('x', ' x ', $s['original'])
            ];
        }, $sizes_map);
    }

    /**
     * Format generic filters like colour, finish, thickness.
     *
     * @param array $values
     * @param string $key
     * @return array
     */
    private static function format_generic_filter(array $values, string $key) {
        return array_map(function($v) use ($key) {
            $id = sanitize_title($v);
            $name = $key === 'thickness' ? str_replace('thickness-', '', $id) . 'mm' : ucfirst($v);
            return ['id' => $id, 'name' => $name];
        }, $values);
    }
}
