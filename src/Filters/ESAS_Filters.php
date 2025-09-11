<?php

namespace ESAdvSearch\Filters;

class ESAS_Filters {

    protected $category;

    public function __construct() {
        // Determine context (same as before)
        $this->category = is_category()
            ? get_queried_object()->term_id
            : strtolower(get_field('mixitup_data_filter'));
    }

    /**
     * Build filters array directly from the REST endpoint JSON
     */
    public function getFiltersFromEndpoint() {

        // Call our batches endpoint; add category/effect if needed
        $endpoint = rest_url('custom/v1/es-advanced-search-filters');

        $args = [];
        if ($this->category && $this->category !== 'all' && $this->category !== 'joblot') {
            // If you want to filter by taxonomy slug rather than ID, adjust here
            $args['category'] = $this->category;
        }

        if (!empty($args)) {
            $endpoint = add_query_arg($args, $endpoint);
        }

        $response = wp_remote_get($endpoint);

        if (is_wp_error($response)) {
            return []; // Bail gracefully if endpoint fails
        }

        $body = wp_remote_retrieve_body($response);
        $filters = json_decode($body, true);

        if (empty($filters) || !is_array($filters)) {
            return [];
        }

         // Hide effects filter if only one effect exists
        if (isset($filters['effects']) && count($filters['effects']) <= 1) {
            unset($filters['effects']);
        }

        return $filters;
        
    }

}
