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

    public static function clear_cache() {

        global $wpdb;

        // Find all transient names starting with 'esas_'
        $transients = $wpdb->get_col("
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_esas_%'
        ");

        if (!empty($transients)) {
            foreach ($transients as $transient) {

                // Remove the '_transient_' prefix to get the actual transient name
                $transient_name = str_replace('_transient_', '', $transient);
                delete_transient($transient_name);
                
            }
        }
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

        // Create cache key for product transient
        $cache_key = self::get_cache_key($category);

        // Check if cached values are set and return if they are
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        // Build products array
        $batches = self::build_batches($category);

        // Cache it (10 min dev, 30 days prod)
        set_transient($cache_key, $batches, defined('WP_DEBUG') && WP_DEBUG ? 10 * MINUTE_IN_SECONDS : 30 * DAY_IN_SECONDS);

        // Return JSON response
        return rest_ensure_response($batches);
    }

    public static function get_cache_key($category = null) {

        $cache_key = 'esas_products_json';

        // Include params in transient cache key if set
        if ($category) {
            $cache_key .= '_cat_' . sanitize_key($category);
        }

        return $cache_key;
    }

    public static function build_batches($category = null) {
        // Populate args for current query
        $args = self::get_query_args($category);

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

    private static function get_query_args($category = null) {
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

        // Remove whitespace from $acf_field (entered by hand in WP admin)
        $acf_trim = array_map(fn($v) => trim($v), get_fields($batch_id));

        // Get effects data (returns array, we only want one entry)
        $effects = wp_get_post_terms($batch_id, 'effect', ['fields' => 'names']);

        $sqm_per_carton = (float) $acf_trim['sqm_per_carton'];

        $qty = (int) $product->get_stock_quantity();

        $sqm = $qty * $sqm_per_carton;

        // Add only the fields needed is reference JSON
        $data = [
            'id'                    => $product->get_id(),
            'title'                 => $product->get_name(),
            'price'                 => $product->get_price(),
            'type'                  => self::getType($product),
            'quantity'              => self::getSqmBand($sqm),
            'image'                 => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'effects'               => !empty($effects) ? $effects[0] : null,
            'colour'                => $acf_trim['colour'] ?? null,
            'finish'                => $acf_trim['finish'] ?? null,
            'thickness'             => !empty($acf_trim['thickness']) ? $acf_trim['thickness'] . 'mm' : null,
            'sizes'                 => isset($acf_trim['dimensions']) ? str_replace(' ', '', $acf_trim['dimensions']) : null,
            'slip_rating'           => $acf_trim['slip_rating'] ?? null,
            'factory'               => $acf_trim['factory_name'] ?? null,
            'product_code'          => $acf_trim['product_code'] ?? null,
            'batch_number'          => $acf_trim['batch_number'] ?? null,
            'discount'              => $acf_trim['discount_percentage'] ?? null,
            'usage'                 => self::setUsage(strtolower($acf_trim['finish'])),
            'sqm'                   => $sqm ?? null,
            'menu_order'            => get_post_field('menu_order', $batch_id),
        ];

        // Lowercase string fields
        foreach (['effects', 'colour', 'finish', 'thickness', 'sizes', 'title', 'factory', 'product_code', 'slip_rating'] as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtolower($data[$field]);
            }
        }

        return $data;
    }

    private static function setUsage($finish) {

        $usages = [
            'Floor'        => ['natural', 'structured'],
            'Wall'         => ['natural', 'polished', 'honed'],
            'Wall & Floor' => ['natural'],
            'Outdoor'      => ['grip'],
        ];

        $matchingUsage = [];
        foreach ($usages as $key => $finishes) {
            if (in_array($finish, $finishes, true)) {
                $matchingUsage[] = $key;
            }
        }

        return $matchingUsage;
    }


    private static function getType($product) {

        // Variable to hold type value
        $type = "";

        // Extract shipping class from product
        $shipping_class = $product->get_shipping_class();

        // Set type basd on shipping class
        if($shipping_class === "shipping-outdoor-tiles") {

            $type = "tile";

        } else if($shipping_class === "shipping-large-slabs") {

            $type = "slab";

        }

        //Return type
        return $type;

    }

    protected static function getSqmBand( $sqm ) {

        $sqmBands = [
                'sqm-0-1'       => [ 'max' => 1,  'name' => '0-1 sqm',   'order' => 0 ],
                'sqm-1-5'       => [ 'max' => 5,  'name' => '1-5 sqm',   'order' => 1 ],
                'sqm-5-10'      => [ 'max' => 10, 'name' => '5-10 sqm',  'order' => 2 ],
                'sqm-10-20'     => [ 'max' => 20, 'name' => '10-20 sqm', 'order' => 3 ],
                'sqm-20-30'     => [ 'max' => 30, 'name' => '20-30 sqm', 'order' => 4 ],
                'sqm-30-40'     => [ 'max' => 40, 'name' => '30-40 sqm', 'order' => 5 ],
                'sqm-40-50'     => [ 'max' => 50, 'name' => '40-50 sqm', 'order' => 6 ],
                'sqm-40-50'     => [ 'max' => 50, 'name' => '40-50 sqm', 'order' => 7 ],
                'sqm-50-60'     => [ 'max' => 60, 'name' => '50-60 sqm', 'order' => 8 ],
                'sqm-60-70'     => [ 'max' => 70, 'name' => '60-70 sqm', 'order' => 9 ],
                'sqm-70-80'     => [ 'max' => 80, 'name' => '70-80 sqm', 'order' => 10 ],
                'sqm-80-90'     => [ 'max' => 90, 'name' => '80-90 sqm', 'order' => 11 ],
                'sqm-90-100'    => [ 'max' => 100, 'name' => '90-100 sqm', 'order' => 12 ],
                'sqm-100-150'   => [ 'max' => 150, 'name' => '100-150 sqm', 'order' => 13 ],
                'sqm-150-plus'  => [ 'max' => null, 'name' => '150+ sqm','order' => 14 ],
            ];

        foreach ( $sqmBands as $id => $band ) {
            // skip the “50+” row which has no max
            if ( isset( $band['max'] ) && $sqm <= $band['max'] ) {
                return $id;
            }
        }
        // if nothing matched, return the final “plus” band
        return 'sqm-150-plus';
    }


}