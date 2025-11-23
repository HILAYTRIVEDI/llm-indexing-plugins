<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation checks and setup.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Activator
 */
class Bulk_Yoast_Meta_Updater_Activator {

	/**
	 * Activate plugin.
	 *
	 * Performs all activation checks and setup.
	 */
	public static function activate() {
		// Load dependencies.
		require_once BYMU_PLUGIN_DIR . 'includes/helpers.php';
		require_once BYMU_PLUGIN_DIR . 'includes/class-db-manager.php';
		require_once BYMU_PLUGIN_DIR . 'includes/class-yoast-checker.php';

		// 1. Check PHP version.
		if ( version_compare( PHP_VERSION, BYMU_MIN_PHP_VERSION, '<' ) ) {
			self::deactivate_with_error(
				sprintf(
					/* translators: 1: Current PHP version, 2: Required PHP version */
					__( 'Bulk SEO Meta Updater requires PHP %2$s or higher. You are running PHP %1$s. Please upgrade PHP.', 'bulk-yoast-meta-updater' ),
					PHP_VERSION,
					BYMU_MIN_PHP_VERSION
				)
			);
			return;
		}

		// 2. Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, BYMU_MIN_WP_VERSION, '<' ) ) {
			self::deactivate_with_error(
				sprintf(
					/* translators: 1: Current WP version, 2: Required WP version */
					__( 'Bulk SEO Meta Updater requires WordPress %2$s or higher. You are running WordPress %1$s. Please update WordPress.', 'bulk-yoast-meta-updater' ),
					$wp_version,
					BYMU_MIN_WP_VERSION
				)
			);
			return;
		}

		// 3. Check if Yoast SEO is active.
		if ( ! Bulk_Yoast_Meta_Updater_Yoast_Checker::is_yoast_active() ) {
			$error = Bulk_Yoast_Meta_Updater_Yoast_Checker::get_activation_error();
			if ( ! $error ) {
				$error = __( 'Bulk SEO Meta Updater requires either Yoast SEO or All in One SEO (AIOSEO) to be installed and active.', 'bulk-yoast-meta-updater' );
			}
			self::deactivate_with_error( $error );
			return;
		}

		// 4. Check Yoast version (warning only, non-blocking).
		$yoast_warning = Bulk_Yoast_Meta_Updater_Yoast_Checker::get_version_warning();
		if ( $yoast_warning ) {
			set_transient( 'bymu_yoast_version_warning', $yoast_warning, HOUR_IN_SECONDS );
		}

		// 5. Create database tables.
		$result = Bulk_Yoast_Meta_Updater_DB_Manager::create_tables();
		
		if ( is_wp_error( $result ) ) {
			self::deactivate_with_error( $result->get_error_message() );
			return;
		}

		// 6. Set default settings (if not already set).
		$existing_settings = get_option( 'bymu_settings' );
		if ( false === $existing_settings ) {
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
			];
			update_option( 'bymu_settings', $defaults );
		}

		// 7. Set activation flag for welcome notice.
		set_transient( 'bymu_activation_notice', true, HOUR_IN_SECONDS );
		update_option( 'bymu_setup_pending', true );
		delete_option( 'bymu_setup_completed' );
	}

	/**
	 * Deactivate plugin with error message.
	 *
	 * @param string $message Error message.
	 */
	private static function deactivate_with_error( $message ) {
		// Deactivate the plugin.
		deactivate_plugins( BYMU_PLUGIN_BASENAME );

		// Set error transient for display.
		set_transient( 'bymu_activation_error', $message, HOUR_IN_SECONDS );

		// Die with error message.
		wp_die(
			wp_kses_post( $message ),
			esc_html__( 'Plugin Activation Failed', 'bulk-yoast-meta-updater' ),
			[
				'back_link' => true,
			]
		);
	}

	/**
	 * Display activation notices.
	 */
	public static function display_activation_notices() {
		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$allowed_screens = [ 'plugins', 'plugins-network' ];

		if ( ! bymu_is_plugin_screen() && ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) ) {
			return;
		}

		// Error notice.
		$error = get_transient( 'bymu_activation_error' );
		if ( $error ) {
			delete_transient( 'bymu_activation_error' );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				wp_kses_post( $error )
			);
			return;
		}

		// Success notice.
		$activated = get_transient( 'bymu_activation_notice' );
		if ( $activated ) {
			delete_transient( 'bymu_activation_notice' );
			$dashboard_url = admin_url( 'admin.php?page=bulk-yoast-meta-updater' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Bulk SEO Meta Updater activated successfully!', 'bulk-yoast-meta-updater' ),
				esc_url( $dashboard_url ),
				esc_html__( 'Get Started', 'bulk-yoast-meta-updater' )
			);
		}

		// Yoast version warning.
		$warning = get_transient( 'bymu_yoast_version_warning' );
		if ( $warning ) {
			delete_transient( 'bymu_yoast_version_warning' );
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html( $warning )
			);
		}
	}
}
