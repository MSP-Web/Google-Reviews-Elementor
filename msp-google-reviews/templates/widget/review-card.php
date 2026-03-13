<?php
/**
 * Widget Template: Review Card
 *
 * Renders a single review card in the order:
 *   1. Star row
 *   2. Review text (truncatable)
 *   3. Reviewer initials prefixed with dash
 *
 * Available variables:
 *   $review_data  array   Pre-escaped values from PresenterService::prepare_review()
 *   $text_data    array   [ 'truncated' => bool, 'short' => string, 'full' => string ]
 *
 * @package MSPGoogleReviews
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="msp-review-card__stars">
	<?php
	// Stars HTML is built by PresenterService using only static HTML + esc_attr()
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $review_data['stars_html'];
	?>
</div>

<div class="msp-review-card__text">
	<?php if ( $text_data['truncated'] ) : ?>
	<span class="msp-review-text-short"><?php
		// Already esc_html()'d by PresenterService::prepare_review_text()
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $text_data['short'];
	?></span>
	<span class="msp-review-text-full" style="display:none;"><?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $text_data['full'];
	?></span>
	<a href="#"
		class="msp-read-more-toggle"
		aria-expanded="false"
		data-more-label="<?php esc_attr_e( 'Read more', 'msp-google-reviews' ); ?>"
		data-less-label="<?php esc_attr_e( 'Show less', 'msp-google-reviews' ); ?>"
	><?php esc_html_e( 'Read more', 'msp-google-reviews' ); ?></a>
	<?php else : ?>
	<span class="msp-review-text-full"><?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $text_data['full'];
	?></span>
	<?php endif; ?>
</div>

<div class="msp-review-card__author">
	&mdash; <?php echo $review_data['initials']; // Already esc_html()'d ?>
</div>
