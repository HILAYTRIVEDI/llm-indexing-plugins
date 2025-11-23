<?php
/**
 * Batch Runner Class
 *
 * Handles batch processing of meta updates via AJAX.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Batch_Runner
 */
class Bulk_Yoast_Meta_Updater_Batch_Runner {

	/**
	 * AJAX handler for processing a batch.
	 */
	public function ajax_process_batch() {
		// Security checks.
		check_ajax_referer( 'bymu_process_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		// Check if this is a completion request.
		$is_complete = isset( $_POST['complete'] ) && (bool) sanitize_text_field( wp_unslash( $_POST['complete'] ) );
		$job_hash    = isset( $_POST['job_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['job_hash'] ) ) : '';

		if ( $is_complete && ! empty( $job_hash ) ) {
			// Mark job as complete.
			$success = $this->complete_job( $job_hash );
			
			if ( $success ) {
				wp_send_json_success( [ 'message' => __( 'Job marked as completed.', 'bulk-yoast-meta-updater' ) ] );
			} else {
				wp_send_json_error( __( 'Failed to mark job as completed.', 'bulk-yoast-meta-updater' ) );
			}
			return;
		}

		// Get parameters for batch processing.
		$batch_rows   = isset( $_POST['batch_rows'] ) ? json_decode( wp_unslash( $_POST['batch_rows'] ), true ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$batch_number = isset( $_POST['batch_number'] ) ? absint( $_POST['batch_number'] ) : 0;
		$post_types   = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [];

		// Validate.
		if ( empty( $job_hash ) || empty( $batch_rows ) ) {
			wp_send_json_error( __( 'Invalid batch data.', 'bulk-yoast-meta-updater' ) );
		}

		// Get or create job.
		$job = Bulk_Yoast_Meta_Updater_Logger::get_job_by_hash( $job_hash );
		
		if ( ! $job && 1 === $batch_number ) {
			// Create new job for first batch.
			$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
				[
					'job_hash'   => $job_hash,
					'file_name'  => isset( $_POST['file_name'] ) ? sanitize_file_name( wp_unslash( $_POST['file_name'] ) ) : 'upload.csv',
					'status'     => 'processing',
					'total_rows' => isset( $_POST['total_rows'] ) ? absint( $_POST['total_rows'] ) : 0,
					'settings'   => [
						'post_types' => $post_types,
					],
				]
			);

			if ( ! $job_id ) {
				wp_send_json_error( __( 'Failed to create job log.', 'bulk-yoast-meta-updater' ) );
			}

			$job = Bulk_Yoast_Meta_Updater_Logger::get_job( $job_id );
		}

		if ( ! $job ) {
			wp_send_json_error( __( 'Job not found.', 'bulk-yoast-meta-updater' ) );
		}

		// Process batch.
		$result = $this->process_batch( $job->id, $batch_rows, $post_types );

		// Update job stats.
		Bulk_Yoast_Meta_Updater_Logger::update_job(
			$job->id,
			[
				'processed_rows' => $job->processed_rows + $result['processed'],
				'updated_rows'   => $job->updated_rows + $result['updated'],
				'skipped_rows'   => $job->skipped_rows + $result['skipped'],
				'error_rows'     => $job->error_rows + $result['errors'],
			]
		);

		wp_send_json_success( $result );
	}

	/**
	 * Process a batch of rows.
	 *
	 * @param int   $job_id     Job ID.
	 * @param array $rows       Batch rows.
	 * @param array $post_types Allowed post types.
	 * @return array Processing results.
	 */
	public function process_batch( $job_id, $rows, $post_types = [] ) {
		$settings = bymu_get_settings();
		$url_mode = isset( $settings['url_mode'] ) ? $settings['url_mode'] : 'lenient'; // Default: path-only.
		
		$resolver     = new Bulk_Yoast_Meta_Updater_Resolver( $post_types, $url_mode );
		$diff_builder = new Bulk_Yoast_Meta_Updater_Diff_Builder();

		$stats = [
			'processed' => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
		];

		$updated_posts = []; // Track posts we've updated (for reindex throttling).

		$post_ids_to_prime = [];
		foreach ( $rows as $row ) {
			if ( ! empty( $row['post_id'] ) ) {
				$post_ids_to_prime[] = absint( $row['post_id'] );
			}
		}
		bymu_prime_current_yoast_meta( $post_ids_to_prime );

		foreach ( $rows as $row ) {
			$row_number = isset( $row['_row_number'] ) ? $row['_row_number'] : 0;
			++$stats['processed'];

			// Resolve post ID.
			$post_id = $resolver->resolve( $row );

			if ( is_wp_error( $post_id ) ) {
				// Log error.
				Bulk_Yoast_Meta_Updater_Logger::log_action(
					$job_id,
					[
						'csv_row' => $row_number,
						'post_id' => 0,
						'url'     => isset( $row['url'] ) ? $row['url'] : '',
						'status'  => 'error',
						'message' => $post_id->get_error_message(),
					]
				);
				++$stats['errors'];
				continue;
			}

			// Build diff to see what will change.
			$diff = $diff_builder->build( $post_id, $row );

			if ( ! $diff['has_changes'] ) {
				// Log skip.
				Bulk_Yoast_Meta_Updater_Logger::log_action(
					$job_id,
					[
						'csv_row' => $row_number,
						'post_id' => $post_id,
						'status'  => 'skipped',
						'message' => __( 'No changes needed.', 'bulk-yoast-meta-updater' ),
					]
				);
				++$stats['skipped'];
				continue;
			}

			// Apply changes.
			$update_result = $this->apply_changes( $post_id, $diff['changes'] );

			if ( is_wp_error( $update_result ) ) {
				// Log error.
				Bulk_Yoast_Meta_Updater_Logger::log_action(
					$job_id,
					[
						'csv_row' => $row_number,
						'post_id' => $post_id,
						'status'  => 'error',
						'message' => $update_result->get_error_message(),
					]
				);
				++$stats['errors'];
				continue;
			}

			// Log each field update.
			foreach ( $diff['changes'] as $field => $change ) {
				if ( $change['will_change'] ) {
					Bulk_Yoast_Meta_Updater_Logger::log_action(
						$job_id,
						[
							'csv_row'   => $row_number,
							'post_id'   => $post_id,
							'field'     => $field,
							'old_value' => $change['current'],
							'new_value' => $change['new'],
							'status'    => 'ok',
							'message'   => implode( ', ', $diff['warnings'] ),
						]
					);
				}
			}

			++$stats['updated'];

			// Track post for reindex (only once per post).
			if ( ! in_array( $post_id, $updated_posts, true ) ) {
				$updated_posts[] = $post_id;
			}

			// Throttle if configured.
			if ( $settings['throttle_delay_ms'] > 0 ) {
				usleep( $settings['throttle_delay_ms'] * 1000 );
			}
		}

		// Trigger Yoast reindex for updated posts.
		foreach ( $updated_posts as $post_id ) {
			bymu_trigger_yoast_reindex( $post_id );
		}

		return $stats;
	}

	/**
	 * Apply meta changes to a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $changes Changes from diff builder.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	private function apply_changes( $post_id, $changes ) {
		$provider = bymu_get_active_seo_provider();

		if ( ! $provider ) {
			return new WP_Error(
				'seo_provider_missing',
				__( 'No supported SEO plugin is currently active.', 'bulk-yoast-meta-updater' )
			);
		}

		$values = [];

		foreach ( $changes as $field => $change ) {
			if ( ! $change['will_change'] ) {
				continue;
			}

			$values[ $field ] = $change['new'];
		}

		if ( empty( $values ) ) {
			return true;
		}

		return $provider->update_meta( $post_id, $values );
	}

	/**
	 * Complete a job.
	 *
	 * @param string $job_hash Job hash.
	 * @return bool True on success.
	 */
	public function complete_job( $job_hash ) {
		$job = Bulk_Yoast_Meta_Updater_Logger::get_job_by_hash( $job_hash );

		if ( ! $job ) {
			return false;
		}

		return Bulk_Yoast_Meta_Updater_Logger::update_job(
			$job->id,
			[
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			]
		);
	}
}
