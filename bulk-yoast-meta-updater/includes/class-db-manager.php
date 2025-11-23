<?php
/**
 * Database Manager Class
 *
 * Handles database table creation, schema updates, and maintenance.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_DB_Manager
 */
class Bulk_Yoast_Meta_Updater_DB_Manager {

	/**
	 * Database version for migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.1';

	/**
	 * Create database tables.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Jobs table - dbDelta requires specific syntax.
		$jobs_table = bymu_get_table_name( 'jobs' );
		
		// Build SQL with explicit newlines for dbDelta.
		$jobs_sql  = "CREATE TABLE {$jobs_table} (\n";
		$jobs_sql .= "  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
		$jobs_sql .= "  job_hash varchar(32) NOT NULL,\n";
		$jobs_sql .= "  user_id bigint(20) unsigned NOT NULL,\n";
		$jobs_sql .= "  file_name varchar(255) NOT NULL,\n";
		$jobs_sql .= "  created_at datetime NOT NULL,\n";
		$jobs_sql .= "  completed_at datetime DEFAULT NULL,\n";
		$jobs_sql .= "  status varchar(20) NOT NULL DEFAULT 'pending',\n";
		$jobs_sql .= "  total_rows int NOT NULL DEFAULT 0,\n";
		$jobs_sql .= "  processed_rows int NOT NULL DEFAULT 0,\n";
		$jobs_sql .= "  updated_rows int NOT NULL DEFAULT 0,\n";
		$jobs_sql .= "  skipped_rows int NOT NULL DEFAULT 0,\n";
		$jobs_sql .= "  error_rows int NOT NULL DEFAULT 0,\n";
		$jobs_sql .= "  settings longtext,\n";
		$jobs_sql .= "  PRIMARY KEY  (id),\n";
		$jobs_sql .= "  UNIQUE KEY job_hash (job_hash),\n";
		$jobs_sql .= "  KEY user_id (user_id),\n";
		$jobs_sql .= "  KEY created_at (created_at),\n";
		$jobs_sql .= "  KEY status (status)\n";
		$jobs_sql .= ") {$charset_collate};";

		// Actions table - dbDelta requires specific syntax.
		$actions_table = bymu_get_table_name( 'actions' );
		
		// Build SQL with explicit newlines for dbDelta.
		// Note: row_number is reserved in MySQL 8.0, renamed to csv_row.
		$actions_sql  = "CREATE TABLE {$actions_table} (\n";
		$actions_sql .= "  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
		$actions_sql .= "  job_id bigint(20) unsigned NOT NULL,\n";
		$actions_sql .= "  csv_row int NOT NULL DEFAULT 0,\n";
		$actions_sql .= "  post_id bigint(20) unsigned NOT NULL DEFAULT 0,\n";
		$actions_sql .= "  url text,\n";
		$actions_sql .= "  field varchar(50) DEFAULT '',\n";
		$actions_sql .= "  old_value text,\n";
		$actions_sql .= "  new_value text,\n";
		$actions_sql .= "  status varchar(20) NOT NULL DEFAULT 'pending',\n";
		$actions_sql .= "  message text,\n";
		$actions_sql .= "  created_at datetime NOT NULL,\n";
		$actions_sql .= "  PRIMARY KEY  (id),\n";
		$actions_sql .= "  KEY job_id (job_id),\n";
		$actions_sql .= "  KEY post_id (post_id),\n";
		$actions_sql .= "  KEY created_at (created_at),\n";
		$actions_sql .= "  KEY status (status)\n";
		$actions_sql .= ") {$charset_collate};";

		// Execute table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$jobs_result = dbDelta( $jobs_sql );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$actions_result = dbDelta( $actions_sql );

		// Log any database errors.
		if ( ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU DB Error: ' . $wpdb->last_error );
		}

		// Verify tables were created.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$jobs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actions_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $actions_table ) );

		// Build detailed error message.
		$error_details = [];
		if ( $jobs_exists !== $jobs_table ) {
			$error_details[] = sprintf( 'Jobs table (%s) not created', $jobs_table );
		}
		if ( $actions_exists !== $actions_table ) {
			$error_details[] = sprintf( 'Actions table (%s) not created', $actions_table );
		}

		if ( ! empty( $error_details ) ) {
			$error_message = __( 'Failed to create database tables: ', 'bulk-yoast-meta-updater' ) . implode( ', ', $error_details );
			
			// Add MySQL error if available.
			if ( ! empty( $wpdb->last_error ) ) {
				$error_message .= sprintf( ' | MySQL Error: %s', $wpdb->last_error );
			}

			// Log detailed error.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Activation Error: ' . $error_message );

			return new WP_Error( 'table_creation_failed', $error_message );
		}

		// Store database version.
		update_option( 'bymu_db_version', self::DB_VERSION );

		return true;
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @return bool True on success.
	 */
	public static function drop_tables() {
		global $wpdb;

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$actions_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$jobs_table}" );

