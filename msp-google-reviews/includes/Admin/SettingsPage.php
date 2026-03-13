<?php
/**
 * Settings Page
 *
 * Handles API key configuration and the delete-data-on-uninstall option.
 * Enforces manage_options capability and nonce validation on all mutations.
 *
 * @package MSPGoogleReviews\Admin
 */

namespace MSPGoogleReviews\Admin;

defined( 'ABSPATH' ) || exit;

use MSPGoogleReviews\Service\CapabilityService;

class SettingsPage {

	/**
	 * Register form-submission hook.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_post_msp_save_settings', [ self::class, 'handle_save' ] );
	}

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! CapabilityService::can_manage_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msp-google-reviews' ) );
		}

		$api_key           = get_option( 'msp_google_reviews_api_key', '' );
		$delete_on_uninstall = get_option( 'msp_google_reviews_delete_on_uninstall', '0' );
		$notice            = get_transient( 'msp_settings_notice' );
		delete_transient( 'msp_settings_notice' );

		include MSP_GOOGLE_REVIEWS_DIR . 'templates/admin/settings-page.php';
	}

	/**
	 * Process the settings form submission.
	 */
	public static function handle_save(): void {
		if ( ! CapabilityService::can_manage_settings() ) {
			wp_die( esc_html__( 'Permission denied.', 'msp-google-reviews' ) );
		}

		check_admin_referer( 'msp_save_settings', 'msp_settings_nonce' );

		$api_key = sanitize_text_field( wp_unslash( $_POST['msp_api_key'] ?? '' ) );
		update_option( 'msp_google_reviews_api_key', $api_key );

		$delete_on_uninstall = isset( $_POST['msp_delete_on_uninstall'] ) ? '1' : '0';
		update_option( 'msp_google_reviews_delete_on_uninstall', $delete_on_uninstall );

		set_transient( 'msp_settings_notice', 'saved', 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=msp-google-reviews' ) );
		exit;
	}
}
