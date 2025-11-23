<?php
/**
 * Import Page Class
 *
 * Handles the CSV import interface with drag-and-drop support.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Import_Page
 */
class Bulk_Yoast_Meta_Updater_Import_Page {

	/**
	 * Render the Import page.
	 */
	public function render() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		$settings = bymu_get_settings();
		?>
		<div class="wrap bymu-wrap">
			<!-- Header -->
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'Import CSV', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle"><?php esc_html_e( 'Upload a CSV to preview and apply bulk SEO meta updates with full logging.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>
					<div class="bymu-header-actions">
						<a class="button button-primary bymu-hero-button" href="<?php echo esc_url( BYMU_PLUGIN_URL . 'assets/sample.csv' ); ?>" download>
							<?php esc_html_e( 'Download Sample CSV', 'bulk-yoast-meta-updater' ); ?>
						</a>
						<a class="button bymu-button-ghost bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings&tab=documentation' ) ); ?>">
							<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php bymu_render_admin_nav( 'import' ); ?>

			<!-- CSV Format Info -->
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-header">
					<h2>📋 <?php esc_html_e( 'CSV Format Requirements', 'bulk-yoast-meta-updater' ); ?></h2>
				</div>
				<div class="bymu-section-body">
					<div class="bymu-info-box">
						<div class="bymu-info-box-icon dashicons dashicons-lightbulb"></div>
						<div class="bymu-info-box-content">
							<p><strong><?php esc_html_e( 'Required Columns (order matters):', 'bulk-yoast-meta-updater' ); ?></strong></p>
							<ol style="margin: 8px 0 0 20px; padding: 0;">
								<li><code>url</code> - <?php esc_html_e( 'Full URL or path to post/page', 'bulk-yoast-meta-updater' ); ?></li>
								<li><code>meta_title</code> - <?php esc_html_e( 'SEO title (max 60 chars recommended)', 'bulk-yoast-meta-updater' ); ?></li>
								<li><code>meta_description</code> - <?php esc_html_e( 'SEO description (max 155 chars recommended)', 'bulk-yoast-meta-updater' ); ?></li>
								<li><code>focus_keyword</code> - <?php esc_html_e( 'Primary focus keyphrase', 'bulk-yoast-meta-updater' ); ?></li>
							</ol>
							<p style="margin-top: 12px;">
								<strong><?php esc_html_e( 'Tip:', 'bulk-yoast-meta-updater' ); ?></strong> 
								<?php
								printf(
									/* translators: %s: Link to export section */
									esc_html__( 'Use the %s feature to generate a properly formatted CSV template.', 'bulk-yoast-meta-updater' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-updater' ) ) . '">' . esc_html__( 'Export', 'bulk-yoast-meta-updater' ) . '</a>'
								);
								?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Upload Section -->
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-header">
					<h2><?php esc_html_e( 'Upload CSV File', 'bulk-yoast-meta-updater' ); ?></h2>
				</div>
				<div class="bymu-section-body">
				<form id="bymu-upload-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'bymu_parse_csv', 'nonce' ); ?>
						
					<!-- Drag and Drop Area -->
					<div id="bymu-drop-zone" class="bymu-upload-area">
						<div class="bymu-upload-icon">📁</div>
						<div class="bymu-upload-text"><?php esc_html_e( 'Drag & Drop CSV file here', 'bulk-yoast-meta-updater' ); ?></div>
						<div class="bymu-upload-or"><?php esc_html_e( 'or', 'bulk-yoast-meta-updater' ); ?></div>
						<button type="button" class="button button-secondary" id="bymu-browse-btn">
							<?php esc_html_e( 'Browse Files', 'bulk-yoast-meta-updater' ); ?>
						</button>
						<input type="file" name="csv_file" id="csv_file" accept=".csv" required style="display: none;" />
					</div>

						<!-- File Info Display -->
						<div id="bymu-file-info" style="display: none; margin-top: 16px;">
							<div class="bymu-file-info">
								<div>
									<span class="bymu-file-name" id="bymu-selected-file"></span>
									<span id="bymu-file-size" style="color: var(--bymu-text-secondary); margin-left: 8px;"></span>
								</div>
								<button type="button" id="bymu-remove-file" class="button button-small" style="color: #d63638;">
									<?php esc_html_e( 'Remove', 'bulk-yoast-meta-updater' ); ?>
								</button>
							</div>
						</div>

						<!-- Settings -->
						<table class="form-table" style="margin-top: 20px;">
							<tr>
								<th scope="row">
									<label for="post_types"><?php esc_html_e( 'Allowed Post Types', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<select name="post_types[]" id="post_types" multiple size="5" style="min-width: 250px;">
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
										<?php esc_html_e( 'Only posts of these types will be processed. URLs pointing to other types will be skipped.', 'bulk-yoast-meta-updater' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary button-large" disabled id="bymu-parse-btn">
								🔍 <?php esc_html_e( 'Parse & Preview', 'bulk-yoast-meta-updater' ); ?>
							</button>
							<span class="spinner" id="bymu-upload-spinner" style="float: none; display: none;"></span>
						</p>
					</form>
				</div>
			</div>

			<!-- Preview Section (populated via AJAX) -->
			<div id="bymu-preview-section"></div>
			
			<!-- Processing Section -->
			<div id="bymu-processing-section"></div>
			
			<!-- Results Section -->
			<div id="bymu-results-section"></div>
		</div>
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
			$preview['debug_raw_csv'] = array_slice( $parsed['rows'], 0, 2 ); // First 2 rows.
		}

		// Generate job hash for this upload.
		$job_hash = bymu_generate_job_hash();

		// Get sanitized filename.
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) ) : 'unknown.csv';

		// Store preview data in transient (1 hour expiry).
		// For large previews (>5000 rows), create temp job in database.
		$preview_size       = strlen( wp_json_encode( $preview ) );
		$max_transient_size = 500000; // ~500KB safe limit.

		if ( $preview_size > $max_transient_size || count( $parsed['rows'] ) > 1000 ) {
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
}

