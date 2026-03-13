<?php
/**
 * Refresh Handler
 *
 * Handles wp_ajax_msp_refresh_reviews — manual review sync trigger.
 * Requires manage_options capability. Never publicly accessible.
 *
 * @package MSPGoogleReviews\AJAX
 */

namespace MSPGoogleReviews\AJAX;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Service\CapabilityService;
use MSPGoogleReviews\Service\ReviewSyncService;

class RefreshHandler {

	/**
	 * Register AJAX hook (admin only).
	 */
	public static function register(): void {
		add_action( 'wp_ajax_msp_refresh_reviews', [ self::class, 'handle' ] );
		// Explicitly NOT registering wp_ajax_nopriv_ — this endpoint must never be public
	}

	/**
	 * Handle manual refresh request.
	 */
	public static function handle(): void {
		if ( ! CapabilityService::can_refresh_reviews() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'msp-google-reviews' ) ], 403 );
		}

		check_ajax_referer( 'msp_refresh_reviews', 'nonce' );

		$location_id = absint( $_POST['location_id'] ?? 0 );

		if ( $location_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid location ID.', 'msp-google-reviews' ) ], 400 );
		}

		$service = new ReviewSyncService();
		$success = $service->sync( $location_id );

		if ( $success ) {
			wp_send_json_success( [ 'message' => __( 'Reviews refreshed successfully.', 'msp-google-reviews' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Sync failed. Check API key and try again.', 'msp-google-reviews' ) ], 500 );
		}
	}
}
