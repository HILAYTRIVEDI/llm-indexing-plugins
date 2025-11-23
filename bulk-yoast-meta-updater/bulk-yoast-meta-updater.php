<?php
/**
 * Plugin Name: Bulk SEO Meta Updater
 * Plugin URI: https://mannadigital.com/bulk-yoast-meta-updater
 * Description: AI-powered bulk SEO tool for Yoast SEO & All in One SEO. Update meta titles, descriptions, and focus keyphrases via CSV import or Google Gemini AI generation. Generate image alt text with Vision API. WordPress VIP compatible.
 * Version: 1.0.3
 * Author: Manna Digital
 * Author URI: https://mannadigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bulk-yoast-meta-updater
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BYMU_VERSION', '1.0.3' );
define( 'BYMU_PLUGIN_FILE', __FILE__ );
define( 'BYMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BYMU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BYMU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BYMU_MIN_PHP_VERSION', '7.4' );
define( 'BYMU_MIN_WP_VERSION', '6.0' );
define( 'BYMU_MIN_YOAST_VERSION', '14.0' );
define( 'BYMU_MIN_AIOSEO_VERSION', '4.0' );

/**
 * Load plugin text domain for translations.
 */
function bymu_load_textdomain() {
	load_plugin_textdomain(
		'bulk-yoast-meta-updater',
		false,
		dirname( BYMU_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'bymu_load_textdomain' );

/**
 * Activation hook.
 */
function bymu_activate() {
	require_once BYMU_PLUGIN_DIR . 'includes/class-activator.php';
	Bulk_Yoast_Meta_Updater_Activator::activate();
}
register_activation_hook( __FILE__, 'bymu_activate' );

/**
 * Deactivation hook.
 */
function bymu_deactivate() {
	require_once BYMU_PLUGIN_DIR . 'includes/class-deactivator.php';
	Bulk_Yoast_Meta_Updater_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'bymu_deactivate' );

/**
 * Load core plugin class and initialize.
 */
function bymu_init() {
	// Check if running in admin.
	if ( ! is_admin() ) {
		return;
	}

	// Autoload classes.
	require_once BYMU_PLUGIN_DIR . 'includes/helpers.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-yoast-checker.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-db-manager.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-activator.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-deactivator.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-uninstaller.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-logger.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-csv-parser.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-resolver.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-diff-builder.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-batch-runner.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-gemini-api.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-image-alt-sync-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-setup-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-admin-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-import-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-ai-updates-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-settings-page.php';
	require_once BYMU_PLUGIN_DIR . 'includes/class-plugin.php';

	// Initialize plugin.
	$plugin = new Bulk_Yoast_Meta_Updater_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'bymu_init' );

/**
 * Load WP-CLI command if available.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once BYMU_PLUGIN_DIR . 'includes/class-cli.php';
}
