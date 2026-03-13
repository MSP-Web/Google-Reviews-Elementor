<?php
/**
 * Schema Installer
 *
 * Creates and upgrades custom database tables using dbDelta.
 * Schema version is tracked in wp_options to support future upgrades.
 *
 * @package MSPGoogleReviews\Schema
 */

namespace MSPGoogleReviews\Schema;

defined( 'ABSPATH' ) || exit;

class SchemaInstaller {

	/**
	 * Current schema version. Increment when table structure changes.
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option key used to track installed schema version.
	 */
	const VERSION_OPTION = 'msp_google_reviews_schema_version';

	/**
	 * Install or upgrade schema if needed.
	 */
	public static function install(): void {
		$installed = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Run dbDelta to create or update tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Locations table
		$locations_table = $wpdb->prefix . 'msp_google_review_locations';
		$sql_locations   = "CREATE TABLE {$locations_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			place_id VARCHAR(255) NOT NULL,
			business_name VARCHAR(255) NOT NULL DEFAULT '',
			formatted_address TEXT NOT NULL,
			google_business_profile_url TEXT NOT NULL,
			write_review_url TEXT NOT NULL,
			aggregate_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
			total_review_count INT UNSIGNED NOT NULL DEFAULT 0,
			sync_status VARCHAR(50) NOT NULL DEFAULT 'pending',
			last_sync_attempt_at DATETIME NULL DEFAULT NULL,
			last_sync_success_at DATETIME NULL DEFAULT NULL,
			last_sync_error_message TEXT NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY place_id_unique (place_id)
		) {$charset_collate};";

		// Reviews table
		$reviews_table = $wpdb->prefix . 'msp_google_review_reviews';
		$sql_reviews   = "CREATE TABLE {$reviews_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT UNSIGNED NOT NULL,
			review_identifier VARCHAR(255) NOT NULL,
			author_name VARCHAR(255) NOT NULL DEFAULT '',
			author_profile_url TEXT NULL DEFAULT NULL,
			author_profile_photo_url TEXT NULL DEFAULT NULL,
			rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
			review_text TEXT NOT NULL,
			relative_time_description VARCHAR(255) NULL DEFAULT NULL,
			review_created_at DATETIME NULL DEFAULT NULL,
			source_last_seen_at DATETIME NULL DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			raw_payload LONGTEXT NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY review_identifier_unique (review_identifier),
			KEY location_id_idx (location_id)
		) {$charset_collate};";

		dbDelta( $sql_locations );
		dbDelta( $sql_reviews );
	}

	/**
	 * Drop custom tables. Used only when full cleanup is requested on uninstall.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table names are not user input
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'msp_google_review_reviews' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'msp_google_review_locations' );
		// phpcs:enable
	}
}
