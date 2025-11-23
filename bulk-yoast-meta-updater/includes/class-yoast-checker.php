<?php
/**
 * Yoast SEO Dependency Checker
 *
 * Validates Yoast SEO installation and version.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Yoast_Checker
 */
class Bulk_Yoast_Meta_Updater_Yoast_Checker {

	/**
	 * Check if Yoast SEO is installed and active.
	 *
	 * @return bool True if active.
	 */
	public static function is_yoast_active() {
		return (bool) bymu_get_active_seo_provider();
	}

	/**
	 * Get Yoast SEO version.
	 *
	 * @return string|false Version or false if not active.
	 */
	public static function get_yoast_version() {
		$provider = bymu_get_active_seo_provider();

		if ( ! $provider ) {
			return false;
		}

		return $provider->get_version();
	}

	/**
	 * Check if Yoast SEO version meets minimum requirement.
	 *
	 * @return bool True if version is sufficient.
	 */
	public static function is_yoast_version_sufficient() {
		$provider = bymu_get_active_seo_provider();

		if ( ! $provider ) {
			return false;
		}

		$version = $provider->get_version();

		if ( false === $version ) {
			return true;
		}

		return version_compare( $version, $provider->get_min_supported_version(), '>=' );
	}

	/**
	 * Get activation error message if Yoast is not active.
	 *
	 * @return string|false Error message or false if no error.
	 */
	public static function get_activation_error() {
		if ( self::is_yoast_active() ) {
			return false;
		}

		$yoast_url  = admin_url( 'plugin-install.php?s=yoast+seo&tab=search&type=term' );
		$aioseo_url = admin_url( 'plugin-install.php?s=all+in+one+seo&tab=search&type=term' );

		return sprintf(
			/* translators: 1: Yoast install link, 2: AIOSEO install link */
			__( 'Bulk SEO Meta Updater requires either Yoast SEO or All in One SEO (AIOSEO) to be installed and active. <a href="%1$s">Install Yoast SEO</a> | <a href="%2$s">Install AIOSEO</a>', 'bulk-yoast-meta-updater' ),
			esc_url( $yoast_url ),
			esc_url( $aioseo_url )
		);
	}

	/**
	 * Get version warning if Yoast version is insufficient.
	 *
	 * @return string|false Warning message or false if no warning.
	 */
	public static function get_version_warning() {
		$provider = bymu_get_active_seo_provider();

		if ( ! $provider ) {
			return false;
		}

		if ( ! self::is_yoast_version_sufficient() ) {
			$version = self::get_yoast_version();
			return sprintf(
				/* translators: 1: Active SEO plugin name, 2: Minimum version, 3: Current version */
				__( 'Bulk SEO Meta Updater works best with %1$s version %2$s or higher. You are running version %3$s. Please update for best results.', 'bulk-yoast-meta-updater' ),
				$provider->get_label(),
				$provider->get_min_supported_version(),
				esc_html( $version )
			);
		}

		return false;
	}

	/**
	 * Display admin notice if Yoast requirements not met.
	 */
	public static function display_admin_notices() {
		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$allowed_screens = [ 'plugins', 'plugins-network' ];

		if ( ! bymu_is_plugin_screen() && ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) ) {
			return;
		}

		// Error notice (blocking).
		$error = self::get_activation_error();
		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				wp_kses_post( $error )
			);
			return;
		}

		// Warning notice (non-blocking).
		$warning = self::get_version_warning();
		if ( $warning ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html( $warning )
			);
		}
	}

	/**
	 * Check if plugin can be used (Yoast is active).
	 *
	 * @return bool True if can be used.
	 */
	public static function can_use_plugin() {
		return self::is_yoast_active();
	}
}
