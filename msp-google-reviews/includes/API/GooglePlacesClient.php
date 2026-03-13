<?php
/**
 * Google Places API Client
 *
 * All outbound communication with Google APIs is isolated here.
 * Uses hardcoded Google API base URLs only (SSRF protection).
 * Never called during front-end page rendering.
 *
 * @package MSPGoogleReviews\API
 */

namespace MSPGoogleReviews\API;

defined( 'ABSPATH' ) || exit;

class GooglePlacesClient {

	/**
	 * Google Places API base URLs — hardcoded, never user-supplied.
	 */
	const PLACES_SEARCH_URL = 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json';
	const PLACE_DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

	/**
	 * Maximum raw_payload size in bytes before truncation (1 MB).
	 */
	const MAX_PAYLOAD_BYTES = 1048576;

	/** @var string */
	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Search for places by text query.
	 * Returns array of candidate results or WP_Error on failure.
	 *
	 * @param string $query Business name and/or address.
	 * @return array|\WP_Error
	 */
	public function search_places( string $query ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Google API key is not configured.', 'msp-google-reviews' ) );
		}

		$url = add_query_arg(
			[
				'input'         => rawurlencode( $query ),
				'inputtype'     => 'textquery',
				'fields'        => 'place_id,name,formatted_address',
				'key'           => $this->api_key,
			],
			self::PLACES_SEARCH_URL
		);

		$response = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => true ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new \WP_Error(
				'api_http_error',
				sprintf( __( 'Google API returned HTTP %d.', 'msp-google-reviews' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['candidates'] ) ) {
			return new \WP_Error( 'api_parse_error', __( 'Unexpected response from Google Places API.', 'msp-google-reviews' ) );
		}

		if ( isset( $data['status'] ) && 'OK' !== $data['status'] && 'ZERO_RESULTS' !== $data['status'] ) {
			return new \WP_Error(
				'api_status_error',
				sprintf( __( 'Google API status: %s', 'msp-google-reviews' ), sanitize_text_field( $data['status'] ) )
			);
		}

		return $this->normalize_search_candidates( $data['candidates'] ?? [] );
	}

	/**
	 * Get full place details including reviews and aggregate rating.
	 * Returns normalized data array or WP_Error on failure.
	 *
	 * @param string $place_id Google Place ID.
	 * @return array|\WP_Error
	 */
	public function get_place_details( string $place_id ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Google API key is not configured.', 'msp-google-reviews' ) );
		}

		$url = add_query_arg(
			[
				'place_id' => rawurlencode( $place_id ),
				'fields'   => 'name,formatted_address,url,rating,user_ratings_total,reviews',
				'key'      => $this->api_key,
			],
			self::PLACE_DETAILS_URL
		);

		$response = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => true ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new \WP_Error(
				'api_http_error',
				sprintf( __( 'Google API returned HTTP %d.', 'msp-google-reviews' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['result'] ) ) {
			return new \WP_Error( 'api_parse_error', __( 'Unexpected response from Google Places Details API.', 'msp-google-reviews' ) );
		}

		if ( isset( $data['status'] ) && 'OK' !== $data['status'] ) {
			return new \WP_Error(
				'api_status_error',
				sprintf( __( 'Google API status: %s', 'msp-google-reviews' ), sanitize_text_field( $data['status'] ) )
			);
		}

		return $this->normalize_place_details( $place_id, $data['result'], $body );
	}

	/**
	 * Normalize search candidate results into a consistent array.
	 *
	 * @param array $candidates Raw candidates from API.
	 * @return array
	 */
	private function normalize_search_candidates( array $candidates ): array {
		$results = [];
		foreach ( $candidates as $candidate ) {
			$results[] = [
				'place_id'          => sanitize_text_field( $candidate['place_id'] ?? '' ),
				'business_name'     => sanitize_text_field( $candidate['name'] ?? '' ),
				'formatted_address' => sanitize_text_field( $candidate['formatted_address'] ?? '' ),
			];
		}
		return $results;
	}

	/**
	 * Normalize full place details into a structured array.
	 * Includes location metadata and individual review records.
	 *
	 * @param string $place_id      The requested place ID.
	 * @param array  $result        Parsed result object from API.
	 * @param string $raw_body      Full raw JSON response body.
	 * @return array
	 */
	private function normalize_place_details( string $place_id, array $result, string $raw_body ): array {
		// Truncate raw payload if abnormally large
		$raw_payload = strlen( $raw_body ) > self::MAX_PAYLOAD_BYTES
			? substr( $raw_body, 0, self::MAX_PAYLOAD_BYTES ) . '...[TRUNCATED]'
			: $raw_body;

		// Build the Google Maps profile URL
		$profile_url     = sanitize_url( $result['url'] ?? '' );
		$write_review_url = '';
		if ( ! empty( $place_id ) ) {
			$write_review_url = 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place_id );
		}

		$location = [
			'place_id'                     => sanitize_text_field( $place_id ),
			'business_name'                => sanitize_text_field( $result['name'] ?? '' ),
			'formatted_address'            => sanitize_text_field( $result['formatted_address'] ?? '' ),
			'google_business_profile_url'  => $profile_url,
			'write_review_url'             => $write_review_url,
			'aggregate_rating'             => isset( $result['rating'] ) ? (float) $result['rating'] : 0.0,
			'total_review_count'           => isset( $result['user_ratings_total'] ) ? (int) $result['user_ratings_total'] : 0,
		];

		$reviews = [];
		if ( ! empty( $result['reviews'] ) && is_array( $result['reviews'] ) ) {
			foreach ( $result['reviews'] as $review ) {
				$reviews[] = $this->normalize_review( $place_id, $review, $raw_payload );
			}
		}

		return [
			'location' => $location,
			'reviews'  => $reviews,
		];
	}

	/**
	 * Normalize a single review record.
	 *
	 * @param string $place_id     Place ID for identifier scoping.
	 * @param array  $review       Raw review data from API.
	 * @param string $raw_payload  Raw JSON payload stored for traceability.
	 * @return array
	 */
	private function normalize_review( string $place_id, array $review, string $raw_payload ): array {
		$author_name  = sanitize_text_field( $review['author_name'] ?? '' );
		$review_time  = isset( $review['time'] ) ? (int) $review['time'] : 0;
		$review_text  = sanitize_textarea_field( $review['text'] ?? '' );
		$rating       = isset( $review['rating'] ) ? (int) $review['rating'] : 0;

		// Compute deterministic review identifier
		// Google Places API does not expose a stable per-review ID, so we hash the composite key
		$review_identifier = sha1( $place_id . '|' . $author_name . '|' . $review_time . '|' . $review_text );

		$review_created_at = $review_time > 0
			? gmdate( 'Y-m-d H:i:s', $review_time )
			: null;

		return [
			'review_identifier'          => $review_identifier,
			'author_name'                => $author_name,
			'author_profile_url'         => sanitize_url( $review['author_url'] ?? '' ),
			'author_profile_photo_url'   => sanitize_url( $review['profile_photo_url'] ?? '' ),
			'rating'                     => $rating,
			'review_text'                => $review_text,
			'relative_time_description'  => sanitize_text_field( $review['relative_time_description'] ?? '' ),
			'review_created_at'          => $review_created_at,
			'raw_payload'                => $raw_payload,
		];
	}
}
