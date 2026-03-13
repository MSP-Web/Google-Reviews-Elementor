<?php
/**
 * Review Filter Service
 *
 * Applies a non-destructive 5-step filtering pipeline to an in-memory
 * review array. Never modifies database records.
 *
 * Filter precedence:
 *   1. Active records only
 *   2. Rating filter (exact multi-value match)
 *   3. Include-text filter (keep reviews matching ANY keyword)
 *   4. Exclude-text filter (remove reviews matching ANY keyword; wins over include)
 *   5. Display count limit
 *
 * @package MSPGoogleReviews\Service
 */

namespace MSPGoogleReviews\Service;

defined( 'ABSPATH' ) || exit;

class ReviewFilterService {

	/**
	 * Filter a review array according to widget settings.
	 *
	 * @param object[] $reviews  Array of review DB objects.
	 * @param array    $settings Elementor widget settings.
	 * @return object[] Filtered array (may be empty).
	 */
	public function filter( array $reviews, array $settings ): array {
		$reviews = $this->step_active( $reviews );
		$reviews = $this->step_rating( $reviews, $settings );
		$reviews = $this->step_include( $reviews, $settings );
		$reviews = $this->step_exclude( $reviews, $settings );
		$reviews = $this->step_limit( $reviews, $settings );

		return $reviews;
	}

	/**
	 * Step 1: Keep only active (is_active = 1) records.
	 * DB query already filters this, but guard here for safety.
	 *
	 * @param object[] $reviews
	 * @return object[]
	 */
	private function step_active( array $reviews ): array {
		return array_values( array_filter( $reviews, fn( $r ) => ! empty( $r->is_active ) ) );
	}

	/**
	 * Step 2: Rating filter — exact multi-value selection.
	 * If no ratings selected, all ratings pass through.
	 *
	 * @param object[] $reviews
	 * @param array    $settings
	 * @return object[]
	 */
	private function step_rating( array $reviews, array $settings ): array {
		$selected = $this->get_selected_ratings( $settings );

		if ( empty( $selected ) ) {
			return $reviews;
		}

		return array_values(
			array_filter( $reviews, fn( $r ) => in_array( (int) $r->rating, $selected, true ) )
		);
	}

	/**
	 * Step 3: Include-text filter.
	 * If include keywords are set, only reviews matching AT LEAST ONE keyword pass through.
	 *
	 * @param object[] $reviews
	 * @param array    $settings
	 * @return object[]
	 */
	private function step_include( array $reviews, array $settings ): array {
		$keywords = $this->parse_keywords( $settings['include_keywords'] ?? '' );

		if ( empty( $keywords ) ) {
			return $reviews;
		}

		return array_values(
			array_filter( $reviews, fn( $r ) => $this->text_matches_any( $r->review_text, $keywords ) )
		);
	}

	/**
	 * Step 4: Exclude-text filter.
	 * Reviews matching ANY exclude keyword are removed. Exclusion beats inclusion.
	 *
	 * @param object[] $reviews
	 * @param array    $settings
	 * @return object[]
	 */
	private function step_exclude( array $reviews, array $settings ): array {
		$keywords = $this->parse_keywords( $settings['exclude_keywords'] ?? '' );

		if ( empty( $keywords ) ) {
			return $reviews;
		}

		return array_values(
			array_filter( $reviews, fn( $r ) => ! $this->text_matches_any( $r->review_text, $keywords ) )
		);
	}

	/**
	 * Step 5: Slice to configured display count (1–5).
	 *
	 * @param object[] $reviews
	 * @param array    $settings
	 * @return object[]
	 */
	private function step_limit( array $reviews, array $settings ): array {
		$limit = isset( $settings['review_count'] ) ? (int) $settings['review_count'] : 5;
		$limit = max( 1, min( 5, $limit ) );

		return array_slice( $reviews, 0, $limit );
	}

	/**
	 * Parse comma-separated keyword string into a clean array.
	 *
	 * @param string $raw
	 * @return string[]
	 */
	private function parse_keywords( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$keywords = explode( ',', $raw );
		$keywords = array_map( 'trim', $keywords );
		$keywords = array_filter( $keywords, fn( $k ) => '' !== $k );

		return array_values( $keywords );
	}

	/**
	 * Case-insensitive substring match against an array of keywords.
	 *
	 * @param string   $text
	 * @param string[] $keywords
	 * @return bool
	 */
	private function text_matches_any( string $text, array $keywords ): bool {
		$text_lower = mb_strtolower( $text );

		foreach ( $keywords as $keyword ) {
			if ( '' !== $keyword && false !== mb_strpos( $text_lower, mb_strtolower( $keyword ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract and validate selected rating values from settings.
	 * Ensures only integers in range [1–5] are returned.
	 *
	 * @param array $settings
	 * @return int[]
	 */
	private function get_selected_ratings( array $settings ): array {
		$raw = $settings['filter_ratings'] ?? [];

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$valid = [];
		foreach ( $raw as $value ) {
			$int = (int) $value;
			if ( $int >= 1 && $int <= 5 ) {
				$valid[] = $int;
			}
		}

		return $valid;
	}
}
