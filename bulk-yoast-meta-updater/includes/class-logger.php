<?php
/**
 * Logger Class
 *
 * Handles database logging for jobs and actions.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Logger
 */
class Bulk_Yoast_Meta_Updater_Logger {

	/**
	 * Create a new job log.
	 *
	 * @param array $data Job data.
	 * @return int|false Job ID or false on failure.
	 */
	public static function create_job( $data ) {
		global $wpdb;

		$table = bymu_get_table_name( 'jobs' );

		$defaults = [
			'job_hash'       => bymu_generate_job_hash(),
			'user_id'        => get_current_user_id(),
			'file_name'      => '',
			'created_at'     => current_time( 'mysql' ),
			'completed_at'   => null,
			'status'         => 'pending',
			'total_rows'     => 0,
			'processed_rows' => 0,
			'updated_rows'   => 0,
			'skipped_rows'   => 0,
			'error_rows'     => 0,
			'settings'       => wp_json_encode( [] ),
		];

		$data = wp_parse_args( $data, $defaults );

		// Ensure settings is JSON.
		if ( is_array( $data['settings'] ) ) {
			$data['settings'] = wp_json_encode( $data['settings'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			$data,
			[
				'%s', // job_hash
				'%d', // user_id
				'%s', // file_name
				'%s', // created_at
				'%s', // completed_at
				'%s', // status
				'%d', // total_rows
				'%d', // processed_rows
				'%d', // updated_rows
				'%d', // skipped_rows
				'%d', // error_rows
				'%s', // settings
			]
		);

		if ( false === $result ) {
			bymu_log_db_error(
				'create_job',
				$wpdb->last_error,
				[
					'file_name' => $data['file_name'],
					'user_id'   => $data['user_id'],
				]
			);
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update job data.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data   Data to update.
	 * @return bool True on success.
	 */
	public static function update_job( $job_id, $data ) {
		global $wpdb;

		$table = bymu_get_table_name( 'jobs' );

		// Ensure settings is JSON if provided.
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings'] = wp_json_encode( $data['settings'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$data,
			[ 'id' => $job_id ],
			null,
			[ '%d' ]
		);

		if ( false === $result ) {
			bymu_log_db_error(
				'update_job',
				$wpdb->last_error,
				[
					'job_id' => $job_id,
					'fields' => array_keys( $data ),
				]
			);
		}

		return false !== $result;
	}

	/**
	 * Get job by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return object|null Job object or null.
	 */
	public static function get_job( $job_id ) {
		global $wpdb;

		$table = bymu_get_table_name( 'jobs' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id )
		);

		if ( $job && ! empty( $job->settings ) ) {
			$job->settings = json_decode( $job->settings, true );
		}

		return $job;
	}

	/**
	 * Get job by hash.
	 *
	 * @param string $job_hash Job hash.
	 * @return object|null Job object or null.
	 */
	public static function get_job_by_hash( $job_hash ) {
		global $wpdb;

		$table = bymu_get_table_name( 'jobs' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE job_hash = %s", $job_hash )
		);

		if ( $job && ! empty( $job->settings ) ) {
			$job->settings = json_decode( $job->settings, true );
		}

		return $job;
	}

	/**
	 * Log an action.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data   Action data.
	 * @return int|false Action ID or false on failure.
	 */
	public static function log_action( $job_id, $data ) {
		global $wpdb;

		$table = bymu_get_table_name( 'actions' );

		$defaults = [
			'job_id'     => $job_id,
			'csv_row'    => 0,
			'post_id'    => 0,
			'url'        => '',
			'field'      => '',
			'old_value'  => '',
			'new_value'  => '',
			'status'     => 'ok',
			'message'    => '',
			'created_at' => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			$data,
			[
				'%d', // job_id
				'%d', // row_number
				'%d', // post_id
				'%s', // url
				'%s', // field
				'%s', // old_value
				'%s', // new_value
				'%s', // status
				'%s', // message
				'%s', // created_at
			]
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get actions for a job.
	 *
	 * @param int $job_id Job ID.
	 * @param int $limit  Limit results (0 for all).
	 * @param int $offset Offset for pagination.
	 * @return array Actions.
	 */
	public static function get_job_actions( $job_id, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$table = bymu_get_table_name( 'actions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE job_id = %d ORDER BY csv_row ASC",
			$job_id
		);

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get recent jobs for a user.
	 *
	 * @param int $user_id User ID (0 for all).
	 * @param int $limit   Number of jobs to return.
	 * @return array Jobs.
	 */
	public static function get_recent_jobs( $user_id = 0, $limit = 10 ) {
		global $wpdb;

		$table = bymu_get_table_name( 'jobs' );

		// Check if table exists (avoid errors after uninstall).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		if ( ! $table_exists ) {
			return [];
		}

		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} 
					WHERE user_id = %d 
					ORDER BY created_at DESC 
					LIMIT %d",
					$user_id,
					$limit
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} 
					ORDER BY created_at DESC 
					LIMIT %d",
					$limit
				)
			);
		}

		// Decode settings JSON.
		foreach ( $jobs as $job ) {
			if ( ! empty( $job->settings ) ) {
				$job->settings = json_decode( $job->settings, true );
			}
		}

		return $jobs;
	}

	/**
	 * Delete a job and its actions.
	 *
	 * @param int $job_id Job ID.
	 * @return bool True on success.
	 */
	public static function delete_job( $job_id ) {
		global $wpdb;

		$jobs_table    = bymu_get_table_name( 'jobs' );
		$actions_table = bymu_get_table_name( 'actions' );

		// Delete actions first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $actions_table, [ 'job_id' => $job_id ], [ '%d' ] );

		// Delete job.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $jobs_table, [ 'id' => $job_id ], [ '%d' ] );

		return false !== $result;
	}
}
