<?php
/**
 * WP-CLI Command Class
 *
 * Handles WP-CLI commands for bulk operations.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk SEO Meta Updater CLI commands.
 */
class Bulk_Yoast_Meta_Updater_CLI {

	/**
	 * Import CSV file and update Yoast meta.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to CSV file
	 *
	 * [--dry-run]
	 * : Preview changes without applying
	 *
	 * [--post-types=<types>]
	 * : Comma-separated list of post types
	 * ---
	 * default: post,page
	 * ---
	 *
	 * [--batch=<size>]
	 * : Batch size for processing
	 * ---
	 * default: 15
	 * ---
	 *
	 * [--url-mode=<mode>]
	 * : URL resolution mode (lenient = path only, strict = full URL)
	 * ---
	 * default: lenient
	 * options:
	 *   - lenient
	 *   - strict
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Dry run
	 *     wp bymu import seo-updates.csv --dry-run
	 *
	 *     # Execute with custom batch size
	 *     wp bymu import seo-updates.csv --batch=100
	 *
	 *     # Only update posts
	 *     wp bymu import seo-updates.csv --post-types=post
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		list( $file ) = $args;

		// Check if Yoast is active.
		if ( ! Bulk_Yoast_Meta_Updater_Yoast_Checker::is_yoast_active() ) {
			WP_CLI::error( 'Yoast SEO plugin is not active.' );
		}

		// Check file exists.
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "File not found: {$file}" );
		}

		// Get parameters.
		$dry_run    = isset( $assoc_args['dry-run'] );
		$post_types = isset( $assoc_args['post-types'] ) ? explode( ',', $assoc_args['post-types'] ) : [ 'post', 'page' ];
		$batch_size = isset( $assoc_args['batch'] ) ? absint( $assoc_args['batch'] ) : 15;
		$url_mode   = isset( $assoc_args['url-mode'] ) ? $assoc_args['url-mode'] : 'lenient'; // Default: path-only matching.

		// Sanitize post types.
		$post_types = array_map( 'sanitize_text_field', $post_types );

		// Update settings temporarily.
		$original_settings = bymu_get_settings();
		bymu_update_settings( [ 'url_mode' => $url_mode ] );

		WP_CLI::line( 'Validating CSV...' );

		// Parse CSV.
		$parser = new Bulk_Yoast_Meta_Updater_CSV_Parser();
		$parsed = $parser->parse( $file );

		if ( is_wp_error( $parsed ) ) {
			WP_CLI::error( $parsed->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Found %d rows.', $parsed['count'] ) );

		// Build preview.
		WP_CLI::line( 'Building preview...' );
		$diff_builder = new Bulk_Yoast_Meta_Updater_Diff_Builder();
		$preview      = $diff_builder->build_batch( $parsed['rows'], $post_types );

		// Display preview summary.
		WP_CLI::line( '' );
		WP_CLI::line( 'Preview:' );
		WP_CLI::line( sprintf( '  - %d rows OK', $preview['stats']['ok'] ) );
		WP_CLI::line( sprintf( '  - %d rows skipped', $preview['stats']['skip'] ) );
		WP_CLI::line( sprintf( '  - %d rows with warnings', $preview['stats']['warning'] ) );
		WP_CLI::line( sprintf( '  - %d rows with errors', $preview['stats']['error'] ) );
		WP_CLI::line( '' );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run complete. No changes applied.' );
			
			// Show first 10 errors if any.
			if ( $preview['stats']['error'] > 0 ) {
				WP_CLI::line( '' );
				WP_CLI::line( 'Sample errors:' );
				$error_count = 0;
				foreach ( $preview['results'] as $result ) {
					if ( 'error' === $result['status'] ) {
						WP_CLI::line( sprintf( '  Row %d: %s', $result['csv_row'], $result['error'] ) );
						++$error_count;
						if ( $error_count >= 10 ) {
							break;
						}
					}
				}
			}
			
			// Restore settings.
			bymu_update_settings( $original_settings );
			return;
		}

		// Confirm execution.
		if ( 0 === $preview['stats']['ok'] ) {
			WP_CLI::error( 'No rows to process.' );
		}

		WP_CLI::confirm( sprintf( 'Apply changes to %d rows?', $preview['stats']['ok'] ) );

		// Create job log.
		$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
			[
				'file_name'  => basename( $file ),
				'status'     => 'processing',
				'total_rows' => $parsed['count'],
				'settings'   => [
					'post_types' => $post_types,
					'url_mode'   => $url_mode,
					'cli'        => true,
				],
			]
		);

		if ( ! $job_id ) {
			WP_CLI::error( 'Failed to create job log.' );
		}

		// Process batches.
		WP_CLI::line( 'Processing batches...' );
		
		$batch_runner = new Bulk_Yoast_Meta_Updater_Batch_Runner();
		$ok_rows      = array_filter(
			$preview['results'],
			function ( $r ) {
				return 'ok' === $r['status'] || 'warning' === $r['status'];
			} 
		);
		
		$total_batches = ceil( count( $ok_rows ) / $batch_size );
		$stats         = [
			'processed' => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
		];

		// Extract original CSV rows for processing.
		$ok_csv_rows = [];
		foreach ( $ok_rows as $result ) {
			foreach ( $parsed['rows'] as $csv_row ) {
				if ( $csv_row['_row_number'] === $result['csv_row'] ) {
					$ok_csv_rows[] = $csv_row;
					break;
				}
			}
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing', $total_batches );

		for ( $i = 0; $i < $total_batches; $i++ ) {
			$start      = $i * $batch_size;
			$batch_rows = array_slice( $ok_csv_rows, $start, $batch_size );

			$batch_stats = $batch_runner->process_batch( $job_id, $batch_rows, $post_types );

			$stats['processed'] += $batch_stats['processed'];
			$stats['updated']   += $batch_stats['updated'];
			$stats['skipped']   += $batch_stats['skipped'];
			$stats['errors']    += $batch_stats['errors'];

			$progress->tick();
		}

		$progress->finish();

		// Update job status.
		Bulk_Yoast_Meta_Updater_Logger::update_job(
			$job_id,
			[
				'status'         => 'completed',
				'completed_at'   => current_time( 'mysql' ),
				'processed_rows' => $stats['processed'],
				'updated_rows'   => $stats['updated'],
				'skipped_rows'   => $stats['skipped'],
				'error_rows'     => $stats['errors'],
			]
		);

		// Display results.
		WP_CLI::line( '' );
		WP_CLI::success( 'Complete!' );
		WP_CLI::line( sprintf( '  - Processed: %d rows', $stats['processed'] ) );
		WP_CLI::line( sprintf( '  - Updated: %d rows', $stats['updated'] ) );
		WP_CLI::line( sprintf( '  - Skipped: %d rows', $stats['skipped'] ) );
		WP_CLI::line( sprintf( '  - Errors: %d rows', $stats['errors'] ) );
		WP_CLI::line( sprintf( 'Log saved to database (Job #%d).', $job_id ) );

		// Restore settings.
		bymu_update_settings( $original_settings );
	}
}

// Register command.
WP_CLI::add_command( 'bymu', 'Bulk_Yoast_Meta_Updater_CLI' );
