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

		// Search interface - populated and submitted via editor JS
		$this->add_control(
			'location_search_heading',
			[
				'label'     => __( 'Search for a Location', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
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

		// Read-only display of the bound location name (set via JS after selection)
		$this->add_control(
			'location_display_name',
			[
				'label'       => __( 'Selected Location', 'msp-google-reviews' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Use the search above to find and bind a location.', 'msp-google-reviews' ),
				'ai'          => [ 'active' => false ],
				'separator'   => 'before',
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

		$this->add_control(
			'show_dots',
			[
				'label'        => __( 'Show Dots', 'msp-google-reviews' ),
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

		$this->add_control(
			'cta_alignment',
			[
				'label'   => __( 'Button Alignment', 'msp-google-reviews' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => __( 'Left', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center'     => [
						'title' => __( 'Center', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-center',
					],
					'flex-end'   => [
						'title' => __( 'Right', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => 'flex-start',
				'toggle'    => false,
				'selectors' => [
					'{{WRAPPER}} .msp-cta-buttons' => 'justify-content: {{VALUE}}; align-items: {{VALUE}};',
				],
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
			'style_heading_cards',
			[
				'label' => __( 'Cards', 'msp-google-reviews' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
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
			'review_text_align',
			[
				'label'   => __( 'Review Text Alignment', 'msp-google-reviews' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [
						'title' => __( 'Left', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => 'left',
				'toggle'    => false,
				'selectors' => [
					'{{WRAPPER}} .msp-review-card__text, {{WRAPPER}} .msp-read-more-row' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'review_text_typography',
				'label'    => __( 'Review Text Typography', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-review-card__text',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'review_initials_typography',
				'label'    => __( 'Reviewer Initials Typography', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-review-card__author',
			]
		);

		$this->add_control(
			'style_heading_stars',
			[
				'label'     => __( 'Rating Stars', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'star_color',
			[
				'label'     => __( 'Filled Star Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F5A623',
				'selectors' => [ '{{WRAPPER}} .msp-star--filled' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'star_empty_color',
			[
				'label'     => __( 'Empty Star Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#cccccc',
				'selectors' => [ '{{WRAPPER}} .msp-star--empty' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'star_size',
			[
				'label'      => __( 'Star Size', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range'      => [
					'px' => [
						'min' => 10,
						'max' => 48,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .msp-stars' => 'font-size: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'style_heading_meta',
			[
				'label'     => __( 'Meta Section', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'meta_align',
			[
				'label'   => __( 'Meta Alignment', 'msp-google-reviews' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => __( 'Left', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center'     => [
						'title' => __( 'Center', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-center',
					],
					'flex-end'   => [
						'title' => __( 'Right', 'msp-google-reviews' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => 'flex-start',
				'toggle'    => false,
				'selectors' => [
					'{{WRAPPER}} .msp-aggregate' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'meta_typography',
				'label'    => __( 'Meta Typography', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-aggregate-rating, {{WRAPPER}} .msp-aggregate-label',
			]
		);

		$this->add_control(
			'meta_text_color',
			[
				'label'     => __( 'Meta Text Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#555555',
				'selectors' => [
					'{{WRAPPER}} .msp-aggregate-rating, {{WRAPPER}} .msp-aggregate-label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'style_heading_cta',
			[
				'label'     => __( 'CTA Buttons', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'cta_typography',
				'label'    => __( 'CTA Typography', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-cta-btn',
			]
		);

		$this->start_controls_tabs( 'cta_button_tabs' );

		$this->start_controls_tab(
			'cta_button_tab_normal',
			[
				'label' => __( 'Normal', 'msp-google-reviews' ),
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label'     => __( 'Text Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'button_background',
			[
				'label'     => __( 'Background', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#4285F4',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'cta_button_tab_hover',
			[
				'label' => __( 'Hover', 'msp-google-reviews' ),
			]
		);

		$this->add_control(
			'button_text_color_hover',
			[
				'label'     => __( 'Text Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn:hover, {{WRAPPER}} .msp-cta-btn:focus' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'button_background_hover',
			[
				'label'     => __( 'Background', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#2f6ed4',
				'selectors' => [ '{{WRAPPER}} .msp-cta-btn:hover, {{WRAPPER}} .msp-cta-btn:focus' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'cta_border',
				'label'    => __( 'Border', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-cta-btn',
			]
		);

		$this->add_responsive_control(
			'cta_border_radius',
			[
				'label'      => __( 'Border Radius', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .msp-cta-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'cta_padding',
			[
				'label'      => __( 'Padding', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .msp-cta-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'style_heading_read_more',
			[
				'label'     => __( 'Read More Link', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'read_more_typography',
				'label'    => __( 'Read More Typography', 'msp-google-reviews' ),
				'selector' => '{{WRAPPER}} .msp-read-more-toggle',
			]
		);

		$this->add_control(
			'read_more_color',
			[
				'label'     => __( 'Read More Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#5f6b7a',
				'selectors' => [
					'{{WRAPPER}} .msp-read-more-toggle' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'read_more_hover_color',
			[
				'label'     => __( 'Read More Hover Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#2f6ed4',
				'selectors' => [
					'{{WRAPPER}} .msp-read-more-toggle:hover, {{WRAPPER}} .msp-read-more-toggle:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'style_heading_navigation',
			[
				'label'     => __( 'Navigation Arrows', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'arrow_size',
			[
				'label'      => __( 'Arrow Size', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 20,
						'max' => 80,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .msp-arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: calc({{SIZE}}{{UNIT}} * 0.55);',
				],
			]
		);

		$this->add_responsive_control(
			'arrow_offset',
			[
				'label'      => __( 'Arrow Horizontal Offset', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => -40,
						'max' => 30,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .msp-arrow--prev' => 'left: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .msp-arrow--next' => 'right: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'arrow_color',
			[
				'label'     => __( 'Arrow Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => [ '{{WRAPPER}} .msp-arrow' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'arrow_background',
			[
				'label'     => __( 'Arrow Background', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(255, 255, 255, 0.9)',
				'selectors' => [ '{{WRAPPER}} .msp-arrow' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'style_heading_dots',
			[
				'label'     => __( 'Pagination Dots', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'dot_size',
			[
				'label'      => __( 'Dot Size', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 4,
						'max' => 20,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .msp-dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'dot_spacing',
			[
				'label'      => __( 'Dot Spacing', 'msp-google-reviews' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 20,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .msp-dot' => 'margin-left: calc({{SIZE}}{{UNIT}} / 2); margin-right: calc({{SIZE}}{{UNIT}} / 2);',
				],
			]
		);

		$this->add_control(
			'dot_color',
			[
				'label'     => __( 'Dot Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#cccccc',
				'selectors' => [ '{{WRAPPER}} .msp-dot' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'dot_active_color',
			[
				'label'     => __( 'Active Dot Color', 'msp-google-reviews' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#4285F4',
				'selectors' => [ '{{WRAPPER}} .msp-dot--active' => 'background-color: {{VALUE}};' ],
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
