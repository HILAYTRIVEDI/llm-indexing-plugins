<?php
/**
 * Plugin Uninstaller
 *
 * Handles complete plugin data removal.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Uninstaller
 */
class Bulk_Yoast_Meta_Updater_Uninstaller {

	/**
	 * Uninstall plugin completely.
	 *
	 * Removes all plugin data: tables, options, transients.
	 *
	 * @throws Exception If any step fails.
	 */
	public static function uninstall() {
		global $wpdb;

		// Security: Verify we're in uninstall context.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		try {
			// 1. Drop database tables.
			self::drop_tables();
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Uninstall - drop_tables failed: ' . $e->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'Failed to drop tables: ' . $e->getMessage() );
		}

		try {
			// 2. Delete all plugin options.
			self::delete_options();
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Uninstall - delete_options failed: ' . $e->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'Failed to delete options: ' . $e->getMessage() );
		}

		try {
			// 3. Clear all transients.
			self::clear_transients();
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Uninstall - clear_transients failed: ' . $e->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'Failed to clear transients: ' . $e->getMessage() );
		}

		try {
			// 4. Multisite cleanup.
			if ( is_multisite() ) {
				self::multisite_cleanup();
			}
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Uninstall - multisite_cleanup failed: ' . $e->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'Failed multisite cleanup: ' . $e->getMessage() );
		}

		// 5. Fire cleanup action for extensibility.
		do_action( 'bymu_after_uninstall' );
	}

	/**
	 * Drop all plugin database tables.
	 *
	 * @throws Exception If database query fails.
	 */
	private static function drop_tables() {
		global $wpdb;

		// Direct table names (avoid function dependency).
		$jobs_table    = $wpdb->prefix . 'bymu_jobs';
		$actions_table = $wpdb->prefix . 'bymu_actions';

		// Drop actions table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$actions_table}" );
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'DROP actions table failed: ' . $wpdb->last_error );
		}
		
		// Drop jobs table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$jobs_table}" );
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'DROP jobs table failed: ' . $wpdb->last_error );
		}
	}

	/**
	 * Delete all plugin options.
	 *
	 * @throws Exception If database query fails.
	 */
	private static function delete_options() {
		global $wpdb;

		// Delete specific options.
		delete_option( 'bymu_settings' );
		delete_option( 'bymu_db_version' );

		// Delete any other options starting with bymu_.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE 'bymu_%'"
		);
		
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'DELETE options failed: ' . $wpdb->last_error );
		}
	}

	/**
	 * Clear all plugin transients.
	 *
	 * @throws Exception If database query fails.
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_bymu_%' 
			OR option_name LIKE '_transient_timeout_bymu_%'"
		);
		
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'DELETE transients failed: ' . $wpdb->last_error );
		}

		// Delete site transients (multisite).
		if ( is_multisite() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				"DELETE FROM {$wpdb->sitemeta} 
				WHERE meta_key LIKE '_site_transient_bymu_%' 
				OR meta_key LIKE '_site_transient_timeout_bymu_%'"
			);
			
			if ( false === $result && ! empty( $wpdb->last_error ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new Exception( 'DELETE site transients failed: ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Cleanup for multisite installations.
	 */
	private static function multisite_cleanup() {
		global $wpdb;

		// Get all blog IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			
			// Delete options for this site.
			delete_option( 'bymu_settings' );
			delete_option( 'bymu_db_version' );
			
			// Drop tables for this site.
			self::drop_tables();
			
			restore_current_blog();
		}
	}

	/**
	 * Manual uninstall via AJAX.
	 *
	 * Note: Security checks (nonce, capability) are performed in the AJAX handler
	 * (class-plugin.php ajax_manual_uninstall) to avoid duplicate checks.
	 *
	 * @return array Result with success/error message.
	 */
	public static function manual_uninstall() {
		// Security checks are done in ajax_manual_uninstall() before calling this method.
		
		try {
			global $wpdb;
			$jobs_table    = $wpdb->prefix . 'bymu_jobs';
			$actions_table = $wpdb->prefix . 'bymu_actions';
			
			// Get stats before deletion (use 0 if tables don't exist).
			$job_count    = 0;
			$action_count = 0;
			
			// Check if tables exist before querying.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$jobs_table}'" );
			
			if ( $table_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$job_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" );
			}
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$actions_table}'" );
			
			if ( $table_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actions_table}" );
			}

			// Execute uninstall (deletes data).
			self::uninstall();

			// Note: Don't deactivate plugin here - it causes fatal error
			// Plugin will be deactivated naturally after data is deleted

			return [
				'success'  => true,
				'message'  => sprintf(
					/* translators: 1: Number of jobs deleted, 2: Number of actions deleted */
					__( '✅ Plugin data deleted successfully. Removed %1$d job logs and %2$d action records. You can now safely delete the plugin.', 'bulk-yoast-meta-updater' ),
					$job_count,
					$action_count
				),
				'redirect' => admin_url( 'plugins.php' ),
			];
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BYMU Uninstall Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString() );
			
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Uninstall failed: %s', 'bulk-yoast-meta-updater' ),
					$e->getMessage()
				),
			];
		}
	}
}
