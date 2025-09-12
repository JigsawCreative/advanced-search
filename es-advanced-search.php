<?php
/*
Plugin Name: ES Advanced Search
Description: Emporio Surfaces API-based advanced search plugin
Version: 1.0
Author: Emporio Surfaces
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'ESAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESAS_URL',  plugin_dir_url( __FILE__ ) );
define( 'ESAS_VERSION', '1.1' );

if ( ! defined( 'SQM_BANDS' ) ) {
    define(
        'SQM_BANDS',
        [
            // id       => [ max sqm, label ]
            'sqm-0-1'     => [ 'max' => 1,  'name' => '0-1 sqm'   ],
            'sqm-1-5'     => [ 'max' => 5,  'name' => '1-5 sqm'   ],
            'sqm-5-10'    => [ 'max' => 10, 'name' => '5-10 sqm'  ],
            'sqm-10-20'   => [ 'max' => 20, 'name' => '10-20 sqm' ],
            'sqm-20-30'   => [ 'max' => 30, 'name' => '20-30 sqm' ],
            'sqm-30-40'   => [ 'max' => 40, 'name' => '30-40 sqm' ],
            'sqm-40-50'   => [ 'max' => 40, 'name' => '40-50 sqm' ],
            'sqm-50-plus' => [ 'max' => null, 'name' => '50+ sqm' ],
        ]
    );
}

// Composer autoload
if ( file_exists( ESAS_PATH . 'vendor/autoload.php' ) ) {
    require_once ESAS_PATH . 'vendor/autoload.php';
}

use ESAdvSearch\API\ESAS_BatchesAPI;
use ESAdvSearch\API\ESAS_FiltersAPI;
use ESAdvSearch\Assets\ESAS_Enqueue;

// Bootstrap main class
add_action( 'plugins_loaded', function() {

    ESAS_Enqueue::init();
    ESAS_BatchesAPI::init();
    ESAS_FiltersAPI::init();

});