<?php
/**
 * AI Updates Page
 *
 * Handles the AI-powered SEO generation interface.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_AI_Updates_Page
 */
class Bulk_Yoast_Meta_Updater_AI_Updates_Page {

	/**
	 * Render the AI Updates page.
	 */
	public function render() {
		$meta_saved = null;
		if ( isset( $_GET['bymu_meta_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_saved = absint( wp_unslash( $_GET['bymu_meta_saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		?>
		<div class="wrap bymu-wrap">
			<!-- Header -->
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'AI SEO Updates', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle">
								<?php esc_html_e( 'Generate and refine AI-powered titles, descriptions, and keyphrases before saving.', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</div>
					</div>
					<div class="bymu-header-actions">
						<a class="button button-primary bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings' ) ); ?>">
							<?php esc_html_e( 'AI Settings', 'bulk-yoast-meta-updater' ); ?>
						</a>
						<a class="button bymu-button-ghost bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings&tab=documentation' ) ); ?>">
							<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php bymu_render_admin_nav( 'ai-updates' ); ?>

			<?php if ( null !== $meta_saved ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: Number of posts updated */
							esc_html( _n( 'Metadata updated for %d post.', 'Metadata updated for %d posts.', $meta_saved, 'bulk-yoast-meta-updater' ) ),
							intval( $meta_saved )
						);
						?>
					</p>
				</div>
				<?php
				$clean_url = remove_query_arg( 'bymu_meta_saved' );
				?>
				<script>
					(function() {
						try {
							var newUrl = <?php echo wp_json_encode( esc_url_raw( $clean_url ) ); ?>;
							if (newUrl) {
								window.history.replaceState({}, document.title, newUrl);
							}
						} catch (e) {}
					})();
				</script>
			<?php endif; ?>

			<!-- Content -->
			<?php $this->render_ai_interface(); ?>
		</div>
		<?php
	}

	/**
	 * Render AI Generation interface.
	 */
	private function render_ai_interface() {
		$settings    = bymu_get_settings();
		$has_api_key = ! empty( $settings['gemini_api_key'] );

		if ( ! $has_api_key ) {
			?>
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-body">
					<div class="bymu-alert warning">
						<div class="bymu-alert-icon dashicons dashicons-warning"></div>
						<div class="bymu-alert-content">
							<strong><?php esc_html_e( 'API Key Required', 'bulk-yoast-meta-updater' ); ?></strong>
							<p>
								<?php
								printf(
									/* translators: %s: Link to settings */
									esc_html__( 'To use AI generation, please configure your Google Gemini API key in %s.', 'bulk-yoast-meta-updater' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings' ) ) . '">' . esc_html__( 'Settings', 'bulk-yoast-meta-updater' ) . '</a>'
								);
								?>
							</p>
							<p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Configure API Key', 'bulk-yoast-meta-updater' ); ?>
								</a>
							</p>
						</div>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		?>
		<!-- Instructions -->
		<div class="bymu-section bymu-section-compact">
			<div class="bymu-section-header">
				<h2><?php esc_html_e( 'How It Works', 'bulk-yoast-meta-updater' ); ?></h2>
			</div>
			<div class="bymu-section-body">
					<div class="bymu-info-box">
					<div class="bymu-info-box-icon dashicons dashicons-lightbulb"></div>
					<div class="bymu-info-box-content">
						<ol style="margin: 0; padding-left: 20px;">
							<li><?php esc_html_e( 'Select post types and load posts you want to optimize', 'bulk-yoast-meta-updater' ); ?></li>
							<li><?php esc_html_e( 'Click "Generate" on any row to get AI suggestions for that post', 'bulk-yoast-meta-updater' ); ?></li>
							<li><?php esc_html_e( 'Review the generated title, meta description, and keyphrase', 'bulk-yoast-meta-updater' ); ?></li>
							<li><?php esc_html_e( 'Click "Save" to apply changes to individual posts, or "Save All Changes" for bulk updates', 'bulk-yoast-meta-updater' ); ?></li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<!-- AI Controls -->
		<div class="bymu-section bymu-section-compact">
			<div class="bymu-section-header">
				<h2><?php esc_html_e( 'Load Posts', 'bulk-yoast-meta-updater' ); ?></h2>
			</div>
			<div class="bymu-section-body">
				<div class="bymu-ai-controls">
					<div class="bymu-form-row">
						<div class="bymu-form-group">
							<label for="bymu-ai-post-types">
								<strong><?php esc_html_e( 'Post Types:', 'bulk-yoast-meta-updater' ); ?></strong>
							</label>
							<select id="bymu-ai-post-types" multiple size="3" class="bymu-select">
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
							<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple types', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
						
						<div class="bymu-form-group">
							<label for="bymu-ai-limit">
								<strong><?php esc_html_e( 'Number of Posts:', 'bulk-yoast-meta-updater' ); ?></strong>
							</label>
							<input type="number" id="bymu-ai-limit" value="20" min="1" max="100" class="bymu-input" />
							<p class="description"><?php esc_html_e( 'Maximum: 100 posts', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
						
						<div class="bymu-form-group">
							<label>
								<strong><?php esc_html_e( 'Filter:', 'bulk-yoast-meta-updater' ); ?></strong>
							</label>
							<label style="font-weight: normal; display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" id="bymu-ai-blank-only" value="1" />
								<?php esc_html_e( 'Only show posts with short or blank meta descriptions (<30 chars)', 'bulk-yoast-meta-updater' ); ?>
							</label>
						</div>
						
						<div class="bymu-form-group">
							<label style="visibility: hidden;"><?php esc_html_e( 'Actions', 'bulk-yoast-meta-updater' ); ?></label>
							<button type="button" class="button button-large" id="bymu-ai-load-posts">
								<?php esc_html_e( 'Load Posts', 'bulk-yoast-meta-updater' ); ?>
							</button>

							<button type="button" class="button button-large bymu-button-accent" id="bymu-ai-generate-all" style="display: none;">
								<?php esc_html_e( 'Generate All On Screen', 'bulk-yoast-meta-updater' ); ?>
							</button>
							
							<button type="button" class="button button-secondary button-large" id="bymu-ai-stop-bulk" style="display: none;">
								<?php esc_html_e( 'Stop Bulk Generation', 'bulk-yoast-meta-updater' ); ?>
							</button>
							
							<button type="button" class="button button-primary button-large" id="bymu-ai-save-all" style="display: none;">
								<?php esc_html_e( 'Save All Changes', 'bulk-yoast-meta-updater' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Posts Container -->
		<div id="bymu-ai-posts-container"></div>
		<?php
	}

	/**
	 * AJAX handler for loading posts for AI generation.
	 */
	public function ajax_load_ai_posts() {
		check_ajax_referer( 'bymu_ai_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [ 'post', 'page' ];
		$limit      = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
		$blank_only = isset( $_POST['blank_only'] ) && '1' === $_POST['blank_only'];
		$limit      = min( $limit, 100 );

		$args = [
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => $blank_only ? -1 : $limit, // Get all if filtering.
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true, // Skip pagination count query.
			'update_post_meta_cache' => true, // Warm cache for Yoast meta.
			'update_post_term_cache' => false, // Skip term cache.
		];

		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			$post_ids_to_prime = wp_list_pluck( $query->posts, 'ID' );
			bymu_prime_current_yoast_meta( $post_ids_to_prime );
		}
		$posts = [];
		$count = 0;

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id    = get_the_ID();
			$yoast_meta = bymu_get_current_yoast_meta( $post_id );

			// If filtering for short/blank descriptions, skip posts that exceed threshold.
			if ( $blank_only && ! bymu_is_value_short_or_empty( $yoast_meta['meta_description'], 30 ) ) {
				continue;
			}

			$title = get_the_title();
			if ( '#post_title' === trim( $title ) ) {
				$title = get_the_title( $post_id );
			}

			$posts[] = [
				'id'         => $post_id,
				'title'      => $title,
				'url'        => get_permalink(),
				'type'       => get_post_type(),
				'meta_title' => $yoast_meta['meta_title'],
				'meta_desc'  => $yoast_meta['meta_description'],
				'keyphrase'  => $yoast_meta['focus_keyword'],
			];

			++$count;
			
			// Stop at limit when filtering.
			if ( $blank_only && $count >= $limit ) {
				break;
			}
		}

		wp_reset_postdata();

		wp_send_json_success( [ 'posts' => $posts ] );
	}

	/**
	 * AJAX handler for generating AI suggestions.
	 */
	public function ajax_generate_ai_suggestions() {
		check_ajax_referer( 'bymu_ai_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$job_id   = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$sequence = isset( $_POST['sequence'] ) ? absint( $_POST['sequence'] ) : 0;
		$job      = null;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post ID.', 'bulk-yoast-meta-updater' ) );
		}

		if ( $job_id ) {
			$job = Bulk_Yoast_Meta_Updater_Logger::get_job( $job_id );

			if ( ! $job || get_current_user_id() !== (int) $job->user_id ) {
				$job_id = 0;
				$job    = null;
			}
		}

		$next_row_index = $sequence ? $sequence : ( $job ? (int) $job->processed_rows + 1 : 0 );

		// Initialize Gemini API.
		$gemini = new Bulk_Yoast_Meta_Updater_Gemini_API();

		if ( ! $gemini->has_api_key() ) {
			wp_send_json_error( __( 'Google Gemini API key not configured.', 'bulk-yoast-meta-updater' ) );
		}

		// Get post content as markdown.
		$markdown = Bulk_Yoast_Meta_Updater_Gemini_API::get_post_markdown( $post_id );

		if ( empty( $markdown ) ) {
			wp_send_json_error( __( 'No content found for this post.', 'bulk-yoast-meta-updater' ) );
		}

		// Generate suggestions for all three fields.
		$title       = $gemini->generate_seo_field( $markdown, 'title' );
		$description = $gemini->generate_seo_field( $markdown, 'description' );
		$keyphrase   = $gemini->generate_seo_field( $markdown, 'keyphrase' );

		// Check for errors.
		$errors         = [];
		$error_codes    = [];
		$error_statuses = [];

		if ( is_wp_error( $title ) ) {
			$errors['title'] = $title->get_error_message();
			$error_codes[]   = $title->get_error_code();
			$data            = $title->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$error_statuses[] = (int) $data['status'];
			}
			$title = '';
		}

		if ( is_wp_error( $description ) ) {
			$errors['description'] = $description->get_error_message();
			$error_codes[]         = $description->get_error_code();
			$data                  = $description->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$error_statuses[] = (int) $data['status'];
			}
			$description = '';
		}

		if ( is_wp_error( $keyphrase ) ) {
			$errors['keyphrase'] = $keyphrase->get_error_message();
			$error_codes[]       = $keyphrase->get_error_code();
			$data                = $keyphrase->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$error_statuses[] = (int) $data['status'];
			}
			$keyphrase = '';
		}

		if ( ! empty( $errors ) ) {
			$error_codes    = array_values( array_unique( array_filter( $error_codes ) ) );
			$error_statuses = array_values( array_unique( array_filter( $error_statuses ) ) );
			$error_messages = [];

			foreach ( $errors as $field_key => $field_message ) {
				$field_key        = is_string( $field_key ) ? $field_key : (string) $field_key;
				$label            = ucwords( str_replace( '_', ' ', $field_key ) );
				$error_messages[] = sprintf( '%s: %s', $label, $field_message );
			}

			$has_rate_limit   = in_array( 'rate_limit', $error_codes, true );
			$has_overload     = in_array( 'service_unavailable', $error_codes, true ) || in_array( 503, $error_statuses, true );
			$friendly_message = implode( '; ', $error_messages );

			if ( $has_overload ) {
				$friendly_message = __( 'Gemini is temporarily overloaded. Please wait a few moments and try again.', 'bulk-yoast-meta-updater' );
			} elseif ( $has_rate_limit ) {
				$friendly_message = __( 'Gemini rate limit reached. Pausing before retrying.', 'bulk-yoast-meta-updater' );
			} elseif ( '' === $friendly_message ) {
				$friendly_message = __( 'Some fields failed to generate.', 'bulk-yoast-meta-updater' );
			}

			if ( $job_id && $job ) {
				Bulk_Yoast_Meta_Updater_Logger::log_action(
					$job_id,
					[
						'csv_row' => $next_row_index,
						'post_id' => $post_id,
						'url'     => get_permalink( $post_id ),
						'field'   => 'ai_generate',
						'status'  => ( $has_rate_limit || $has_overload ) ? 'warning' : 'error',
						'message' => $friendly_message,
					]
				);

				Bulk_Yoast_Meta_Updater_Logger::update_job(
					$job_id,
					[
						'processed_rows' => (int) $job->processed_rows + 1,
						'error_rows'     => (int) $job->error_rows + ( $has_rate_limit || $has_overload ? 0 : 1 ),
						'status'         => 'running',
					]
				);
			}

			// For service-wide issues (overload/rate limit), don't include individual field errors
			// to avoid confusing duplicate messages in the UI.
			$response_data = [
				'message'      => $friendly_message,
				'error_codes'  => $error_codes,
				'error_status' => $error_statuses,
				'friendly_msg' => $friendly_message,
			];

			// Only include individual field errors if it's not a service-wide issue.
			if ( ! $has_overload && ! $has_rate_limit ) {
				$response_data['errors']         = $errors;
				$response_data['friendly_notes'] = $error_messages;
			}

			wp_send_json_error( $response_data );
		}

		if ( $job_id && $job ) {
			$new_value = wp_json_encode(
				array_filter(
					[
						'title'       => $title,
						'description' => $description,
						'keyphrase'   => $keyphrase,
					],
					static function ( $value ) {
						return '' !== $value && null !== $value;
					}
				)
			);

			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row'   => $next_row_index,
					'post_id'   => $post_id,
					'url'       => get_permalink( $post_id ),
					'field'     => 'ai_generate',
					'new_value' => $new_value,
					'status'    => 'ok',
					'message'   => __( 'AI suggestions generated (pending review)', 'bulk-yoast-meta-updater' ),
				]
			);

			Bulk_Yoast_Meta_Updater_Logger::update_job(
				$job_id,
				[
					'processed_rows' => (int) $job->processed_rows + 1,
					'updated_rows'   => (int) $job->updated_rows + 1,
					'status'         => 'running',
				]
			);
		}

		wp_send_json_success(
			[
				'title'       => $title,
				'description' => $description,
				'keyphrase'   => $keyphrase,
			]
		);
	}

	/**
	 * AJAX handler to start a bulk AI generation job.
	 */
	public function ajax_start_ai_bulk_job() {
		check_ajax_referer( 'bymu_ai_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$total_rows = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;

		$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
			[
				'job_hash'   => bymu_generate_job_hash(),
				'file_name'  => 'ai-bulk-' . gmdate( 'Y-m-d-His' ) . '.json',
				'status'     => 'running',
				'total_rows' => $total_rows,
				'settings'   => [
					'type'           => 'ai_bulk_generate',
					'requested_rows' => $total_rows,
				],
			]
		);

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Unable to create log entry for this job.', 'bulk-yoast-meta-updater' ) );
		}

		wp_send_json_success(
			[
				'job_id' => $job_id,
			]
		);
	}

	/**
	 * AJAX handler to finalize a bulk AI generation job.
	 */
	public function ajax_finish_ai_bulk_job() {
		check_ajax_referer( 'bymu_ai_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$job_id    = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$processed = isset( $_POST['processed'] ) ? absint( $_POST['processed'] ) : 0;
		$success   = isset( $_POST['success'] ) ? absint( $_POST['success'] ) : 0;
		$errors    = isset( $_POST['errors'] ) ? absint( $_POST['errors'] ) : 0;
		$total     = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Invalid job ID.', 'bulk-yoast-meta-updater' ) );
		}

		$job = Bulk_Yoast_Meta_Updater_Logger::get_job( $job_id );

		if ( ! $job || get_current_user_id() !== (int) $job->user_id ) {
			wp_send_json_error( __( 'Log entry not found or access denied.', 'bulk-yoast-meta-updater' ) );
		}

		$current_settings = is_array( $job->settings ) ? $job->settings : [];

		$current_settings['summary'] = [
			'success'   => $success,
			'errors'    => $errors,
			'total'     => $total,
			'processed' => $processed,
		];

		$status = $errors > 0 ? 'completed_with_errors' : 'completed';

		Bulk_Yoast_Meta_Updater_Logger::update_job(
			$job_id,
			[
				'status'         => $status,
				'processed_rows' => $processed,
				'updated_rows'   => $success,
				'error_rows'     => $errors,
				'completed_at'   => current_time( 'mysql' ),
				'settings'       => $current_settings,
			]
		);

		wp_send_json_success();
	}

	/**
	 * AJAX handler for saving AI suggestions.
	 */
	public function ajax_save_ai_suggestion() {
		check_ajax_referer( 'bymu_ai_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$keyphrase   = isset( $_POST['keyphrase'] ) ? sanitize_text_field( wp_unslash( $_POST['keyphrase'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post ID.', 'bulk-yoast-meta-updater' ) );
		}

