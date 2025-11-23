<?php
/**
 * URL/Post Resolver Class
 *
 * Resolves URLs to post IDs and validates posts.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Resolver
 */
class Bulk_Yoast_Meta_Updater_Resolver {

	/**
	 * Allowed post types (empty for all).
	 *
	 * @var array
	 */
	private $allowed_post_types = [];

	/**
	 * URL resolution mode (strict or lenient).
	 *
	 * @var string
	 */
	private $url_mode = 'lenient';

	/**
	 * Constructor.
	 *
	 * @param array  $allowed_post_types Allowed post types.
	 * @param string $url_mode          URL resolution mode (lenient = path only, strict = full URL).
	 */
	public function __construct( $allowed_post_types = [], $url_mode = 'lenient' ) {
		$this->allowed_post_types = $allowed_post_types;
		$this->url_mode           = $url_mode;
	}

	/**
	 * Resolve post ID from row data.
	 *
	 * @param array $row Row data with post_id and/or url.
	 * @return int|WP_Error Post ID or error.
	 */
	public function resolve( $row ) {
		$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
		$url     = isset( $row['url'] ) ? trim( $row['url'] ) : '';

		// If post_id provided, validate it.
		if ( $post_id > 0 ) {
			$validation = $this->validate_post_id( $post_id );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			return $post_id;
		}

		// If URL provided, resolve it.
		if ( ! empty( $url ) ) {
			return $this->resolve_url( $url );
		}

		// Neither provided.
		return new WP_Error(
			'missing_identifier',
			__( 'Row must have either post_id or url.', 'bulk-yoast-meta-updater' )
		);
	}

	/**
	 * Validate post ID exists and is allowed.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error True or error.
	 */
	private function validate_post_id( $post_id ) {
		$post = get_post( $post_id );

		// Post doesn't exist.
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf(
					/* translators: %d: Post ID */
					__( 'Post ID %d not found.', 'bulk-yoast-meta-updater' ),
					$post_id
				)
			);
		}

		// Post is in trash.
		if ( 'trash' === $post->post_status ) {
			return new WP_Error(
				'post_in_trash',
				sprintf(
					/* translators: %d: Post ID */
					__( 'Post ID %d is in trash.', 'bulk-yoast-meta-updater' ),
					$post_id
				)
			);
		}

		// Check post type filter.
		if ( ! empty( $this->allowed_post_types ) && ! in_array( $post->post_type, $this->allowed_post_types, true ) ) {
			return new WP_Error(
				'post_type_not_allowed',
				sprintf(
					/* translators: 1: Post ID, 2: Post type */
					__( 'Post ID %1$d has post type "%2$s" which is not in the selected filter.', 'bulk-yoast-meta-updater' ),
					$post_id,
					$post->post_type
				)
			);
		}

		return true;
	}

	/**
	 * Resolve URL to post ID.
	 *
	 * @param string $url URL to resolve.
	 * @return int|WP_Error Post ID or error.
	 */
	private function resolve_url( $url ) {
		// Normalize URL.
		$normalized_url = $this->normalize_url( $url );
		
		if ( is_wp_error( $normalized_url ) ) {
			return $normalized_url;
		}

		// Try WordPress core function first (use VIP version if available).
		if ( function_exists( 'wpcom_vip_url_to_postid' ) ) {
			$post_id = wpcom_vip_url_to_postid( $normalized_url );
		} else {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			$post_id = url_to_postid( $normalized_url );
		}
		
		if ( $post_id > 0 ) {
			$validation = $this->validate_post_id( $post_id );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			return $post_id;
		}

		// Fallback: Try path-based lookup.
		$post_id = $this->resolve_by_path( $normalized_url );
		
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $post_id > 0 ) {
			$validation = $this->validate_post_id( $post_id );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			return $post_id;
		}

		// Not found.
		return new WP_Error(
			'url_not_resolved',
			sprintf(
				/* translators: %s: URL */
				__( 'Could not find post for URL: %s', 'bulk-yoast-meta-updater' ),
				esc_url( $url )
			)
		);
	}

	/**
	 * Normalize URL for matching.
	 *
	 * @param string $url URL to normalize.
	 * @return string|WP_Error Normalized URL or error.
	 */
	private function normalize_url( $url ) {
		// Remove fragments and queries.
		$url = strtok( $url, '#' );
		$url = strtok( $url, '?' );

		// Parse URL.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parsed = parse_url( $url );
		
		if ( false === $parsed ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL format.', 'bulk-yoast-meta-updater' ) );
		}

		// Check domain in strict mode.
		if ( 'strict' === $this->url_mode ) {
			$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
			$url_host  = isset( $parsed['host'] ) ? $parsed['host'] : '';

			if ( $url_host !== $site_host && ! empty( $url_host ) ) {
				return new WP_Error(
					'domain_mismatch',
					sprintf(
						/* translators: 1: URL host, 2: Site host */
						__( 'URL domain "%1$s" does not match site domain "%2$s".', 'bulk-yoast-meta-updater' ),
						$url_host,
						$site_host
					)
				);
			}
		}

		// Decode URL.
		$url = urldecode( $url );

		return $url;
	}

	/**
	 * Resolve URL by path (fallback method).
	 *
	 * @param string $url URL to resolve.
	 * @return int|WP_Error Post ID or error.
	 */
	private function resolve_by_path( $url ) {
		// Get path from URL.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parsed = parse_url( $url );
		$path   = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';

		if ( empty( $path ) ) {
			return new WP_Error( 'empty_path', __( 'URL path is empty.', 'bulk-yoast-meta-updater' ) );
		}

		// Get last segment of path as post name.
		$segments  = explode( '/', $path );
		$post_name = end( $segments );
		$post_name = sanitize_title( $post_name );

		// Query by post_name.
		$args = [
			'name'           => $post_name,
			'post_type'      => ! empty( $this->allowed_post_types ) ? $this->allowed_post_types : 'any',
			'post_status'    => [ 'publish', 'private', 'draft', 'pending' ],
			'posts_per_page' => 2, // Get 2 to check for ambiguity.
			'fields'         => 'ids',
		];

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return 0; // Not found.
		}

		if ( count( $posts ) > 1 ) {
			return new WP_Error(
				'ambiguous_url',
				sprintf(
					/* translators: %s: URL */
					__( 'Multiple posts found for URL: %s', 'bulk-yoast-meta-updater' ),
					esc_url( $url )
				)
			);
		}

		return $posts[0];
	}

	/**
	 * Get post info for display.
	 *
	 * @param int $post_id Post ID.
	 * @return array Post info.
	 */
	public function get_post_info( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return [
				'id'        => 0,
				'title'     => '',
				'type'      => '',
				'status'    => '',
				'edit_link' => '',
			];
		}

		return [
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'edit_link' => get_edit_post_link( $post->ID ),
		];
	}
}
