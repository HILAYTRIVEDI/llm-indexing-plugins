<?php
/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation cleanup (preserves data).
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Deactivator
 */
class Bulk_Yoast_Meta_Updater_Deactivator {

	/**
	 * Deactivate plugin.
	 *
	 * Cleans up temporary data but preserves logs and settings.
	 */
	public static function deactivate() {
		global $wpdb;

		// 1. Clear all transients.
		self::clear_transients();

		// 2. Mark any in-progress jobs as interrupted.
		$jobs_table = bymu_get_table_name( 'jobs' );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$jobs_table} 
			SET status = 'interrupted', 
			    completed_at = NOW() 
			WHERE status IN ('pending', 'processing')"
		);

		// 3. Set deactivation notice.
		set_transient( 'bymu_deactivation_notice', true, HOUR_IN_SECONDS );

		// Note: We do NOT delete:
		// - Database tables (preserved for reactivation)
		// - Settings (preserved for reactivation)
		// - Job logs (preserved for reactivation)
	}

	/**
	 * Clear all plugin transients.
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all transients starting with bymu_.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_bymu_%' 
			OR option_name LIKE '_transient_timeout_bymu_%'"
		);

		// For multisite, also clear site transients.
		if ( is_multisite() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				"DELETE FROM {$wpdb->sitemeta} 
				WHERE meta_key LIKE '_site_transient_bymu_%' 
				OR meta_key LIKE '_site_transient_timeout_bymu_%'"
			);
		}
	}

	/**
	 * Display deactivation notice.
	 */
	public static function display_deactivation_notice() {
		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$allowed_screens = [ 'plugins', 'plugins-network' ];

		if ( ! bymu_is_plugin_screen() && ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) ) {
			return;
		}

		$deactivated = get_transient( 'bymu_deactivation_notice' );
		
		if ( $deactivated ) {
			delete_transient( 'bymu_deactivation_notice' );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'Bulk SEO Meta Updater has been deactivated. Your data has been preserved and will be available if you reactivate the plugin.', 'bulk-yoast-meta-updater' )
			);
		}
	}
}
