<?php
/**
 * Main Plugin Class
 *
 * Orchestrates all plugin functionality.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Plugin
 */
class Bulk_Yoast_Meta_Updater_Plugin {

	/**
	 * Admin page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Admin_Page
	 */
	private $admin_page;

	/**
	 * Import page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Import_Page
	 */
	private $import_page;

	/**
	 * Settings page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Settings_Page
	 */
	private $settings_page;

	/**
	 * AI Updates page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_AI_Updates_Page
	 */
	private $ai_updates_page;

	/**
	 * Batch runner instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Batch_Runner
	 */
	private $batch_runner;

	/**
	 * Image Alt sync page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page
	 */
	private $image_alt_page;

	/**
	 * Setup page instance.
	 *
	 * @var Bulk_Yoast_Meta_Updater_Setup_Page
	 */
	private $setup_page;

	/**
	 * Screen IDs where footer stats should display.
	 *
	 * @var array
	 */
	private $plugin_screen_ids = [
		'toplevel_page_bulk-yoast-meta-updater',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-updater',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-import',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-ai-updates',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-settings',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-image-alt',
		'bulk-yoast-meta-updater_page_bulk-yoast-meta-setup',
		'admin_page_bulk-yoast-meta-setup',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->admin_page      = new Bulk_Yoast_Meta_Updater_Admin_Page();
		$this->import_page     = new Bulk_Yoast_Meta_Updater_Import_Page();
		$this->settings_page   = new Bulk_Yoast_Meta_Updater_Settings_Page();
		$this->ai_updates_page = new Bulk_Yoast_Meta_Updater_AI_Updates_Page();
		$this->batch_runner    = new Bulk_Yoast_Meta_Updater_Batch_Runner();
		$this->image_alt_page  = new Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page();
		$this->setup_page      = new Bulk_Yoast_Meta_Updater_Setup_Page();
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Check if Yoast is active before proceeding.
		if ( ! Bulk_Yoast_Meta_Updater_Yoast_Checker::can_use_plugin() ) {
			add_action( 'admin_notices', [ 'Bulk_Yoast_Meta_Updater_Yoast_Checker', 'display_admin_notices' ] );
			return;
		}

		// Ensure database schema is up to date.
		Bulk_Yoast_Meta_Updater_DB_Manager::maybe_upgrade();

		// Register hooks.
		$this->register_hooks();
	}

	/**
	 * Register all WordPress hooks.
	 */
	private function register_hooks() {
		// Admin notices.
		add_action( 'admin_notices', [ 'Bulk_Yoast_Meta_Updater_Activator', 'display_activation_notices' ] );
		add_action( 'admin_notices', [ 'Bulk_Yoast_Meta_Updater_Deactivator', 'display_deactivation_notice' ] );
		add_action( 'admin_notices', [ 'Bulk_Yoast_Meta_Updater_Yoast_Checker', 'display_admin_notices' ] );

		// Admin menu.
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . BYMU_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );

		// Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		
		// Custom admin menu styling.
		add_action( 'admin_head', [ $this, 'admin_menu_styles' ] );
		add_action( 'admin_menu', [ $this->setup_page, 'register_page' ] );
		add_action( 'admin_init', [ $this->setup_page, 'maybe_redirect_to_setup' ] );

