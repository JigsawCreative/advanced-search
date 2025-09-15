<?php

namespace ESAdvSearch\Assets;

class ESAS_Enqueue {

    public static function init() {

        // Enqueue front-end scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

    }

    public static function enqueue_scripts() {

        // Enqueue search JS on all pages
        wp_enqueue_script( 'advanced-search', plugin_dir_url(__FILE__) . '/../../../assets/js/search.js', [], ESAS_VERSION, true );

        // Enqueue tile filter JS
        wp_enqueue_script( 'tile-filter', plugin_dir_url(__FILE__) . '/../../../assets/js/TileFilter.js', [], ESAS_VERSION, true );

        // Determine the category/slug value for the current page
        $category_value = '';
        if ( is_category() ) {
            // taxonomy template – use category term_id or slug
            $category_value = (string) get_queried_object()->slug;
        } elseif ( is_page_template( 'grouped-products.php' ) ) {
            // grouped-products template – use ACF field value
            $category_value = (string) get_field( 'mixitup_data_filter' );
        }

        // Pass REST endpoint URL and nonce if needed
        wp_localize_script('tile-filter', 'ESAS', [
            'endpoint' => esc_url(rest_url('custom/v1/es-advanced-search')),
            'category'  => $category_value ?: ''
        ]);

        // Enqueue tile filter styles
        wp_enqueue_style('tile-filter-css', plugin_dir_url(__FILE__) . '/../../../assets/css/tile-filter.css', [], ESAS_VERSION );

    }

}