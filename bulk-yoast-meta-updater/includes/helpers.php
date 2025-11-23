<?php

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BYMU_PLUGIN_DIR . 'includes/class-seo-provider.php';
/**
 * Get human-friendly plugin name.
 *
 * @return string
 */
function bymu_get_brand_name() {
	return __( 'Bulk SEO Meta Updater', 'bulk-yoast-meta-updater' );
}

/**
 * Get current mode label based on active SEO provider.
 *
 * @return string
 */
function bymu_get_mode_label() {
	$provider = bymu_get_active_seo_provider();
	$label    = $provider ? $provider->get_label() : __( 'Yoast SEO', 'bulk-yoast-meta-updater' );

	return sprintf(
		/* translators: %s: Active SEO provider label */
		__( '%s Mode', 'bulk-yoast-meta-updater' ),
		$label
	);
}

/**
 * Render primary admin navigation tabs.
 *
 * @param string $active_slug Active tab identifier.
 * @return void
 */
function bymu_render_admin_nav( $active_slug = 'dashboard' ) {
	$items = [
		'dashboard'  => [
			'label' => __( 'Dashboard', 'bulk-yoast-meta-updater' ),
			'page'  => 'bulk-yoast-meta-updater',
		],
		'import'     => [
			'label' => __( 'Import CSV', 'bulk-yoast-meta-updater' ),
			'page'  => 'bulk-yoast-meta-import',
		],
		'ai-updates' => [
			'label' => __( 'AI Updates', 'bulk-yoast-meta-updater' ),
			'page'  => 'bulk-yoast-meta-ai-updates',
		],
		'image-alt'  => [
			'label' => __( 'Image Alt Texts', 'bulk-yoast-meta-updater' ),
			'page'  => 'bulk-yoast-meta-image-alt',
		],
		'settings'   => [
			'label' => __( 'Settings', 'bulk-yoast-meta-updater' ),
			'page'  => 'bulk-yoast-meta-settings',
		],
	];

	echo '<nav class="bymu-page-nav">';

	foreach ( $items as $slug => $item ) {
		$url   = admin_url( 'admin.php?page=' . $item['page'] );
		$class = 'bymu-page-nav__link';

		if ( $slug === $active_slug ) {
			$class .= ' is-active';
		}

		printf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_html( $item['label'] )
		);
	}

	echo '</nav>';
}

/**
 * Get the rendered HTML for the current mode badge.
 *
 * @param array $args Optional arguments (tag, class).
 * @return string
 */
function bymu_get_mode_badge_html( $args = [] ) {
	$defaults = [
		'tag'   => 'div',
		'class' => '',
		'icon'  => '<span class="bymu-mode-dot" aria-hidden="true"></span>',
	];

	$args  = wp_parse_args( $args, $defaults );
	$tag   = esc_attr( $args['tag'] );
	$class = trim( 'bymu-mode-badge ' . $args['class'] );
	$label = esc_html( bymu_get_mode_label() );

	return sprintf(
		'<%1$s class="%2$s">%3$s<span class="bymu-mode-label">%4$s</span></%1$s>',
		$tag,
		esc_attr( $class ),
		$args['icon'],
		$label
	);
}

/**
 * Render the current mode badge.
 *
 * @param array $args Optional arguments for the badge markup.
 */
function bymu_render_mode_badge( $args = [] ) {
	echo wp_kses_post( bymu_get_mode_badge_html( $args ) );
}

/**
 * Log AI rate limit events so they appear in Recent Jobs.
 *
 * @param string $provider Provider slug (gemini/openai).
 * @param string $model    Model name.
 * @param array  $context  Additional context.
 * @return void
 */
function bymu_log_rate_limit_event( $provider, $model = '', $context = [] ) {
	if ( ! class_exists( 'Bulk_Yoast_Meta_Updater_Logger' ) ) {
		require_once BYMU_PLUGIN_DIR . 'includes/class-logger.php';
	}

	$settings = [
		'type'     => 'ai_rate_limit',
		'provider' => $provider,
		'model'    => $model,
		'context'  => $context,
	];

	$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
		[
			'file_name'      => sprintf(
				/* translators: %s: AI provider */
				__( '%s Rate Limit', 'bulk-yoast-meta-updater' ),
				ucfirst( $provider )
			),
			'status'         => 'error',
			'total_rows'     => 0,
			'processed_rows' => 0,
			'error_rows'     => 1,
			'settings'       => $settings,
		]
	);

	if ( ! $job_id ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Provider, 2: Model */
		__( '%1$s provider hit a rate limit for model %2$s.', 'bulk-yoast-meta-updater' ),
		ucfirst( $provider ),
		$model ? $model : __( '(unspecified)', 'bulk-yoast-meta-updater' )
	);

	Bulk_Yoast_Meta_Updater_Logger::log_action(
		$job_id,
		[
			'field'     => 'ai_rate_limit',
			'status'    => 'error',
			'message'   => $message,
			'new_value' => ! empty( $context ) ? wp_json_encode( $context ) : '',
		]
	);

	Bulk_Yoast_Meta_Updater_Logger::update_job(
		$job_id,
		[
			'status'       => 'error',
			'completed_at' => current_time( 'mysql' ),
		]
	);
}

/**
 * Get cached post type counts (totals + per-status).
 *
 * @return array
 */
