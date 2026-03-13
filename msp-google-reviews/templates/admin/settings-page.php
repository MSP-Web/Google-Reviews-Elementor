<?php
/**
 * Admin Template: Settings Page
 *
 * Renders API key configuration and plugin settings form.
 *
 * Available variables:
 *   $api_key             string  Current saved API key
 *   $delete_on_uninstall string  '1' | '0'
 *   $notice              mixed   Transient notice string or false
 *
 * @package MSPGoogleReviews
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap msp-admin-wrap">
	<h1><?php esc_html_e( 'MSP Google Reviews — Settings', 'msp-google-reviews' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Settings saved successfully.', 'msp-google-reviews' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="msp_save_settings">
		<?php wp_nonce_field( 'msp_save_settings', 'msp_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>

				<tr>
					<th scope="row">
						<label for="msp_api_key"><?php esc_html_e( 'Google Places API Key', 'msp-google-reviews' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="msp_api_key"
							name="msp_api_key"
							value="<?php echo esc_attr( $api_key ); ?>"
							class="regular-text"
							autocomplete="off"
							spellcheck="false"
						>
						<p class="description">
							<?php esc_html_e( 'Requires Google Places API (New) or Places API enabled in your Google Cloud project.', 'msp-google-reviews' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Data on Uninstall', 'msp-google-reviews' ); ?>
					</th>
					<td>
						<label for="msp_delete_on_uninstall">
							<input
								type="checkbox"
								id="msp_delete_on_uninstall"
								name="msp_delete_on_uninstall"
								value="1"
								<?php checked( $delete_on_uninstall, '1' ); ?>
							>
							<?php esc_html_e( 'Delete all plugin data (database tables) when uninstalling', 'msp-google-reviews' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'By default, cached review data is retained after uninstall to prevent data loss. Enable this only if you want a full cleanup.', 'msp-google-reviews' ); ?>
						</p>
					</td>
				</tr>

			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'msp-google-reviews' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Plugin Information', 'msp-google-reviews' ); ?></h2>
	<table class="widefat striped" style="max-width:500px;">
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Version', 'msp-google-reviews' ); ?></td>
				<td><?php echo esc_html( MSP_GOOGLE_REVIEWS_VERSION ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Author', 'msp-google-reviews' ); ?></td>
				<td>Joshua Garza &mdash; MSP WebOps</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Schema Version', 'msp-google-reviews' ); ?></td>
				<td><?php echo esc_html( get_option( 'msp_google_reviews_schema_version', '—' ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Next Scheduled Sync', 'msp-google-reviews' ); ?></td>
				<td><?php
					$next = wp_next_scheduled( 'msp_google_reviews_daily_sync' );
					echo $next
						? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'M j, Y g:i a' ) )
						: esc_html__( 'Not scheduled', 'msp-google-reviews' );
				?></td>
			</tr>
		</tbody>
	</table>
</div>