		return true;
	}

	/**
	 * Optimize plugin tables.
	 *
	 * @return bool True on success.
	 */
	public static function optimize_tables() {
		global $wpdb;

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "OPTIMIZE TABLE {$jobs_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "OPTIMIZE TABLE {$actions_table}" );

		return true;
	}

	/**
	 * Clean up old jobs based on retention setting.
	 *
	 * @param int $user_id User ID to clean up for (0 for all users).
	 * @return int Number of jobs deleted.
	 */
	public static function cleanup_old_jobs( $user_id = 0, $force_all = false ) {
		global $wpdb;

		$settings  = bymu_get_settings();
		$retention = absint( $settings['log_retention'] );

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		if ( $force_all ) {
			// Get all job IDs.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$job_ids = $wpdb->get_col( "SELECT id FROM {$jobs_table}" );

			return self::delete_jobs_in_batches( $job_ids );
		}

		if ( $retention <= 0 ) {
			return 0;
		}

		// Get jobs to delete (keep only latest N per user).
		// MySQL 5.7 compatible - use LEFT JOIN instead of NOT IN with LIMIT.
		if ( $user_id > 0 ) {
			// Get IDs to keep first (latest N jobs for this user).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs_to_keep = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$jobs_table} 
					WHERE user_id = %d 
					ORDER BY created_at DESC 
					LIMIT %d",
					$user_id,
					$retention
				)
			);
			
			if ( empty( $jobs_to_keep ) ) {
				return 0;
			}
			
			$placeholders_keep = implode( ',', array_fill( 0, count( $jobs_to_keep ), '%d' ) );
			
			// Get jobs to delete (all jobs NOT in the keep list).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs_to_delete = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT id FROM {$jobs_table} 
					WHERE user_id = %d 
					AND id NOT IN ({$placeholders_keep})",
					$user_id,
					...$jobs_to_keep
				)
			);
		} else {
			// For all users, keep N per user (MySQL 5.7 compatible).
			// Get distinct user IDs first.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$jobs_table}" );
			
			$jobs_to_delete = [];
			foreach ( $user_ids as $uid ) {
				// Get IDs to keep for this user.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$jobs_to_keep = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$jobs_table} 
						WHERE user_id = %d 
						ORDER BY created_at DESC 
						LIMIT %d",
						$uid,
						$retention
					)
				);
				
				if ( empty( $jobs_to_keep ) ) {
					continue;
				}
				
				$placeholders_keep = implode( ',', array_fill( 0, count( $jobs_to_keep ), '%d' ) );
				
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$to_delete = $wpdb->get_col(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->prepare(
						"SELECT id FROM {$jobs_table} 
						WHERE user_id = %d 
						AND id NOT IN ({$placeholders_keep})",
						$uid,
						...$jobs_to_keep
					)
				);
				$jobs_to_delete = array_merge( $jobs_to_delete, $to_delete );
			}
		}

		if ( empty( $jobs_to_delete ) ) {
			return 0;
		}

		return self::delete_jobs_in_batches( $jobs_to_delete );
	}

	/**
	 * Delete jobs (and related actions) in batches to avoid long-running queries.
	 *
	 * @param array $job_ids Job IDs to delete.
	 * @return int Number of jobs deleted.
	 */
	private static function delete_jobs_in_batches( $job_ids ) {
		global $wpdb;

		if ( empty( $job_ids ) ) {
			return 0;
		}

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		$total_deleted = 0;
		$chunks        = array_chunk( array_map( 'absint', $job_ids ), 500 );

		foreach ( $chunks as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

			// Delete associated actions first.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$actions_table} WHERE job_id IN ({$placeholders})",
					...$chunk
				)
			);

			// Delete jobs.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$jobs_table} WHERE id IN ({$placeholders})",
					...$chunk
				)
			);

			if ( false === $deleted ) {
				continue;
			}

			$total_deleted += (int) $deleted;
		}

		return $total_deleted;
	}

	/**
	 * Maybe upgrade database schema when version changes.
	 */
	public static function maybe_upgrade() {
		$current_version = get_option( 'bymu_db_version', '0' );

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$result = self::create_tables();

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BYMU DB Upgrade Error: ' . $result->get_error_message() );
			}
		}
	}

	/**
	 * Get table statistics.
	 *
	 * @return array Table stats.
	 */
	public static function get_table_stats() {
		$cached = get_transient( 'bymu_table_stats' );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		// Check if tables exist before querying (avoid errors after uninstall).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$jobs_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$jobs_table}'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actions_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$actions_table}'" );

		$job_count    = 0;
		$action_count = 0;

		if ( $jobs_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$job_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" );
		}

		if ( $actions_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$action_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$actions_table}" );
		}

		$stats = [
			'total_jobs'    => absint( $job_count ),
			'total_actions' => absint( $action_count ),
		];

		set_transient( 'bymu_table_stats', $stats, 30 );

		return $stats;
	}
}
