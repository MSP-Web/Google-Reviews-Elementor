<?php
/**
 * Admin Controller
 *
 * Registers the admin menu and dispatches to individual page handlers.
 * All capability checks delegate to CapabilityService.
 *
 * @package MSPGoogleReviews\Admin
 */

namespace MSPGoogleReviews\Admin;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Service\CapabilityService;

class AdminController {

	/**
	 * Register admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );

		// Handle form submissions for settings and locations
		SettingsPage::register_hooks();
		LocationsPage::register_hooks();
	}

	/**
	 * Register the plugin admin menu pages.
	 */
	public static function add_menu_pages(): void {
		add_menu_page(
			__( 'MSP Google Reviews', 'msp-google-reviews' ),
			__( 'Google Reviews', 'msp-google-reviews' ),
			'manage_options',
			'msp-google-reviews',
			[ SettingsPage::class, 'render' ],
			'dashicons-star-filled',
			80
		);

		add_submenu_page(
			'msp-google-reviews',
			__( 'Settings', 'msp-google-reviews' ),
			__( 'Settings', 'msp-google-reviews' ),
			'manage_options',
			'msp-google-reviews',
			[ SettingsPage::class, 'render' ]
		);

		add_submenu_page(
			'msp-google-reviews',
			__( 'Locations', 'msp-google-reviews' ),
			__( 'Locations', 'msp-google-reviews' ),
			'manage_options',
			'msp-google-reviews-locations',
			[ LocationsPage::class, 'render' ]
		);
	}

	/**
	 * Enqueue admin-only CSS/JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		$plugin_pages = [
			'toplevel_page_msp-google-reviews',
			'google-reviews_page_msp-google-reviews-locations',
		];

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'msp-google-reviews-admin',
			MSP_GOOGLE_REVIEWS_URL . 'assets/css/msp-reviews-widget.css',
			[],
			MSP_GOOGLE_REVIEWS_VERSION
		);
	}
}