		$provider = bymu_get_active_seo_provider();
		if ( ! $provider ) {
			wp_send_json_error( __( 'No supported SEO provider is active.', 'bulk-yoast-meta-updater' ) );
		}

		$old_meta = bymu_get_current_yoast_meta( $post_id );

		$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
			[
				'job_hash'       => bymu_generate_job_hash(),
				'file_name'      => 'ai-generation-' . gmdate( 'Y-m-d-His' ) . '.csv',
				'status'         => 'completed',
				'total_rows'     => 1,
				'processed_rows' => 1,
				'updated_rows'   => 0,
				'settings'       => [
					'type'    => 'ai_generation',
					'post_id' => $post_id,
				],
			]
		);

		$values        = [];
		$updated_count = 0;

		if ( '' !== $title ) {
			$values['meta_title'] = $title;
		}

		if ( '' !== $description ) {
			$values['meta_description'] = $description;
		}

		if ( '' !== $keyphrase ) {
			$values['focus_keyword'] = $keyphrase;
		}

		if ( ! empty( $values ) ) {
			$result = $provider->update_meta( $post_id, $values );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}

			$permalink = get_permalink( $post_id );

			foreach ( $values as $field => $new_value ) {
				Bulk_Yoast_Meta_Updater_Logger::log_action(
					$job_id,
					[
						'csv_row'   => 1,
						'post_id'   => $post_id,
						'url'       => $permalink,
						'field'     => $field,
						'old_value' => isset( $old_meta[ $field ] ) ? $old_meta[ $field ] : '',
						'new_value' => $new_value,
						'status'    => 'ok',
						'message'   => 'AI-generated via Google Gemini',
					],
				);
				++$updated_count;
			}
		}

		if ( $updated_count > 0 ) {
			Bulk_Yoast_Meta_Updater_Logger::update_job(
				$job_id,
				[
					'updated_rows' => $updated_count,
					'completed_at' => current_time( 'mysql' ),
				]
			);

			bymu_trigger_yoast_reindex( $post_id );
		}

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: Number of fields updated */
					__( 'Successfully updated %d field(s).', 'bulk-yoast-meta-updater' ),
					$updated_count
				),
				'job_id'  => $job_id,
			]
		);
	}
}