function bymu_get_post_type_counts() {
	$cache_key = 'bymu_post_type_counts_v1';
	$counts    = wp_cache_get( $cache_key, 'bymu_admin' );

	if ( false !== $counts ) {
		return $counts;
	}

	$post_types = bymu_get_post_type_options();
	$statuses   = [ 'publish', 'draft', 'private', 'pending', 'future' ];
	$data       = [
		'post_types' => [],
		'statuses'   => $statuses,
		'generated'  => time(),
	];

	foreach ( $post_types as $type => $label ) {
		$wp_counts  = wp_count_posts( $type );
		$status_map = [];
		$total      = 0;

		foreach ( $statuses as $status_key ) {
			$value                     = isset( $wp_counts->{$status_key} ) ? (int) $wp_counts->{$status_key} : 0;
			$status_map[ $status_key ] = $value;
			$total                    += $value;
		}

		$data['post_types'][ $type ] = [
			'label'    => $label,
			'total'    => $total,
			'statuses' => $status_map,
		];
	}

	wp_cache_set( $cache_key, $data, 'bymu_admin', 5 * MINUTE_IN_SECONDS );

	return $data;
}

/**
 * Default Gemini models used when API lookup is unavailable.
 *
 * @return array
 */
function bymu_get_default_gemini_models() {
	return [
		[
			'id'           => 'gemini-2.5-flash',
			'display_name' => 'Gemini 2.5 Flash',
			'category'     => 'text',
			'outputs'      => [ 'text' ],
			'inputs'       => [ 'text' ],
			'methods'      => [ 'generatecontent' ],
		],
		[
			'id'           => 'gemini-2.5-pro',
			'display_name' => 'Gemini 2.5 Pro',
			'category'     => 'text',
			'outputs'      => [ 'text' ],
			'inputs'       => [ 'text' ],
			'methods'      => [ 'generatecontent' ],
		],
		[
			'id'           => 'gemini-2.5-flash-lite',
			'display_name' => 'Gemini 2.5 Flash Lite',
			'category'     => 'text',
			'outputs'      => [ 'text' ],
			'inputs'       => [ 'text' ],
			'methods'      => [ 'generatecontent' ],
		],
		[
			'id'           => 'gemini-2.0-flash',
			'display_name' => 'Gemini 2.0 Flash',
			'category'     => 'text',
			'outputs'      => [ 'text' ],
			'inputs'       => [ 'text' ],
			'methods'      => [ 'generatecontent' ],
		],
		[
			'id'           => 'gemini-2.5-flash-image',
			'display_name' => 'Gemini 2.5 Flash Image',
			'category'     => 'image',
			'outputs'      => [ 'text', 'image' ],
			'inputs'       => [ 'text', 'image' ],
			'methods'      => [ 'generatecontent', 'generateimage' ],
		],
		[
			'id'           => 'gemini-2.5-flash-image-preview',
			'display_name' => 'Gemini 2.5 Flash Image Preview',
			'category'     => 'image',
			'outputs'      => [ 'text', 'image' ],
			'inputs'       => [ 'text', 'image' ],
			'methods'      => [ 'generatecontent', 'generateimage' ],
		],
		[
			'id'           => 'gemini-2.0-flash-preview-image-generation',
			'display_name' => 'Gemini 2.0 Flash Image (Preview)',
			'category'     => 'image',
			'outputs'      => [ 'text', 'image' ],
			'inputs'       => [ 'text', 'image' ],
			'methods'      => [ 'generatecontent', 'generateimage' ],
		],
	];
}

/**
 * Update plugin settings.
 *
 * @param array $settings Settings to update.
 * @return bool
 */
function bymu_update_settings( $settings ) {
	$current = bymu_get_settings();
	$updated = wp_parse_args( $settings, $current );
	$result  = update_option( 'bymu_settings', $updated );

	// Clear object cache to force reload.
	wp_cache_delete( 'bymu_settings_v2', 'bymu_settings' );

	return $result;
}

/**
 * Helper functions for Bulk SEO Meta Updater.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

/**
 * Sanitize meta title value.
 *
 * @param string $value Raw title value.
 * @return string Sanitized title.
 */
function bymu_sanitize_title( $value ) {
	return trim( wp_strip_all_tags( $value ) );
}

/**
 * Sanitize meta description value.
 *
 * @param string $value Raw description value.
 * @return string Sanitized description.
 */
function bymu_sanitize_description( $value ) {
	return trim( wp_strip_all_tags( $value ) );
}

/**
 * Sanitize focus keyword value.
 *
 * @param string $value Raw keyword value.
 * @return string Sanitized keyword.
 */
function bymu_sanitize_focuskw( $value ) {
	return trim( sanitize_text_field( $value ) );
}

/**
 * Check if a value is empty (for CSV handling).
 *
 * Empty means: truly empty, whitespace-only, or just quotes.
 *
 * @param string $value Value to check.
 * @return bool True if should be considered empty.
 */
function bymu_is_empty_value( $value ) {
	$value = trim( $value );
	return empty( $value ) || '""' === $value;
}

/**
 * Get string length with multibyte support fallback.
 *
 * @param string $value String to measure.
 * @return int Character length.
 */
function bymu_str_length( $value ) {
	$value = (string) $value;

	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $value );
	}

	return strlen( $value );
}

/**
 * Check if a value is empty or shorter than threshold.
 *
 * @param string $value      Value to check.
 * @param int    $threshold  Character threshold.
 * @return bool True if empty or shorter.
 */
function bymu_is_value_short_or_empty( $value, $threshold = 30 ) {
	$threshold = max( 1, absint( $threshold ) );
	$value     = trim( (string) $value );

	if ( '' === $value ) {
		return true;
	}

	return bymu_str_length( $value ) < $threshold;
}

