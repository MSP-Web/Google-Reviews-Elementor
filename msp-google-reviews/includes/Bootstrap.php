<?php
/**
 * Bootstrap
 *
 * Central initialization point. Registers hooks, wires services,
 * enqueues assets, and registers the Elementor widget.
 *
 * @package MSPGoogleReviews
 */

namespace MSPGoogleReviews;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Admin\AdminController;
use MSPGoogleReviews\AJAX\SearchPlacesHandler;
use MSPGoogleReviews\AJAX\RefreshHandler;
use MSPGoogleReviews\Service\ReviewSyncService;
use MSPGoogleReviews\Schema\SchemaInstaller;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Bootstrap {

	/**
	 * Initialize the plugin. Called on plugins_loaded.
	 */
	public static function init(): void {
		// Run schema check on every load in case of manual file deployment
		SchemaInstaller::install();

		// GitHub-based update checker — must run unconditionally (not admin-only)
		// so WP-CLI, health checks, and multisite tooling see it too.
		self::init_update_checker();

		// Admin interface
		if ( is_admin() ) {
			AdminController::register();
		}

		// AJAX handlers (admin-only endpoints)
		SearchPlacesHandler::register();
		RefreshHandler::register();

		// Cron handler for daily sync
		add_action( Activator::CRON_HOOK, [ ReviewSyncService::class, 'sync_all_locations' ] );

		// Elementor widget registration
		add_action( 'elementor/widgets/register', [ self::class, 'register_elementor_widget' ] );

		// Enqueue front-end assets when Elementor is active on the page
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ] );

		// Enqueue editor assets inside Elementor editor
		add_action( 'elementor/editor/after_enqueue_scripts', [ self::class, 'enqueue_editor_assets' ] );
	}

	/**
	 * Set up the GitHub Releases-based update checker.
	 */
	public static function init_update_checker(): void {
		require_once MSP_GOOGLE_REVIEWS_DIR . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';

		$updateChecker = PucFactory::buildUpdateChecker(
			'https://github.com/MSP-Web/Google-Reviews-Elementor/',
			MSP_GOOGLE_REVIEWS_FILE,
			'msp-google-reviews'
		);
		$updateChecker->setBranch( 'main' );
		$updateChecker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );
	}

	/**
	 * Register the Elementor widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_elementor_widget( $widgets_manager ): void {
		require_once MSP_GOOGLE_REVIEWS_DIR . 'includes/Elementor/ReviewsWidget.php';
		$widgets_manager->register( new \MSPGoogleReviews\Elementor\ReviewsWidget() );
	}

	/**
	 * Enqueue front-end CSS and JS (only when widget may be present).
	 */
	public static function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'msp-google-reviews',
			MSP_GOOGLE_REVIEWS_URL . 'assets/css/msp-reviews-widget.css',
			[],
			self::asset_version( 'assets/css/msp-reviews-widget.css' )
		);

		wp_enqueue_script(
			'msp-google-reviews',
			MSP_GOOGLE_REVIEWS_URL . 'assets/js/msp-reviews-widget.js',
			[],
			self::asset_version( 'assets/js/msp-reviews-widget.js' ),
			true // load in footer
		);
	}

	/**
	 * Enqueue assets needed inside the Elementor editor panel.
	 *
	 * Uses a dedicated editor script (msp-reviews-editor.js) that is separate
	 * from the front-end carousel script. This prevents handle collisions and
	 * ensures wp_localize_script data is always attached to the correct handle.
	 */
	public static function enqueue_editor_assets(): void {
		// Front-end carousel script is also needed in the editor preview iframe
		wp_enqueue_script(
			'msp-google-reviews',
			MSP_GOOGLE_REVIEWS_URL . 'assets/js/msp-reviews-widget.js',
			[],
			self::asset_version( 'assets/js/msp-reviews-widget.js' ),
			true
		);

		// Dedicated editor-only script — location search + model binding
		wp_enqueue_script(
			'msp-google-reviews-editor',
			MSP_GOOGLE_REVIEWS_URL . 'assets/js/msp-reviews-editor.js',
			[ 'jquery' ],
			self::asset_version( 'assets/js/msp-reviews-editor.js' ),
			true
		);

		// Pass AJAX config to the editor script only
		wp_localize_script(
			'msp-google-reviews-editor',
			'mspGoogleReviewsEditor',
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'searchNonce'       => wp_create_nonce( 'msp_search_places' ),
				'saveLocationNonce' => wp_create_nonce( 'msp_save_location' ),
			]
		);
	}

	/**
	 * Resolve the cache-busting version string for an asset.
	 *
	 * In debug mode, uses the file's modification time so edits are picked
	 * up immediately without a manual version bump. In production, falls
	 * back to the stable plugin version for normal release-based caching.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string
	 */
	private static function asset_version( string $relative_path ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$file = MSP_GOOGLE_REVIEWS_DIR . $relative_path;
			if ( file_exists( $file ) ) {
				return (string) filemtime( $file );
			}
		}
		return MSP_GOOGLE_REVIEWS_VERSION;
	}
}