		// Footer performance stats.
		add_action( 'admin_footer', [ $this, 'render_performance_footer' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_bymu_parse_csv', [ $this->import_page, 'ajax_parse_csv' ] );
		add_action( 'wp_ajax_bymu_process_batch', [ $this->batch_runner, 'ajax_process_batch' ] );
		add_action( 'wp_ajax_bymu_download_log', [ $this->admin_page, 'ajax_download_log' ] );
		add_action( 'wp_ajax_bymu_view_log', [ $this->admin_page, 'ajax_view_log' ] );
		add_action( 'wp_ajax_bymu_export_meta', [ $this->admin_page, 'ajax_export_meta' ] );
		add_action( 'wp_ajax_bymu_test_seo_updates', [ $this->admin_page, 'ajax_test_seo_updates' ] );
		add_action( 'wp_ajax_bymu_load_ai_posts', [ $this->ai_updates_page, 'ajax_load_ai_posts' ] );
		add_action( 'wp_ajax_bymu_generate_ai', [ $this->ai_updates_page, 'ajax_generate_ai_suggestions' ] );
		add_action( 'wp_ajax_bymu_start_ai_bulk_job', [ $this->ai_updates_page, 'ajax_start_ai_bulk_job' ] );
		add_action( 'wp_ajax_bymu_finish_ai_bulk_job', [ $this->ai_updates_page, 'ajax_finish_ai_bulk_job' ] );
		add_action( 'wp_ajax_bymu_save_ai', [ $this->ai_updates_page, 'ajax_save_ai_suggestion' ] );
		add_action( 'wp_ajax_bymu_generate_image_alt', [ $this, 'ajax_generate_image_alt' ] );
		add_action( 'wp_ajax_bymu_save_attachment_alt', [ $this, 'ajax_save_attachment_alt' ] );
		add_action( 'wp_ajax_bymu_fetch_gemini_models', [ $this, 'ajax_fetch_gemini_models' ] );
		add_action( 'wp_ajax_bymu_manual_uninstall', [ $this, 'ajax_manual_uninstall' ] );
		add_action( 'wp_ajax_bymu_clear_old_logs', [ $this, 'ajax_clear_old_logs' ] );
		add_action( 'wp_ajax_bymu_optimize_database', [ $this, 'ajax_optimize_database' ] );
		add_action( 'wp_ajax_bymu_sync_image_alt', [ $this, 'ajax_sync_image_alt' ] );
		add_action( 'wp_ajax_bymu_load_attachment_refs', [ $this, 'ajax_load_attachment_refs' ] );

		// Media library integration.
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_alt_generate_button' ], 10, 2 );
		
		// Add button to attachment edit screen.
		add_action( 'admin_print_footer_scripts', [ $this, 'inject_alt_generate_button_on_edit_screen' ] );

		// Show admin notice after uninstall redirect.
		add_action( 'admin_notices', [ $this, 'show_uninstall_notice' ] );

		// Daily cron for cleanup.
		add_action( 'bymu_daily_cleanup', [ $this, 'daily_cleanup' ] );
		if ( ! wp_next_scheduled( 'bymu_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bymu_daily_cleanup' );
		}
	}

	/**
	 * Determine if current screen is one of our plugin pages.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		if ( bymu_is_plugin_screen() ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, $this->plugin_screen_ids, true );
	}

	/**
	 * Append body class for plugin admin pages.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public function add_admin_body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for body class.
		if ( $this->is_plugin_screen() || isset( $_GET['page'] ) && in_array(
			sanitize_key( wp_unslash( $_GET['page'] ) ),
			[
				'bulk-yoast-meta-updater',
				'bulk-yoast-meta-import',
				'bulk-yoast-meta-ai-updates',
				'bulk-yoast-meta-settings',
				'bulk-yoast-meta-image-alt',
				'bulk-yoast-meta-setup',
			],
			true 
		) ) {
			$classes .= ' bymu-admin-shell';
		}

		return $classes;
	}

	/**
	 * Output simple performance stats in footer.
	 *
	 * Shows generation time and query count for plugin pages.
	 */
	public function render_performance_footer() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		$generation_time = timer_stop( 0, 3 );
		$query_count     = get_num_queries();

		printf(
			'<div class="bymu-performance-footer" style="margin-top:20px;padding:12px 16px;border-top:1px solid #dcdcde;font-size:12px;color:#555;display:flex;gap:16px;align-items:center;justify-content:flex-end;"><span>%s <strong>%s</strong></span><span>%s <strong>%d</strong></span></div>',
			esc_html__( 'Page generated in', 'bulk-yoast-meta-updater' ),
			esc_html( $generation_time . 's' ),
			esc_html__( 'Database queries', 'bulk-yoast-meta-updater' ),
			intval( $query_count )
		);
	}

