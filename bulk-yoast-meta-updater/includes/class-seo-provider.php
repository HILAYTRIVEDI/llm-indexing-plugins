<?php
/**
 * SEO Provider Abstraction.
 *
 * Defines provider-specific logic for Yoast SEO and All in One SEO.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract SEO provider.
 */
abstract class Bulk_Yoast_Meta_Updater_Abstract_SEO_Provider {

	/**
	 * Simple in-request meta cache.
	 *
	 * @var array
	 */
	protected $meta_cache = [];

	/**
	 * Provider slug (yoast, aioseo, etc).
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Human readable provider name.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Current provider version string.
	 *
	 * @return string|false
	 */
	abstract public function get_version();

	/**
	 * Minimum supported version string.
	 *
	 * @return string
	 */
	abstract public function get_min_supported_version();

	/**
	 * Return current meta for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	abstract public function get_current_meta( $post_id );

	/**
	 * Update meta values for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values  Associative array of fields => value.
	 * @return bool|WP_Error
	 */
	abstract public function update_meta( $post_id, $values );

	/**
	 * Trigger reindex/invalidation after updates.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function trigger_reindex( $post_id ) {
		$this->touch_post_and_fire_save( $post_id );
	}

	/**
	 * Default meta keys for reference (optional override).
	 *
	 * @return array
	 */
	public function get_meta_keys() {
		return [
			'meta_title'       => '',
			'meta_description' => '',
			'focus_keyword'    => '',
		];
	}

	/**
	 * Bump post modified timestamps and fire save_post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function touch_post_and_fire_save( $post_id ) {
		$current_time = current_time( 'mysql' );

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $current_time,
				'post_modified_gmt' => get_gmt_from_date( $current_time ),
			],
			[ 'ID' => $post_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		clean_post_cache( $post_id );

		do_action( 'save_post', $post_id, get_post( $post_id ), true );
	}

	/**
	 * Prime meta cache for a batch of posts.
	 *
	 * @param array $post_ids Post IDs.
	 * @return array Associative array of post_id => meta.
	 */
	public function prime_meta_cache( $post_ids ) {
		$results = [];

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id ) {
				continue;
			}

			$results[ $post_id ] = $this->get_current_meta( $post_id );
		}

		return $results;
	}

	/**
	 * Retrieve cached meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|false
	 */
	protected function get_cached_meta( $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id && isset( $this->meta_cache[ $post_id ] ) ) {
			return $this->meta_cache[ $post_id ];
		}

		return false;
	}

	/**
	 * Store cached meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Meta array.
	 * @return void
	 */
	protected function set_cached_meta( $post_id, $meta ) {
		$post_id = absint( $post_id );

		if ( $post_id ) {
			$this->meta_cache[ $post_id ] = $meta;
		}
	}

	/**
	 * Bust cached meta for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function bust_meta_cache( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		unset( $this->meta_cache[ $post_id ] );
		wp_cache_delete( 'post_' . $post_id, 'bymu_current_meta' );
	}
}

/**
 * Yoast SEO provider implementation.
 */
class Bulk_Yoast_Meta_Updater_Yoast_Provider extends Bulk_Yoast_Meta_Updater_Abstract_SEO_Provider {