/**
 * Determine if meta fields are empty or shorter than threshold.
 *
 * @param string $meta_title       Meta title value.
 * @param string $meta_description Meta description value.
 * @param int    $threshold        Character threshold (default 30).
 * @return bool True if either field is empty or shorter than threshold.
 */
function bymu_meta_is_short_or_empty( $meta_title, $meta_description, $threshold = 30 ) {
	return bymu_is_value_short_or_empty( $meta_title, $threshold ) || bymu_is_value_short_or_empty( $meta_description, $threshold );
}

/**
 * Get plugin settings.
 *
 * Uses object cache to minimize database queries.
 *
 * @return array Plugin settings with defaults.
 */
function bymu_get_settings() {
	// Try object cache first (persistent on VIP with Redis).
	$cache_key = 'bymu_settings_v2';
	$cached    = wp_cache_get( $cache_key, 'bymu_settings' );
	
	if ( false !== $cached ) {
		return $cached;
	}

	$defaults = [
		'batch_size'            => 15,
		'log_retention'         => 10,
		'max_upload_size_mb'    => 4,
		'title_warning_chars'   => 60,
		'desc_warning_chars'    => 160,
		'keyword_warning_chars' => 100,
		'url_mode'              => 'lenient', // Default: match by path only (ignore hostname).
		'throttle_delay_ms'     => 180,
		'enable_cli_notices'    => true,
		// AI Generation defaults.
		'gemini_api_key'        => '',
		'gemini_text_model'     => 'gemini-2.5-flash',
		'gemini_image_model'    => 'gemini-2.5-flash-image',
		'ai_brand_name'         => get_bloginfo( 'name' ),
		'ai_title_prompt'       => 'Generate an SEO-optimized title tag for this content. Requirements: 1) Maximum 60 characters, 2) Include primary keyword naturally, 3) Be compelling and click-worthy, 4) Accurately reflect the content, 5) Use title case, 6) Avoid keyword stuffing. Return ONLY the title text, nothing else.',
		'ai_description_prompt' => 'Generate an SEO-optimized meta description for this content. Requirements: 1) Maximum 155 characters, 2) Include primary keyword naturally, 3) Be compelling with clear call-to-action, 4) Accurately summarize the content, 5) Use active voice, 6) Avoid duplicate content. Return ONLY the meta description text, nothing else.',
		'ai_keyphrase_prompt'   => 'Identify the primary focus keyphrase for this content. Requirements: 1) 1-4 words maximum, 2) Match user search intent, 3) Appear naturally in the content, 4) High search relevance, 5) Avoid generic terms. Return ONLY the keyphrase, nothing else.',
		'ai_image_alt_prompt'   => 'Please provide a functional, objective description of the provided image in no more than around 15 words so that someone who could not see it would be able to imagine it. If possible, follow an "object-action-context" framework: The object is the main focus. The action describes what\'s happening, usually what the object is doing. The context describes the surrounding environment. If there is no text found in the image, then there is no need to mention it. You should not begin the description with any variation of "The image". Include {{BRAND}} as a brand and connect the topic to the brand in a relevant way. Return ONLY the alt text, nothing else.',
	];

	$settings                       = get_option( 'bymu_settings', [] );
	$settings                       = wp_parse_args( $settings, $defaults );
	$settings['batch_size']         = 15;
	$settings['max_upload_size_mb'] = 4;
	$settings['throttle_delay_ms']  = 180;
	
	// Cache for request lifetime (reduces repeated get_option calls).
	wp_cache_set( $cache_key, $settings, 'bymu_settings' );
	
	return $settings;
}

/**
 * Get enforced upload size limit in MB.
 *
 * @return int
 */
function bymu_get_upload_limit_mb() {
	$settings = get_option( 'bymu_settings', [] );
	$limit    = 4;

	if ( isset( $settings['max_upload_size_mb'] ) && $settings['max_upload_size_mb'] > 0 ) {
		$limit = absint( $settings['max_upload_size_mb'] );
	}

	/**
	 * Filter the maximum CSV upload size (in MB).
	 *
	 * @param int $limit Current limit.
	 */
	$limit = (int) apply_filters( 'bymu_upload_limit_mb', $limit );

	return max( 1, $limit );
}

/**
 * Enable test mode for a user for a limited duration.
 *
 * @param int $user_id  User ID.
 * @param int $duration Duration in seconds (default 900 = 15 minutes).
 */
function bymu_enable_test_mode_for_user( $user_id, $duration = 900 ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return;
	}

	$duration = max( 60, absint( $duration ) ); // Minimum 1 minute.
	$expires  = time() + $duration;

	update_user_meta( $user_id, 'bymu_test_mode_until', $expires );
}

/**
 * Disable test mode for a user.
 *
 * @param int $user_id Optional user ID. Defaults to current user.
 */
function bymu_disable_test_mode_for_user( $user_id = 0 ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return;
	}

	delete_user_meta( $user_id, 'bymu_test_mode_until' );
}

/**
 * Check if test mode is enabled for a user.
 *
 * @param int $user_id Optional user ID. Defaults to current user.
 * @return bool True if test mode is enabled.
 */
function bymu_is_test_mode_enabled( $user_id = 0 ) {
	if ( defined( 'BYMU_FORCE_TEST_MODE' ) && BYMU_FORCE_TEST_MODE ) {
		return true;
	}

	if ( ! $user_id ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();
	}

	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return false;
	}

	$expires = (int) get_user_meta( $user_id, 'bymu_test_mode_until', true );

	if ( $expires && $expires >= time() ) {
		return true;
	}

	if ( $expires ) {
		delete_user_meta( $user_id, 'bymu_test_mode_until' );
	}

	return false;
}