	/**
	 * Show admin notice after successful uninstall.
	 */
	public function show_uninstall_notice() {
		$message = get_transient( 'bymu_uninstall_success' );
		
		if ( ! $message ) {
			return;
		}
		
		// Delete transient so it only shows once.
		delete_transient( 'bymu_uninstall_success' );
		
		?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html( $message ); ?></strong></p>
		</div>
		<?php
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_admin_menu() {
		// Custom icon SVG (data URI).
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
				<circle cx="12" cy="12" r="1" fill="currentColor"/>
			</svg>'
		);

		// Main top-level menu.
		add_menu_page(
			bymu_get_brand_name(),
			__( 'Bulk SEO Meta', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-updater',
			[ $this->admin_page, 'render' ],
			$icon_svg,
			99 // Position (bottom of menu).
		);

		// Dashboard submenu (rename default).
		add_submenu_page(
			'bulk-yoast-meta-updater',
			__( 'Dashboard', 'bulk-yoast-meta-updater' ),
			__( '📊 Dashboard', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-updater',
			[ $this->admin_page, 'render' ]
		);

		// Import submenu.
		add_submenu_page(
			'bulk-yoast-meta-updater',
			__( 'Import CSV', 'bulk-yoast-meta-updater' ),
			__( 'Import CSV', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-import',
			[ $this->import_page, 'render' ]
		);

		// AI Updates submenu.
		add_submenu_page(
			'bulk-yoast-meta-updater',
			__( 'AI Updates', 'bulk-yoast-meta-updater' ),
			__( 'AI Updates', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-ai-updates',
			[ $this->ai_updates_page, 'render' ]
		);

		// Image Alt Texts submenu.
		add_submenu_page(
			'bulk-yoast-meta-updater',
			__( 'Image Alt Texts', 'bulk-yoast-meta-updater' ),
			__( 'Image Alt Texts', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-image-alt',
			[ $this->image_alt_page, 'render' ]
		);

		// Settings submenu.
		add_submenu_page(
			'bulk-yoast-meta-updater',
			__( 'Settings', 'bulk-yoast-meta-updater' ),
			__( 'Settings', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-settings',
			[ $this->settings_page, 'render' ]
		);

		// Note: Uninstall page is handled separately via admin_init hook
		// to avoid deprecation warnings with null parent slug.
	}

	/**
	 * Add plugin action links on Plugins page.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_plugin_action_links( $links ) {
		// Get stats (returns 0 if tables don't exist).
		$stats = Bulk_Yoast_Meta_Updater_DB_Manager::get_table_stats();
		
		$dashboard_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=bulk-yoast-meta-updater' ),
			__( 'Dashboard', 'bulk-yoast-meta-updater' )
		);

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=bulk-yoast-meta-settings' ),
			__( 'Settings', 'bulk-yoast-meta-updater' )
		);

		// Only show uninstall link if tables exist (data not yet deleted).
		$has_data = ( $stats['total_jobs'] > 0 || $stats['total_actions'] > 0 );
		
		// Check if tables actually exist.
		global $wpdb;
		$jobs_table = bymu_get_table_name( 'jobs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$jobs_table}'" );
		
		$custom_links = [ $dashboard_link, $settings_link ];
		
		if ( $tables_exist ) {
			$uninstall_link = sprintf(
				'<a href="#" class="bymu-uninstall-link" data-job-count="%d" data-action-count="%d" style="color: #d63638; font-weight: 600;">%s</a>',
				absint( $stats['total_jobs'] ),
				absint( $stats['total_actions'] ),
				__( 'Uninstall', 'bulk-yoast-meta-updater' )
			);
			$custom_links[] = $uninstall_link;
		}

		// Add to beginning of array.
		array_unshift( $links, ...$custom_links );

		return $links;
	}

	/**
	 * Render uninstall confirmation page.
	 */
	public function render_uninstall_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		// Get database stats.
		$stats = Bulk_Yoast_Meta_Updater_DB_Manager::get_table_stats();

		?>
		<div class="wrap bymu-wrap bymu-uninstall-page">
			<style>
				.bymu-uninstall-page .bymu-danger-header {
					background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
					color: #fff;
					padding: 40px;
					margin: 20px 0 var(--bymu-gutter) 0;
					border-radius: var(--bymu-radius);
					box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
					position: relative;
					overflow: hidden;
				}
				.bymu-uninstall-page .bymu-danger-header::before {
					content: '';
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					height: 50%;
					background: linear-gradient(to bottom, rgba(255, 255, 255, 0.1), transparent);
					pointer-events: none;
				}
				.bymu-uninstall-page .bymu-danger-header h1 {
					color: #fff;
					margin: 0 0 12px 0;
					font-size: 32px;
					font-weight: 700;
					position: relative;
					z-index: 1;
				}
				.bymu-uninstall-page .bymu-danger-header p {
					margin: 0;
					opacity: 0.95;
					font-size: 16px;
					line-height: 1.6;
					position: relative;
					z-index: 1;
				}
				.bymu-uninstall-page .bymu-warning-box {
					background: linear-gradient(135deg, #fff3cd 0%, #ffe8a1 100%);
					border: 2px solid #ffc107;
					border-radius: var(--bymu-radius);
					padding: var(--bymu-spacing);
					margin: var(--bymu-gutter) 0;
				}
				.bymu-uninstall-page .bymu-warning-box h2 {
					color: #856404;
					margin-top: 0;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.bymu-uninstall-page .bymu-delete-list {
					background: #fff;
					border: 1px solid var(--bymu-border);
					border-radius: var(--bymu-radius);
					padding: var(--bymu-spacing);
					margin: var(--bymu-gutter) 0;
				}
				.bymu-uninstall-page .bymu-delete-list ul {
					list-style: none;
					padding: 0;
					margin: 15px 0;
				}
				.bymu-uninstall-page .bymu-delete-list li {
					padding: 10px 0;
					border-bottom: 1px solid var(--bymu-border);
					display: flex;
					align-items: center;
					gap: 10px;
				}
				.bymu-uninstall-page .bymu-delete-list li:last-child {
					border-bottom: none;
				}
				.bymu-uninstall-page .bymu-delete-list li::before {
					content: '•';
					font-size: 16px;
					line-height: 1;
				}
				.bymu-uninstall-page .bymu-uninstall-actions {
					display: flex;
					gap: 12px;
					margin: var(--bymu-gutter) 0;
				}
				.bymu-uninstall-page .button-danger {
					background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
					color: #fff;
					border: none;
					box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
					font-weight: 600;
					font-size: 15px;
					padding: 10px 24px;
					height: auto;
				}
				.bymu-uninstall-page .button-danger:hover {
					background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
					box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
				}
			</style>

			<div class="bymu-danger-header">
				<h1><?php esc_html_e( 'Uninstall Plugin', 'bulk-yoast-meta-updater' ); ?></h1>
				<p><?php esc_html_e( 'Permanently remove all plugin data from your website.', 'bulk-yoast-meta-updater' ); ?></p>
			</div>

			<div class="bymu-warning-box">
				<h2>
					<?php esc_html_e( 'Warning: This Action Cannot Be Undone', 'bulk-yoast-meta-updater' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'Clicking the uninstall button below will permanently delete all plugin data from your database. This action is irreversible.', 'bulk-yoast-meta-updater' ); ?>
				</p>
			</div>

			<div class="bymu-delete-list">
				<h3><?php esc_html_e( 'The following will be permanently deleted:', 'bulk-yoast-meta-updater' ); ?></h3>
				<ul>
					<li>
						<strong>
							<?php
							printf(
								/* translators: %d: Number of jobs */
								esc_html__( 'All job logs (%d jobs)', 'bulk-yoast-meta-updater' ),
								absint( $stats['total_jobs'] )
							);
							?>
						</strong>
					</li>
					<li>
						<strong>
							<?php
							printf(
								/* translators: %s: Number of actions */
								esc_html__( 'All action records (%s actions)', 'bulk-yoast-meta-updater' ),
								esc_html( number_format_i18n( $stats['total_actions'] ) )
							);
							?>
						</strong>
					</li>
					<li><strong><?php esc_html_e( 'All plugin settings and configuration', 'bulk-yoast-meta-updater' ); ?></strong></li>
					<li><strong><?php esc_html_e( 'Database tables (bymu_jobs, bymu_actions)', 'bulk-yoast-meta-updater' ); ?></strong></li>
					<li><strong><?php esc_html_e( 'All plugin files and folders', 'bulk-yoast-meta-updater' ); ?></strong></li>
				</ul>
			</div>

			<div class="bymu-section">
				<div class="bymu-section-body">
					<div class="bymu-info-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border-left-color: #17a2b8;">
						<div class="bymu-info-box-icon dashicons dashicons-info"></div>
						<div class="bymu-info-box-content">
							<p><strong><?php esc_html_e( 'Important Note:', 'bulk-yoast-meta-updater' ); ?></strong></p>
							<p><?php esc_html_e( 'Your Yoast SEO or All in One SEO meta data will NOT be affected. This only removes plugin logs, settings, and database tables created by this plugin.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>

					<div class="bymu-uninstall-actions">
						<button 
							type="button" 
							id="bymu-manual-uninstall-btn" 
							class="button button-danger"
							data-job-count="<?php echo esc_attr( $stats['total_jobs'] ); ?>"
							data-action-count="<?php echo esc_attr( $stats['total_actions'] ); ?>">
							<?php esc_html_e( 'Yes, Uninstall Plugin & Delete All Data', 'bulk-yoast-meta-updater' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary button-large">
							← <?php esc_html_e( 'Cancel & Go Back', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on plugins page.
		$is_plugins_page = ( 'plugins.php' === $hook );
		
		// Check if we're on media upload/edit pages.
		$is_media_page = in_array( $hook, [ 'upload.php', 'post.php', 'post-new.php', 'async-upload.php' ], true );
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for asset loading.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Load on our plugin pages (top-level menu).
		$allowed_hooks = [
			'toplevel_page_bulk-yoast-meta-updater',  // Dashboard.
			'bulk-yoast-meta-updater_page_bulk-yoast-meta-import', // Import submenu.
			'bulk-yoast-meta-updater_page_bulk-yoast-meta-ai-updates', // AI Updates submenu.
			'bulk-yoast-meta-updater_page_bulk-yoast-meta-settings', // Settings submenu.
			'bulk-yoast-meta-updater_page_bulk-yoast-meta-image-alt', // Image Alt Texts submenu.
			'bulk-yoast-meta-updater_page_bulk-yoast-meta-setup',
			'admin_page_bulk-yoast-meta-setup',
		];

		$allowed_pages = [
			'bulk-yoast-meta-updater',
			'bulk-yoast-meta-import',
			'bulk-yoast-meta-ai-updates',
			'bulk-yoast-meta-settings',
			'bulk-yoast-meta-image-alt',
			'bulk-yoast-meta-setup',
		];
		
		$is_plugin_page = in_array( $hook, $allowed_hooks, true ) || in_array( $current_page, $allowed_pages, true );
		
		if ( ! $is_plugin_page && ! $is_media_page && ! $is_plugins_page ) {
			return;
		}

		$settings = bymu_get_settings();

		$style_handle  = 'bymu-admin-style';
		$script_handle = 'bymu-admin-script';

		if ( ! wp_style_is( $style_handle, 'enqueued' ) ) {
			wp_register_style(
				$style_handle,
				BYMU_PLUGIN_URL . 'assets/css/admin.css',
				[],
				BYMU_VERSION
			);
			wp_enqueue_style( $style_handle );
		}

		if ( ! wp_script_is( $script_handle, 'registered' ) ) {
			wp_register_script(
				$script_handle,
				BYMU_PLUGIN_URL . 'assets/js/admin.js',
				[ 'jquery' ],
				BYMU_VERSION,
				true
			);
		}

		wp_enqueue_script( $script_handle );

		// Only localize once per request.
		static $script_localized = false;

		if ( ! $script_localized ) {
			wp_localize_script(
				$script_handle,
				'bymuAdmin',
				[
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'nonces'        => [
						'parse_csv'            => wp_create_nonce( 'bymu_parse_csv' ),
						'process_batch'        => wp_create_nonce( 'bymu_process_batch' ),
						'download_log'         => wp_create_nonce( 'bymu_download_log' ),
						'view_log'             => wp_create_nonce( 'bymu_view_log' ),
						'export_meta'          => wp_create_nonce( 'bymu_export_meta' ),
						'test_seo'             => wp_create_nonce( 'bymu_test_seo' ),
						'ai_generate'          => wp_create_nonce( 'bymu_ai_generate' ),
						'generate_image_alt'   => wp_create_nonce( 'bymu_generate_image_alt' ),
						'sync_alt'             => wp_create_nonce( 'bymu_sync_alt' ),
						'save_alt'             => wp_create_nonce( 'bymu_save_alt' ),
						'uninstall'            => wp_create_nonce( 'bymu_uninstall' ),
						'clear_logs'           => wp_create_nonce( 'bymu_clear_logs' ),
						'optimize_db'          => wp_create_nonce( 'bymu_optimize_db' ),
						'fetch_models'         => wp_create_nonce( 'bymu_fetch_gemini_models' ),
						'load_attachment_refs' => wp_create_nonce( 'bymu_load_attachment_refs' ),
					],
					'settings'      => [
						'logRetention' => isset( $settings['log_retention'] ) ? absint( $settings['log_retention'] ) : 0,
						'batchSize'    => 15,
						'maxUploadMb'  => 4,
					],
					'models'        => bymu_split_gemini_models_by_category( bymu_merge_models_with_defaults( bymu_get_cached_gemini_models() ) ),
					'modelMessages' => [
						'refreshing'  => __( 'Fetching models…', 'bulk-yoast-meta-updater' ),
						'updated'     => __( 'Model list updated.', 'bulk-yoast-meta-updater' ),
						'error'       => __( 'Unable to load models.', 'bulk-yoast-meta-updater' ),
						'missingKey'  => __( 'Enter your Gemini API key first.', 'bulk-yoast-meta-updater' ),
						'placeholder' => __( '-- Select a model --', 'bulk-yoast-meta-updater' ),
					],
					'strings'       => [
						'processing'             => __( 'Processing...', 'bulk-yoast-meta-updater' ),
						'complete'               => __( 'Complete!', 'bulk-yoast-meta-updater' ),
						'error'                  => __( 'Error occurred', 'bulk-yoast-meta-updater' ),
						'confirmUninstall'       => __( 'Type DELETE to confirm', 'bulk-yoast-meta-updater' ),
						'uninstallWarning'       => __( 'This will permanently delete all plugin data!', 'bulk-yoast-meta-updater' ),
						/* translators: %d: Number of posts */
						'syncAltConfirm'         => __( 'Sync alt text across all %d referenced post(s)?', 'bulk-yoast-meta-updater' ),
						'syncAltNoRefs'          => __( 'No post references found for this image. Nothing to update.', 'bulk-yoast-meta-updater' ),
						'generateAltConfirm'     => __( 'Generate alt text for this image?', 'bulk-yoast-meta-updater' ),
						'generateAltSaving'      => __( 'Generated text inserted. Click Save to store.', 'bulk-yoast-meta-updater' ),
						'exportEstimateFiltered' => __( 'Estimate unavailable while short/empty filter is enabled.', 'bulk-yoast-meta-updater' ),
						'exportEstimateSelect'   => __( 'Select at least one post type and status to see an estimated row count.', 'bulk-yoast-meta-updater' ),
						/* translators: %d: Number of rows */
						'exportEstimateLabel'    => __( '%d row(s) estimated for export.', 'bulk-yoast-meta-updater' ),
					],
				]
			);
			$script_localized = true;
		}
	}

	/**
	 * AJAX handler for manual uninstall.
	 */
	public function ajax_manual_uninstall() {
		// Start with clean output buffer.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();
		
		try {
			check_ajax_referer( 'bymu_uninstall', 'nonce' );
			
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
			}
			
			$result = Bulk_Yoast_Meta_Updater_Uninstaller::manual_uninstall();
			
			if ( $result['success'] ) {
				// Set transient for success message after redirect.
				set_transient( 'bymu_uninstall_success', $result['message'], 60 );
				
				// Clean buffer before sending JSON.
				ob_clean();
				
				wp_send_json_success(
					[
						'message'  => $result['message'],
						'redirect' => admin_url( 'plugins.php' ),
					]
				);
			} else {
				ob_clean();
				wp_send_json_error( $result['message'] );
			}
		} catch ( Exception $e ) {
			ob_clean();
			wp_send_json_error( 'Exception: ' . $e->getMessage() );
		}
		
		exit;
	}

	/**
	 * AJAX handler for clearing old logs.
	 */
	public function ajax_clear_old_logs() {
		// Clean output buffer to prevent JSON parsing errors.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		try {
			check_ajax_referer( 'bymu_clear_logs', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				ob_clean();
				wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
			}

			$force_all = isset( $_POST['force_all'] ) && '1' === $_POST['force_all'];
			$deleted   = Bulk_Yoast_Meta_Updater_DB_Manager::cleanup_old_jobs( 0, $force_all );

			ob_clean();
			wp_send_json_success(
				[
					/* translators: %d: Number of jobs deleted */
					'message' => sprintf( __( 'Deleted %d old job logs.', 'bulk-yoast-meta-updater' ), $deleted ),
				]
			);
		} catch ( Exception $e ) {
			ob_clean();
			wp_send_json_error( 'Error: ' . $e->getMessage() );
		}
		exit;
	}

	/**
	 * AJAX handler for optimizing database.
	 */
	public function ajax_optimize_database() {
		// Clean output buffer to prevent JSON parsing errors.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		try {
			check_ajax_referer( 'bymu_optimize_db', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				ob_clean();
				wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
			}

			Bulk_Yoast_Meta_Updater_DB_Manager::optimize_tables();

			ob_clean();
			wp_send_json_success(
				[
					'message' => __( 'Database tables optimized successfully.', 'bulk-yoast-meta-updater' ),
				]
			);
		} catch ( Exception $e ) {
			ob_clean();
			wp_send_json_error( 'Error: ' . $e->getMessage() );
		}
		exit;
	}

	/**
	 * AJAX handler for saving attachment alt text.
	 */
	public function ajax_save_attachment_alt() {
		check_ajax_referer( 'bymu_save_alt', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$alt_text      = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';

		$result = Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page::save_attachment_alt( $attachment_id, $alt_text );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result['message'] );
	}

	/**
	 * AJAX handler for syncing image alt text across posts.
	 */
	public function ajax_sync_image_alt() {
		check_ajax_referer( 'bymu_sync_alt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		$result = Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page::sync_attachment_alt( $attachment_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result['message'] );
	}

	/**
	 * Daily cleanup cron job.
	 */
	public function daily_cleanup() {
		// Cleanup old jobs for all users.
		Bulk_Yoast_Meta_Updater_DB_Manager::cleanup_old_jobs();
	}

	/**
	 * Add custom styling for admin menu.
	 */
	public function admin_menu_styles() {
		?>
		<style>
			/* Custom menu icon styling */
			#adminmenu .toplevel_page_bulk-yoast-meta-updater .wp-menu-image img {
				width: 20px;
				height: 20px;
				opacity: 0.6;
				transition: opacity 0.2s ease;
			}
			
			#adminmenu .toplevel_page_bulk-yoast-meta-updater:hover .wp-menu-image img,
			#adminmenu .toplevel_page_bulk-yoast-meta-updater.current .wp-menu-image img {
				opacity: 1;
			}
			
			/* Menu highlight color */
			#adminmenu .toplevel_page_bulk-yoast-meta-updater.current .wp-menu-image::before,
			#adminmenu .toplevel_page_bulk-yoast-meta-updater:hover .wp-menu-image::before {
				color: #667eea;
			}
			
			#adminmenu .toplevel_page_bulk-yoast-meta-updater.wp-has-current-submenu .wp-menu-name,
			#adminmenu .toplevel_page_bulk-yoast-meta-updater.current .wp-menu-name {
				color: #fff;
			}
			
			/* Submenu highlight */
			#adminmenu .toplevel_page_bulk-yoast-meta-updater .wp-submenu a:hover,
			#adminmenu .toplevel_page_bulk-yoast-meta-updater .wp-submenu a.current {
				color: #667eea;
			}
		</style>
		<?php
	}

	/**
	 * Add "Generate" button to attachment alt text field.
	 *
	 * @param array  $form_fields Form fields.
	 * @param object $post         Post object.
	 * @return array Modified form fields.
	 */
	public function add_alt_generate_button( $form_fields, $post ) {
		// Only for images.
		if ( ! str_starts_with( $post->post_mime_type, 'image/' ) ) {
			return $form_fields;
		}

		// Check if API key is configured.
		$settings    = bymu_get_settings();
		$has_api_key = ! empty( $settings['gemini_api_key'] );

		if ( ! $has_api_key ) {
			return $form_fields;
		}

		// Build the generate button HTML.
		$button_html = '
			<div class="bymu-image-alt-generate" style="margin-top: 8px;">
				<button type="button" 
					class="button button-secondary bymu-media-generate-alt-btn" 
					data-attachment-id="' . esc_attr( $post->ID ) . '">
					' . esc_html__( 'Generate with AI', 'bulk-yoast-meta-updater' ) . '
				</button>
				<span class="bymu-alt-status" style="margin-left: 10px; font-weight: 500;"></span>
			</div>
		';

		// Add button to the image_alt field if it exists.
		if ( isset( $form_fields['image_alt'] ) ) {
			// Append button to existing HTML.
			$form_fields['image_alt']['html'] .= $button_html;
		} else {
			// Create the image_alt field with button (fallback for some contexts).
			$current_alt              = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
			$form_fields['image_alt'] = [
				'label' => __( 'Alternative Text', 'bulk-yoast-meta-updater' ),
				'input' => 'html',
				'html'  => '<input type="text" class="text" id="attachments-' . esc_attr( $post->ID ) . '-image_alt" name="attachments[' . esc_attr( $post->ID ) . '][image_alt]" value="' . esc_attr( $current_alt ) . '" />' . $button_html,
				'value' => $current_alt,
				'helps' => __( 'Alt text for the image, e.g. "The Mona Lisa"', 'bulk-yoast-meta-updater' ),
			];
		}

		return $form_fields;
	}

	/**
	 * Inject alt text generate button on attachment edit screen.
	 */
	public function inject_alt_generate_button_on_edit_screen() {
		$screen = get_current_screen();
		
		// Only run on attachment edit screen.
		if ( ! $screen || 'attachment' !== $screen->id || 'post' !== $screen->base ) {
			return;
		}
		
		// Check if API key is configured.
		$settings = bymu_get_settings();
		if ( empty( $settings['gemini_api_key'] ) ) {
			return;
		}
		
		// Get the attachment ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking post ID for button injection.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}
		
		// Only for image attachments.
		if ( ! wp_attachment_is_image( $post_id ) ) {
			return;
		}
		
		// Output JavaScript to inject button.
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Find the alt text field
			var $altField = $('#attachment_alt, input[name="_wp_attachment_image_alt"]');
			
			if ($altField.length) {
				// Check if button already exists
				if ($('.bymu-media-generate-alt-btn').length === 0) {
					// Create the button
					var $button = $('<button>', {
						type: 'button',
						class: 'button button-secondary bymu-media-generate-alt-btn',
						'data-attachment-id': '<?php echo esc_js( $post_id ); ?>',
						text: '<?php esc_html_e( 'Generate with AI', 'bulk-yoast-meta-updater' ); ?>',
						css: {
							marginTop: '8px',
							marginBottom: '8px'
						}
					});
					
					// Create status span
					var $status = $('<span>', {
						class: 'bymu-alt-status',
						css: {
							marginLeft: '10px',
							fontWeight: '500'
						}
					});
					
					// Create wrapper
					var $wrapper = $('<div>', {
						class: 'bymu-image-alt-generate',
						css: {
							marginTop: '8px'
						}
					}).append($button).append($status);
					
					// Insert after the alt text field
					$altField.after($wrapper);
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to fetch Gemini models.
	 */
	public function ajax_fetch_gemini_models() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		check_ajax_referer( 'bymu_fetch_gemini_models', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$api_key = '';

		if ( isset( $_POST['api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		}

		if ( empty( $api_key ) ) {
			$settings = bymu_get_settings();
			$api_key  = $settings['gemini_api_key'] ?? '';
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'Please enter your Gemini API key before refreshing models.', 'bulk-yoast-meta-updater' ) );
		}

		// Clear cache to force fresh fetch.
		delete_transient( 'bymu_gemini_models' );
		
		$models = bymu_fetch_gemini_models_from_api( $api_key );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message() );
		}

		$merged_models = bymu_merge_models_with_defaults( $models );
		bymu_cache_gemini_models( $merged_models );

		wp_send_json_success(
			[
				'models' => bymu_split_gemini_models_by_category( $merged_models ),
			]
		);
	}

	/**
	 * AJAX handler for generating image alt text.
	 */
	public function ajax_generate_image_alt() {
		// Security checks.
		check_ajax_referer( 'bymu_generate_image_alt', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment ID.', 'bulk-yoast-meta-updater' ) );
		}

		// Initialize Gemini API.
		$gemini = new Bulk_Yoast_Meta_Updater_Gemini_API();

		if ( ! $gemini->has_api_key() ) {
			wp_send_json_error( __( 'Google Gemini API key not configured. Please configure it in plugin settings.', 'bulk-yoast-meta-updater' ) );
		}

		// Get current alt text for logging.
		$old_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		// Generate alt text.
		$alt_text = $gemini->generate_image_alt_text( $attachment_id );

		if ( is_wp_error( $alt_text ) ) {
			// Log error.
			bymu_log_db_error(
				'generate_image_alt',
				$alt_text,
				[
					'attachment_id' => $attachment_id,
				]
			);
			wp_send_json_error( $alt_text->get_error_message() );
		}

		// Create job log for AI image alt generation.
		$job_id = Bulk_Yoast_Meta_Updater_Logger::create_job(
			[
				'job_hash'       => bymu_generate_job_hash(),
				'file_name'      => 'ai-image-alt-' . gmdate( 'Y-m-d-His' ) . '.csv',
				'status'         => 'completed',
				'total_rows'     => 1,
				'processed_rows' => 1,
				'updated_rows'   => 0,
				'settings'       => [
					'type'          => 'ai_image_alt',
					'attachment_id' => $attachment_id,
				],
			]
		);

		// Log the generation (note: not saved yet, just generated).
		Bulk_Yoast_Meta_Updater_Logger::log_action(
			$job_id,
			[
				'csv_row'   => 1,
				'post_id'   => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'field'     => 'image_alt',
				'old_value' => $old_alt_text,
				'new_value' => $alt_text,
				'status'    => 'ok',
				'message'   => 'AI-generated via Google Gemini Vision (not saved - pending user review)',
			]
		);

		// Return the generated alt text (don't save automatically).
		wp_send_json_success(
			[
				'alt_text' => $alt_text,
				'message'  => __( 'Alt text generated successfully! Review and update if needed.', 'bulk-yoast-meta-updater' ),
				'job_id'   => $job_id,
			]
		);
	}

	/**
	 * AJAX handler for loading attachment reference counts (lazy loading).
	 */
	public function ajax_load_attachment_refs() {
		check_ajax_referer( 'bymu_load_attachment_refs', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bulk-yoast-meta-updater' ) );
		}

		$attachment_ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) $_POST['attachment_ids'] ) : [];

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( __( 'No attachment IDs provided.', 'bulk-yoast-meta-updater' ) );
		}

		$results = [];

		foreach ( $attachment_ids as $attachment_id ) {
			$reference_data = Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page::count_attachment_references( $attachment_id );
			
			// Prime post cache for better performance.
			Bulk_Yoast_Meta_Updater_Image_Alt_Sync_Page::prime_post_cache( $reference_data['posts'] );
			
			// Build post data with titles for display.
			$post_data = [];
			foreach ( $reference_data['posts'] as $post_id ) {
				$post_title  = get_the_title( $post_id );
				$edit_link   = get_edit_post_link( $post_id );
				$post_data[] = [
					'id'    => $post_id,
					/* translators: %d: Post ID */
					'title' => $post_title ? $post_title : sprintf( __( 'Post #%d', 'bulk-yoast-meta-updater' ), $post_id ),
					'url'   => $edit_link ? $edit_link : '',
				];
			}
			
			$results[ $attachment_id ] = [
				'count' => absint( $reference_data['count'] ),
				'posts' => $post_data,
			];
		}

		wp_send_json_success( $results );
	}
}

