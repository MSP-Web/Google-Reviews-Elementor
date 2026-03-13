<?php
/**
 * Presenter Service
 *
 * Applies privacy-safe transformations and output escaping for front-end rendering.
 * All data passing through this service is safe to render directly in HTML.
 *
 * Rules:
 *   - author_name → initials only (e.g. "George Washington" → "G. W.")
 *   - all text values are escaped via esc_html()
 *   - all URLs are validated (allowed schemes: https, http) and escaped via esc_url()
 *   - no reviewer photos, no full names ever reach rendered output
 *
 * @package MSPGoogleReviews\Service
 */

namespace MSPGoogleReviews\Service;

defined( 'ABSPATH' ) || exit;

class PresenterService {

	/**
	 * Allowed URL schemes for user-configurable links.
	 */
	const ALLOWED_URL_SCHEMES = [ 'https', 'http' ];

	/**
	 * Prepare a single review object for safe front-end rendering.
	 * Returns an array with all values pre-escaped for HTML context.
	 *
	 * @param object $review   Raw review DB object.
	 * @return array
	 */
	public function prepare_review( object $review ): array {
		return [
			'initials'    => esc_html( $this->to_initials( (string) $review->author_name ) ),
			'rating'      => (int) $review->rating,
			'review_text' => esc_html( (string) $review->review_text ),
			'stars_html'  => $this->build_stars_html( (int) $review->rating ),
		];
	}

	/**
	 * Prepare a location object for safe front-end rendering.
	 *
	 * @param object $location Raw location DB object.
	 * @return array
	 */
	public function prepare_location( object $location ): array {
		return [
			'business_name'               => esc_html( (string) $location->business_name ),
			'aggregate_rating'            => number_format( (float) $location->aggregate_rating, 1 ),
			'aggregate_rating_raw'        => (float) $location->aggregate_rating,
			'total_review_count'          => (int) $location->total_review_count,
			'google_business_profile_url' => $this->safe_url( (string) $location->google_business_profile_url ),
			'write_review_url'            => $this->safe_url( (string) $location->write_review_url ),
		];
	}

	/**
	 * Validate and escape a URL. Returns empty string for disallowed schemes.
	 *
	 * @param string $url
	 * @return string
	 */
	public function safe_url( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! in_array( strtolower( (string) $scheme ), self::ALLOWED_URL_SCHEMES, true ) ) {
			return '';
		}

		return esc_url( $url );
	}

	/**
	 * Convert a full name to initials format.
	 * Examples:
	 *   "George Washington"      → "G. W."
	 *   "Madonna"                → "M."
	 *   "Mary Jane Watson-Parker" → "M. J. W."  (hyphenated treated as separate)
	 *
	 * @param string $name
	 * @return string
	 */
	public function to_initials( string $name ): string {
		$name = trim( $name );

		if ( '' === $name ) {
			return '';
		}

		// Split on spaces and hyphens
		$parts    = preg_split( '/[\s\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
		$initials = [];

		foreach ( $parts as $part ) {
			$first = mb_substr( $part, 0, 1 );
			if ( '' !== $first ) {
				$initials[] = mb_strtoupper( $first ) . '.';
			}
		}

		return implode( ' ', $initials );
	}

	/**
	 * Build a star row HTML string using Unicode star characters.
	 * Output is safe — contains only numeric interpolation and static HTML.
	 *
	 * @param int $rating  1–5
	 * @return string
	 */
	public function build_stars_html( int $rating ): string {
		$rating = max( 0, min( 5, $rating ) );
		$html   = '<span class="msp-stars" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'msp-google-reviews' ), $rating ) ) . '">';

		for ( $i = 1; $i <= 5; $i++ ) {
			$class = $i <= $rating ? 'msp-star msp-star--filled' : 'msp-star msp-star--empty';
			$html .= '<span class="' . esc_attr( $class ) . '" aria-hidden="true">&#9733;</span>';
		}

		$html .= '</span>';
		return $html;
	}

	/**
	 * Truncate review text for carousel display and return both versions.
	 *
	 * @param string $text       Full review text.
	 * @param int    $max_length Maximum characters before truncation (default 255).
	 * @return array [ 'truncated' => bool, 'short' => string, 'full' => string ]
	 */
	public function prepare_review_text( string $text, int $max_length = 255 ): array {
		$full      = esc_html( $text );
		$truncated = mb_strlen( $text ) > $max_length;
		$short     = $truncated ? esc_html( mb_substr( $text, 0, $max_length ) ) . '&hellip;' : $full;

		return [
			'truncated' => $truncated,
			'short'     => $short,
			'full'      => $full,
		];
	}
}
