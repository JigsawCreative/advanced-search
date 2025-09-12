<?php

namespace ESAdvSearch\Filters;

class ESAS_Filters {

    public static function determinePageType()

    {

        if(is_category()) {

            //if the current page is a taxonomy template, return category name
            return get_queried_object()->term_id;

        } else if(is_page_template('grouped-products.php')) {

            //if page is using grouped products template return 'grouped-products'
            //retrieve category slug from ACF field
            return get_field('mixitup_data_filter');

        }

    }

    /**
     * Retrieve filters for the current page/category.
     * Falls back to global filters if a category-specific transient is missing.
     */
    public static function getFiltersForPage() {

        $category_key = self::determinePageType();

        // Build transient key: esas_filters_json_{slug} or 'all'
        $key = 'esas_filters_json_' . ($category_key ?: 'all');

        // Try category-specific filters first
        $filters = get_transient($key);

        if ($filters === false) {
            // Fallback to global
            $filters = get_transient('esas_filters_json_all');
        }

        return is_array($filters) ? $filters : [];
    }
}