<?php
/**
 * Uninstall for Simple LLMs.txt Manager.
 * Drops the custom tables, purges all options, cache keys, and transients.
 *
 * @package Simple_LLMs_Txt_Manager
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN') ) {
    exit;
}

global $wpdb;

// Drop custom database tables.
$txt_table  = $wpdb->prefix . 'md_llms_txt';
$jobs_table = $wpdb->prefix . 'md_llms_jobs';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema deletion on uninstall.
$wpdb->query("DROP TABLE IF EXISTS {$txt_table}");
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema deletion on uninstall.
$wpdb->query("DROP TABLE IF EXISTS {$jobs_table}");

// Delete all plugin options.
$options = array(
    'md_llms_txt_master_statement',
    'md_llms_txt_industry',
    'md_llms_openai_api_key',
    'md_llms_gemini_api_key',
    'md_llms_default_provider',
    'md_llms_openai_model',
    'md_llms_gemini_model',
    'md_llms_openai_models',
    'md_llms_gemini_models',
);

foreach ( $options as $option ) {
    delete_option($option);
}

// Clear all object cache keys.
if (function_exists('wp_cache_delete') ) {
    wp_cache_delete('content_v1', 'md_llms_txt');
    wp_cache_delete('master_v1', 'md_llms_txt');
    wp_cache_delete('industry_v1', 'md_llms_txt');
    wp_cache_delete('content', 'md_llms_txt');
    wp_cache_delete('master_statement', 'md_llms_txt');
    wp_cache_delete('industry', 'md_llms_txt');
}

// Clear all transients.
delete_transient('md_llms_user_agents');
delete_transient('md_llms_builder_status');

// Unschedule all scheduled events.
$scheduled_hook = 'md_llms_builder_process_job';
$cron_array     = _get_cron_array();

if (is_array($cron_array) ) {
    foreach ( $cron_array as $timestamp => $crons ) {
        if (isset($crons[ $scheduled_hook ]) ) {
            foreach ( $crons[ $scheduled_hook ] as $key => $cron ) {
                wp_unschedule_event($timestamp, $scheduled_hook, $cron['args']);
            }
        }
    }
}

// Clean up uploaded files in llm-optimizer directory.
$uploads = wp_upload_dir();
if (! empty($uploads['basedir']) && empty($uploads['error']) ) {
    $plugin_upload_dir = trailingslashit($uploads['basedir']) . 'llm-optimizer';

    // Use WP_Filesystem API if available (VIP-compliant).
    global $wp_filesystem;
    if (empty($wp_filesystem) ) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if ($wp_filesystem && $wp_filesystem->exists($plugin_upload_dir) ) {
        $wp_filesystem->rmdir($plugin_upload_dir, true);
    }
}

// Flush rewrite rules to remove custom rewrite rules.
flush_rewrite_rules(false);
