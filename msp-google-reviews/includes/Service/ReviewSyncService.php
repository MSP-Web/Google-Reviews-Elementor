<?php
/**
 * Review Sync Service
 *
 * Orchestrates the full sync cycle for a location:
 *   API fetch → normalize → upsert location → upsert reviews → mark stale → update metadata
 *
 * On failure: updates sync_status to 'error' and preserves the existing
 * cached dataset. Never clears data on API error.
 *
 * @package MSPGoogleReviews\Service
 */

namespace MSPGoogleReviews\Service;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\API\GooglePlacesClient;
use MSPGoogleReviews\Repository\LocationRepository;
use MSPGoogleReviews\Repository\ReviewRepository;

class ReviewSyncService {

	private LocationRepository $location_repo;
	private ReviewRepository   $review_repo;

	public function __construct() {
		$this->location_repo = new LocationRepository();
		$this->review_repo   = new ReviewRepository();
	}

	/**
	 * Cron callback: sync all active locations.
	 */
	public static function sync_all_locations(): void {
		$service   = new self();
		$locations = $service->location_repo->get_all();

		foreach ( $locations as $location ) {
			$service->sync( (int) $location->id );
		}
	}

	/**
	 * Sync a single location by its internal ID.
	 *
	 * @param int $location_id
	 * @return bool True on success, false on failure.
	 */
	public function sync( int $location_id ): bool {
		$location = $this->location_repo->find_by_id( $location_id );

		if ( ! $location ) {
			return false;
		}

		// Mark sync attempt
		$this->location_repo->update_sync_status( $location_id, 'pending', '', false );

		$api_key = get_option( 'msp_google_reviews_api_key', '' );
		$client  = new GooglePlacesClient( sanitize_text_field( $api_key ) );

		$result = $client->get_place_details( $location->place_id );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$this->location_repo->update_sync_status(
				$location_id,
				'error',
				$error_message,
				false
			);
			return false;
		}

		// Update location aggregate fields
		$this->location_repo->upsert( $result['location'] );

		// Process reviews
		$seen_identifiers = [];

		foreach ( $result['reviews'] as $review_data ) {
			// Skip reviews with no textual content
			if ( $this->is_empty_text( $review_data['review_text'] ) ) {
				continue;
			}

			$this->review_repo->upsert( $location_id, $review_data );
			$seen_identifiers[] = $review_data['review_identifier'];
		}

		// Mark reviews not present in this sync as stale
		$this->review_repo->mark_stale_except( $location_id, $seen_identifiers );

		// Record success
		$this->location_repo->update_sync_status( $location_id, 'active', '', true );

		return true;
	}

	/**
	 * Sync a location by place_id. Used when a location is first bound in the widget editor.
	 *
	 * @param string $place_id
	 * @return int|false Location ID on success, false on failure.
	 */
	public function sync_by_place_id( string $place_id ) {
		$location = $this->location_repo->find_by_place_id( $place_id );

		if ( ! $location ) {
			// Location doesn't exist yet — create a stub record so we have an ID
			$api_key = get_option( 'msp_google_reviews_api_key', '' );
			$client  = new GooglePlacesClient( sanitize_text_field( $api_key ) );

			$result = $client->get_place_details( $place_id );

			if ( is_wp_error( $result ) ) {
				return false;
			}

			$location_id = $this->location_repo->upsert( $result['location'] );

			if ( ! $location_id ) {
				return false;
			}

			// Process reviews for this new location
			$seen_identifiers = [];
			foreach ( $result['reviews'] as $review_data ) {
				if ( $this->is_empty_text( $review_data['review_text'] ) ) {
					continue;
				}
				$this->review_repo->upsert( $location_id, $review_data );
				$seen_identifiers[] = $review_data['review_identifier'];
			}

			$this->review_repo->mark_stale_except( $location_id, $seen_identifiers );
			$this->location_repo->update_sync_status( $location_id, 'active', '', true );

			return $location_id;
		}

		// Location exists — run normal sync
		$synced = $this->sync( (int) $location->id );
		return $synced ? (int) $location->id : false;
	}

	/**
	 * Check if a review text value should be treated as empty.
	 *
	 * @param mixed $text
	 * @return bool
	 */
	private function is_empty_text( $text ): bool {
		return null === $text || '' === trim( (string) $text );
	}
}
