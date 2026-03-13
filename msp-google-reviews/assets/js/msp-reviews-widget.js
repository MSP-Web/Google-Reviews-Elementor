/**
 * MSP Google Reviews Widget — Front-end JS
 *
 * Handles:
 *   - Carousel initialization (autoplay, arrow navigation, dot indicators)
 *   - Read-more text expansion
 *   - Elementor editor: location search AJAX and location binding
 *
 * Configuration is passed via sanitized localized variables (wp_localize_script).
 * No user-supplied content is executed as code.
 *
 * @package MSPGoogleReviews
 */

( function () {
	'use strict';

	// =========================================================================
	// Carousel
	// =========================================================================

	function initCarousel( widget ) {
		var cards    = widget.querySelectorAll( '.msp-review-card' );
		var dots     = widget.querySelectorAll( '.msp-dot' );
		var prevBtn  = widget.querySelector( '.msp-arrow--prev' );
		var nextBtn  = widget.querySelector( '.msp-arrow--next' );
		var total    = cards.length;
		var current  = 0;
		var timer    = null;

		if ( total === 0 ) return;

		var autoplay = widget.dataset.autoplay === 'true';
		var interval = parseInt( widget.dataset.interval, 10 ) || 5000;
		interval     = Math.max( 1000, Math.min( 30000, interval ) );

		function showCard( index ) {
			cards.forEach( function ( card, i ) {
				var active = i === index;
				card.classList.toggle( 'msp-review-card--active', active );
				card.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
			} );

			dots.forEach( function ( dot, i ) {
				dot.classList.toggle( 'msp-dot--active', i === index );
			} );

			current = index;
		}

		function next() {
			showCard( ( current + 1 ) % total );
		}

		function prev() {
			showCard( ( current - 1 + total ) % total );
		}

		function startAutoplay() {
			if ( autoplay && total > 1 ) {
				timer = setInterval( next, interval );
			}
		}

		function stopAutoplay() {
			if ( timer ) {
				clearInterval( timer );
				timer = null;
			}
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				stopAutoplay();
				next();
				startAutoplay();
			} );
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				stopAutoplay();
				prev();
				startAutoplay();
			} );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				stopAutoplay();
				showCard( i );
				startAutoplay();
			} );
		} );

		// Pause on hover
		widget.addEventListener( 'mouseenter', stopAutoplay );
		widget.addEventListener( 'mouseleave', startAutoplay );

		// Keyboard navigation
		widget.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) { stopAutoplay(); prev(); startAutoplay(); }
			if ( e.key === 'ArrowRight' ) { stopAutoplay(); next(); startAutoplay(); }
		} );

		startAutoplay();
	}

	// =========================================================================
	// Read-more toggle
	// =========================================================================

	function initReadMore( widget ) {
		widget.querySelectorAll( '.msp-read-more-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var card      = btn.closest( '.msp-review-card__text' );
				var shortText = card.querySelector( '.msp-review-text-short' );
				var fullText  = card.querySelector( '.msp-review-text-full' );
				var expanded  = btn.getAttribute( 'aria-expanded' ) === 'true';

				if ( expanded ) {
					shortText.style.display = '';
					fullText.style.display  = 'none';
					btn.textContent         = btn.dataset.moreLabel || 'Read more';
					btn.setAttribute( 'aria-expanded', 'false' );
				} else {
					shortText.style.display = 'none';
					fullText.style.display  = '';
					btn.textContent         = btn.dataset.lessLabel || 'Show less';
					btn.setAttribute( 'aria-expanded', 'true' );
				}
			} );
		} );
	}

	// =========================================================================
	// Front-end initialization
	// =========================================================================

	function initWidgets() {
		document.querySelectorAll( '.msp-reviews-widget' ).forEach( function ( widget ) {
			initCarousel( widget );
			initReadMore( widget );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initWidgets );
	} else {
		initWidgets();
	}

	// Re-init when Elementor renders widgets on the front-end
	if ( window.elementorFrontend ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/msp_google_reviews.default', function ( $scope ) {
			var widget = $scope[0].querySelector( '.msp-reviews-widget' );
			if ( widget ) {
				initCarousel( widget );
				initReadMore( widget );
			}
		} );
	}

	// Editor location search is handled entirely in msp-reviews-editor.js.
	// That file uses jQuery event delegation and is loaded only in the
	// Elementor editor context via elementor/editor/after_enqueue_scripts.

} )();