/**
 * Get Yoast meta keys mapping.
 *
 * @return array Associative array of field => meta_key.
 */
function bymu_get_yoast_meta_keys() {
	$provider = bymu_get_active_seo_provider();

	if ( $provider ) {
		return $provider->get_meta_keys();
	}

	return [
		'meta_title'       => '_yoast_wpseo_title',
		'meta_description' => '_yoast_wpseo_metadesc',
		'focus_keyword'    => '_yoast_wpseo_focuskw',
	];
}

/**
 * Get current Yoast meta for a post.
 *
 * @param int $post_id Post ID.
 * @return array Associative array of field => value.
 */
function bymu_get_current_yoast_meta( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return [
			'meta_title'       => '',
			'meta_description' => '',
			'focus_keyword'    => '',
		];
	}

	$cache_key = 'post_' . $post_id;
	$cached    = wp_cache_get( $cache_key, 'bymu_current_meta' );

	if ( false !== $cached ) {
		return $cached;
	}

	$provider = bymu_get_active_seo_provider();

	if ( $provider ) {
		$meta = $provider->get_current_meta( $post_id );
	} else {
		$meta = [
			'meta_title'       => '',
			'meta_description' => '',
			'focus_keyword'    => '',
		];
	}

	wp_cache_set( $cache_key, $meta, 'bymu_current_meta', MINUTE_IN_SECONDS );

	return $meta;
}

/**
 * Prime the cached Yoast/AIOSEO meta for an array of posts.
 *
 * @param array $post_ids Post IDs.
 * @return void
 */
function bymu_prime_current_yoast_meta( $post_ids ) {
	$post_ids = array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) );

	if ( empty( $post_ids ) ) {
		return;
	}

	$provider = bymu_get_active_seo_provider();

	if ( ! $provider ) {
		return;
	}

	$uncached = [];

	foreach ( $post_ids as $post_id ) {
		$cache_key = 'post_' . $post_id;
		if ( false === wp_cache_get( $cache_key, 'bymu_current_meta' ) ) {
			$uncached[] = $post_id;
		}
	}

	if ( empty( $uncached ) ) {
		return;
	}

	$batch = $provider->prime_meta_cache( $uncached );

	if ( ! is_array( $batch ) ) {
		$batch = [];
	}

	foreach ( $uncached as $post_id ) {
		$meta = $batch[ $post_id ] ?? $provider->get_current_meta( $post_id );
		wp_cache_set( 'post_' . $post_id, $meta, 'bymu_current_meta', MINUTE_IN_SECONDS );
	}
}

/**
 * Format bytes to human-readable size.
 *
 * @param int $bytes Bytes.
 * @return string Formatted size.
 */
function bymu_format_bytes( $bytes ) {
	$units  = [ 'B', 'KB', 'MB', 'GB' ];
	$bytes  = max( $bytes, 0 );
	$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
	$pow    = min( $pow, count( $units ) - 1 );
	$bytes /= pow( 1024, $pow );

	return round( $bytes, 2 ) . ' ' . $units[ $pow ];
}

/**
 * Check if value exceeds character limit.
 *
 * @param string $value Value to check.
 * @param string $field Field type (title, description, keyword).
 * @return bool|int False if OK, character count if exceeds limit.
 */
function bymu_check_char_limit( $value, $field ) {
	$settings = bymu_get_settings();
	$length   = mb_strlen( $value );

	$limits = [
		'meta_title'       => $settings['title_warning_chars'],
		'meta_description' => $settings['desc_warning_chars'],
		'focus_keyword'    => $settings['keyword_warning_chars'],
	];

	if ( isset( $limits[ $field ] ) && $length > $limits[ $field ] ) {
		return $length;
	}

	return false;
}

/**
 * Generate unique job hash.
 *
 * @return string 32-character hash.
 */
function bymu_generate_job_hash() {
	$user_id   = get_current_user_id();
	$timestamp = microtime( true );
	$random    = wp_rand( 1000, 9999 );
	$unique    = uniqid( '', true );
	
	return md5( $user_id . '-' . $timestamp . '-' . $random . '-' . $unique );
}

/**
 * Get post type labels (for UI display).
 *
 * @return array Associative array of post_type => label.
 */
function bymu_get_post_type_options() {
	$post_types = get_post_types( [ 'public' => true ], 'objects' );
	$options    = [];

	foreach ( $post_types as $post_type ) {
		if ( 'attachment' !== $post_type->name ) {
			$options[ $post_type->name ] = $post_type->label;
		}
	}

	return $options;
}

/**
 * Check if Yoast SEO is active.
 *
 * @return bool
 */
function bymu_is_yoast_active() {
	return defined( 'WPSEO_VERSION' );
}

/**
 * Check if All in One SEO is active.
 *
 * @return bool
 */
function bymu_is_aioseo_active() {
	return defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' );
}

/**
 * Get Yoast SEO version.
 *
 * @return string|false
 */
function bymu_get_yoast_version() {
	return bymu_is_yoast_active() ? WPSEO_VERSION : false;
}

/**
 * Get All in One SEO version.
 *
 * @return string|false
 */
function bymu_get_aioseo_version() {
	return defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : false;
}

/**
 * Get a list of supported SEO plugin labels.
 *
 * @return array
 */
