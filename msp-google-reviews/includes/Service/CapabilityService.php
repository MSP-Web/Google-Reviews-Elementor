<?php
/**
 * Capability Service
 *
 * Centralized capability policy. Every permission check in the plugin
 * delegates here. No capability strings are hardcoded elsewhere.
 *
 * Permission model:
 *   Admin-level:  manage_options  (API key, location CRUD, manual sync)
 *   Editor-level: edit_posts       (widget location search in Elementor)
 *
 * @package MSPGoogleReviews\Service
 */

namespace MSPGoogleReviews\Service;

defined( 'ABSPATH' ) || exit;

class CapabilityService {

	/**
	 * Can the current user manage plugin settings (API key, global config)?
	 */
	public static function can_manage_settings(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Can the current user manage locations in the admin interface?
	 * Covers add, delete, and listing in admin.
	 */
	public static function can_manage_locations(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Can the current user trigger a manual review refresh?
	 */
	public static function can_refresh_reviews(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Can the current user search for and bind locations in the Elementor widget?
	 * Intentionally broader than manage_options to allow trusted editors
	 * to configure widgets without needing full admin rights.
	 */
	public static function can_search_locations(): bool {
		return current_user_can( 'edit_posts' );
	}
}
