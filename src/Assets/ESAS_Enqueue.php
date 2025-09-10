<?php

namespace ESAdvSearch\Assets;

class ESAS_Enqueue {

    public static function init() {

        // Enqueue front-end scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

    }

    public static function enqueue_scripts() {

        // Enqueue tile filter JS
        wp_enqueue_script( 'tile-filter', plugin_dir_url(__FILE__) . '/../../../assets/js/TileFilter.js', [], ESAS_VERSION, true );

        // Pass REST endpoint URL and nonce if needed
        wp_localize_script('tile-filter', 'ESAS', [
            'endpoint' => esc_url(rest_url('custom/v1/es-advanced-search')),
            'nonce'    => wp_create_nonce('wp_rest')
        ]);

        // Enqueue tile filter styles
        wp_enqueue_style('tile-filter-css', plugin_dir_url(__FILE__) . '/../../../assets/css/tile-filter.css', [], ESAS_VERSION );

    }

}