function bymu_get_supported_seo_plugins() {
	return [
		'yoast'  => __( 'Yoast SEO', 'bulk-yoast-meta-updater' ),
		'aioseo' => __( 'All in One SEO', 'bulk-yoast-meta-updater' ),
	];
}

/**
 * Get the active SEO provider instance.
 *
 * @return Bulk_Yoast_Meta_Updater_Abstract_SEO_Provider|false
 */
function bymu_get_active_seo_provider() {
	static $provider = null;

	if ( null !== $provider ) {
		return $provider;
	}

	if ( bymu_is_yoast_active() ) {
		$provider = new Bulk_Yoast_Meta_Updater_Yoast_Provider();
		return $provider;
	}

	if ( bymu_is_aioseo_active() ) {
		$provider = new Bulk_Yoast_Meta_Updater_AIOSEO_Provider();
		return $provider;
	}

	$provider = false;

	return $provider;
}

/**
 * Get the label for the currently active SEO provider.
 *
 * @return string
 */
function bymu_get_active_seo_provider_label() {
	$provider = bymu_get_active_seo_provider();

	return $provider ? $provider->get_label() : '';
}

/**
 * Log database errors.
 *
 * @param string $operation Operation being performed.
 * @param mixed  $error     Error object or message.
 * @param array  $context   Additional context.
 * @return void
 */
function bymu_log_db_error( $operation, $error, $context = [] ) {
	global $wpdb;
	
	// Build error message.
	$error_msg = sprintf(
		'[BYMU DB Error] Operation: %s | Error: %s',
		$operation,
		is_wp_error( $error ) ? $error->get_error_message() : (string) $error
	);
	
	// Add context if provided.
	if ( ! empty( $context ) ) {
		$error_msg .= ' | Context: ' . wp_json_encode( $context );
	}
	
	// Add MySQL error if available.
	if ( ! empty( $wpdb->last_error ) ) {
		$error_msg .= ' | MySQL: ' . $wpdb->last_error;
	}
	
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $error_msg );
	
	// Store in transient for admin notice (expires in 1 hour).
	$errors = get_transient( 'bymu_db_errors' );
	if ( ! is_array( $errors ) ) {
		$errors = [];
	}
	
	$errors[] = [
		'timestamp' => current_time( 'mysql' ),
		'operation' => $operation,
		'message'   => is_wp_error( $error ) ? $error->get_error_message() : (string) $error,
		'context'   => $context,
	];
	
	// Keep only last 50 errors.
	$errors = array_slice( $errors, -50 );
	
	set_transient( 'bymu_db_errors', $errors, HOUR_IN_SECONDS );
}

/**
 * Get recent database errors.
 *
 * @param int $limit Number of errors to retrieve.
 * @return array Recent errors.
 */
function bymu_get_recent_db_errors( $limit = 10 ) {
	$errors = get_transient( 'bymu_db_errors' );
	
	if ( ! is_array( $errors ) ) {
		return [];
	}
	
	return array_slice( array_reverse( $errors ), 0, $limit );
}

/**
 * Clear database error log.
 *
 * @return bool Success.
 */
function bymu_clear_db_errors() {
	return delete_transient( 'bymu_db_errors' );
}

/**
 * Determine if current admin screen belongs to this plugin.
 *
 * @return bool
 */
function bymu_is_plugin_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for screen detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return in_array(
			$page,
			[
				'bulk-yoast-meta-updater',
				'bulk-yoast-meta-import',
				'bulk-yoast-meta-ai-updates',
				'bulk-yoast-meta-settings',
				'bulk-yoast-meta-image-alt',
				'bulk-yoast-meta-setup',
			],
			true
		);
	}

	$screen = get_current_screen();

	if ( ! $screen ) {
		return false;
	}

	$ids = [
		'toplevel_page_bulk-yoast-meta-updater',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-import',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-ai-updates',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-settings',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-image-alt',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-setup',
		'admin_page_bulk-yoast-meta-setup',
	];

	/**
	 * Allow developers to add/remove plugin screen IDs for perf optimizations.
	 *
	 * @param array $ids Screen IDs.
	 */
	$ids = apply_filters( 'bymu_plugin_screen_ids', $ids );

	if ( in_array( $screen->id, $ids, true ) ) {
		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for screen detection.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	return in_array(
		$page,
		[
			'bulk-yoast-meta-updater',
			'bulk-yoast-meta-import',
			'bulk-yoast-meta-ai-updates',
			'bulk-yoast-meta-settings',
			'bulk-yoast-meta-image-alt',
			'bulk-yoast-meta-setup',
		],
		true
	);
}

/**
 * Clear all plugin caches.
 *
 * Useful after bulk updates or debugging.
 *
 * @return void
 */
function bymu_clear_all_caches() {
	global $wpdb;
	
	// Clear object cache for settings.
	wp_cache_delete( 'bymu_settings_v1', 'bymu_settings' );
	
	// Clear all markdown transients (rendered content cache).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_bymu_markdown_%'
		)
	);
	
	// Clear database error log.
	bymu_clear_db_errors();
}

/**
 * Trigger Yoast reindex for a post.
 *
 * @param int $post_id Post ID.
 * @return bool True on success.
 */
function bymu_trigger_yoast_reindex( $post_id ) {
	$provider = bymu_get_active_seo_provider();

	if ( $provider ) {
		// Clear cached markdown for this post (performance optimization).
		delete_transient( 'bymu_markdown_' . $post_id );
		$provider->trigger_reindex( $post_id );
	}

	return true;
}

