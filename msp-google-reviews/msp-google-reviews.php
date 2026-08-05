<?php
/**
 * Plugin Name: MSP Google Reviews Widget
 * Description: Elementor widget for displaying Google Business reviews with filtering, privacy-safe rendering, and carousel controls.
 * Version: 1.2.0
 * Author: Joshua Garza
 * Organization: MSP WebOps
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: msp-google-reviews
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'MSP_GOOGLE_REVIEWS_VERSION', '1.2.0' );
define( 'MSP_GOOGLE_REVIEWS_FILE', __FILE__ );
define( 'MSP_GOOGLE_REVIEWS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSP_GOOGLE_REVIEWS_URL', plugin_dir_url( __FILE__ ) );

// Autoloader — maps namespace segments to directory structure
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'MSPGoogleReviews\\';
	$base_dir = MSP_GOOGLE_REVIEWS_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Activation / deactivation hooks — must be registered before Bootstrap runs
register_activation_hook( __FILE__, [ 'MSPGoogleReviews\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'MSPGoogleReviews\\Activator', 'deactivate' ] );

// Bootstrap the plugin on plugins_loaded so all WP APIs are available
add_action( 'plugins_loaded', function (): void {
	\MSPGoogleReviews\Bootstrap::init();
} );
