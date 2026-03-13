<?php
/**
 * Locations Page
 *
 * Admin interface for viewing, managing, and manually syncing locations.
 * All mutations enforce capability checks and nonce validation.
 *
 * @package MSPGoogleReviews\Admin
 */

namespace MSPGoogleReviews\Admin;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Service\CapabilityService;
use MSPGoogleReviews\Service\ReviewSyncService;
use MSPGoogleReviews\Repository\LocationRepository;

class LocationsPage {

	/**
	 * Register form-submission hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_post_msp_delete_location', [ self::class, 'handle_delete' ] );
		add_action( 'admin_post_msp_refresh_location', [ self::class, 'handle_refresh' ] );
	}

	/**
	 * Render the locations management page.
	 */
	public static function render(): void {
		if ( ! CapabilityService::can_manage_locations() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msp-google-reviews' ) );
		}

		$repo      = new LocationRepository();
		$locations = $repo->get_all();
		$notice    = get_transient( 'msp_locations_notice' );
		delete_transient( 'msp_locations_notice' );

		include MSP_GOOGLE_REVIEWS_DIR . 'templates/admin/locations-page.php';
	}

	/**
	 * Handle location delete action.
	 */
	public static function handle_delete(): void {
		if ( ! CapabilityService::can_manage_locations() ) {
			wp_die( esc_html__( 'Permission denied.', 'msp-google-reviews' ) );
		}

		check_admin_referer( 'msp_delete_location', 'msp_location_nonce' );

		$location_id = absint( $_POST['location_id'] ?? 0 );

		if ( $location_id > 0 ) {
			$repo = new LocationRepository();
			$repo->delete( $location_id );
			set_transient( 'msp_locations_notice', 'deleted', 30 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=msp-google-reviews-locations' ) );
		exit;
	}

	/**
	 * Handle manual location refresh action.
	 */
	public static function handle_refresh(): void {
		if ( ! CapabilityService::can_manage_locations() ) {
			wp_die( esc_html__( 'Permission denied.', 'msp-google-reviews' ) );
		}

		check_admin_referer( 'msp_refresh_location', 'msp_location_nonce' );

		$location_id = absint( $_POST['location_id'] ?? 0 );

		if ( $location_id > 0 ) {
			$service = new ReviewSyncService();
			$success = $service->sync( $location_id );
			$notice  = $success ? 'refreshed' : 'refresh_failed';
			set_transient( 'msp_locations_notice', $notice, 30 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=msp-google-reviews-locations' ) );
		exit;
	}
}