/**
 * Get database table name with prefix.
 *
 * @param string $table Table name without prefix (jobs or actions).
 * @return string Full table name with prefix.
 */
function bymu_get_table_name( $table ) {
	global $wpdb;
	return $wpdb->prefix . 'bymu_' . $table;
}

/**
 * Generate a random test phrase of specific length.
 *
 * @param int $length Target character length.
 * @return string Random phrase.
 */
function bymu_generate_test_phrase( $length ) {
	$words = [
		'SEO',
		'optimized',
		'content',
		'marketing',
		'strategy',
		'digital',
		'website',
		'performance',
		'analysis',
		'keyword',
		'research',
		'traffic',
		'conversion',
		'engagement',
		'visibility',
		'ranking',
		'search',
		'engine',
		'optimization',
		'organic',
		'growth',
		'meta',
		'title',
		'description',
		'quality',
		'unique',
		'compelling',
		'effective',
		'professional',
		'expert',
		'comprehensive',
		'advanced',
		'proven',
		'results',
		'success',
		'innovative',
		'creative',
		'targeted',
		'focused',
		'measurable',
		'actionable',
		'data-driven',
		'strategic',
		'tactical',
	];

	$phrase    = 'Test ' . time() . ' - ';
	$remaining = $length - strlen( $phrase );

	while ( strlen( $phrase ) < $length - 10 ) {
		$word = $words[ array_rand( $words ) ];
		if ( strlen( $phrase ) + strlen( $word ) + 1 <= $length ) {
			$phrase .= $word . ' ';
		} else {
			break;
		}
	}

	// Pad to exact length if needed.
	$phrase = trim( $phrase );
	if ( strlen( $phrase ) < $length ) {
		$phrase .= ' ' . substr( str_repeat( 'xyz', $length ), 0, $length - strlen( $phrase ) - 1 );
	}

	return substr( trim( $phrase ), 0, $length );
}

/**
 * Normalize Gemini model data returned by the API.
 *
 * @param array $raw_models Raw models.
 * @return array Normalized models.
 */
function bymu_normalize_gemini_models( $raw_models ) {
	$normalized = [];

	foreach ( $raw_models as $model ) {
		$raw_name = isset( $model['name'] ) ? $model['name'] : '';
		$raw_name = is_string( $raw_name ) ? $raw_name : '';

		if ( '' === $raw_name ) {
			continue;
		}

		$model_id = str_replace( 'models/', '', $raw_name );
		$model_id = is_string( $model_id ) ? $model_id : '';

		if ( '' === $model_id ) {
			continue;
		}

		$display_name = isset( $model['displayName'] ) ? $model['displayName'] : $model_id;
		$outputs      = [];
		$inputs       = [];
		$methods      = [];

		if ( ! empty( $model['outputModalities'] ) && is_array( $model['outputModalities'] ) ) {
			$outputs = array_map( 'strtolower', $model['outputModalities'] );
		}

		if ( ! empty( $model['inputModalities'] ) && is_array( $model['inputModalities'] ) ) {
			$inputs = array_map( 'strtolower', $model['inputModalities'] );
		}

		if ( ! empty( $model['supportedGenerationMethods'] ) && is_array( $model['supportedGenerationMethods'] ) ) {
			$methods = array_map( 'strtolower', $model['supportedGenerationMethods'] );
		}

		$category = 'text';

		if ( '' !== $model_id && false !== strpos( $model_id, 'image' ) ) {
			$category = 'image';
		} elseif ( '' !== $model_id && false !== strpos( $model_id, 'tts' ) ) {
			$category = 'tts';
		} elseif ( ! in_array( 'text', $outputs, true ) ) {
			$category = 'other';
		}

		$normalized[] = [
			'id'           => $model_id,
			'display_name' => $display_name,
			'outputs'      => $outputs,
			'inputs'       => $inputs,
			'methods'      => $methods,
			'category'     => $category,
		];
	}

	return $normalized;
}

/**
 * Fetch Gemini models from the API.
 *
 * @param string $api_key Gemini API key.
 * @return array|WP_Error Normalized list or error.
 */
