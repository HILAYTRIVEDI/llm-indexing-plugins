<?php
/**
 * Diff Builder Class
 *
 * Compares current and new meta values and builds change diffs.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Diff_Builder
 */
class Bulk_Yoast_Meta_Updater_Diff_Builder {

	/**
	 * Build diff for a post.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $new_values New meta values from CSV row.
	 * @return array Diff data.
	 */
	public function build( $post_id, $new_values ) {
		// Get current Yoast meta.
		$current_meta = bymu_get_current_yoast_meta( $post_id );

		$fields = [
			'meta_title'       => 'title',
			'meta_description' => 'description',
			'focus_keyword'    => 'keyword',
		];

		// Debug: Log the input data for troubleshooting.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( "BYMU Diff Debug - Post ID: {$post_id}, New values: " . print_r( $new_values, true ) );
		}

		$changes     = [];
		$has_changes = false;
		$warnings    = [];

		foreach ( $fields as $field => $label ) {
			// Get new value from CSV (if provided).
			$new_value = isset( $new_values[ $field ] ) ? $new_values[ $field ] : '';

			// Debug: Log the raw value for troubleshooting.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_var_export
				error_log( "BYMU Debug - Field: {$field}, Raw value: '" . var_export( $new_value, true ) . "', Empty check: " . ( bymu_is_empty_value( $new_value ) ? 'YES' : 'NO' ) );
			}

			// Skip if empty (blank means no change).
			if ( bymu_is_empty_value( $new_value ) ) {
				$changes[ $field ] = [
					'field'       => $field,
					'label'       => $label,
					'current'     => $current_meta[ $field ],
					'new'         => '',
					'will_change' => false,
					'reason'      => 'empty',
				];
				continue;
			}

			// Sanitize new value.
			$new_value = $this->sanitize_field( $field, $new_value );

			// Get current value.
			$current_value = $current_meta[ $field ];

			// Check if different.
			$is_different = ( $current_value !== $new_value );

			if ( $is_different ) {
				$has_changes = true;
			}

			// Check character limits.
			$char_warning = bymu_check_char_limit( $new_value, $field );
			if ( false !== $char_warning ) {
				$warnings[] = sprintf(
					/* translators: 1: Field name, 2: Character count */
					__( '%1$s exceeds recommended length (%2$d characters).', 'bulk-yoast-meta-updater' ),
					ucfirst( $label ),
					$char_warning
				);
			}

			$changes[ $field ] = [
				'field'       => $field,
				'label'       => $label,
				'current'     => $current_value,
				'new'         => $new_value,
				'will_change' => $is_different,
				'reason'      => $is_different ? 'different' : 'identical',
			];
		}

		// Determine row status.
		$status = 'skip';
		if ( $has_changes ) {
			$status = ! empty( $warnings ) ? 'warning' : 'ok';
		}

		return [
			'post_id'     => $post_id,
			'changes'     => $changes,
			'has_changes' => $has_changes,
			'status'      => $status,
			'warnings'    => $warnings,
		];
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param string $value Value to sanitize.
	 * @return string Sanitized value.
	 */
	private function sanitize_field( $field, $value ) {
		switch ( $field ) {
			case 'meta_title':
				return bymu_sanitize_title( $value );

			case 'meta_description':
				return bymu_sanitize_description( $value );

			case 'focus_keyword':
				return bymu_sanitize_focuskw( $value );

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Build batch diff (multiple rows).
	 *
	 * @param array $rows       CSV rows with resolved post_ids.
	 * @param array $post_types Allowed post types.
	 * @return array Batch diff data.
	 */
	public function build_batch( $rows, $post_types = [] ) {
		$settings = bymu_get_settings();
		$url_mode = isset( $settings['url_mode'] ) ? $settings['url_mode'] : 'lenient'; // Default: path-only.
		
		$resolver = new Bulk_Yoast_Meta_Updater_Resolver( $post_types, $url_mode );
		$results  = [];
		$stats    = [
			'total'   => 0,
			'ok'      => 0,
			'skip'    => 0,
			'warning' => 0,
			'error'   => 0,
		];

		foreach ( $rows as $row ) {
			++$stats['total'];

			// Resolve post ID.
			$post_id = $resolver->resolve( $row );

			if ( is_wp_error( $post_id ) ) {
				$results[] = [
					'csv_row'     => $row['_row_number'],
					'post_id'     => 0,
					'post_info'   => null,
					'changes'     => [],
					'has_changes' => false,
					'status'      => 'error',
					'error'       => $post_id->get_error_message(),
					'warnings'    => [],
				];
				++$stats['error'];
				continue;
			}

			// Get post info.
			$post_info = $resolver->get_post_info( $post_id );

			// Build diff.
			$diff = $this->build( $post_id, $row );

			$results[] = array_merge(
				[
					'csv_row'   => $row['_row_number'],
					'post_info' => $post_info,
				],
				$diff
			);

			// Update stats.
			++$stats[ $diff['status'] ];
		}

		return [
			'results' => $results,
			'stats'   => $stats,
		];
	}

	/**
	 * Get fields that will be updated.
	 *
	 * @param array $changes Changes array from build().
	 * @return array Array of field names that will change.
	 */
	public function get_changed_fields( $changes ) {
		$changed = [];

		foreach ( $changes as $field => $change ) {
			if ( $change['will_change'] ) {
				$changed[] = $field;
			}
		}

		return $changed;
	}
}
