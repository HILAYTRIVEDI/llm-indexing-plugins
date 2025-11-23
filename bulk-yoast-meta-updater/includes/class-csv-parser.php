<?php
/**
 * CSV Parser Class
 *
 * Handles CSV file parsing and validation.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_CSV_Parser
 */
class Bulk_Yoast_Meta_Updater_CSV_Parser {

	/**
	 * Required CSV headers (at least one identifier and one meta field).
	 *
	 * @var array
	 */
	private $valid_headers = [
		'post_id',
		'url',
		'meta_title',
		'meta_description',
		'focus_keyword',
	];

	/**
	 * Parse uploaded CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array|WP_Error Parsed data or error.
	 */
	public function parse( $file_path ) {
		// Validate file exists.
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'bulk-yoast-meta-updater' ) );
		}

		// Open file for reading.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		
		if ( false === $handle ) {
			return new WP_Error( 'file_open_failed', __( 'Could not open CSV file.', 'bulk-yoast-meta-updater' ) );
		}

		// Detect delimiter (comma or tab).
		$delimiter = $this->detect_delimiter( $handle );
		
		// Parse headers.
		$headers = fgetcsv( $handle, 0, $delimiter );
		
		if ( false === $headers ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			return new WP_Error( 'empty_file', __( 'CSV file is empty.', 'bulk-yoast-meta-updater' ) );
		}

		// Normalize headers (lowercase, trim).
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		// Debug: Log headers for troubleshooting.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( 'BYMU CSV Debug - Headers: ' . print_r( $headers, true ) );
		}

		// Validate headers.
		$validation_result = $this->validate_headers( $headers );
		if ( is_wp_error( $validation_result ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			return $validation_result;
		}

		// Parse rows.
		$rows              = [];
		$row_number        = 1;
		$skipped_malformed = 0;
		$header_count      = count( $headers );

		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			++$row_number;

			// Skip empty rows.
			if ( $this->is_empty_row( $row ) ) {
				continue;
			}

			// Check column count mismatch.
			if ( count( $row ) !== $header_count ) {
				++$skipped_malformed;
				continue; // Malformed row, skip.
			}

			// Combine headers with row data.
			$row_data = array_combine( $headers, $row );
			
			if ( false === $row_data ) {
				++$skipped_malformed;
				continue;
			}

			// Debug: Log parsed row data for troubleshooting.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $row_number <= 3 ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
				error_log( "BYMU CSV Debug - Row {$row_number}: " . print_r( $row_data, true ) );
			}

			// Add row number for tracking.
			$row_data['_row_number'] = $row_number;

			$rows[] = $row_data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		// Validate we have data rows.
		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data_rows', __( 'No data rows found in CSV file.', 'bulk-yoast-meta-updater' ) );
		}

		// Warn about malformed rows.
		$warnings = [];
		if ( $skipped_malformed > 0 ) {
			$warnings[] = sprintf(
				/* translators: %d: Number of skipped rows */
				__( 'Warning: Skipped %d malformed rows with column count mismatch.', 'bulk-yoast-meta-updater' ),
				$skipped_malformed
			);
		}

		return [
			'headers'  => $headers,
			'rows'     => $rows,
			'count'    => count( $rows ),
			'warnings' => $warnings,
		];
	}

	/**
	 * Validate CSV headers.
	 *
	 * @param array $headers Headers array.
	 * @return bool|WP_Error True or error.
	 */
	private function validate_headers( $headers ) {
		// Check for at least one identifier (post_id or url).
		$has_identifier = in_array( 'post_id', $headers, true ) || in_array( 'url', $headers, true );
		
		if ( ! $has_identifier ) {
			return new WP_Error(
				'missing_identifier',
				__( 'CSV must have either "post_id" or "url" column.', 'bulk-yoast-meta-updater' )
			);
		}

		// Check for at least one meta field.
		$meta_fields = [ 'meta_title', 'meta_description', 'focus_keyword' ];
		$has_meta    = false;
		
		foreach ( $meta_fields as $field ) {
			if ( in_array( $field, $headers, true ) ) {
				$has_meta = true;
				break;
			}
		}

		if ( ! $has_meta ) {
			return new WP_Error(
				'missing_meta_fields',
				__( 'CSV must have at least one meta field: meta_title, meta_description, or focus_keyword.', 'bulk-yoast-meta-updater' )
			);
		}

		return true;
	}

	/**
	 * Check if row is empty.
	 *
	 * @param array $row Row data.
	 * @return bool True if empty.
	 */
	private function is_empty_row( $row ) {
		if ( empty( $row ) ) {
			return true;
		}

		// Check if all cells are empty.
		foreach ( $row as $cell ) {
			if ( ! bymu_is_empty_value( $cell ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate file upload.
	 *
	 * @param array $file Uploaded file data from $_FILES.
	 * @return string|WP_Error File path or error.
	 */
	public function validate_upload( $file ) {
		// Check for upload errors.
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload failed.', 'bulk-yoast-meta-updater' ) );
		}

		// Validate file type.
		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv' ];
		$file_type     = $file['type'];
		
		// Also check extension as fallback.
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		
		if ( ! in_array( $file_type, $allowed_types, true ) && 'csv' !== $file_ext ) {
			return new WP_Error(
				'invalid_file_type',
				__( 'Invalid file type. Please upload a CSV file.', 'bulk-yoast-meta-updater' )
			);
		}

		// Validate file size.
		$max_size_mb    = bymu_get_upload_limit_mb();
		$max_size_bytes = $max_size_mb * 1024 * 1024;
		
		if ( $file['size'] > $max_size_bytes ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Maximum file size */
					__( 'File is too large. Maximum size: %s', 'bulk-yoast-meta-updater' ),
					bymu_format_bytes( $max_size_bytes )
				)
			);
		}

		// Return file path.
		return $file['tmp_name'];
	}

	/**
	 * Detect CSV delimiter (comma or tab).
	 *
	 * @param resource $handle File handle.
	 * @return string Detected delimiter.
	 */
	private function detect_delimiter( $handle ) {
		// Get first line.
		$first_line = fgets( $handle );
		
		// Reset file pointer.
		rewind( $handle );
		
		// Count tabs vs commas.
		$tab_count   = substr_count( $first_line, "\t" );
		$comma_count = substr_count( $first_line, ',' );
		
		// Return tab if more tabs than commas, otherwise comma.
		return ( $tab_count > $comma_count ) ? "\t" : ',';
	}

	/**
	 * Get row count from CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return int Row count (excluding header).
	 */
	public function get_row_count( $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		
		if ( false === $handle ) {
			return 0;
		}

		// Detect delimiter.
		$delimiter = $this->detect_delimiter( $handle );

		$count = 0;
		
		// Skip header row.
		fgetcsv( $handle, 0, $delimiter );
		
		while ( fgetcsv( $handle, 0, $delimiter ) !== false ) {
			++$count;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return $count;
	}
}
