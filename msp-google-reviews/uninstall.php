<?php
/**
 * Uninstall Routine
 *
 * Runs when the plugin is deleted via WordPress admin.
 * - Always removes the scheduled cron event
 * - Always removes plugin options
 * - Drops custom tables ONLY if delete_data_on_uninstall is enabled
 *
 * Tables are retained by default to prevent accidental data loss.
 *
 * @package MSPGoogleReviews
 */

// WordPress uninstall safety check
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove scheduled cron event
wp_clear_scheduled_hook( 'msp_google_reviews_daily_sync' );

// Remove all plugin options
$options_to_delete = [
	'msp_google_reviews_api_key',
	'msp_google_reviews_delete_on_uninstall',
	'msp_google_reviews_schema_version',
];
foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Drop custom tables only if the user explicitly opted in
$delete_data = get_option( 'msp_google_reviews_delete_on_uninstall', '0' );

if ( '1' === $delete_data ) {
	global $wpdb;

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table names are not user input
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'msp_google_review_reviews' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'msp_google_review_locations' );
	// phpcs:enable
}
