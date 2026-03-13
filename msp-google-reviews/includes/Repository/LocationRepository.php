<?php
/**
 * Location Repository
 *
 * All database operations for the locations table.
 * Enforces UNIQUE on place_id — upsert pattern prevents duplicates.
 *
 * @package MSPGoogleReviews\Repository
 */

namespace MSPGoogleReviews\Repository;

defined( 'ABSPATH' ) || exit;

class LocationRepository {

	/**
	 * @return string Full table name with WP prefix.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'msp_google_review_locations';
	}

	/**
	 * Upsert a location record by place_id.
	 * Inserts new, or updates all mutable fields if place_id already exists.
	 *
	 * @param array $data Normalized location data from GooglePlacesClient.
	 * @return int|false Location ID on success, false on failure.
	 */
	public function upsert( array $data ) {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$existing = $this->find_by_place_id( $data['place_id'] );

		if ( $existing ) {
			$wpdb->update(
				$this->table(),
				[
					'business_name'               => $data['business_name'],
					'formatted_address'           => $data['formatted_address'],
					'google_business_profile_url' => $data['google_business_profile_url'],
					'write_review_url'            => $data['write_review_url'],
					'aggregate_rating'            => $data['aggregate_rating'],
					'total_review_count'          => $data['total_review_count'],
					'updated_at'                  => $now,
				],
				[ 'id' => $existing->id ],
				[ '%s', '%s', '%s', '%s', '%f', '%d', '%s' ],
				[ '%d' ]
			);
			return (int) $existing->id;
		}

		$result = $wpdb->insert(
			$this->table(),
			[
				'place_id'                    => $data['place_id'],
				'business_name'               => $data['business_name'],
				'formatted_address'           => $data['formatted_address'],
				'google_business_profile_url' => $data['google_business_profile_url'],
				'write_review_url'            => $data['write_review_url'],
				'aggregate_rating'            => $data['aggregate_rating'],
				'total_review_count'          => $data['total_review_count'],
				'sync_status'                 => 'pending',
				'created_at'                  => $now,
				'updated_at'                  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update sync status and related metadata fields after a sync attempt.
	 *
	 * @param int    $location_id
	 * @param string $status            'active' | 'error' | 'pending'
	 * @param string $error_message     Empty string on success.
	 * @param bool   $is_success
	 */
	public function update_sync_status( int $location_id, string $status, string $error_message = '', bool $is_success = false ): void {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$fields = [
			'sync_status'             => $status,
			'last_sync_attempt_at'    => $now,
			'last_sync_error_message' => $is_success ? null : sanitize_text_field( $error_message ),
			'updated_at'              => $now,
		];

		if ( $is_success ) {
			$fields['last_sync_success_at'] = $now;
		}

		$wpdb->update(
			$this->table(),
			$fields,
			[ 'id' => $location_id ],
			[ '%s', '%s', $is_success ? 'NULL' : '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Find a location by place_id.
	 *
	 * @param string $place_id
	 * @return object|null
	 */
	public function find_by_place_id( string $place_id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE place_id = %s LIMIT 1",
				$place_id
			)
		) ?: null;
	}

	/**
	 * Find a location by its internal ID.
	 *
	 * @param int $location_id
	 * @return object|null
	 */
	public function find_by_id( int $location_id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
				$location_id
			)
		) ?: null;
	}

	/**
	 * Return all locations, ordered by business name.
	 *
	 * @return array
	 */
	public function get_all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no dynamic values
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY business_name ASC" ) ?: [];
	}

	/**
	 * Delete a location and cascade-mark its reviews as inactive.
	 *
	 * @param int $location_id
	 */
	public function delete( int $location_id ): void {
		global $wpdb;

		// Mark associated reviews inactive rather than hard-deleting
		$reviews_table = $wpdb->prefix . 'msp_google_review_reviews';
		$wpdb->update(
			$reviews_table,
			[ 'is_active' => 0, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'location_id' => $location_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		$wpdb->delete( $this->table(), [ 'id' => $location_id ], [ '%d' ] );
	}
}