	/**
	 * Meta keys for Yoast.
	 *
	 * @var array
	 */
	private $meta_keys = [
		'meta_title'       => '_yoast_wpseo_title',
		'meta_description' => '_yoast_wpseo_metadesc',
		'focus_keyword'    => '_yoast_wpseo_focuskw',
	];

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'yoast';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label() {
		return 'Yoast SEO';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_version() {
		return defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_supported_version() {
		return defined( 'BYMU_MIN_YOAST_VERSION' ) ? BYMU_MIN_YOAST_VERSION : '14.0';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_meta( $post_id ) {
		$cached = $this->get_cached_meta( $post_id );

		if ( false !== $cached ) {
			return $cached;
		}

		$meta = [
			'meta_title'       => '',
			'meta_description' => '',
			'focus_keyword'    => '',
		];

		foreach ( $this->meta_keys as $field => $meta_key ) {
			$meta[ $field ] = get_post_meta( $post_id, $meta_key, true );
		}

		$this->set_cached_meta( $post_id, $meta );

		return $meta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_meta( $post_id, $values ) {
		$values = array_intersect_key( $values, $this->meta_keys );

		if ( empty( $values ) ) {
			return true;
		}

		// Clear WordPress post meta cache before updating to ensure fresh reads.
		wp_cache_delete( $post_id, 'post_meta' );

		foreach ( $values as $field => $value ) {
			$meta_key = $this->meta_keys[ $field ];
			
			// Use update_post_meta which handles add/update automatically.
			$result = update_post_meta( $post_id, $meta_key, $value );

			// Clear cache immediately after each update to ensure fresh read.
			wp_cache_delete( $post_id, 'post_meta' );
			clean_post_cache( $post_id );

			// Verify the save by reading directly from database (bypass cache).
			global $wpdb;
			$saved_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
					$post_id,
					$meta_key
				)
			);

			// Compare values (handle empty strings vs null).
			$expected_value = (string) $value;
			$actual_value   = null !== $saved_value ? (string) $saved_value : '';

			if ( $actual_value !== $expected_value ) {
				return new WP_Error(
					'yoast_meta_verification_failed',
					sprintf(
						/* translators: 1: Field name, 2: Expected value, 3: Actual value */
						__( 'Failed to verify %1$s update. Expected: "%2$s", Got: "%3$s"', 'bulk-yoast-meta-updater' ),
						$field,
						$expected_value,
						$actual_value
					)
				);
			}

			// If update_post_meta returned false, it could mean the value didn't change.
			// But we've verified it's correct above, so that's fine.
		}

		// Clear all caches related to this post.
		$this->bust_meta_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		clean_post_cache( $post_id );

		// Clear Yoast-specific transients if they exist.
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'wpseo_post_' . $post_id );
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function trigger_reindex( $post_id ) {
		// Clear WordPress object cache for this post.
		wp_cache_delete( $post_id, 'posts' );
		wp_cache_delete( $post_id, 'post_meta' );
		clean_post_cache( $post_id );

		// Clear Yoast transients.
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'wpseo_post_' . $post_id );
		}

		// Clear Yoast indexable cache if available.
		if ( class_exists( 'WPSEO_Meta_Surface' ) ) {
			delete_transient( 'wpseo_post_' . $post_id );

			if ( class_exists( 'Yoast\WP\SEO\Builders\Indexable_Post_Builder' ) ) {
				try {
					$indexable_repository = YoastSEO()->classes->get( 'Yoast\WP\SEO\Repositories\Indexable_Repository' );
					if ( $indexable_repository ) {
						$indexable = $indexable_repository->find_by_id_and_type( $post_id, 'post', false );
						if ( $indexable ) {
							// Invalidate the indexable by updating timestamp.
							$indexable_repository->update_timestamp( $indexable->id, time() - 10000 );
							// Clear indexable cache.
							wp_cache_delete( 'indexable_post_' . $post_id, 'yoast' );
						}
					}
				} catch ( Exception $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'BYMU: Yoast reindex method failed: ' . $e->getMessage() );
					}
				}
			}
		}

		// Fire WordPress save_post action to trigger Yoast hooks.
		parent::trigger_reindex( $post_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_meta_keys() {
		return $this->meta_keys;
	}

	/**
	 * {@inheritdoc}
	 */
	public function prime_meta_cache( $post_ids ) {
		$post_ids = array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		update_meta_cache( 'post', $post_ids );

		$results = [];

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = $this->get_current_meta( $post_id );
		}

		return $results;
	}
}

/**
 * All in One SEO provider implementation.
 */
