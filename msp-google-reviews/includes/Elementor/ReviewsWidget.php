<?php
/**
 * Elementor Reviews Widget
 *
 * Renders Google Business reviews from locally cached database data only.
 * Never calls the Google API during front-end rendering.
 *
 * Reads: ReviewRepository → ReviewFilterService → PresenterService → template partials
 *
 * @package MSPGoogleReviews\Elementor
 */

namespace MSPGoogleReviews\Elementor;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Repository\LocationRepository;
use MSPGoogleReviews\Repository\ReviewRepository;
use MSPGoogleReviews\Service\ReviewFilterService;
use MSPGoogleReviews\Service\PresenterService;

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	return;
}

class ReviewsWidget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'msp_google_reviews';
	}

	public function get_title(): string {
		return __( 'MSP Google Reviews', 'msp-google-reviews' );
	}

	public function get_icon(): string {
		return 'eicon-star';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'google', 'reviews', 'rating', 'testimonials', 'msp' ];
	}

	/**
	 * Register all Elementor controls.
	 */
	protected function register_controls(): void {
		$this->register_location_section();
		$this->register_display_section();
		$this->register_filter_section();
		$this->register_carousel_section();
		$this->register_cta_section();
		$this->register_style_section();
	}

	// =========================================================================
	// SECTION: Location
	// =========================================================================

	private function register_location_section(): void {
		$this->start_controls_section(
			'section_location',
			[
				'label' => __( 'Location', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Hidden field: stores the bound place_id
		$this->add_control(
			'place_id',
			[
				'label'       => __( 'Place ID', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::HIDDEN,
				'default'     => '',
			]
		);

		// Read-only display of the bound location name (set via JS after selection)
		$this->add_control(
			'location_display_name',
			[
				'label'       => __( 'Selected Location', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Use the search below to find and bind a location.', 'msp-google-reviews' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'location_display_address',
			[
				'label'   => __( 'Address', 'msp-google-reviews' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'ai'      => [ 'active' => false ],
			]
		);

		// Search interface — populated and submitted via editor JS
		$this->add_control(
			'location_search_heading',
			[
				'label'     => __( 'Search for a Location', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'location_search_query',
			[
				'label'       => __( 'Business Name or Address', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Sunrise Dental, Dallas TX', 'msp-google-reviews' ),
				'ai'          => [ 'active' => false ],
			]
		);

		// Rendered as a plain HTML button so jQuery event delegation can reliably
		// bind to it regardless of Elementor editor initialization order.
		$this->add_control(
			'location_search_button',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<button type="button" class="elementor-button elementor-button-default msp-do-search" style="width:100%;margin-top:6px;">'
				          . esc_html__( 'Search', 'msp-google-reviews' )
				          . '</button>',
			]
		);

		$this->add_control(
			'location_search_results',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<div class="msp-search-results-container"></div>',
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// SECTION: Display
	// =========================================================================

	private function register_display_section(): void {
		$this->start_controls_section(
			'section_display',
			[
				'label' => __( 'Display', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'review_count',
			[
				'label'   => __( 'Number of Reviews', 'msp-google-reviews' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 5,
				'min'     => 1,
				'max'     => 5,
				'step'    => 1,
			]
		);

		$this->add_control(
			'show_aggregate',
			[
				'label'        => __( 'Show Aggregate Rating', 'msp-google-reviews' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'msp-google-reviews' ),
				'label_off'    => __( 'Hide', 'msp-google-reviews' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// SECTION: Filters
	// =========================================================================

	private function register_filter_section(): void {
		$this->start_controls_section(
			'section_filters',
			[
				'label' => __( 'Filters', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Multi-value rating filter — exact star selection
		$this->add_control(
			'filter_ratings',
			[
				'label'       => __( 'Show Only These Star Ratings', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => [
					'5' => __( '5 Stars', 'msp-google-reviews' ),
					'4' => __( '4 Stars', 'msp-google-reviews' ),
					'3' => __( '3 Stars', 'msp-google-reviews' ),
					'2' => __( '2 Stars', 'msp-google-reviews' ),
					'1' => __( '1 Star', 'msp-google-reviews' ),
				],
				'multiple'    => true,
				'default'     => [],
				'description' => __( 'Leave empty to show all ratings.', 'msp-google-reviews' ),
			]
		);

		$this->add_control(
			'include_keywords',
			[
				'label'       => __( 'Include Reviews Containing', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'friendly, clean, professional', 'msp-google-reviews' ),
				'description' => __( 'Comma-separated keywords. Reviews must match at least one.', 'msp-google-reviews' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'exclude_keywords',
			[
				'label'       => __( 'Exclude Reviews Containing', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'wait, parking', 'msp-google-reviews' ),
				'description' => __( 'Comma-separated keywords. Matching reviews are hidden.', 'msp-google-reviews' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// SECTION: Carousel
	// =========================================================================

	private function register_carousel_section(): void {
		$this->start_controls_section(
			'section_carousel',
			[
				'label' => __( 'Carousel', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label'        => __( 'Autoplay', 'msp-google-reviews' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'msp-google-reviews' ),
				'label_off'    => __( 'Off', 'msp-google-reviews' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'autoplay_interval',
			[
				'label'      => __( 'Autoplay Interval (ms)', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::NUMBER,
				'default'    => 5000,
				'min'        => 1000,
				'max'        => 30000,
				'step'       => 500,
				'condition'  => [ 'autoplay' => 'yes' ],
			]
		);

		$this->add_control(
			'show_arrows',
			[
				'label'        => __( 'Arrow Navigation', 'msp-google-reviews' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'msp-google-reviews' ),
				'label_off'    => __( 'Hide', 'msp-google-reviews' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// SECTION: CTA Buttons
	// =========================================================================

	private function register_cta_section(): void {
		$this->start_controls_section(
			'section_cta',
			[
				'label' => __( 'CTA Buttons', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Read More Reviews
		$this->add_control(
			'show_read_more',
			[
				'label'        => __( 'Show "Read More Reviews"', 'msp-google-reviews' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'msp-google-reviews' ),
				'label_off'    => __( 'Hide', 'msp-google-reviews' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'read_more_url',
			[
				'label'       => __( 'Custom "Read More" URL', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'Leave blank to use Google Business Profile URL', 'msp-google-reviews' ),
				'condition'   => [ 'show_read_more' => 'yes' ],
				'ai'          => [ 'active' => false ],
			]
		);

		// Write a Review
		$this->add_control(
			'show_write_review',
			[
				'label'        => __( 'Show "Write a Review"', 'msp-google-reviews' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'msp-google-reviews' ),
				'label_off'    => __( 'Hide', 'msp-google-reviews' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'write_review_url',
			[
				'label'       => __( 'Custom "Write a Review" URL', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'Leave blank to use default Google review URL', 'msp-google-reviews' ),
				'condition'   => [ 'show_write_review' => 'yes' ],
				'ai'          => [ 'active' => false ],
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// SECTION: Style
	// =========================================================================

	private function register_style_section(): void {
		$this->start_controls_section(
			'section_style',
			[
				'label' => __( 'Style', 'msp-google-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'card_background',
			[
				'label'     => __( 'Card Background', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .msp-review-card' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'text_color',
			[
				'label'     => __( 'Text Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => [ '{{WRAPPER}} .msp-review-card' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'star_color',
			[
				'label'     => __( 'Star Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F5A623',
				'selectors' => [ '{{WRAPPER}} .msp-star--filled' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'button_background',
			[
				'label'     => __( 'Button Background', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#4285F4',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label'     => __( 'Button Text Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// RENDER
	// =========================================================================

	/**
	 * Render widget output. Reads from local DB only — zero Google API calls.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$place_id = sanitize_text_field( $settings['place_id'] ?? '' );

		if ( empty( $place_id ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="msp-editor-placeholder">' . esc_html__( 'Select a location in the widget settings to display reviews.', 'msp-google-reviews' ) . '</div>';
			}
			return;
		}

		// Resolve location from local DB
		$location_repo = new LocationRepository();
		$location      = $location_repo->find_by_place_id( $place_id );

		if ( ! $location ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="msp-editor-placeholder">' . esc_html__( 'Location data not found. Try refreshing reviews in the admin panel.', 'msp-google-reviews' ) . '</div>';
			}
			return;
		}

		// Fetch active reviews from local DB
		$review_repo = new ReviewRepository();
		$reviews     = $review_repo->get_active_by_location( (int) $location->id );

		// Apply non-destructive filter pipeline
		$filter_service = new ReviewFilterService();
		$filtered       = $filter_service->filter( $reviews, $settings );

		// Prepare presenter
		$presenter    = new PresenterService();
		$location_data = $presenter->prepare_location( $location );

		// Prepare CTA URLs (custom override or default from location)
		$read_more_url    = $this->resolve_cta_url( $settings['read_more_url'] ?? [], $location_data['google_business_profile_url'] );
		$write_review_url = $this->resolve_cta_url( $settings['write_review_url'] ?? [], $location_data['write_review_url'] );

		// Pass config to carousel JS via data attributes (sanitized scalars only)
		$autoplay          = ( 'yes' === ( $settings['autoplay'] ?? 'yes' ) ) ? 'true' : 'false';
		$autoplay_interval = absint( $settings['autoplay_interval'] ?? 5000 );
		$autoplay_interval = max( 1000, min( 30000, $autoplay_interval ) );

		// Render
		if ( empty( $filtered ) ) {
			include MSP_GOOGLE_REVIEWS_DIR . 'templates/widget/summary-only.php';
		} else {
			include MSP_GOOGLE_REVIEWS_DIR . 'templates/widget/carousel.php';
		}
	}

	/**
	 * Resolve CTA URL: prefer custom override (if valid), fall back to location default.
	 *
	 * @param array  $elementor_url_value URL control value array from Elementor.
	 * @param string $default_url         Default URL from location data.
	 * @return string Safe escaped URL.
	 */
	private function resolve_cta_url( array $elementor_url_value, string $default_url ): string {
		$custom = sanitize_text_field( $elementor_url_value['url'] ?? '' );

		if ( ! empty( $custom ) ) {
			$presenter = new PresenterService();
			$safe      = $presenter->safe_url( $custom );
			if ( ! empty( $safe ) ) {
				return $safe;
			}
		}

		return $default_url;
	}
}
