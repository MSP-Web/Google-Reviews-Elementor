/**
 * MSP Google Reviews Widget — Elementor Editor JS
 *
 * Handles location search and binding inside the Elementor editor panel.
 *
 * Approach:
 *   1. Native capture-phase click handling + $(document).on() delegation.
 *      Works regardless of when the panel renders or when Elementor initializes,
 *      and capture mode runs before Elementor bubble handlers.
 *   2. elementor.hooks (panel/open_editor) — additional targeted binding once
 *      Elementor is ready, gives us direct panel + model access.
 *
 * The AJAX config check is inside the handler, not at module load time, so
 * the click listener is always registered even if wp_localize_script data
 * arrives later than the IIFE executes.
 *
 * @package MSPGoogleReviews
 */

( function ( $ ) {
	'use strict';

	function runSearchFromClick( e, $btn, $scope, model ) {
		e.preventDefault();
		e.stopPropagation();
		if ( typeof e.stopImmediatePropagation === 'function' ) {
			e.stopImmediatePropagation();
		}

		var config = window.mspGoogleReviewsEditor || {};

		if ( ! config.ajaxUrl ) {
			window.console && console.warn( '[MSP Reviews] mspGoogleReviewsEditor config not found. Check plugin activation and script enqueue.' );
			return;
		}

		doSearch( $btn, $scope, model, config );
	}

	// =========================================================================
	// Path 1: Capture + delegation — always active from script load
	// =========================================================================

	// Capture-phase handler fires before Elementor bubble handlers.
	document.addEventListener(
		'click',
		function ( e ) {
			if ( ! e.target || typeof e.target.closest !== 'function' ) {
				return;
			}
			var btnEl = e.target.closest( '.msp-do-search' );
			if ( ! btnEl ) {
				return;
			}

			var $btn   = $( btnEl );
			var $scope = $btn.closest( '#elementor-panel, .elementor-panel' );
			if ( ! $scope.length ) {
				$scope = $( document );
			}

			runSearchFromClick( e, $btn, $scope, null );
		},
		true
	);

	$( document ).on( 'click', '.msp-do-search', function ( e ) {
		var $btn   = $( this );
		// Walk up to the nearest panel container; fall back to document if not found
		var $scope = $btn.closest( '#elementor-panel, .elementor-panel' );
		if ( ! $scope.length ) {
			$scope = $( document );
		}

		runSearchFromClick( e, $btn, $scope, null );
	} );

	// =========================================================================
	// Path 2: Elementor hook — fires when our widget's panel is opened
	// Gives us direct model access for reliable setting updates.
	// =========================================================================

	function attachElementorHook() {
		if ( typeof window.elementor === 'undefined' ) return;
		if ( ! window.elementor.hooks ) return;

		window.elementor.hooks.addAction(
			'panel/open_editor/widget/msp_google_reviews',
			function ( panel, model /*, view */ ) {
				var $panel = panel.$el;

				// Remove any previous binding to avoid duplicate handlers
				$panel.off( 'click.mspDoSearch', '.msp-do-search' );

				$panel.on( 'click.mspDoSearch', '.msp-do-search', function ( e ) {
					runSearchFromClick( e, $( this ), $panel, model );
				} );
			}
		);
	}

	// Try immediately (in case Elementor is already initialized)
	attachElementorHook();

	// Also try after the standard Elementor init event fires
	window.addEventListener( 'elementor/initialized', attachElementorHook );

	// jQuery fallback event (some Elementor versions dispatch on window via jQuery)
	$( window ).on( 'elementor:init', attachElementorHook );

	// =========================================================================
	// Core: perform search AJAX
	// =========================================================================

	function doSearch( $btn, $scope, model, config ) {
		// Find the query input — Elementor wraps controls with data-setting attribute
		var $input = $scope.find( '[data-setting="location_search_query"] input' ).first();

		// Fallback: find by control class name
		if ( ! $input.length ) {
			$input = $scope.find( '.elementor-control-location_search_query input' ).first();
		}

		var query  = $.trim( $input.val() );
		var $results = $scope.find( '.msp-search-results-container' ).first();

		if ( ! query ) {
			$results.html( '<em style="color:#888;display:block;margin-top:6px;">Please enter a business name or address.</em>' );
			return;
		}

		$results.html( '<em style="color:#888;display:block;margin-top:6px;">Searching&hellip;</em>' );
		$btn.prop( 'disabled', true );

		$.ajax( {
			url:  config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'msp_search_places',
				nonce:  config.searchNonce,
				query:  query,
			},
			success: function ( res ) {
				$btn.prop( 'disabled', false );

				if ( ! res.success ) {
					var errMsg = ( res.data && res.data.message ) ? res.data.message : 'Search failed.';
					$results.html( '<em style="color:#dc3232;display:block;margin-top:6px;">' + $( '<span>' ).text( errMsg ).html() + '</em>' );
					return;
				}

				if ( ! $.isArray( res.data ) || res.data.length === 0 ) {
					$results.html( '<em style="color:#888;display:block;margin-top:6px;">No results found. Try a more specific name or address.</em>' );
					return;
				}

				renderResults( res.data, $results, $scope, model, config );
			},
			error: function ( xhr ) {
				$btn.prop( 'disabled', false );
				$results.html( '<em style="color:#dc3232;display:block;margin-top:6px;">Request failed (HTTP ' + xhr.status + '). Please try again.</em>' );
			},
		} );
	}

	// =========================================================================
	// Render result list — text nodes only, no innerHTML from API data
	// =========================================================================

	function renderResults( results, $container, $scope, model, config ) {
		var $list = $( '<ul>' ).css( {
			listStyle:    'none',
			margin:       '6px 0 0',
			padding:      0,
			border:       '1px solid #ddd',
			borderRadius: '3px',
			overflow:     'hidden',
			background:   '#fff',
			fontSize:     '12px',
		} );

		$.each( results, function ( i, result ) {
			var $li = $( '<li>' ).css( {
				padding:      '7px 9px',
				cursor:       'pointer',
				borderBottom: ( i < results.length - 1 ) ? '1px solid #eee' : 'none',
			} );

			var $name = $( '<strong>' );
			$name[0].textContent = result.business_name; // textContent — injection-safe

			var $addr = $( '<div>' ).css( { fontSize: '11px', color: '#777', marginTop: '2px' } );
			$addr[0].textContent = result.formatted_address;

			$li.append( $name ).append( $addr );
			$li.on( 'mouseenter', function () { $( this ).css( 'background', '#f0f7ff' ); } );
			$li.on( 'mouseleave', function () { $( this ).css( 'background', '#fff' ); } );
			$li.on( 'click',      function () { bindLocation( result, $container, $scope, model, config ); } );

			$list.append( $li );
		} );

		$container.empty().append( $list );
	}

	// =========================================================================
	// Bind a selected location — upsert via AJAX, then update widget model
	// =========================================================================

	function bindLocation( result, $container, $scope, model, config ) {
		$container.html( '<em style="color:#888;display:block;margin-top:6px;">Saving location&hellip;</em>' );

		$.ajax( {
			url:  config.ajaxUrl,
			type: 'POST',
			data: {
				action:            'msp_save_location',
				nonce:             config.saveLocationNonce,
				place_id:          result.place_id,
				business_name:     result.business_name,
				formatted_address: result.formatted_address,
			},
			success: function ( res ) {
				if ( ! res.success || ! res.data ) {
					$container.html( '<em style="color:#dc3232;display:block;margin-top:6px;">Failed to save location. Check your API key in plugin settings.</em>' );
					return;
				}

				updateWidgetSettings( $scope, model, res.data );

				// Confirmation message — text nodes only
				var $ok    = $( '<div>' ).css( { marginTop: '6px', padding: '6px 8px', background: '#f0fff4', border: '1px solid #b2dfdb', borderRadius: '3px', fontSize: '12px' } );
				var $icon  = $( '<span>' ).text( '\u2713 Bound: ' ).css( { color: '#2e7d32', fontWeight: 'bold' } );
				var $bname = $( '<strong>' );
				$bname[0].textContent = res.data.business_name;
				var $baddr = $( '<div>' ).css( { color: '#555', fontSize: '11px', marginTop: '2px' } );
				$baddr[0].textContent = res.data.formatted_address;

				$ok.append( $icon ).append( $bname ).append( $baddr );
				$container.empty().append( $ok );
			},
			error: function ( xhr ) {
				$container.html( '<em style="color:#dc3232;display:block;margin-top:6px;">Request failed (HTTP ' + xhr.status + '). Please try again.</em>' );
			},
		} );
	}

	// =========================================================================
	// Update Elementor widget model settings after a location is bound
	// =========================================================================

	function updateWidgetSettings( $scope, model, data ) {
		// Primary: use Elementor model API (most reliable — triggers re-render)
		if ( model && typeof model.setSetting === 'function' ) {
			model.setSetting( 'place_id',                 data.place_id );
			model.setSetting( 'location_display_name',    data.business_name );
			model.setSetting( 'location_display_address', data.formatted_address );
			return;
		}

		// Fallback: try to get the current model from the active panel view
		try {
			var activeModel = window.elementor
				.getPanelView()
				.getCurrentPageView()
				.model;
			activeModel.setSetting( 'place_id',                 data.place_id );
			activeModel.setSetting( 'location_display_name',    data.business_name );
			activeModel.setSetting( 'location_display_address', data.formatted_address );
			return;
		} catch ( e ) {
			// Model API not available — fall through to DOM fallback
		}

		// Last resort: set input values directly and fire change events
		// Elementor listens for input/change events on control fields
		setControlValue( $scope, 'place_id',                 data.place_id );
		setControlValue( $scope, 'location_display_name',    data.business_name );
		setControlValue( $scope, 'location_display_address', data.formatted_address );
	}

	/**
	 * Set an Elementor control's input value and trigger the events Elementor
	 * listens to so it registers the change in the model.
	 */
	function setControlValue( $scope, settingKey, value ) {
		var $input = $scope.find( '[data-setting="' + settingKey + '"] input' ).first();
		if ( ! $input.length ) {
			$input = $scope.find( '.elementor-control-' + settingKey + ' input' ).first();
		}
		if ( $input.length ) {
			$input.val( value ).trigger( 'input' ).trigger( 'change' );
		}
	}

} )( jQuery );
