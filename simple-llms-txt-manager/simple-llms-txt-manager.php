<?php
/**
 * Plugin Name: Simple LLMs.txt Manager
 * Description: Create, manage, and optimize your llms.txt file for AI crawlers. Includes a powerful Builder to generate context from your site content.
 * Version: 1.0.3
 * Author: Hilay Trivedi
 * Text Domain: simple-llms-txt-manager
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 * @package Simple_LLMs_Txt_Manager
 */

declare(strict_types=1);

if (! defined('ABSPATH') ) {
    exit;
}

if (! defined('MD_LLMS_TXT_PLUGIN_FILE') ) {
    define('MD_LLMS_TXT_PLUGIN_FILE', __FILE__);
}

// Load Builder dependencies.
require_once __DIR__ . '/includes/class-md-llms-builder-util.php';
require_once __DIR__ . '/includes/class-md-llms-builder-user-agent-rotator.php';
require_once __DIR__ . '/includes/class-md-llms-builder-robots.php';
require_once __DIR__ . '/includes/class-md-llms-builder-sitemap.php';
require_once __DIR__ . '/includes/class-md-llms-builder-fetch-result.php';
require_once __DIR__ . '/includes/class-md-llms-builder-fetcher.php';
require_once __DIR__ . '/includes/class-md-llms-builder-content-extractor.php';
require_once __DIR__ . '/includes/class-md-llms-builder-markdown-converter.php';
require_once __DIR__ . '/includes/class-md-llms-builder-summarizer.php';
require_once __DIR__ . '/includes/class-md-llms-builder-page-group.php';
require_once __DIR__ . '/includes/class-md-llms-builder-page-grouper.php';
require_once __DIR__ . '/includes/class-md-llms-builder-group-helper.php';
require_once __DIR__ . '/includes/class-md-llms-builder-summary-validator.php';
require_once __DIR__ . '/includes/class-md-llms-builder-artifacts.php';
require_once __DIR__ . '/includes/class-md-llms-builder-emitter.php';
require_once __DIR__ . '/includes/class-md-llms-builder-pipeline.php';
require_once __DIR__ . '/includes/class-md-llms-builder-job-store.php';
require_once __DIR__ . '/includes/class-md-llms-builder-plugin.php';

// Load Manager dependencies.
require_once __DIR__ . '/includes/class-md-llms-admin.php';
require_once __DIR__ . '/includes/class-md-llms-public.php';

/**
 * Main Manager Class.
 */
final class MD_LLMs_Txt_Manager
{


    const VERSION            = '1.0.3';
    const MENU_SLUG          = 'md-llm-optimizer';
    const QUERY_VAR          = 'md_llms_txt';
    const BLOCK_NAME         = 'md-llms-txt/llm-snippet';
    const CAPABILITY         = 'manage_options';
    const NONCE_SAVE         = 'md_llms_txt_save_action';
    const NONCE_CLEAR        = 'md_llms_txt_clear_action';
    const NONCE_CACHE        = 'md_llms_txt_cache_action';
    const CACHE_GROUP        = 'md_llms_txt';
    const CACHE_KEY          = 'content';
    const CACHE_KEY_MASTER   = 'master_statement';
    const CACHE_KEY_INDUSTRY = 'industry';
    const ROW_ID             = 1;

    const OPTION_MASTER_STATEMENT = 'md_llms_txt_master_statement';
    const OPTION_INDUSTRY         = 'md_llms_txt_industry';
    const OPTION_OPENAI_API_KEY   = 'md_llms_openai_api_key';
    const OPTION_GEMINI_API_KEY   = 'md_llms_gemini_api_key';
    const OPTION_DEFAULT_PROVIDER = 'md_llms_default_provider';
    const OPTION_OPENAI_MODEL     = 'md_llms_openai_model';
    const OPTION_GEMINI_MODEL     = 'md_llms_gemini_model';
    const OPTION_OPENAI_MODELS    = 'md_llms_openai_models';
    const OPTION_GEMINI_MODELS    = 'md_llms_gemini_models';

    const MERGE_FIELD_TOKENS = array(
    '{{Title}}',
    '{{Yoast_Title}}',
    '{{Yoast_MDescription}}',
    '{{Post_Author}}',
    '{{Permalink}}',
    '{{Last_Modified_Date}}',
    '{{Homepage}}',
    '{{Industry}}',
    );

