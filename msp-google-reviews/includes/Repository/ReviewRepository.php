<?php
/**
 * Review Repository
 *
 * All database operations for the reviews table.
 * Upserts by review_identifier. Marks missing reviews as stale.
 * Never deletes canonical records.
 *
 * @package MSPGoogleReviews\Repository
 */

namespace MSPGoogleReviews\Repository;

defined( 'ABSPATH' ) || exit;

class ReviewRepository {

	/**
	 * @return string Full table name with WP prefix.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'msp_google_review_reviews';
	}

	/**
	 * Upsert a review by review_identifier.
	 * Inserts if new, updates mutable fields if already present.
	 *
	 * @param int   $location_id
	 * @param array $data Normalized review data from GooglePlacesClient.
	 * @return int|false Review ID on success, false on failure.
	 */
	public function upsert( int $location_id, array $data ) {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$existing = $this->find_by_identifier( $data['review_identifier'] );

		if ( $existing ) {
			$wpdb->update(
				$this->table(),
				[
					'author_name'               => $data['author_name'],
					'author_profile_url'        => $data['author_profile_url'] ?: null,
					'author_profile_photo_url'  => $data['author_profile_photo_url'] ?: null,
					'rating'                    => $data['rating'],
					'review_text'               => $data['review_text'],
					'relative_time_description' => $data['relative_time_description'] ?: null,
					'review_created_at'         => $data['review_created_at'],
					'source_last_seen_at'       => $now,
					'is_active'                 => 1,
					'raw_payload'               => $data['raw_payload'] ?: null,
					'updated_at'                => $now,
				],
				[ 'id' => $existing->id ],
				[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
				[ '%d' ]
			);
			return (int) $existing->id;
		}

		$result = $wpdb->insert(
			$this->table(),
			[
				'location_id'               => $location_id,
				'review_identifier'         => $data['review_identifier'],
				'author_name'               => $data['author_name'],
				'author_profile_url'        => $data['author_profile_url'] ?: null,
				'author_profile_photo_url'  => $data['author_profile_photo_url'] ?: null,
				'rating'                    => $data['rating'],
				'review_text'               => $data['review_text'],
				'relative_time_description' => $data['relative_time_description'] ?: null,
				'review_created_at'         => $data['review_created_at'],
				'source_last_seen_at'       => $now,
				'is_active'                 => 1,
				'raw_payload'               => $data['raw_payload'] ?: null,
				'created_at'                => $now,
				'updated_at'                => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Mark all reviews for a location as stale EXCEPT those in the keep list.
	 * Used to flag reviews that disappeared from the latest API response.
	 *
	 * @param int   $location_id
	 * @param array $seen_identifiers Array of review_identifier strings seen in latest sync.
	 */
	public function mark_stale_except( int $location_id, array $seen_identifiers ): void {
		global $wpdb;

		if ( empty( $seen_identifiers ) ) {
			// Mark all as stale for this location
			$wpdb->update(
				$this->table(),
				[ 'is_active' => 0, 'updated_at' => current_time( 'mysql', true ) ],
				[ 'location_id' => $location_id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
			return;
		}

		// Build a safe IN clause using individual prepare calls
		$placeholders = implode( ', ', array_fill( 0, count( $seen_identifiers ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET is_active = 0, updated_at = %s
				 WHERE location_id = %d AND is_active = 1
				 AND review_identifier NOT IN ({$placeholders})",
				array_merge(
					[ current_time( 'mysql', true ), $location_id ],
					$seen_identifiers
				)
			)
		);
	}

	/**
	 * Get all active reviews for a location, ordered by review date descending.
	 *
	 * @param int $location_id
	 * @return array
	 */
	public function get_active_by_location( int $location_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()}
				 WHERE location_id = %d AND is_active = 1
				 ORDER BY review_created_at DESC",
				$location_id
			)
		) ?: [];
	}

	/**
	 * Find a review by its identifier.
	 *
	 * @param string $identifier
	 * @return object|null
	 */
	public function find_by_identifier( string $identifier ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE review_identifier = %s LIMIT 1",
				$identifier
			)
		) ?: null;
	}

	/**
	 * Return all review identifiers currently stored for a location.
	 * Used during sync to compute which records have gone stale.
	 *
	 * @param int $location_id
	 * @return array String array of review_identifier values.
	 */
	public function get_identifiers_by_location( int $location_id ): array {
		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT review_identifier FROM {$this->table()} WHERE location_id = %d",
				$location_id
			)
		);

		return $results ?: [];
	}
}