class Bulk_Yoast_Meta_Updater_AIOSEO_Provider extends Bulk_Yoast_Meta_Updater_Abstract_SEO_Provider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'aioseo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label() {
		return 'All in One SEO';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_version() {
		return defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_supported_version() {
		return defined( 'BYMU_MIN_AIOSEO_VERSION' ) ? BYMU_MIN_AIOSEO_VERSION : '4.0';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_meta( $post_id ) {
		$cached = $this->get_cached_meta( $post_id );

		if ( false !== $cached ) {
			return $cached;
		}

		$db_meta  = $this->get_meta_via_db( $post_id );
		$api_meta = null;

		// Pull from API when DB missing or any field still empty.
		if ( null === $db_meta || $this->is_meta_incomplete( $db_meta ) ) {
			$api_meta = $this->get_meta_via_api( $post_id );
		}

		if ( null !== $api_meta ) {
			$db_meta = wp_parse_args( $db_meta ? $db_meta : [], $api_meta );
		}

		$meta = wp_parse_args(
			$db_meta ? $db_meta : [],
			[
				'meta_title'       => '',
				'meta_description' => '',
				'focus_keyword'    => '',
			]
		);

		$this->set_cached_meta( $post_id, $meta );

		return $meta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_meta( $post_id, $values ) {
		$allowed_fields = [ 'meta_title', 'meta_description', 'focus_keyword' ];
		$values         = array_intersect_key( $values, array_flip( $allowed_fields ) );

		if ( empty( $values ) ) {
			return true;
		}

		$result = $this->update_meta_via_api( $post_id, $values );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( true !== $result ) {
			$result = $this->update_meta_via_db( $post_id, $values );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Clear cache BEFORE verification to ensure we read fresh data from database/API.
		$this->bust_meta_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		clean_post_cache( $post_id );

		// Verify the update by reading fresh data (bypass cache).
		$current = $this->get_current_meta( $post_id );

		foreach ( $values as $field => $expected ) {
			if ( isset( $current[ $field ] ) && (string) $current[ $field ] !== (string) $expected ) {
				return new WP_Error(
					'aioseo_meta_verification_failed',
					sprintf(
						/* translators: 1: Field name */
						__( 'Failed to verify %s update in All in One SEO.', 'bulk-yoast-meta-updater' ),
						$field
					)
				);
			}
		}

		return true;
	}

	/**
	 * Attempt to read meta via the AIOSEO API.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function get_meta_via_api( $post_id ) {
		if ( ! function_exists( 'aioseo' ) ) {
			return null;
		}

		try {
			$aioseo = aioseo();

			if ( ! isset( $aioseo->meta->metaData ) ) {
				return null;
			}

			$meta = $aioseo->meta->metaData->getMeta( $post_id );

			if ( empty( $meta ) ) {
				return null;
			}

			// Safely convert to array without touching typed properties directly.
			$meta_array = json_decode( wp_json_encode( $meta ), true );

			if ( ! is_array( $meta_array ) ) {
				return null;
			}
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BYMU AIOSEO API meta error: ' . $e->getMessage() );
			}
			return null;
		}

		return [
			'meta_title'       => isset( $meta_array['title'] ) ? (string) $meta_array['title'] : '',
			'meta_description' => isset( $meta_array['description'] ) ? (string) $meta_array['description'] : '',
			'focus_keyword'    => $this->extract_focus_keyphrase( $meta_array ),
		];
	}

	/**
	 * Pull meta directly from the aioseo_posts table.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function get_meta_via_db( $post_id ) {
		global $wpdb;

		$table = $this->get_aioseo_table_name();

		if ( ! $table ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT title, description, keyphrases FROM {$table} WHERE post_id = %d LIMIT 1",
				$post_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return [
			'meta_title'       => isset( $row['title'] ) ? (string) $row['title'] : '',
			'meta_description' => isset( $row['description'] ) ? (string) $row['description'] : '',
			'focus_keyword'    => $this->extract_focus_keyphrase( $row ),
		];
	}

	/**
	 * Determine if any meta fields are empty.
	 *
	 * @param array $meta Meta array.
	 * @return bool
	 */
	private function is_meta_incomplete( $meta ) {
		if ( ! is_array( $meta ) ) {
			return true;
		}

		$fields = [ 'meta_title', 'meta_description', 'focus_keyword' ];

		foreach ( $fields as $field ) {
			if ( ! isset( $meta[ $field ] ) || '' === trim( (string) $meta[ $field ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update via AIOSEO API if available.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values  Values to update.
	 * @return bool|WP_Error True on success, false if API unavailable.
	 */
	private function update_meta_via_api( $post_id, $values ) {
		if ( ! function_exists( 'aioseo' ) ) {
			return false;
		}

		$aioseo = aioseo();

		if ( ! isset( $aioseo->meta->metaData ) ) {
			return false;
		}

		$payload = [];

		if ( isset( $values['meta_title'] ) ) {
			$payload['title'] = $values['meta_title'];
		}

		if ( isset( $values['meta_description'] ) ) {
			$payload['description'] = $values['meta_description'];
		}

		if ( isset( $values['focus_keyword'] ) ) {
			$payload['keyphrases'] = [
				'focus' => [
					'keyphrase' => $values['focus_keyword'],
					'synonyms'  => [],
				],
			];
		}

		if ( empty( $payload ) ) {
			return true;
		}

		try {
			$aioseo->meta->metaData->saveMeta( $post_id, $payload );
		} catch ( Exception $e ) {
			return new WP_Error(
				'aioseo_meta_api_error',
				sprintf(
					/* translators: 1: Error message */
					__( 'AIOSEO API error: %s', 'bulk-yoast-meta-updater' ),
					$e->getMessage()
				)
			);
		}

		return true;
	}

	/**
	 * Update via direct database writes.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values  Values to update.
	 * @return bool|WP_Error
	 */
	private function update_meta_via_db( $post_id, $values ) {
		global $wpdb;

		$table = $this->get_aioseo_table_name();

		if ( ! $table ) {
			return new WP_Error(
				'aioseo_table_missing',
				__( 'AIOSEO data table was not found. Ensure All in One SEO is installed correctly.', 'bulk-yoast-meta-updater' )
			);
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d LIMIT 1",
				$post_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error(
				'aioseo_row_missing',
				__( 'AIOSEO metadata has not been initialized for this post. Please open it once in the editor to generate defaults.', 'bulk-yoast-meta-updater' )
			);
		}

		$data    = [];
		$formats = [];

		if ( isset( $values['meta_title'] ) ) {
			$data['title'] = $values['meta_title'];
			$formats[]     = '%s';
		}

		if ( isset( $values['meta_description'] ) ) {
			$data['description'] = $values['meta_description'];
			$formats[]           = '%s';
		}

		if ( isset( $values['focus_keyword'] ) ) {
			$data['keyphrases'] = wp_json_encode(
				$this->build_keyphrases_payload( $values['focus_keyword'], isset( $row['keyphrases'] ) ? $row['keyphrases'] : '' )
			);
			$formats[]          = '%s';
		}

		if ( empty( $data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$table,
			$data,
			[ 'post_id' => $post_id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'aioseo_meta_update_failed',
				sprintf(
					/* translators: 1: Post ID */
					__( 'Failed to update AIOSEO meta for post ID %d.', 'bulk-yoast-meta-updater' ),
					$post_id
				)
			);
		}

		return true;
	}

	/**
	 * Extract the focus keyphrase from assorted payload formats.
	 *
	 * @param mixed $data Raw meta payload.
	 * @return string
	 */
	private function extract_focus_keyphrase( $data ) {
		if ( isset( $data['focus_keyword'] ) && is_string( $data['focus_keyword'] ) ) {
			return $data['focus_keyword'];
		}

		$keyphrases = null;

		if ( isset( $data['keyphrases'] ) ) {
			$keyphrases = $data['keyphrases'];
		}

		if ( ! $keyphrases && isset( $data['focus']['keyphrase'] ) ) {
			return (string) $data['focus']['keyphrase'];
		}

		if ( is_string( $keyphrases ) ) {
			$decoded = json_decode( $keyphrases, true );
			if ( isset( $decoded['focus']['keyphrase'] ) ) {
				return (string) $decoded['focus']['keyphrase'];
			}
		} elseif ( is_array( $keyphrases ) ) {
			if ( isset( $keyphrases['focus']['keyphrase'] ) ) {
				return (string) $keyphrases['focus']['keyphrase'];
			}
		} elseif ( is_object( $keyphrases ) && isset( $keyphrases->focus->keyphrase ) ) {
			return (string) $keyphrases->focus->keyphrase;
		}

		return '';
	}

	/**
	 * Build keyphrases payload for storage.
	 *
	 * @param string $focus_keyword Focus keyword.
	 * @param string $existing_json Existing JSON payload.
	 * @return array
	 */
	private function build_keyphrases_payload( $focus_keyword, $existing_json = '' ) {
		$payload = [
			'focus' => [
				'keyphrase' => $focus_keyword,
				'synonyms'  => [],
			],
		];

		if ( ! empty( $existing_json ) ) {
			$decoded = json_decode( $existing_json, true );
			if ( is_array( $decoded ) && isset( $decoded['focus'] ) ) {
				$payload['focus'] = array_merge( $decoded['focus'], $payload['focus'] );
			}
		}

		return $payload;
	}

	/**
	 * Resolve the aioseo_posts table name.
	 *
	 * @return string
	 */
	private function get_aioseo_table_name() {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $exists ? $table : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function prime_meta_cache( $post_ids ) {
		global $wpdb;

		$post_ids = array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		$table = $this->get_aioseo_table_name();

		if ( $table ) {
			$chunks = array_chunk( $post_ids, 100 );

			foreach ( $chunks as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
				$query = $wpdb->prepare(
					"SELECT post_id, title, description, keyphrases FROM {$table} WHERE post_id IN ($placeholders)",
					...$chunk
				);

				$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				if ( empty( $rows ) ) {
					continue;
				}

				foreach ( $rows as $row ) {
					$pid = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;

					if ( ! $pid ) {
						continue;
					}

					$meta = [
						'meta_title'       => isset( $row['title'] ) ? (string) $row['title'] : '',
						'meta_description' => isset( $row['description'] ) ? (string) $row['description'] : '',
						'focus_keyword'    => $this->extract_focus_keyphrase( $row ),
					];

					$this->set_cached_meta( $pid, $meta );
				}
			}
		}

		$results = [];

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = $this->get_current_meta( $post_id );
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_meta_keys() {
		return [
			'meta_title'       => 'seo_title',
			'meta_description' => 'seo_description',
			'focus_keyword'    => 'keyphrases.focus.keyphrase',
		];
	}
}
