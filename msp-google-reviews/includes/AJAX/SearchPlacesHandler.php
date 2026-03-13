<?php
/**
 * Search Places AJAX Handler
 *
 * Handles wp_ajax_msp_search_places requests from the Elementor widget editor.
 * Requires nonce verification and edit_posts capability (editor-friendly).
 * Returns sanitized JSON of place candidates.
 *
 * A second action (msp_save_location) is also handled here:
 * it upserts the selected location and triggers an initial sync.
 *
 * @package MSPGoogleReviews\AJAX
 */

namespace MSPGoogleReviews\AJAX;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Service\CapabilityService;
use MSPGoogleReviews\Service\ReviewSyncService;
use MSPGoogleReviews\Repository\LocationRepository;
use MSPGoogleReviews\API\GooglePlacesClient;

class SearchPlacesHandler {

	/**
	 * Register AJAX hooks (admin only — location search is not public).
	 */
	public static function register(): void {
		add_action( 'wp_ajax_msp_search_places', [ self::class, 'handle_search' ] );
		add_action( 'wp_ajax_msp_save_location', [ self::class, 'handle_save_location' ] );
	}

	/**
	 * Handle place search request.
	 */
	public static function handle_search(): void {
		if ( ! CapabilityService::can_search_locations() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'msp-google-reviews' ) ], 403 );
		}

		check_ajax_referer( 'msp_search_places', 'nonce' );

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );

		if ( empty( $query ) ) {
			wp_send_json_error( [ 'message' => __( 'Search query is required.', 'msp-google-reviews' ) ], 400 );
		}

		$api_key = get_option( 'msp_google_reviews_api_key', '' );
		$client  = new GooglePlacesClient( sanitize_text_field( $api_key ) );
		$results = $client->search_places( $query );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( [ 'message' => esc_html( $results->get_error_message() ) ], 500 );
		}

		wp_send_json_success( $results );
	}

	/**
	 * Handle location save/bind request from the widget editor.
	 * Upserts the location into the global table and triggers initial sync.
	 */
	public static function handle_save_location(): void {
		if ( ! CapabilityService::can_search_locations() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'msp-google-reviews' ) ], 403 );
		}

		check_ajax_referer( 'msp_save_location', 'nonce' );

		$place_id         = sanitize_text_field( wp_unslash( $_POST['place_id'] ?? '' ) );
		$business_name    = sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) );
		$formatted_address = sanitize_text_field( wp_unslash( $_POST['formatted_address'] ?? '' ) );

		if ( empty( $place_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Place ID is required.', 'msp-google-reviews' ) ], 400 );
		}

		// Check if location already exists — if so, return its ID immediately
		$repo     = new LocationRepository();
		$existing = $repo->find_by_place_id( $place_id );

		if ( $existing ) {
			wp_send_json_success( [
				'location_id'      => (int) $existing->id,
				'place_id'         => esc_html( $existing->place_id ),
				'business_name'    => esc_html( $existing->business_name ),
				'formatted_address' => esc_html( $existing->formatted_address ),
			] );
		}

		// New location — trigger full sync which will upsert the record
		$service     = new ReviewSyncService();
		$location_id = $service->sync_by_place_id( $place_id );

		if ( ! $location_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch location data. Check your API key.', 'msp-google-reviews' ) ], 500 );
		}

		$location = $repo->find_by_id( $location_id );

		wp_send_json_success( [
			'location_id'      => (int) $location_id,
			'place_id'         => esc_html( $place_id ),
			'business_name'    => esc_html( $location ? $location->business_name : $business_name ),
			'formatted_address' => esc_html( $location ? $location->formatted_address : $formatted_address ),
		] );
	}
}
