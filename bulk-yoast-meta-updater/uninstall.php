<?php
/**
 * Plugin Uninstall Handler
 *
 * This file is called by WordPress when the plugin is deleted via the admin interface.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin constants.
define( 'BYMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Load dependencies.
require_once BYMU_PLUGIN_DIR . 'includes/helpers.php';
require_once BYMU_PLUGIN_DIR . 'includes/class-uninstaller.php';

// Execute complete uninstall.
Bulk_Yoast_Meta_Updater_Uninstaller::uninstall();
