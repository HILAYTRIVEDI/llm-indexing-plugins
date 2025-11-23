<?php
/**
 * Admin Page Class
 *
 * Handles the main admin page UI and AJAX handlers.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Admin_Page
 */
class Bulk_Yoast_Meta_Updater_Admin_Page {

	/**
	 * Render the admin page.
	 */
	public function render() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		$settings         = bymu_get_settings();
		$provider         = bymu_get_active_seo_provider();
		$provider_label   = $provider ? $provider->get_label() : __( 'Yoast SEO & All in One SEO', 'bulk-yoast-meta-updater' );
		$post_types       = bymu_get_post_type_options();
		$post_type_counts = bymu_get_post_type_counts();
		$tracked_statuses = isset( $post_type_counts['statuses'] ) ? (array) $post_type_counts['statuses'] : [ 'publish', 'draft', 'private', 'pending' ];
		$post_type_meta   = isset( $post_type_counts['post_types'] ) ? (array) $post_type_counts['post_types'] : [];

		?>
		<div class="wrap bymu-wrap">
			<style>
				/* Prevent default footer text from overlapping the layout */
				.wp-admin #wpfooter {
					position: static;
				}
			</style>
			<!-- Header -->
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'Dashboard', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle"><?php esc_html_e( 'Monitor health, run quick actions, and review Recent Jobs insights.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>
					<div class="bymu-header-actions">
						<a class="button bymu-button-ghost bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings&tab=documentation' ) ); ?>">
							<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php bymu_render_admin_nav( 'dashboard' ); ?>

			<!-- Main Layout -->
			<div>
					<!-- Test Section -->
					<?php if ( bymu_is_test_mode_enabled() ) : ?>
					<div id="bymu-test-section" class="bymu-section bymu-section-compact">
				<div class="bymu-section-header">
					<h2><?php esc_html_e( 'Test SEO Updates', 'bulk-yoast-meta-updater' ); ?></h2>
					<p><?php esc_html_e( 'Test the plugin by applying random SEO data to selected posts. All changes are logged and appear in Recent Jobs below.', 'bulk-yoast-meta-updater' ); ?></p>
				</div>
				<div class="bymu-section-body">
					<div class="bymu-info-box" style="background: #fff3cd; border-left-color: #ffc107;">
						<div class="bymu-info-box-icon dashicons dashicons-warning"></div>
						<div class="bymu-info-box-content">
							<p><strong><?php esc_html_e( 'Test Data Format:', 'bulk-yoast-meta-updater' ); ?></strong></p>
							<ul style="margin: 0.5em 0 0 1.5em;">
								<li><?php esc_html_e( 'Meta Title: Random 60-character phrase with timestamp', 'bulk-yoast-meta-updater' ); ?></li>
								<li><?php esc_html_e( 'Meta Description: Random 155-character phrase with timestamp', 'bulk-yoast-meta-updater' ); ?></li>
								<li><?php esc_html_e( 'Focus Keyphrase: "Keyphrase (post ID)"', 'bulk-yoast-meta-updater' ); ?></li>
								<li><strong><?php esc_html_e( 'All changes are logged with before/after values', 'bulk-yoast-meta-updater' ); ?></strong></li>
							</ul>
							<p style="margin-top: 0.5em;"><em><?php esc_html_e( 'This will overwrite existing Yoast SEO or All in One SEO data. Use only on test posts! Check Recent Jobs below to view detailed logs.', 'bulk-yoast-meta-updater' ); ?></em></p>
						</div>
					</div>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="test_post_types"><?php esc_html_e( 'Post Types', 'bulk-yoast-meta-updater' ); ?></label>
						</th>
						<td>
							<select name="test_post_types[]" id="test_post_types" multiple size="5">
								<?php
								$post_types = bymu_get_post_type_options();
								foreach ( $post_types as $type => $label ) {
									$selected = in_array( $type, [ 'post', 'page' ], true ) ? ' selected' : '';
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $type ),
										$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select post types to test (hold Ctrl/Cmd to select multiple).', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="test_limit"><?php esc_html_e( 'Number of Posts', 'bulk-yoast-meta-updater' ); ?></label>
						</th>
						<td>
							<input type="number" name="test_limit" id="test_limit" 
								value="5" min="1" max="50" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Number of random posts to update (1-50). Uses most recent posts.', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary" id="bymu-test-btn">
						<?php esc_html_e( 'Apply Test SEO Data', 'bulk-yoast-meta-updater' ); ?>
					</button>
					<span id="bymu-test-loading" class="spinner" style="float: none; display: none;"></span>
					<span id="bymu-test-status" style="margin-left: 10px; font-weight: 500;"></span>
				</p>
				</div>
			</div>
					<?php endif; ?>

			<!-- Export Section -->
			<div id="bymu-export-section" class="bymu-section">
				<div class="bymu-section-header">
					<h2><?php esc_html_e( 'Export Current Meta Data', 'bulk-yoast-meta-updater' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: Active SEO provider label */
							esc_html__( 'Export current %s meta data to CSV. The exported CSV matches the import schema—edit columns 3-5 and re-import directly.', 'bulk-yoast-meta-updater' ),
							esc_html( $provider_label )
						);
						?>
					</p>
				</div>
				<div class="bymu-section-body">
				<table class="form-table bymu-export-layout">
					<tr>
						<td class="bymu-export-col">
							<label for="export_post_types"><strong><?php esc_html_e( 'Post Types', 'bulk-yoast-meta-updater' ); ?></strong></label><br />
							<select name="export_post_types[]" id="export_post_types" multiple size="5">
								<?php
								foreach ( $post_types as $type => $label ) {
									$selected      = in_array( $type, [ 'post', 'page' ], true ) ? ' selected' : '';
									$count         = isset( $post_type_meta[ $type ]['total'] ) ? $post_type_meta[ $type ]['total'] : 0;
									$display_label = sprintf(
										/* translators: 1: Post type label 2: Count */
										__( '%1$s (%2$s)', 'bulk-yoast-meta-updater' ),
										$label,
										number_format_i18n( $count )
									);
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $type ),
										$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										esc_html( $display_label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select which post types to export (hold Ctrl/Cmd to select multiple).', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</td>
						<td class="bymu-export-col">
							<label for="export_post_status"><strong><?php esc_html_e( 'Post Status', 'bulk-yoast-meta-updater' ); ?></strong></label><br />
							<select name="export_post_status[]" id="export_post_status" multiple size="5">
								<option value="publish" selected><?php esc_html_e( 'Published', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="private"><?php esc_html_e( 'Private', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending', 'bulk-yoast-meta-updater' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select which post statuses to include in export.', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td colspan="2">
							<label for="export_limit"><strong><?php esc_html_e( 'Limit', 'bulk-yoast-meta-updater' ); ?></strong></label><br />
							<input type="number" name="export_limit" id="export_limit" 
								value="0" min="0" max="10000" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Maximum number of posts to export (0 = all posts).', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td></td>
						<td>
							<label for="export_short_only"><strong><?php esc_html_e( 'Only short or empty metas', 'bulk-yoast-meta-updater' ); ?></strong></label><br />
							<label style="display: inline-flex; align-items: center; gap: 6px;">
								<input type="checkbox" name="export_short_only" id="export_short_only" value="1" />
								<span><?php esc_html_e( 'Only include posts where the meta title or description is empty or under 30 characters.', 'bulk-yoast-meta-updater' ); ?></span>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="bymu-export-btn">
						<?php esc_html_e( 'Export to CSV', 'bulk-yoast-meta-updater' ); ?>
					</button>
					<span id="bymu-export-loading" class="spinner" style="float: none; margin-left: 6px;"></span>
					<span id="bymu-export-status" style="margin-left: 10px; font-weight: 500;"></span>
				</p>
				<p class="description" id="bymu-export-estimate"></p>
				</div>
				<script type="application/json" id="bymu-export-counts">
				<?php
				echo wp_json_encode(
					[
						'post_types' => $post_type_meta,
						'statuses'   => $tracked_statuses,
					] 
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
																		</script>
			</div>
				</div>
			</div><!-- End Main Layout -->

			<!-- Recent Jobs - Full Width -->
			<div id="bymu-recent-jobs-section" class="bymu-section">
				<div class="bymu-section-header">
					<h2><?php esc_html_e( 'Recent Jobs', 'bulk-yoast-meta-updater' ); ?></h2>
					<p><?php esc_html_e( 'Your last 10 bulk update jobs with viewable logs.', 'bulk-yoast-meta-updater' ); ?></p>
				</div>
				<div class="bymu-section-body">
					<?php $this->render_recent_jobs(); ?>
				</div>
			</div>

			<!-- Log Viewer Modal -->
			<div id="bymu-log-modal" class="bymu-modal" style="display: none;">
				<div class="bymu-modal-overlay"></div>
				<div class="bymu-modal-container">
					<div class="bymu-modal-header">
						<h2 id="bymu-modal-title"><?php esc_html_e( 'View Log', 'bulk-yoast-meta-updater' ); ?></h2>
						<button type="button" class="bymu-modal-close" aria-label="<?php esc_attr_e( 'Close', 'bulk-yoast-meta-updater' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="bymu-modal-body">
						<pre id="bymu-log-content" style="background: #f5f5f5; padding: 20px; border-radius: 4px; overflow-x: auto; max-height: 600px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6;"></pre>
					</div>
					<div class="bymu-modal-footer">
						<button type="button" class="button button-primary bymu-modal-close">
							<?php esc_html_e( 'Close', 'bulk-yoast-meta-updater' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render recent jobs table.
	 */
	private function render_recent_jobs() {
		$recent_jobs = $this->get_recent_jobs_cached( 10 );

		if ( empty( $recent_jobs ) ) {
			echo '<p>' . esc_html__( 'No recent jobs found.', 'bulk-yoast-meta-updater' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'File', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Total Rows', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Skipped', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Errors', 'bulk-yoast-meta-updater' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bulk-yoast-meta-updater' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $job->created_at ) ); ?></td>
						<td><?php echo esc_html( $job->file_name ); ?></td>
						<td>
							<?php
							$status_class = 'completed' === $job->status ? 'success' : ( 'failed' === $job->status || 'interrupted' === $job->status ? 'error' : 'info' );
							printf(
								'<span class="bymu-status bymu-status-%s">%s</span>',
								esc_attr( $status_class ),
								esc_html( ucfirst( $job->status ) )
							);
							?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $job->total_rows ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $job->updated_rows ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $job->skipped_rows ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $job->error_rows ) ); ?></td>
						<td>
							<button type="button" class="button button-small bymu-view-log-btn" 
								data-job-id="<?php echo esc_attr( $job->id ); ?>">
								<?php esc_html_e( 'View', 'bulk-yoast-meta-updater' ); ?>
							</button>
							<button type="button" class="button button-small bymu-download-log-btn" 
								data-job-id="<?php echo esc_attr( $job->id ); ?>" 
								data-format="csv">
								<?php esc_html_e( 'CSV', 'bulk-yoast-meta-updater' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX handler for parsing CSV.
	 */
	public function ajax_parse_csv() {
		// Security checks.
		check_ajax_referer( 'bymu_parse_csv', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		// Validate file upload.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validation happens in validate_upload().
		if ( ! isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'bulk-yoast-meta-updater' ) );
		}

		$parser = new Bulk_Yoast_Meta_Updater_CSV_Parser();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validation happens in validate_upload().
		$file_path = $parser->validate_upload( $_FILES['csv_file'] );

		if ( is_wp_error( $file_path ) ) {
			wp_send_json_error( $file_path->get_error_message() );
		}

		// Parse CSV.
		$parsed = $parser->parse( $file_path );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		// Get post types filter.
		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [];

		// Build preview diff.
		$diff_builder = new Bulk_Yoast_Meta_Updater_Diff_Builder();
		$preview      = $diff_builder->build_batch( $parsed['rows'], $post_types );

		// Debug: Add raw CSV data to response for troubleshooting.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$preview['debug_raw_csv'] = array_slice( $parsed['rows'], 0, 2 ); // First 2 rows
		}

		// Generate job hash for this upload.
		$job_hash = bymu_generate_job_hash();

		// Store preview data in transient (1 hour expiry).
		// For large previews (>5000 rows), create temp job in database.
		$preview_size       = strlen( wp_json_encode( $preview ) );
		$max_transient_size = 500000; // ~500KB safe limit.

		if ( $preview_size > $max_transient_size || count( $parsed['rows'] ) > 1000 ) {
			// Get sanitized filename.
			$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) ) : 'unknown.csv';

			// Store in database instead.
			$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
				[
					'job_hash'   => $job_hash,
					'file_name'  => $file_name,
					'status'     => 'preview',
					'total_rows' => $parsed['count'],
					'settings'   => [
						'post_types'   => $post_types,
						'preview_data' => $preview,
					],
				]
			);

			if ( ! $job_id ) {
				wp_send_json_error( __( 'Failed to store preview data. Please try a smaller CSV file.', 'bulk-yoast-meta-updater' ) );
			}
		} else {
			// Store in transient (faster for small files).
			set_transient( 'bymu_preview_' . $job_hash, $preview, HOUR_IN_SECONDS );
		}

		// Get sanitized filename.
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) ) : 'unknown.csv';

		// Add warnings from CSV parser if any.
		$response_data = [
			'job_hash'   => $job_hash,
			'file_name'  => $file_name,
			'preview'    => $preview,
			'post_types' => $post_types,
		];

		if ( ! empty( $parsed['warnings'] ) ) {
			$response_data['warnings'] = $parsed['warnings'];
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Retrieve cached list of recent jobs for the current user.
	 *
	 * @param int $limit Number of jobs.
	 * @return array
	 */
	private function get_recent_jobs_cached( $limit = 10 ) {
		$user_id     = get_current_user_id();
		$limit       = max( 1, absint( $limit ) );
		$cache_key   = sprintf( 'bymu_recent_jobs_%d_%d', $user_id, $limit );
		$cached_jobs = get_transient( $cache_key );

		if ( false !== $cached_jobs ) {
			return $cached_jobs;
		}

		$jobs = Bulk_Yoast_Meta_Updater_Logger::get_recent_jobs( $user_id, $limit );

		set_transient( $cache_key, $jobs, 30 );

		return $jobs;
	}

	/**
	 * AJAX handler for downloading logs.
	 */
	public function ajax_download_log() {
		// Security checks.
		check_ajax_referer( 'bymu_download_log', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		$format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'csv';

		if ( ! $job_id ) {
			wp_die( esc_html__( 'Invalid job ID.', 'bulk-yoast-meta-updater' ) );
		}

		// Get job and actions.
		$job     = Bulk_Yoast_Meta_Updater_Logger::get_job( $job_id );
		$actions = Bulk_Yoast_Meta_Updater_Logger::get_job_actions( $job_id );

		if ( ! $job ) {
			wp_die( esc_html__( 'Job not found.', 'bulk-yoast-meta-updater' ) );
		}

		// Generate log content.
		if ( 'csv' === $format ) {
			$this->generate_csv_log( $job, $actions );
		} else {
			$this->generate_txt_log( $job, $actions );
		}
	}

	/**
	 * Generate CSV log file.
	 *
	 * @param object $job     Job object.
	 * @param array  $actions Actions array.
	 */
	private function generate_csv_log( $job, $actions ) {
		$filename = 'bymu-log-' . $job->id . '-' . gmdate( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		// Headers.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv( $output, [ 'Row', 'Post ID', 'URL', 'Field', 'Old Value', 'New Value', 'Status', 'Message', 'Timestamp' ] );

		// Data rows.
		foreach ( $actions as $action ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$output,
				[
					$action->csv_row,
					$action->post_id,
					$action->url,
					$action->field,
					$action->old_value,
					$action->new_value,
					$action->status,
					$action->message,
					$action->created_at,
				]
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Generate TXT log file.
	 *
	 * @param object $job     Job object.
	 * @param array  $actions Actions array.
	 */
	private function generate_txt_log( $job, $actions ) {
		$filename = 'bymu-log-' . $job->id . '-' . gmdate( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Cache-Control: no-cache, must-revalidate' );

		echo $this->format_txt_log( $job, $actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in function

		exit;
	}

	/**
	 * Format TXT log content (used for both download and viewing).
	 *
	 * @param object $job     Job object.
	 * @param array  $actions Actions array.
	 * @return string Formatted log content.
	 */
	private function format_txt_log( $job, $actions ) {
		ob_start();

		echo "Bulk SEO Meta Updater - Job Log\n";
		echo esc_html( str_repeat( '=', 50 ) ) . "\n\n";
		echo 'Job ID: ' . absint( $job->id ) . "\n";
		echo 'Job Hash: ' . esc_html( $job->job_hash ) . "\n";
		echo 'File: ' . esc_html( $job->file_name ) . "\n";
		echo 'Created: ' . esc_html( $job->created_at ) . "\n";
		echo 'Status: ' . esc_html( $job->status ) . "\n\n";

		echo "Summary:\n";
		echo '- Total Rows: ' . absint( $job->total_rows ) . "\n";
		echo '- Processed: ' . absint( $job->processed_rows ) . "\n";
		echo '- Updated: ' . absint( $job->updated_rows ) . "\n";
		echo '- Skipped: ' . absint( $job->skipped_rows ) . "\n";
		echo '- Errors: ' . absint( $job->error_rows ) . "\n\n";

		echo esc_html( str_repeat( '=', 50 ) ) . "\n";
		echo "Detailed Actions:\n";
		echo esc_html( str_repeat( '=', 50 ) ) . "\n\n";

		if ( empty( $actions ) ) {
			echo "No actions found for this job.\n";
			echo "This may indicate a database issue or the actions were not logged.\n\n";
		} else {
			foreach ( $actions as $action ) {
				echo 'Row ' . absint( $action->csv_row ) . ' | Post ' . absint( $action->post_id );
				if ( ! empty( $action->field ) ) {
					echo ' | Field: ' . esc_html( $action->field );
				}
				echo ' | Status: ' . esc_html( strtoupper( $action->status ) ) . "\n";
				
				if ( ! empty( $action->old_value ) || ! empty( $action->new_value ) ) {
					echo '  Old: ' . esc_html( $action->old_value ) . "\n";
					echo '  New: ' . esc_html( $action->new_value ) . "\n";
				}
				
				if ( ! empty( $action->message ) ) {
					echo '  Message: ' . esc_html( $action->message ) . "\n";
				}
				
				echo "\n";
			}
		}

		return ob_get_clean();
	}

	/**
	 * AJAX handler for viewing log in admin.
	 */
	public function ajax_view_log() {
		// Start with a clean slate - clear any existing output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		
		// Start fresh buffer for JSON only.
		ob_start();
		
		try {
			// Security checks.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bymu_view_log' ) ) {
				wp_send_json_error( __( 'Invalid security token.', 'bulk-yoast-meta-updater' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
			}

			$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

			if ( ! $job_id ) {
				wp_send_json_error( __( 'Invalid job ID.', 'bulk-yoast-meta-updater' ) );
			}

			// Check if Logger class exists.
			if ( ! class_exists( 'Bulk_Yoast_Meta_Updater_Logger' ) ) {
				wp_send_json_error( __( 'Logger class not found. Please refresh the page.', 'bulk-yoast-meta-updater' ) );
			}

			// Get job and actions.
			$job     = Bulk_Yoast_Meta_Updater_Logger::get_job( $job_id );
			$actions = Bulk_Yoast_Meta_Updater_Logger::get_job_actions( $job_id );

			if ( ! $job ) {
				ob_clean();
				wp_send_json_error( __( 'Job not found in database.', 'bulk-yoast-meta-updater' ) );
			}

			// Check if we got actions.
			if ( empty( $actions ) ) {
				// Log for debugging.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BYMU: No actions found for job_id ' . $job_id );
			}

			// Format the log content.
			$log_content = $this->format_txt_log( $job, $actions );

			// Debug log the content length.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU View Log: Job ID ' . $job_id . ', Actions count: ' . count( $actions ) . ', Content length: ' . strlen( $log_content ) );

			// Clean buffer and send JSON.
			ob_clean();
			
			wp_send_json_success(
				[
					'content'      => $log_content,
					'job_id'       => absint( $job->id ),
					'file_name'    => sanitize_text_field( $job->file_name ),
					'created'      => sanitize_text_field( $job->created_at ),
					'action_count' => count( $actions ),
				]
			);
		} catch ( Exception $e ) {
			// Clean buffer and send error.
			if ( ob_get_level() ) {
				ob_clean();
			}
			wp_send_json_error( 'Exception: ' . $e->getMessage() );
		}
		
		exit;
	}

	/**
	 * AJAX handler for test SEO updates.
	 */
	public function ajax_test_seo_updates() {
		// Security checks.
		check_ajax_referer( 'bymu_test_seo', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		if ( ! bymu_is_test_mode_enabled() ) {
			wp_send_json_error(
				__( 'Test mode is disabled. Visit the plugin settings screen with ?testmode=1 to enable these tools temporarily.', 'bulk-yoast-meta-updater' )
			);
		}

		// Get parameters.
		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [ 'post' ];
		$limit      = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;
		$limit      = min( $limit, 50 ); // Cap at 50.

		// Validate post types.
		$valid_types = get_post_types( [ 'public' => true ] );
		$post_types  = array_intersect( $post_types, array_keys( $valid_types ) );

		if ( empty( $post_types ) ) {
			wp_send_json_error( __( 'No valid post types selected.', 'bulk-yoast-meta-updater' ) );
		}

		// Get recent posts.
		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];

		$query    = new WP_Query( $args );
		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			wp_send_json_error( __( 'No posts found matching criteria.', 'bulk-yoast-meta-updater' ) );
		}

		// Apply test SEO data with logging.
		$results = bymu_apply_test_seo_data( $post_ids );

		if ( $results['success'] > 0 ) {
			$message = sprintf(
				/* translators: 1: Success count, 2: Failed count */
				__( 'Test complete! Updated %1$d posts successfully. %2$d failed.', 'bulk-yoast-meta-updater' ),
				$results['success'],
				$results['failed']
			);

			// Add note about viewing logs.
			if ( $results['job_id'] ) {
				$message .= ' ' . __( 'View logs below in Recent Jobs.', 'bulk-yoast-meta-updater' );
			}

			wp_send_json_success(
				[
					'message'  => $message,
					'success'  => $results['success'],
					'failed'   => $results['failed'],
					'errors'   => $results['errors'],
					'post_ids' => $post_ids,
					'job_id'   => $results['job_id'],
				]
			);
		} else {
			wp_send_json_error(
				sprintf(
					/* translators: %s: Error details */
					__( 'All test updates failed. Errors: %s', 'bulk-yoast-meta-updater' ),
					implode( ', ', $results['errors'] )
				)
			);
		}
	}

	/**
	 * AJAX handler for exporting current meta data.
	 */
	public function ajax_export_meta() {
		$params = $this->prepare_export_params();
		$this->perform_meta_export( $params );
	}

	/**
	 * Validate request inputs and build export parameters.
	 *
	 * @return array
	 */
	private function prepare_export_params() {
		check_ajax_referer( 'bymu_export_meta', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$post_types  = isset( $_GET['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_GET['post_types'] ) ) : [ 'post', 'page' ];
		$post_status = isset( $_GET['post_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_GET['post_status'] ) ) : [ 'publish' ];
		$limit       = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 0;
		$short_only  = isset( $_GET['short_only'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['short_only'] ) );

		$valid_types = get_post_types( [ 'public' => true ] );
		$post_types  = array_intersect( $post_types, array_keys( $valid_types ) );

		if ( empty( $post_types ) ) {
			wp_die( esc_html__( 'No valid post types selected.', 'bulk-yoast-meta-updater' ) );
		}

		return [
			'post_types'  => $post_types,
			'post_status' => ! empty( $post_status ) ? $post_status : [ 'publish' ],
			'limit'       => $limit,
			'short_only'  => $short_only,
			'filename'    => 'yoast-meta-export-' . gmdate( 'Y-m-d-His' ) . '.csv',
		];
	}

	/**
	 * Stream CSV export using prepared parameters.
	 *
	 * @param array $params Export params.
	 * @return void
	 */
	private function perform_meta_export( $params ) {
		$this->send_csv_headers( $params['filename'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );
		$this->write_export_header_row( $output );

		$paged          = 1;
		$per_page       = 100;
		$total_exported = 0;
		$max_export     = $params['limit'] > 0 ? $params['limit'] : PHP_INT_MAX;

		while ( $total_exported < $max_export ) {
			$stop_loop = false;
			$query     = new WP_Query(
				[
					'post_type'              => $params['post_types'],
					'post_status'            => $params['post_status'],
					'posts_per_page'         => min( $per_page, $max_export - $total_exported ),
					'paged'                  => $paged,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				]
			);

			if ( ! $query->have_posts() ) {
				break;
			}

			if ( ! empty( $query->posts ) ) {
				bymu_prime_current_yoast_meta( wp_list_pluck( $query->posts, 'ID' ) );
			}

			foreach ( $query->posts as $post ) {
				$post_id    = $post->ID;
				$yoast_meta = bymu_get_current_yoast_meta( $post_id );

				if ( $params['short_only'] && ! bymu_meta_is_short_or_empty( $yoast_meta['meta_title'], $yoast_meta['meta_description'], 30 ) ) {
					continue;
				}

				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
				fputcsv(
					$output,
					[
						$post_id,
						get_permalink( $post_id ),
						$yoast_meta['meta_title'],
						$yoast_meta['meta_description'],
						isset( $yoast_meta['meta_keyword'] ) ? $yoast_meta['meta_keyword'] : '',
						$post->post_title,
						$post->post_type,
						$post->post_status,
					]
				);

				++$total_exported;

				if ( $total_exported >= $max_export ) {
					$stop_loop = true;
					break;
				}
			}

			wp_reset_postdata();

			if ( $stop_loop ) {
				break;
			}

			++$paged;
		}

		if ( 0 === $total_exported ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $output );
			wp_die( esc_html__( 'No posts found matching the criteria.', 'bulk-yoast-meta-updater' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Send CSV headers.
	 *
	 * @param string $filename Filename.
	 * @return void
	 */
	private function send_csv_headers( $filename ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Cache-Control: no-cache, must-revalidate' );
	}

	/**
	 * Output CSV column headers.
	 *
	 * @param resource $output Output handle.
	 * @return void
	 */
	private function write_export_header_row( $output ) {
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$output,
			[
				'post_id',
				'url',
				'meta_title',
				'meta_description',
				'focus_keyword',
				'post_title',
				'post_type',
				'post_status',
			]
		);
	}
}

