<?php
/**
 * Admin Template: Locations Page
 *
 * Displays all saved locations with sync status and action buttons.
 * Sync error messages are shown only to admins — never rendered publicly.
 *
 * Available variables:
 *   $locations  array   Location DB objects
 *   $notice     mixed   Transient notice string or false
 *
 * @package MSPGoogleReviews
 */

defined( 'ABSPATH' ) || exit;

$notice_messages = [
	'deleted'        => __( 'Location deleted.', 'msp-google-reviews' ),
	'refreshed'      => __( 'Reviews refreshed successfully.', 'msp-google-reviews' ),
	'refresh_failed' => __( 'Sync failed. Check your API key and try again.', 'msp-google-reviews' ),
];
?>

<div class="wrap msp-admin-wrap">
	<h1><?php esc_html_e( 'MSP Google Reviews — Locations', 'msp-google-reviews' ); ?></h1>

	<?php if ( $notice && isset( $notice_messages[ $notice ] ) ) :
		$notice_class = ( 'refresh_failed' === $notice ) ? 'notice-error' : 'notice-success';
	?>
	<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
		<p><?php echo esc_html( $notice_messages[ $notice ] ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( empty( $locations ) ) : ?>
	<p><?php esc_html_e( 'No locations saved yet. Use the Elementor widget to search and bind a location — it will appear here automatically.', 'msp-google-reviews' ); ?></p>

	<?php else : ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Business Name', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Address', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Rating', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Reviews', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Sync Status', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Last Sync', 'msp-google-reviews' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'msp-google-reviews' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $locations as $location ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $location->business_name ); ?></strong>
					<br><code style="font-size:10px;"><?php echo esc_html( $location->place_id ); ?></code>
				</td>
				<td><?php echo esc_html( $location->formatted_address ); ?></td>
				<td><?php echo esc_html( number_format( (float) $location->aggregate_rating, 1 ) ); ?> &#9733;</td>
				<td><?php echo esc_html( number_format_i18n( (int) $location->total_review_count ) ); ?></td>
				<td>
					<?php
					$status_labels = [
						'active'  => '<span style="color:#46b450;">&#10003; ' . esc_html__( 'Active', 'msp-google-reviews' ) . '</span>',
						'error'   => '<span style="color:#dc3232;">&#10007; ' . esc_html__( 'Error', 'msp-google-reviews' ) . '</span>',
						'pending' => '<span style="color:#ffb900;">&#8987; ' . esc_html__( 'Pending', 'msp-google-reviews' ) . '</span>',
					];
					$status = sanitize_key( $location->sync_status );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe static strings with esc_html() inside
					echo $status_labels[ $status ] ?? esc_html( $status );

					// Show error message only to admins, never publicly
					if ( 'error' === $status && ! empty( $location->last_sync_error_message ) ) :
					?>
					<br><small style="color:#dc3232;"><?php echo esc_html( $location->last_sync_error_message ); ?></small>
					<?php endif; ?>
				</td>
				<td>
					<?php
					if ( ! empty( $location->last_sync_success_at ) ) {
						echo esc_html(
							get_date_from_gmt( $location->last_sync_success_at, 'M j, Y g:i a' )
						);
					} else {
						esc_html_e( 'Never', 'msp-google-reviews' );
					}
					?>
				</td>
				<td>
					<!-- Manual Refresh -->
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="msp_refresh_location">
						<input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $location->id ); ?>">
						<?php wp_nonce_field( 'msp_refresh_location', 'msp_location_nonce' ); ?>
						<button type="submit" class="button button-small">
							<?php esc_html_e( 'Refresh', 'msp-google-reviews' ); ?>
						</button>
					</form>

					<!-- Delete -->
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;"
						onsubmit="return confirm('<?php echo esc_js( __( 'Delete this location? Associated reviews will be marked inactive.', 'msp-google-reviews' ) ); ?>')">
						<input type="hidden" name="action" value="msp_delete_location">
						<input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $location->id ); ?>">
						<?php wp_nonce_field( 'msp_delete_location', 'msp_location_nonce' ); ?>
						<button type="submit" class="button button-small button-link-delete">
							<?php esc_html_e( 'Delete', 'msp-google-reviews' ); ?>
						</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php endif; ?>

	<p style="margin-top:20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msp-google-reviews' ) ); ?>">
			&larr; <?php esc_html_e( 'Back to Settings', 'msp-google-reviews' ); ?>
		</a>
	</p>
</div>