    /**
     * Database table name.
     *
     * @var string
     */
    private $table;

    /**
     * Merge field values for template replacement.
     *
     * @var array<string, string>
     */
    private $merge_field_values = array();

    /**
     * Admin class instance.
     *
     * @var MD_LLMs_Admin
     */
    private $admin;

    /**
     * Public class instance.
     *
     * @var MD_LLMs_Public
     */
    private $public;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table  = $wpdb->prefix . 'md_llms_txt';
        $this->admin  = new MD_LLMs_Admin($this);
        $this->public = new MD_LLMs_Public($this);
    }

    /**
     * Register hooks.
     */
    public function hooks(): void
    {
        add_action('plugins_loaded', array( $this, 'load_textdomain' ));

        if (is_admin() ) {
            $this->admin->hooks();
        }

        $this->public->hooks();

        register_activation_hook(MD_LLMS_TXT_PLUGIN_FILE, array( __CLASS__, 'activate' ));
        register_deactivation_hook(MD_LLMS_TXT_PLUGIN_FILE, array( __CLASS__, 'deactivate' ));
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain('simple-llms-txt-manager', false, dirname(plugin_basename(MD_LLMS_TXT_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Get the database table name.
     *
     * @return string The table name.
     */
    public function get_table_name(): string
    {
        return $this->table;
    }

    /**
     * Get the llms.txt content from database.
     *
     * @return string The content.
     */
    public function get_content(): string
    {
        $cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
        if (is_string($cached) ) {
            return $cached;
        }

        global $wpdb;
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from prefixed constant.
        $sql     = $wpdb->prepare("SELECT content FROM {$this->table} WHERE id = %d", self::ROW_ID);
        $content = (string) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

        if (! is_string($content) ) {
            $content = '';
        }

        wp_cache_set(self::CACHE_KEY, $content, self::CACHE_GROUP);
        return $content;
    }

    /**
     * Get the master statement from options.
     *
     * @return string The master statement.
     */
    public function get_master_statement(): string
    {
        $cached = wp_cache_get(self::CACHE_KEY_MASTER, self::CACHE_GROUP);
        if (is_string($cached) ) {
            return $cached;
        }

        $raw = get_option(self::OPTION_MASTER_STATEMENT, '');
        if (! is_string($raw) ) {
            $raw = '';
        }

        $sanitized = $this->sanitize_plain_text($raw);
        wp_cache_set(self::CACHE_KEY_MASTER, $sanitized, self::CACHE_GROUP);

        return $sanitized;
    }

    /**
     * Get the industry from options.
     *
     * @return string The industry.
     */
    public function get_industry(): string
    {
        $cached = wp_cache_get(self::CACHE_KEY_INDUSTRY, self::CACHE_GROUP);
        if (is_string($cached) ) {
            return $cached;
        }

        $raw = get_option(self::OPTION_INDUSTRY, '');
        if (! is_string($raw) ) {
            $raw = '';
        }

        $sanitized = $this->sanitize_plain_text($raw);
        wp_cache_set(self::CACHE_KEY_INDUSTRY, $sanitized, self::CACHE_GROUP);

        return $sanitized;
    }

    /**
     * Get the stored OpenAI API key.
     *
     * @return string The API key.
     */
    public function get_stored_openai_key(): string
    {
        if (defined('MD_LLMS_OPENAI_API_KEY') && is_string(MD_LLMS_OPENAI_API_KEY) && '' !== MD_LLMS_OPENAI_API_KEY ) {
            return MD_LLMS_OPENAI_API_KEY;
        }

        $value = get_option(self::OPTION_OPENAI_API_KEY, '');
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Get the stored Gemini API key.
     *
     * @return string The API key.
     */
    public function get_stored_gemini_key(): string
    {
        if (defined('MD_LLMS_GEMINI_API_KEY') && is_string(MD_LLMS_GEMINI_API_KEY) && '' !== MD_LLMS_GEMINI_API_KEY ) {
            return MD_LLMS_GEMINI_API_KEY;
        }

        $value = get_option(self::OPTION_GEMINI_API_KEY, '');
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Check if llms.txt is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    public function is_enabled(): bool
    {
        global $wpdb;
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from prefixed constant.
        $sql     = $wpdb->prepare("SELECT enabled FROM {$this->table} WHERE id = %d", self::ROW_ID);
        $enabled = $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

        if (null === $enabled ) {
            return true;
        }
        return 1 === (int) $enabled;
    }

    /**
     * Set the llms.txt content in database.
     *
     * @param  string $content The content to save.
     * @return void
     */
    public function set_content( string $content ): void
    {
        global $wpdb;
        $now = current_time('mysql', true);
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from prefixed constant.
        $exists_q = $wpdb->prepare("SELECT COUNT(1) FROM {$this->table} WHERE id = %d", self::ROW_ID);
        $existing = (int) $wpdb->get_var($exists_q); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

        if ($existing ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $this->table,
                array(
                'content'    => $content,
                'updated_at' => $now,
                ),
                array( 'id' => self::ROW_ID ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $this->table,
                array(
                'id'         => self::ROW_ID,
                'content'    => $content,
                'enabled'    => 1,
                'updated_at' => $now,
                ),
                array( '%d', '%s', '%d', '%s' )
            );
        }
    }

    /**
     * Set the master statement in options.
     *
     * @param  string $statement The master statement.
     * @return void
     */
    public function set_master_statement( string $statement ): void
    {
        $statement = $this->sanitize_plain_text($statement);
        update_option(self::OPTION_MASTER_STATEMENT, $statement);
        wp_cache_set(self::CACHE_KEY_MASTER, $statement, self::CACHE_GROUP);
    }

    /**
     * Set the industry in options.
     *
     * @param  string $industry The industry.
     * @return void
     */
    public function set_industry( string $industry ): void
    {
        $industry = $this->sanitize_plain_text($industry);
        update_option(self::OPTION_INDUSTRY, $industry);
        wp_cache_set(self::CACHE_KEY_INDUSTRY, $industry, self::CACHE_GROUP);
    }

    /**
     * Set the OpenAI API key.
     *
     * @param  string $value The API key.
     * @return void
     */
    public function set_openai_key( string $value ): void
    {
        update_option(self::OPTION_OPENAI_API_KEY, trim($value), false);
    }

    /**
     * Set the Gemini API key.
     *
     * @param  string $value The API key.
     * @return void
     */
    public function set_gemini_key( string $value ): void
    {
        update_option(self::OPTION_GEMINI_API_KEY, trim($value), false);
    }

    /**
     * Set the default LLM provider.
     *
     * @param  string $provider The provider name (openai, gemini, or none).
     * @return void
     */
    public function set_default_provider( string $provider ): void
    {
        $allowed = array( 'none', 'openai', 'gemini' );
        if (! in_array($provider, $allowed, true) ) {
            $provider = 'none';
        }
        update_option(self::OPTION_DEFAULT_PROVIDER, $provider, false);
    }

    /**
     * Set the model for a specific provider.
     *
     * @param  string $provider The provider name.
     * @param  string $model    The model identifier.
     * @return void
     */
    public function set_provider_model( string $provider, string $model ): void
    {
        $model = sanitize_text_field($model);
        if ('openai' === $provider ) {
            update_option(self::OPTION_OPENAI_MODEL, $model, false);
        } elseif ('gemini' === $provider ) {
            update_option(self::OPTION_GEMINI_MODEL, $model, false);
        }
    }

    /**
     * Set the enabled status.
     *
     * @param  int $enabled 1 for enabled, 0 for disabled.
     * @return void
     */
    public function set_enabled( int $enabled ): void
    {
        global $wpdb;
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from prefixed constant.
        $exists_q = $wpdb->prepare("SELECT COUNT(1) FROM {$this->table} WHERE id = %d", self::ROW_ID);
        $existing = (int) $wpdb->get_var($exists_q); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

        if ($existing ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $this->table,
                array( 'enabled' => $enabled ),
                array( 'id' => self::ROW_ID ),
                array( '%d' ),
                array( '%d' )
            );
        } else {
            $now = current_time('mysql', true);
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $this->table,
                array(
                'id'         => self::ROW_ID,
                'content'    => '',
                'enabled'    => $enabled,
                'updated_at' => $now,
                ),
                array( '%d', '%s', '%d', '%s' )
            );
        }
    }

    /**
     * Clear all cached values.
     *
     * @return void
     */
    public function bust_cache(): void
    {
        wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
        wp_cache_delete(self::CACHE_KEY_MASTER, self::CACHE_GROUP);
        wp_cache_delete(self::CACHE_KEY_INDUSTRY, self::CACHE_GROUP);
    }

    /**
     * Clear all transients used by the plugin.
     *
     * @return void
     */
    public function clear_transients(): void
    {
        delete_transient('md_llms_user_agents');
        delete_transient('md_llms_builder_status');
    }

    /**
     * Prime the cache with current values.
     *
     * @return void
     */
    public function prime_cache(): void
    {
        wp_cache_set(self::CACHE_KEY, $this->read_content_raw(), self::CACHE_GROUP);
        wp_cache_set(self::CACHE_KEY_MASTER, $this->read_master_statement_raw(), self::CACHE_GROUP);
        wp_cache_set(self::CACHE_KEY_INDUSTRY, $this->read_industry_raw(), self::CACHE_GROUP);
    }

    /**
     * Read content directly from database without caching.
     *
     * @return string The raw content.
     */
    private function read_content_raw(): string
    {
        global $wpdb;
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, comes from prefixed constant.
        $sql     = $wpdb->prepare("SELECT content FROM {$this->table} WHERE id = %d", self::ROW_ID);
        $content = (string) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return is_string($content) ? $content : '';
    }

    /**
     * Read master statement directly from options without caching.
     *
     * @return string The raw master statement.
     */
    private function read_master_statement_raw(): string
    {
        $value = get_option(self::OPTION_MASTER_STATEMENT, '');
        if (! is_string($value) ) {
            return '';
        }

        return $this->sanitize_plain_text($value);
    }

    /**
     * Read industry directly from options without caching.
     *
     * @return string The raw industry.
     */
    private function read_industry_raw(): string
    {
        $value = get_option(self::OPTION_INDUSTRY, '');
        if (! is_string($value) ) {
            return '';
        }

        return $this->sanitize_plain_text($value);
    }

    /**
     * Purge edge cache for llms.txt and related URLs.
     *
     * @param  array $extra_urls Additional URLs to purge.
     * @return void
     */
    public function purge_edge_cache( array $extra_urls = array() ): void
    {
        if (! function_exists('home_url') ) {
            return;
        }

        $defaults = array(
        home_url('/llms.txt'),
        home_url('/'),
        );

        $urls = array_merge($defaults, $extra_urls);
        $urls = array_filter(
            array_unique(
                array_map(
                    static function ( $url ) {
                        return is_string($url) ? trim($url) : '';
                    },
                    $urls
                )
            )
        );

        if (empty($urls) ) {
            return;
        }

        $urls = array_map('esc_url_raw', $urls);
        $urls = apply_filters('md_llms_txt_purge_urls', $urls);

        if (empty($urls) ) {
            return;
        }

        if (function_exists('wpcom_vip_purge_edge_cache_for_url') ) {
            foreach ( $urls as $url ) {
                wpcom_vip_purge_edge_cache_for_url($url);
            }
        }

        do_action('md_llms_txt_purged_urls', $urls);
    }

    /**
     * Sanitize plain text content.
     *
     * @param  string $value The value to sanitize.
     * @return string The sanitized value.
     */
    public function sanitize_plain_text( string $value ): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value);
        if (null === $value ) {
            $value = '';
        }
        $value = str_replace(array( "\r\n", "\r" ), "\n", $value);
        return wp_check_invalid_utf8($value, true);
    }

    /**
     * Sanitize content for use in HTML comments.
     *
     * @param  string $value The value to sanitize.
     * @return string The sanitized value.
     */
    public function sanitize_comment_fragment( string $value ): string
    {
        $value = $this->sanitize_plain_text($value);
        $value = $this->disarm_comment_delimiter($value);

        return trim($value);
    }

    /**
     * Disarm HTML comment delimiters to prevent breaking comments.
     *
     * @param  string $value The value to process.
     * @return string The processed value.
     */
    public function disarm_comment_delimiter( string $value ): string
    {
        return str_replace('-->', '-- >', $value);
    }

    /**
     * Ensure snippet has START and END delimiters.
     *
     * @param  string $value The snippet content.
     * @return string The delimited snippet.
     */
    public function ensure_snippet_delimiters( string $value ): string
    {
        if ('' === trim($value) ) {
            return '';
        }

        $has_start = false !== strpos($value, '```START```');
        $has_end   = false !== strpos($value, '```END```');

        if ($has_start && $has_end ) {
            return $value;
        }

        $body = str_replace(array( '```START```', '```END```' ), '', $value);
        $body = trim($body);

        if ('' === $body ) {
            return '';
        }

        return "```START```\n" . $body . "\n```END```";
    }

    /**
     * Remove snippet delimiters from content.
     *
     * @param  string $value The snippet content.
     * @return string The content without delimiters.
     */
    public function strip_snippet_delimiters( string $value ): string
    {
        if ('' === $value ) {
            return '';
        }

        $value = str_replace(array( '```START```', '```END```' ), '', $value);

        return trim($value);
    }

    /**
     * Check if currently requesting a block preview.
     *
     * @return bool True if requesting preview.
     */
    public function is_requesting_block_preview(): bool
    {
        if (is_admin() && ! wp_doing_ajax() ) {
            return true;
        }

        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Extract snippet content from parsed blocks.
     *
     * @param  array $blocks The parsed blocks.
     * @return array The extracted snippets.
     */
    public function extract_snippet_content_from_blocks( array $blocks ): array
    {
        $snippets = array();

        foreach ( $blocks as $block ) {
            if (! is_array($block) ) {
                continue;
            }

            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';

            if (self::BLOCK_NAME === $name ) {
                $attrs    = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
                $content  = isset($attrs['content']) ? (string) $attrs['content'] : '';
                $content  = $this->apply_merge_fields($content);
                $sanitary = $this->sanitize_comment_fragment($content);

                if ('' !== $sanitary ) {
                    $snippets[] = $sanitary;
                }
            }

            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks']) ) {
                $snippets = array_merge($snippets, $this->extract_snippet_content_from_blocks($block['innerBlocks']));
            }
        }

        return $snippets;
    }

    /**
     * Prepare merge field values for a post.
     *
     * @param  \WP_Post $post The post object.
     * @return void
     */
    public function prepare_merge_field_values( \WP_Post $post ): void
    {
        $values = array_fill_keys(self::MERGE_FIELD_TOKENS, '');

        $home_option = get_option('home', '');
        if (! is_string($home_option) || '' === $home_option ) {
            $home_option = home_url('/');
        }
        if (is_string($home_option) ) {
            $values['{{Homepage}}'] = $this->sanitize_plain_text($home_option);
        }

        $wp_title = get_the_title($post);
        if (is_string($wp_title) ) {
            $values['{{Title}}'] = $this->sanitize_plain_text($wp_title);
        }

        $author = get_the_author_meta('display_name', (int) $post->post_author);
        if (is_string($author) ) {
            $values['{{Post_Author}}'] = $this->sanitize_plain_text($author);
        }

        $permalink = get_permalink($post);
        if (is_string($permalink) ) {
            $values['{{Permalink}}'] = $this->sanitize_plain_text($permalink);
        }

        $modified = get_post_modified_time('c', true, $post);
        if (is_string($modified) ) {
            $values['{{Last_Modified_Date}}'] = $this->sanitize_plain_text($modified);
        }

        $yoast_title = '';
        if (class_exists('\WPSEO_Meta') ) {
            $yoast_title = (string) \WPSEO_Meta::get_value('title', $post->ID);
        }

        if ('' === $yoast_title && isset($values['{{Title}}']) ) {
            $yoast_title = $values['{{Title}}'];
        }

        if (is_string($yoast_title) ) {
            $values['{{Yoast_Title}}'] = $this->sanitize_plain_text($yoast_title);
        }

        $yoast_desc = '';
        if (class_exists('\WPSEO_Meta') ) {
            $yoast_desc = (string) \WPSEO_Meta::get_value('metadesc', $post->ID);
        }

        if ('' === $yoast_desc ) {
            if (has_excerpt($post) ) {
                $yoast_desc = (string) $post->post_excerpt;
            } else {
                $yoast_desc = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 55);
            }
        }

        if (is_string($yoast_desc) ) {
            $values['{{Yoast_MDescription}}'] = $this->sanitize_plain_text($yoast_desc);
        }

        $this->merge_field_values = $values;
    }

    /**
     * Apply merge fields to content.
     *
     * @param  string $value The content with merge field tokens.
     * @return string The content with tokens replaced.
     */
    public function apply_merge_fields( string $value ): string
    {
        if ('' === $value ) {
            return '';
        }

        $replacements = $this->get_merge_field_replacements();

        if (empty($replacements) ) {
            return $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    /**
     * Get merge field replacement values.
     *
     * @return array The replacement values.
     */
    private function get_merge_field_replacements(): array
    {
        $defaults = array_fill_keys(self::MERGE_FIELD_TOKENS, '');
        $globals  = $this->get_global_merge_field_values();

        return $globals + $this->merge_field_values + $defaults;
    }

    /**
     * Get global merge field values.
     *
     * @return array The global values.
     */
    private function get_global_merge_field_values(): array
    {
        $home_option = get_option('home', '');
        if (! is_string($home_option) || '' === $home_option ) {
            $home_option = home_url('/');
        }

        $homepage = '';
        if (is_string($home_option) ) {
            $homepage = $this->sanitize_plain_text($home_option);
        }

        return array(
        '{{Homepage}}' => $homepage,
        '{{Industry}}' => $this->get_industry(),
        );
    }

    /**
     * Get merge field descriptions for UI.
     *
     * @return array The field descriptions.
     */
    public function get_merge_field_descriptions(): array
    {
        return array(
        '{{Title}}'              => __('Page or post title.', 'md-llms-txt'),
        '{{Yoast_Title}}'        => __('Yoast SEO title when set; falls back to the page title.', 'md-llms-txt'),
        '{{Yoast_MDescription}}' => __('Yoast SEO meta description, excerpt, or trimmed content.', 'md-llms-txt'),
        '{{Post_Author}}'        => __('Display name of the post author.', 'md-llms-txt'),
        '{{Permalink}}'          => __('Canonical permalink URL for the page or post.', 'md-llms-txt'),
        '{{Last_Modified_Date}}' => __('UTC ISO8601 timestamp of the last modification.', 'md-llms-txt'),
        '{{Homepage}}'           => __('Site Address (URL) configured in Settings → General.', 'md-llms-txt'),
        '{{Industry}}'           => __('Organization category or industry defined in plugin settings.', 'md-llms-txt'),
        );
    }

    /**
     * Get saved models for a provider.
     *
     * @param  string $provider The provider name.
     * @return array The saved models.
     */
    private function get_saved_models( string $provider ): array
    {
        $option = 'openai' === $provider ? self::OPTION_OPENAI_MODELS : self::OPTION_GEMINI_MODELS;
        $value  = get_option($option, array());

        if (! is_array($value) ) {
            return array();
        }

        return array_values(
            array_filter(
                array_map(
                    static function ( $model ) {
                        return is_string($model) ? sanitize_text_field($model) : null;
                    },
                    $value
                )
            )
        );
    }

    /**
     * Prepare models for settings display.
     *
     * @param  string $provider The provider name.
     * @param  string $fallback The fallback model.
     * @return array The prepared models.
     */
    public function prepare_models_for_settings( string $provider, string $fallback ): array
    {
        $models = $this->get_saved_models($provider);

        if (empty($models) ) {
            $models = array( $fallback );
        }

        $models = array_values(array_unique(array_filter($models)));

        return $models;
    }

    /**
     * Persist a model choice to options.
     *
     * @param  string $provider The provider name.
     * @param  string $model    The model identifier.
     * @return void
     */
    public function persist_model_choice( string $provider, string $model ): void
    {
        $model = sanitize_text_field($model);
        if ('' === $model ) {
            return;
        }

        $models = $this->get_saved_models($provider);
        if (in_array($model, $models, true) ) {
            return;
        }

        $models[] = $model;
        $option   = 'openai' === $provider ? self::OPTION_OPENAI_MODELS : self::OPTION_GEMINI_MODELS;
        update_option($option, $models, false);
    }

    /**
     * Fetch available models from OpenAI API.
     *
     * @param  string $api_key The API key.
     * @return array The models and default.
     */
    public function fetch_openai_models( string $api_key ): array
    {
        $response = wp_safe_remote_get(
            'https://api.openai.com/v1/models',
            array(
            'timeout'            => 20,
            'headers'            => array(
            'Authorization' => 'Bearer ' . $api_key,
            'User-Agent'    => 'md-llm-optimizer/' . self::VERSION,
            ),
            'reject_unsafe_urls' => true,
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response) ) {
            return array(
            'models'  => array(),
            'default' => '',
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['data']) ) {
            return array(
            'models'  => array(),
            'default' => '',
            );
        }

        $models = array();
        foreach ( $body['data'] as $entry ) {
            if (! is_array($entry) || empty($entry['id']) ) {
                continue;
            }

            $id = sanitize_text_field($entry['id']);
            if ('' === $id ) {
                continue;
            }

            if (preg_match('/^(gpt|o1)/i', $id) ) {
                $models[] = $id;
            }
        }

        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return array(
        'models'  => $models,
        'default' => $this->determine_default_model($models, array( 'gpt-4o-mini', 'gpt-4o' )),
        );
    }

    /**
     * Fetch available models from Gemini API.
     *
     * @param  string $api_key The API key.
     * @return array The models and default.
     */
    public function fetch_gemini_models( string $api_key ): array
    {
        $url = add_query_arg(
            array(
            'key' => $api_key,
            ),
            'https://generativelanguage.googleapis.com/v1beta/models'
        );

        $response = wp_safe_remote_get(
            $url,
            array(
            'timeout'            => 20,
            'user-agent'         => 'md-llm-optimizer/' . self::VERSION,
            'reject_unsafe_urls' => true,
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response) ) {
            return array(
            'models'  => array(),
            'default' => '',
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['models']) ) {
            return array(
            'models'  => array(),
            'default' => '',
            );
        }

        $models = array();
        foreach ( $body['models'] as $entry ) {
            if (! is_array($entry) || empty($entry['name']) ) {
                continue;
            }

            $name = sanitize_text_field($entry['name']);
            if ('' === $name ) {
                continue;
            }

            $name = preg_replace('#^models/#', '', $name);
            if ('' === $name ) {
                continue;
            }

            $methods = isset($entry['supportedGenerationMethods']) && is_array($entry['supportedGenerationMethods']) ? $entry['supportedGenerationMethods'] : array();
            if (! in_array('generateContent', $methods, true) ) {
                continue;
            }

            $models[] = $name;
        }

        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return array(
        'models'  => $models,
        'default' => $this->determine_default_model($models, array( 'gemini-1.5-pro', 'gemini-1.5-flash' )),
        );
    }

    /**
     * Determine the default model from a list.
     *
     * @param  array $models    Available models.
     * @param  array $preferred Preferred models in order.
     * @return string The default model.
     */
    private function determine_default_model( array $models, array $preferred ): string
    {
        foreach ( $preferred as $candidate ) {
            if (in_array($candidate, $models, true) ) {
                return $candidate;
            }
        }

        return $models[0] ?? '';
    }

    /**
     * Ensure an option has autoload disabled.
     *
     * @param  string $option The option name.
     * @return void
     */
    public function ensure_option_autoload_off( string $option ): void
    {
        $value = get_option($option, '__md_llms_missing__');
        if ('__md_llms_missing__' === $value ) {
            return;
        }

        update_option($option, $value, false);
    }

    /**
     * Plugin activation hook.
     *
     * @return void
     */
    public static function activate(): void
    {
        global $wpdb;

        $table           = $wpdb->prefix . 'md_llms_txt';
        $charset_collate = $wpdb->get_charset_collate();

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL,
			content LONGTEXT NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

        dbDelta($sql); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbDelta

     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe.
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE id = %d", self::ROW_ID));
        if (0 === $exists ) {
            $now = current_time('mysql', true);
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $table,
                array(
                'id'         => self::ROW_ID,
                'content'    => "User-agent: *\nAllow: /",
                'enabled'    => 1,
                'updated_at' => $now,
                ),
                array( '%d', '%s', '%d', '%s' )
            );
        }

        add_option(self::OPTION_MASTER_STATEMENT, '');

        add_rewrite_tag('%md_llms_txt%', '([1])');
        add_rewrite_rule('^llms\.txt$', 'index.php?md_llms_txt=1', 'top');
        flush_rewrite_rules(false);
    }

    /**
     * Plugin deactivation hook.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }
}

$md_llms_txt_manager = new MD_LLMs_Txt_Manager();
$md_llms_txt_manager->hooks();

$md_llms_builder_plugin = new MD_LLMs_Builder_Plugin();
$md_llms_builder_plugin->hooks();
