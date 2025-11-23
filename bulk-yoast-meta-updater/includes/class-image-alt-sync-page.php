<?php
/**
 * Image Alt Texts Page
 *
 * Allows syncing attachment alt text across post content.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page
 */
class Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page {

	const PER_PAGE = 20;

	/**
	 * Render the image alt sync page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		$paged        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
		$view_mode    = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
		$is_top_view  = ( 'top' === $view_mode );
		$is_short_alt = ( 'short-alt' === $view_mode );

		$total_items = 0;
		if ( $is_top_view ) {
			$attachments = $this->get_top_attachments( $search, $total_items, 100 );
			$total_pages = 1;
			$paged       = 1;
		} elseif ( $is_short_alt ) {
			$attachments = $this->get_short_alt_attachments( $paged, $search, $total_items );
			$total_pages = $total_items > 0 ? ceil( $total_items / self::PER_PAGE ) : 1;
		} else {
			$attachments = $this->get_attachments( $paged, $search, $total_items );
			$total_pages = $total_items > 0 ? ceil( $total_items / self::PER_PAGE ) : 1;
		}
		?>
		<div class="wrap bymu-wrap">
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'Image Alt Texts', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle"><?php esc_html_e( 'Review attachment alt text, generate AI suggestions, and sync updates across referenced posts.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>
					<div class="bymu-header-actions">
						<a class="button bymu-button-ghost bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings&tab=documentation' ) ); ?>">
							<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php bymu_render_admin_nav( 'image-alt' ); ?>

			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-body">
					<form method="get">
						<input type="hidden" name="page" value="bulk-yoast-meta-image-alt" />
						<?php if ( $is_top_view ) : ?>
							<input type="hidden" name="view" value="top" />
						<?php endif; ?>
						<p class="search-box">
							<label class="screen-reader-text" for="bymu-image-search"><?php esc_html_e( 'Search Images', 'bulk-yoast-meta-updater' ); ?></label>
							<input type="search" id="bymu-image-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
							<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'bulk-yoast-meta-updater' ); ?>" />
						</p>
					</form>

					<?php
					$base_url     = admin_url( 'admin.php?page=bulk-yoast-meta-image-alt' );
					$top_url_args = [ 'view' => 'top' ];
					$default_args = [];
					if ( $search ) {
						$top_url_args['s'] = $search;
						$default_args['s'] = $search;
					}
					$top_url     = add_query_arg( $top_url_args, $base_url );
					$default_url = $default_args ? add_query_arg( $default_args, $base_url ) : $base_url;
					?>

					<div class="bymu-toolbar bymu-toolbar-split">
						<div class="bymu-toolbar-left">
							<?php if ( $is_top_view || $is_short_alt ) : ?>
								<a href="<?php echo esc_url( $default_url ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Return to Paginated View', 'bulk-yoast-meta-updater' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( ! $is_top_view ) : ?>
								<a href="<?php echo esc_url( $top_url ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Show Top 100 Most Used Images', 'bulk-yoast-meta-updater' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( ! $is_short_alt ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array_merge( $default_args, [ 'view' => 'short-alt' ] ), $base_url ) ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Show Short Alt Texts', 'bulk-yoast-meta-updater' ); ?>
								</a>
							<?php endif; ?>
						</div>
						<div class="bymu-toolbar-right">
							<div class="bymu-bulk-utility">
								<div class="bymu-bulk-utility-header">
									<strong><?php esc_html_e( 'Bulk Functions', 'bulk-yoast-meta-updater' ); ?></strong>
									<p><?php esc_html_e( 'Perform updates only on the images shown below.', 'bulk-yoast-meta-updater' ); ?></p>
								</div>

								<div class="bymu-bulk-step-grid">
									<div class="bymu-bulk-step">
										<div class="bymu-bulk-step-number">1</div>
										<div class="bymu-bulk-step-body">
											<div class="bymu-bulk-step-title"><?php esc_html_e( 'Generate Image Alt Text', 'bulk-yoast-meta-updater' ); ?></div>
											<div class="bymu-bulk-step-controls">
												<label for="bymu-bulk-alt-concurrency" class="bymu-bulk-alt-label">
													<?php esc_html_e( 'Batch operations', 'bulk-yoast-meta-updater' ); ?>
													<select id="bymu-bulk-alt-concurrency">
														<option value="1"><?php esc_html_e( '1 at a time (default)', 'bulk-yoast-meta-updater' ); ?></option>
														<option value="2">2</option>
														<option value="5">5</option>
														<option value="8">8</option>
													</select>
												</label>
												<button type="button" class="button button-primary" id="bymu-bulk-generate-alt">
													<?php esc_html_e( 'Generate Image Alt Text', 'bulk-yoast-meta-updater' ); ?>
												</button>
											</div>
										</div>
									</div>

									<div class="bymu-bulk-step">
										<div class="bymu-bulk-step-number">2</div>
										<div class="bymu-bulk-step-body">
											<div class="bymu-bulk-step-title"><?php esc_html_e( 'Save Generated Alt Text', 'bulk-yoast-meta-updater' ); ?></div>
											<div class="bymu-inline-save-controls">
												<button type="button" class="button button-primary" id="bymu-inline-save-all">
													<?php esc_html_e( 'Save Generated Alt Text', 'bulk-yoast-meta-updater' ); ?>
												</button>
												<button type="button" class="button" id="bymu-inline-cancel-save">
													<?php esc_html_e( 'Cancel Pending Changes', 'bulk-yoast-meta-updater' ); ?>
												</button>
											</div>
										</div>
									</div>

									<div class="bymu-bulk-step">
										<div class="bymu-bulk-step-number">3</div>
										<div class="bymu-bulk-step-body">
											<div class="bymu-bulk-step-title"><?php esc_html_e( 'Sync Alt Text In Content', 'bulk-yoast-meta-updater' ); ?></div>
											<div class="bymu-bulk-step-controls">
												<button type="button" class="button button-secondary" id="bymu-bulk-sync-alt" disabled>
													<?php esc_html_e( 'Sync Alt Text In Content', 'bulk-yoast-meta-updater' ); ?>
												</button>
											</div>
										</div>
									</div>
								</div>

								<div id="bymu-bulk-alt-status" class="bymu-bulk-alt-status" aria-live="polite"></div>
							</div>
						</div>
					</div>

					<?php if ( $is_top_view ) : ?>
						<div class="bymu-alert info">
							<div class="bymu-alert-icon dashicons dashicons-info"></div>
							<div class="bymu-alert-content">
								<p>
									<?php
									printf(
										/* translators: %d: number of images */
										esc_html__( 'Showing the top %d images sorted by total references across posts and pages.', 'bulk-yoast-meta-updater' ),
										min( 100, $total_items )
									);
									?>
								</p>
							</div>
						</div>
					<?php endif; ?>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Image', 'bulk-yoast-meta-updater' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Alt Text', 'bulk-yoast-meta-updater' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Referenced In', 'bulk-yoast-meta-updater' ); ?></th>
								<th scope="col" style="width: 160px;"><?php esc_html_e( 'Actions', 'bulk-yoast-meta-updater' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $attachments ) ) : ?>
								<tr>
									<td colspan="5"><?php esc_html_e( 'No attachments found matching your criteria.', 'bulk-yoast-meta-updater' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $attachments as $attachment ) : ?>
									<?php
									$attachment_id = $attachment->ID;
									$alt_text      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
									$alt_text      = $alt_text ? $alt_text : get_the_title( $attachment_id );
									$file_path     = get_attached_file( $attachment_id );
									$file_name     = $file_path ? wp_basename( $file_path ) : __( 'File not found', 'bulk-yoast-meta-updater' );
									$caption       = wp_get_attachment_caption( $attachment_id );
									
									// Lazy-load reference data to avoid slow queries on page load.
									// Only load if already cached or if this is the top view (where it's pre-calculated).
									if ( isset( $attachment->usage_posts ) ) {
										$reference_data = [
											'count' => absint( $attachment->usage_count ),
											'posts' => $attachment->usage_posts,
										];
										self::prime_post_cache( $reference_data['posts'] );
									} else {
										// Check cache first, but don't do expensive query on initial load.
										$cache_key = 'bymu_attachment_refs_' . $attachment_id;
										$cached    = get_transient( $cache_key );
										if ( false !== $cached ) {
											$reference_data = $cached;
											self::prime_post_cache( $reference_data['posts'] );
										} else {
											// Defer to AJAX - use placeholder.
											$reference_data = [
												'count'   => 0,
												'posts'   => [],
												'loading' => true, // Flag for AJAX loading.
											];
										}
									}
									$ref_count = absint( $reference_data['count'] );
									?>
									<tr data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
										<td class="column-image">
											<div class="bymu-image-stack">
												<a class="bymu-image-preview" href="<?php echo esc_url( get_edit_post_link( $attachment_id ) ); ?>">
													<?php echo wp_get_attachment_image( $attachment_id, [ 120, 120 ], true ); ?>
												</a>
												<div class="bymu-image-meta">
													<strong><?php echo esc_html( get_the_title( $attachment_id ) ); ?></strong>
													<div><code><?php echo esc_html( $file_name ); ?></code></div>
													<?php if ( $caption ) : ?>
														<p class="description"><?php echo esc_html( $caption ); ?></p>
													<?php endif; ?>
												</div>
											</div>
										</td>
										<td class="column-alt">
											<div class="bymu-alt-wrapper">
												<div class="bymu-current-alt" style="white-space: pre-line;"><?php echo esc_html( $alt_text ); ?></div>
												<div class="bymu-generated-alt" style="display:none;">
													<textarea class="regular-text" rows="3" style="width:100%;"></textarea>
													<div class="bymu-alt-actions" style="margin-top:6px; display:flex; gap:6px;">
														<button type="button" class="button button-primary button-small bymu-inline-save-alt" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>"><?php esc_html_e( 'Save', 'bulk-yoast-meta-updater' ); ?></button>
														<button type="button" class="button button-small bymu-inline-cancel-alt"><?php esc_html_e( 'Cancel', 'bulk-yoast-meta-updater' ); ?></button>
													</div>
												</div>
											</div>
										</td>
										<td class="column-references" data-attachment-ref-id="<?php echo esc_attr( $attachment_id ); ?>">
											<?php if ( isset( $reference_data['loading'] ) && $reference_data['loading'] ) : ?>
												<span class="bymu-loading-refs" style="display:inline-block;">
													<span class="spinner is-active" style="float:none;margin:0;"></span>
													<?php esc_html_e( 'Loading...', 'bulk-yoast-meta-updater' ); ?>
												</span>
											<?php elseif ( $ref_count > 0 ) : ?>
												<p class="bymu-reference-count">
													<?php
													printf(
														/* translators: %d: number of posts */
														esc_html__( '%d post(s)', 'bulk-yoast-meta-updater' ),
														$ref_count
													);
													?>
												</p>
												<ul class="bymu-reference-list">
													<?php foreach ( $reference_data['posts'] as $post_id ) : ?>
														<?php
														$post_title = get_the_title( $post_id );
														$edit_link  = get_edit_post_link( $post_id );
														?>
														<li>
															<?php if ( $edit_link && $post_title ) : ?>
																<a href="<?php echo esc_url( $edit_link ); ?>">
																	<?php echo esc_html( $post_title ); ?>
																</a>
															<?php else : ?>
																<?php echo esc_html( $post_title ? $post_title : sprintf( __( 'Post #%d', 'bulk-yoast-meta-updater' ), $post_id ) ); ?>
															<?php endif; ?>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<span class="dashicons dashicons-minus"></span>
											<?php endif; ?>
										</td>
										<td class="column-actions">
											<button
												type="button"
												class="button bymu-sync-alt-btn"
												data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>"
												data-ref-count="<?php echo esc_attr( $ref_count ); ?>">
												<?php esc_html_e( 'Sync Alt Text', 'bulk-yoast-meta-updater' ); ?>
											</button>
											<button
												type="button"
												class="button button-secondary bymu-inline-generate-alt-btn"
												data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>"
												style="margin-left:6px;">
												<?php esc_html_e( 'Generate Alt Text', 'bulk-yoast-meta-updater' ); ?>
											</button>
											<div class="bymu-sync-alt-status" style="margin-top:6px;"></div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<?php
					if ( ! $is_top_view ) {
						$base_args = [
							'paged' => '%#%',
							's'     => $search,
						];

						if ( $is_short_alt ) {
							$base_args['view'] = 'short-alt';
						}

						$pagination = paginate_links(
							[
								'base'      => add_query_arg( $base_args, $base_url ),
								'format'    => '?paged=%#%',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => __( '&laquo; Previous', 'bulk-yoast-meta-updater' ),
								'next_text' => __( 'Next &raquo;', 'bulk-yoast-meta-updater' ),
								'type'      => 'plain',
							]
						);

						if ( $pagination ) {
							echo wp_kses_post( $pagination );
						}
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Retrieve attachments.
	 *
	 * @param int    $paged  Page number.
	 * @param string $search Search term.
	 * @param int    $total  Total items (passed by reference).
	 * @return array
	 */
	private function get_attachments( $paged, $search, &$total ) {
		global $wpdb;

		$limit  = max( 1, self::PER_PAGE );
		$offset = ( max( 1, $paged ) - 1 ) * self::PER_PAGE;

		$search_clause = '';
		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$search_clause = $wpdb->prepare( 'AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)', $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Optimized query: Remove expensive EXISTS subquery on initial load.
		// Reference counts will be loaded lazily via AJAX or from cache.
		$sql_body = "
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
			WHERE p.post_type = 'attachment'
				AND p.post_status = 'inherit'
				AND p.post_mime_type LIKE 'image/%'
				{$search_clause}
		";

		$items_sql = $wpdb->prepare(
			"
				SELECT p.*
				{$sql_body}
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d
			",
			$limit,
			$offset
		);

		$count_sql = "
			SELECT COUNT(1)
			{$sql_body}
		";

		$query = $wpdb->get_results( $items_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $query;
	}

	/**
	 * Retrieve attachments with short alt text (<10 chars).
	 *
	 * @param string $search Search term.
	 * @param int    $total  Total items (passed by reference).
	 * @return array
	 */
	private function get_short_alt_attachments( $paged, $search, &$total ) {
		global $wpdb;

		$limit         = max( 1, self::PER_PAGE );
		$offset        = ( max( 1, $paged ) - 1 ) * self::PER_PAGE;
		$search_clause = '';

		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$search_clause = $wpdb->prepare( 'AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)', $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$sql_base = "
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
			WHERE p.post_type = 'attachment'
				AND p.post_status = 'inherit'
				AND p.post_mime_type LIKE 'image/%'
				AND LENGTH(IFNULL(pm.meta_value, '')) > 0
				AND CHAR_LENGTH(TRIM(pm.meta_value)) < 10
				{$search_clause}
		";

		$sql_items = "
			SELECT p.*
			{$sql_base}
			ORDER BY p.post_date DESC
			LIMIT %d OFFSET %d
		";

		$sql_count = "
			SELECT COUNT(1)
			{$sql_base}
		";

		$attachments = $wpdb->get_results( $wpdb->prepare( $sql_items, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total       = (int) $wpdb->get_var( $sql_count ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $attachments ) ? $attachments : [];
	}

	/**
	 * Retrieve top-used attachments.
	 *
	 * @param string $search Search term.
	 * @param int    $total  Total items (passed by reference).
	 * @param int    $limit  Maximum number to return.
	 * @return array
	 */
	private function get_top_attachments( $search, &$total, $limit = 100 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );

		$search_clause = '';
		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$search_clause = $wpdb->prepare( 'AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)', $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$sql = $wpdb->prepare(
			"
				SELECT p.*, (
					SELECT COUNT(*)
					FROM {$wpdb->posts} content_post
					WHERE content_post.post_status IN ('publish','future','draft','pending','private')
						AND content_post.post_type IN ('post','page')
						AND (
							content_post.post_content LIKE CONCAT('%%wp-image-', p.ID, '%%')
							OR content_post.post_content LIKE CONCAT('%%data-id=\"', p.ID, '\"%%')
							OR content_post.post_content LIKE CONCAT('%%\"id\":', p.ID, '%%')
						)
				) AS usage_count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
				WHERE p.post_type = 'attachment'
					AND p.post_status = 'inherit'
					AND p.post_mime_type LIKE 'image/%'
					{$search_clause}
				HAVING usage_count > 0
				ORDER BY usage_count DESC, p.post_date DESC
				LIMIT %d
			",
			$limit
		);

		$attachments = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total       = is_array( $attachments ) ? count( $attachments ) : 0;

		if ( empty( $attachments ) ) {
			return [];
		}

		// Batch load reference counts for top attachments (only 100, so acceptable).
		// Use cached data when available to avoid expensive queries.
		foreach ( $attachments as $attachment ) {
			$cache_key = 'bymu_attachment_refs_' . $attachment->ID;
			$cached    = get_transient( $cache_key );
			
			if ( false !== $cached ) {
				// Use cached data.
				$attachment->usage_count = absint( $cached['count'] );
				$attachment->usage_posts = $cached['posts'];
				self::prime_post_cache( $cached['posts'] );
			} else {
				// Only calculate if not cached (expensive operation).
				$usage = self::count_attachment_references( $attachment->ID );
				self::prime_post_cache( $usage['posts'] );
				$attachment->usage_count = absint( $usage['count'] );
				$attachment->usage_posts = $usage['posts'];
			}
		}

		usort(
			$attachments,
			static function ( $a, $b ) {
				$usage_a = isset( $a->usage_count ) ? (int) $a->usage_count : 0;
				$usage_b = isset( $b->usage_count ) ? (int) $b->usage_count : 0;

				if ( $usage_a === $usage_b ) {
					// Fall back to most recent upload date when counts match.
					$time_a = isset( $a->post_date ) ? strtotime( $a->post_date ) : 0;
					$time_b = isset( $b->post_date ) ? strtotime( $b->post_date ) : 0;

					return $time_b <=> $time_a;
				}

				return $usage_b <=> $usage_a;
			}
		);

		return $attachments;
	}

	/**
	 * Count attachment references in post content.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function count_attachment_references( $attachment_id ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return [
				'count' => 0,
				'posts' => [],
			];
		}

		$cache_key = 'bymu_attachment_refs_' . $attachment_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		static $reference_cache = [];

		if ( isset( $reference_cache[ $attachment_id ] ) ) {
			return $reference_cache[ $attachment_id ];
		}

		// Look for block/class references to wp-image-ID.
		$like_class = '%' . $wpdb->esc_like( 'wp-image-' . $attachment_id ) . '%';
		$like_data  = '%' . $wpdb->esc_like( 'data-id="' . $attachment_id . '"' ) . '%';
		$like_json  = '%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%';

		$filename_patterns = self::get_attachment_reference_terms( $attachment_id );
		$clauses           = [
			'post_content LIKE %s',
			'post_content LIKE %s',
			'post_content LIKE %s',
		];
		$params            = [ $like_class, $like_data, $like_json ];

		foreach ( $filename_patterns as $pattern ) {
			$clauses[] = 'post_content LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $pattern ) . '%';
		}

		$where_clause = implode( ' OR ', $clauses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status IN ('publish','future','draft','pending','private')
				AND post_type IN ('post','page')
				AND (
					{$where_clause}
				)",
				$params
			)
		);

		$result = [
			'count' => count( $post_ids ),
			'posts' => $post_ids,
		];

		$reference_cache[ $attachment_id ] = $result;
		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Prime WordPress post cache for a set of IDs.
	 *
	 * @param array $post_ids Post IDs.
	 * @return void
	 */
	public static function prime_post_cache( $post_ids ) {
		$post_ids = array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return;
		}

		get_posts(
			[
				'post__in'               => $post_ids,
				'post_type'              => 'any',
				'post_status'            => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'posts_per_page'         => count( $post_ids ),
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'fields'                 => 'all',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
	}

	/**
	 * Build search patterns for attachment file references.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private static function get_attachment_reference_terms( $attachment_id ) {
		$terms         = [];
		$upload_dir    = wp_get_upload_dir();
		$relative_path = '';

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $attached_file ) {
			$relative_path = ltrim( $attached_file, '/' );
			$terms[]       = $relative_path;

			if ( ! empty( $upload_dir['baseurl'] ) ) {
				$terms[] = trailingslashit( $upload_dir['baseurl'] ) . $relative_path;
			}

			$terms[] = trailingslashit( 'wp-content/uploads' ) . $relative_path;
		}

		$guid = get_post_field( 'guid', $attachment_id );
		if ( $guid ) {
			$terms[] = $guid;

			$query_string = wp_parse_url( $guid, PHP_URL_QUERY );
			if ( $query_string ) {
				$parsed_query = array_keys( wp_parse_args( $query_string ) );
				if ( ! empty( $parsed_query ) ) {
					$normalized = remove_query_arg( $parsed_query, $guid );
					if ( $normalized ) {
						$terms[] = $normalized;
					}
				}
			}
		}

		if ( $relative_path && ! empty( $upload_dir['baseurl'] ) ) {
			$uploads_path = wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH );
			if ( $uploads_path ) {
				$terms[] = trailingslashit( trim( $uploads_path, '/' ) ) . $relative_path;
			}
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $term ) {
							return is_string( $term ) ? trim( $term ) : '';
						},
						$terms
					)
				)
			)
		);
	}

	/**
	 * Sync alt text across posts for given attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function sync_attachment_alt( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return [
				'success' => false,
				'message' => __( 'Invalid attachment ID.', 'bulk-yoast-meta-updater' ),
			];
		}

		$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' === $alt_text ) {
			$alt_text = get_the_title( $attachment_id );
		}

		$alt_text = trim( wp_strip_all_tags( $alt_text ) );
		if ( '' === $alt_text ) {
			return [
				'success' => false,
				'message' => __( 'Attachment is missing alt text. Please add one first.', 'bulk-yoast-meta-updater' ),
			];
		}

		$reference_data = self::count_attachment_references( $attachment_id );
		$post_ids       = $reference_data['posts'];
		self::prime_post_cache( $post_ids );

		if ( empty( $post_ids ) ) {
			return [
				'success' => true,
				'message' => __( 'No posts reference this image. Nothing to update.', 'bulk-yoast-meta-updater' ),
				'updated' => 0,
			];
		}

		$updated = 0;
		foreach ( $post_ids as $post_id ) {
			$content         = get_post_field( 'post_content', $post_id );
			$updated_content = self::replace_image_alt_in_content( $content, $attachment_id, $alt_text );

			if ( $updated_content !== $content ) {
				$result = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => $updated_content,
					],
					true
				);

				if ( ! is_wp_error( $result ) ) {
					++$updated;
				}
			}
		}

		return [
			'success' => true,
			'message' => __( 'Alt text synced successfully.', 'bulk-yoast-meta-updater' ),
			'updated' => $updated,
		];
	}

	/**
	 * Save attachment alt text.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt_text      Alt text to store.
	 * @return array
	 */
	public static function save_attachment_alt( $attachment_id, $alt_text ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return [
				'success' => false,
				'message' => __( 'Invalid attachment ID.', 'bulk-yoast-meta-updater' ),
			];
		}

		$alt_text = trim( wp_strip_all_tags( $alt_text ) );

		if ( '' === $alt_text ) {
			return [
				'success' => false,
				'message' => __( 'Alt text cannot be empty.', 'bulk-yoast-meta-updater' ),
			];
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		return [
			'success'  => true,
			'alt_text' => $alt_text,
			'message'  => __( 'Alt text updated.', 'bulk-yoast-meta-updater' ),
		];
	}

	/**
	 * Replace image alt text within content.
	 *
	 * @param string $content       Post content.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt_text      New alt text.
	 * @return string
	 */
	private static function replace_image_alt_in_content( $content, $attachment_id, $alt_text ) {
		$alt_attr = esc_attr( $alt_text );
		$pattern  = '/<img[^>]*class="[^"]*wp-image-' . $attachment_id . '[^"]*"[^>]*>/i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $alt_attr ) {
				$tag = $matches[0];

				if ( preg_match( '/alt="[^"]*"/i', $tag ) ) {
					$tag = preg_replace( '/alt="[^"]*"/i', 'alt="' . $alt_attr . '"', $tag, 1 );
				} else {
					$tag = preg_replace( '/<img/i', '<img alt="' . $alt_attr . '"', $tag, 1 );
				}

				return $tag;
			},
			$content
		);
	}
}


