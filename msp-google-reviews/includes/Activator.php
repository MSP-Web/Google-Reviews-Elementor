<?php
/**
 * Activator
 *
 * Handles plugin activation and deactivation.
 * Installs schema, sets default options, and schedules/unschedules cron.
 *
 * @package MSPGoogleReviews
 */

namespace MSPGoogleReviews;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Schema\SchemaInstaller;

class Activator {

	/**
	 * Cron hook name. Also referenced in uninstall.php.
	 */
	const CRON_HOOK = 'msp_google_reviews_daily_sync';

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		// Install or upgrade database schema
		SchemaInstaller::install();

		// Set default options if not already present
		if ( false === get_option( 'msp_google_reviews_api_key' ) ) {
			add_option( 'msp_google_reviews_api_key', '' );
		}
		if ( false === get_option( 'msp_google_reviews_delete_on_uninstall' ) ) {
			add_option( 'msp_google_reviews_delete_on_uninstall', '0' );
		}

		// Schedule daily sync if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}

		// Flush rewrite rules on next request
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Remove scheduled cron event
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