function bymu_fetch_gemini_models_from_api( $api_key ) {
	if ( empty( $api_key ) ) {
		return new WP_Error( 'missing_api_key', __( 'Google Gemini API key not configured.', 'bulk-yoast-meta-updater' ) );
	}

	$url = add_query_arg(
		[
			'key'      => $api_key,
			'pageSize' => 200,
		],
		'https://generativelanguage.googleapis.com/v1beta/models'
	);

	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get, WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
	$response = wp_remote_get(
		$url,
		[
			'timeout' => 20,
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$body   = wp_remote_retrieve_body( $response );

	if ( 200 !== $status ) {
		return new WP_Error(
			'api_error',
			sprintf(
				/* translators: 1: Status code, 2: Response body */
				__( 'Gemini API error %1$d: %2$s', 'bulk-yoast-meta-updater' ),
				$status,
				substr( $body, 0, 200 )
			)
		);
	}

	$data = json_decode( $body, true );

	if ( empty( $data['models'] ) || ! is_array( $data['models'] ) ) {
		return new WP_Error( 'api_error', __( 'Gemini API returned an unexpected response.', 'bulk-yoast-meta-updater' ) );
	}

	return bymu_normalize_gemini_models( $data['models'] );
}

/**
 * Cache Gemini models.
 *
 * @param array $models Models to cache.
 * @param int   $ttl    Cache lifetime in seconds.
 * @return void
 */
function bymu_cache_gemini_models( $models, $ttl = DAY_IN_SECONDS ) {
	set_transient( 'bymu_gemini_models', array_values( $models ), $ttl );
}

/**
 * Retrieve cached Gemini models.
 *
 * @return array
 */
function bymu_get_cached_gemini_models() {
	$cached = get_transient( 'bymu_gemini_models' );

	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	return [];
}

/**
 * Retrieve Gemini models, fetching from API if needed.
 *
 * @param string $api_key Optional API key override.
 * @return array|WP_Error
 */
function bymu_get_or_fetch_gemini_models( $api_key = '' ) {
	$cached = bymu_get_cached_gemini_models();
	if ( ! empty( $cached ) ) {
		// Ensure defaults are always included even in cached data.
		return bymu_merge_models_with_defaults( $cached );
	}

	if ( empty( $api_key ) ) {
		$settings = bymu_get_settings();
		$api_key  = $settings['gemini_api_key'] ?? '';
	}

	if ( empty( $api_key ) ) {
		return bymu_get_default_gemini_models();
	}

	$fetched = bymu_fetch_gemini_models_from_api( $api_key );
	if ( is_wp_error( $fetched ) ) {
		// Return defaults on error, not the error itself.
		return bymu_get_default_gemini_models();
	}

	$merged = bymu_merge_models_with_defaults( $fetched );
	bymu_cache_gemini_models( $merged );

	return $merged;
}

/**
 * Merge fetched models with defaults (ensuring uniqueness).
 *
 * @param array $models Models fetched from API.
 * @return array
 */
function bymu_merge_models_with_defaults( $models ) {
	$combined = [];
	$seen     = [];

	// Process API models first (they take precedence), then defaults fill in gaps.
	$all_models = array_merge( $models, bymu_get_default_gemini_models() );

	foreach ( $all_models as $model ) {
		$model_id = $model['id'] ?? ( $model['name'] ?? '' );
		$model_id = is_string( $model_id ) ? $model_id : (string) $model_id;

		if ( '' === $model_id ) {
			continue;
		}

		// Normalize model ID (remove 'models/' prefix if present).
		$model_id = str_replace( 'models/', '', $model_id );

		// Skip if we've already seen this model ID (first occurrence wins - API models processed first).
		if ( isset( $seen[ $model_id ] ) ) {
			continue;
		}

		if ( ! isset( $model['display_name'] ) || '' === $model['display_name'] ) {
			$model['display_name'] = $model_id;
		}

		if ( ! isset( $model['category'] ) ) {
			if ( false !== strpos( $model_id, 'image' ) ) {
				$model['category'] = 'image';
			} elseif ( false !== strpos( $model_id, 'tts' ) ) {
				$model['category'] = 'tts';
			} else {
				$model['category'] = 'text';
			}
		}

		// Ensure ID is set correctly.
		$model['id'] = $model_id;

		$combined[]        = $model;
		$seen[ $model_id ] = true;
	}

	return $combined;
}

/**
 * Split models by category for UI consumption.
 *
 * @param array $models Normalized models.
 * @return array
 */
function bymu_split_gemini_models_by_category( $models ) {
	$result = [
		'text'  => [],
		'image' => [],
		'tts'   => [],
		'other' => [],
	];

	foreach ( $models as $model ) {
		$category = $model['category'] ?? 'other';
		if ( ! isset( $result[ $category ] ) ) {
			$result[ $category ] = [];
		}
		$result[ $category ][] = $model;
	}

	foreach ( $result as $category => $list ) {
		if ( empty( $list ) ) {
			continue;
		}

		usort(
			$list,
			function ( $a, $b ) {
				$id_a = strtolower( $a['id'] ?? '' );
				$id_b = strtolower( $b['id'] ?? '' );
				
				// Prioritize 2.5 models first.
				$is_25_a = ( false !== strpos( $id_a, '2.5' ) || false !== strpos( $id_a, '2-5' ) );
				$is_25_b = ( false !== strpos( $id_b, '2.5' ) || false !== strpos( $id_b, '2-5' ) );
				
				if ( $is_25_a && ! $is_25_b ) {
					return -1; // A comes first.
				}
				if ( ! $is_25_a && $is_25_b ) {
					return 1; // B comes first.
				}
				
				// Within same version group, prioritize recommended models.
				$is_recommended_a = ( false !== strpos( $id_a, 'flash' ) && false === strpos( $id_a, 'lite' ) && false === strpos( $id_a, 'preview' ) );
				$is_recommended_b = ( false !== strpos( $id_b, 'flash' ) && false === strpos( $id_b, 'lite' ) && false === strpos( $id_b, 'preview' ) );
				
				if ( $is_recommended_a && ! $is_recommended_b ) {
					return -1;
				}
				if ( ! $is_recommended_a && $is_recommended_b ) {
					return 1;
				}
				
				// Then sort alphabetically by display name.
				$label_a = isset( $a['display_name'] ) && $a['display_name'] ? strtolower( $a['display_name'] ) : $id_a;
				$label_b = isset( $b['display_name'] ) && $b['display_name'] ? strtolower( $b['display_name'] ) : $id_b;

				return strcmp( $label_a, $label_b );
			}
		);

		$result[ $category ] = $list;
	}

	return $result;
}

/**
 * Determine if a model ID exists in list.
 *
 * @param array  $models   Models array.
 * @param string $model_id Model identifier.
 * @return bool
 */
function bymu_model_exists( $models, $model_id ) {
	foreach ( $models as $model ) {
		if ( isset( $model['id'] ) && $model['id'] === $model_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Apply test SEO data to posts with logging.
 *
 * @param array $post_ids Array of post IDs to update.
 * @param int   $job_id   Optional job ID for logging.
 * @return array Results with success count, errors, and job_id.
 */
function bymu_apply_test_seo_data( $post_ids, $job_id = 0 ) {
	$results = [
		'success' => 0,
		'failed'  => 0,
		'errors'  => [],
		'job_id'  => $job_id,
	];

	$provider = bymu_get_active_seo_provider();

	if ( ! $provider ) {
		$results['errors'][] = __( 'No supported SEO plugin is active.', 'bulk-yoast-meta-updater' );
		$results['failed']   = count( $post_ids );
		return $results;
	}

	// Create job log if not provided.
	if ( ! $job_id ) {
		$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
			[
				'job_hash'   => bymu_generate_job_hash(),
				'file_name'  => 'test-seo-updates-' . gmdate( 'Y-m-d-His' ) . '.csv',
				'status'     => 'processing',
				'total_rows' => count( $post_ids ),
				'settings'   => [
					'type'       => 'test',
					'post_count' => count( $post_ids ),
				],
			]
		);

		if ( ! $job_id ) {
			$results['errors'][] = 'Failed to create job log.';
			$results['failed']   = count( $post_ids );
			return $results;
		}

		$results['job_id'] = $job_id;
	}

	$row_number = 0;

	foreach ( $post_ids as $post_id ) {
		++$row_number;
		$post_id = absint( $post_id );
		
		if ( ! $post_id || ! get_post( $post_id ) ) {
			$error_msg           = sprintf( 'Invalid post ID: %d', $post_id );
			$results['errors'][] = $error_msg;
			++$results['failed'];
			
			// Log error.
			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row' => $row_number,
					'post_id' => $post_id,
					'status'  => 'error',
					'message' => $error_msg,
				]
			);
			continue;
		}

		// Get current values for logging.
		$current_meta    = $provider->get_current_meta( $post_id );
		$current_title   = isset( $current_meta['meta_title'] ) ? $current_meta['meta_title'] : '';
		$current_desc    = isset( $current_meta['meta_description'] ) ? $current_meta['meta_description'] : '';
		$current_keyword = isset( $current_meta['focus_keyword'] ) ? $current_meta['focus_keyword'] : '';

		// Generate unique test data.
		$test_title       = bymu_generate_test_phrase( 60 );
		$test_description = bymu_generate_test_phrase( 155 );
		$test_keyphrase   = 'Keyphrase (' . $post_id . ')';

		$values = [
			'meta_title'       => $test_title,
			'meta_description' => $test_description,
			'focus_keyword'    => $test_keyphrase,
		];

		$update_result = $provider->update_meta( $post_id, $values );

		if ( is_wp_error( $update_result ) ) {
			$results['errors'][] = $update_result->get_error_message();
			++$results['failed'];
		
			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row' => $row_number,
					'post_id' => $post_id,
					'status'  => 'error',
					'message' => $update_result->get_error_message(),
				]
			);
			continue;
		}

		$verify_meta    = $provider->get_current_meta( $post_id );
		$verify_title   = isset( $verify_meta['meta_title'] ) ? $verify_meta['meta_title'] : '';
		$verify_desc    = isset( $verify_meta['meta_description'] ) ? $verify_meta['meta_description'] : '';
		$verify_keyword = isset( $verify_meta['focus_keyword'] ) ? $verify_meta['focus_keyword'] : '';

		if ( $verify_title === $test_title && $verify_desc === $test_description && $verify_keyword === $test_keyphrase ) {
			// Log each field update.
			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row'   => $row_number,
					'post_id'   => $post_id,
					'url'       => get_permalink( $post_id ),
					'field'     => 'meta_title',
					'old_value' => $current_title,
					'new_value' => $test_title,
					'status'    => 'ok',
					'message'   => 'Test data applied',
				]
			);

			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row'   => $row_number,
					'post_id'   => $post_id,
					'url'       => get_permalink( $post_id ),
					'field'     => 'meta_description',
					'old_value' => $current_desc,
					'new_value' => $test_description,
					'status'    => 'ok',
					'message'   => 'Test data applied',
				]
			);

			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row'   => $row_number,
					'post_id'   => $post_id,
					'url'       => get_permalink( $post_id ),
					'field'     => 'focus_keyword',
					'old_value' => $current_keyword,
					'new_value' => $test_keyphrase,
					'status'    => 'ok',
					'message'   => 'Test data applied',
				]
			);

			// Trigger Yoast reindex.
			bymu_trigger_yoast_reindex( $post_id );
			++$results['success'];
		} else {
			$error_msg           = sprintf( 'Verification failed for post ID: %d', $post_id );
			$results['errors'][] = $error_msg;
			++$results['failed'];

			// Log error.
			Bulk_Yoast_Meta_Updater_Logger::log_action(
				$job_id,
				[
					'csv_row' => $row_number,
					'post_id' => $post_id,
					'url'     => get_permalink( $post_id ),
					'status'  => 'error',
					'message' => $error_msg,
				]
			);
		}
	}

	// Update job stats.
	if ( $job_id ) {
		Bulk_Yoast_Meta_Updater_Logger::update_job(
			$job_id,
			[
				'status'         => 'completed',
				'completed_at'   => current_time( 'mysql' ),
				'processed_rows' => $row_number,
				'updated_rows'   => $results['success'],
				'error_rows'     => $results['failed'],
			]
		);
	}

	return $results;
}
