<?php
/**
 * Admin functionality for LLMs.txt Manager.
 *
 * @package MD_LLMs_Txt
 */

declare(strict_types=1);

if (! defined('ABSPATH') ) {
    exit;
}

/**
 * Admin functionality handler.
 *
 * Manages admin menu, settings page, AJAX handlers, and schema upgrades.
 */
class MD_LLMs_Admin
{


    /**
     * Main manager instance.
     *
     * @var MD_LLMs_Txt_Manager
     */
    private $manager;

    /**
     * Constructor.
     *
     * @param MD_LLMs_Txt_Manager $manager Main manager instance.
     */
    public function __construct( MD_LLMs_Txt_Manager $manager )
    {
        $this->manager = $manager;
    }

    /**
     * Register admin hooks.
     */
    public function hooks(): void
    {
        add_action('admin_init', array( $this, 'maybe_upgrade_schema' ));
        add_action('admin_menu', array( $this, 'register_admin_menu' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ));
        add_action('admin_post_md_llms_txt_save', array( $this, 'handle_save' ));
        add_action('admin_post_md_llms_txt_clear', array( $this, 'handle_clear' ));
        add_action('admin_post_md_llms_txt_cache', array( $this, 'handle_cache' ));
        add_action('wp_ajax_md_llms_fetch_models', array( $this, 'ajax_fetch_models' ));
    }

    /**
     * Ensure schema is current on admin hits (adds 'enabled' column for legacy installs).
     */
    public function maybe_upgrade_schema(): void
    {
        global $wpdb;
        $table = $this->manager->get_table_name();
     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
        $col = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'enabled'");
        if (empty($col) ) {
            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL,
				content LONGTEXT NOT NULL,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id)
			) {$charset_collate};";
            dbDelta($sql); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbDelta
         // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema update.
            $wpdb->query("UPDATE {$table} SET enabled = 1 WHERE id = 1 AND (enabled IS NULL OR enabled = '')");
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_MASTER_STATEMENT, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_MASTER_STATEMENT, '');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_INDUSTRY, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_INDUSTRY, '');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_API_KEY, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_API_KEY, '', '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_API_KEY, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_API_KEY, '', '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER, 'none', '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL, 'gpt-4o-mini', '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL, 'gemini-1.5-pro', '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS, array(), '', 'no');
        }

        if (false === get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS, false) ) {
            add_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS, array(), '', 'no');
        }

        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_OPENAI_API_KEY);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_GEMINI_API_KEY);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS);
        $this->manager->ensure_option_autoload_off(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS);
    }

    /**
     * Register admin menu and submenu pages.
     */
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('LLM Optimizer', 'md-llms-txt'),
            __('LLM Optimizer', 'md-llms-txt'),
            MD_LLMs_Txt_Manager::CAPABILITY,
            MD_LLMs_Txt_Manager::MENU_SLUG,
            array( $this, 'render_settings_page' ),
            'dashicons-analytics',
            100
        );

        add_submenu_page(
            MD_LLMs_Txt_Manager::MENU_SLUG,
            __('LLMs.txt', 'md-llms-txt'),
            __('LLMs.txt', 'md-llms-txt'),
            MD_LLMs_Txt_Manager::CAPABILITY,
            MD_LLMs_Txt_Manager::MENU_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets for settings page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_settings_assets( string $hook ): void
    {
        if (false === strpos($hook, MD_LLMs_Txt_Manager::MENU_SLUG) ) {
            return;
        }

        $style_path = plugin_dir_path(MD_LLMS_TXT_PLUGIN_FILE) . 'assets/css/llm-builder-admin.css';
        $style_url  = plugins_url('assets/css/llm-builder-admin.css', MD_LLMS_TXT_PLUGIN_FILE);
        $style_ver  = file_exists($style_path) ? (string) filemtime($style_path) : MD_LLMs_Txt_Manager::VERSION;

        wp_enqueue_style(
            'md-llms-builder-admin',
            $style_url,
            array(),
            $style_ver
        );

        $script_path = plugin_dir_path(MD_LLMS_TXT_PLUGIN_FILE) . 'assets/js/llm-settings-admin.js';
        $script_url  = plugins_url('assets/js/llm-settings-admin.js', MD_LLMS_TXT_PLUGIN_FILE);
        $script_ver  = file_exists($script_path) ? (string) filemtime($script_path) : MD_LLMs_Txt_Manager::VERSION;

        wp_enqueue_script(
            'md-llms-settings-admin',
            $script_url,
            array( 'jquery' ),
            $script_ver,
            true
        );

        wp_localize_script(
            'md-llms-settings-admin',
            'mdLlmsSettings',
            array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('md_llms_fetch_models'),
            'labels'  => array(
            'refreshing' => __('Refreshing…', 'md-llms-txt'),
            'updated'    => __('Model list updated.', 'md-llms-txt'),
            'error'      => __('Unable to refresh models.', 'md-llms-txt'),
            ),
            )
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page(): void
    {
        if (! current_user_can(MD_LLMs_Txt_Manager::CAPABILITY) ) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'md-llms-txt'));
        }

        $content          = $this->manager->get_content();
        $enabled          = $this->manager->is_enabled();
        $master_statement = $this->manager->get_master_statement();
        $industry         = $this->manager->get_industry();
        $openai_defined   = defined('MD_LLMS_OPENAI_API_KEY');
        $gemini_defined   = defined('MD_LLMS_GEMINI_API_KEY');
        $openai_key       = $this->manager->get_stored_openai_key();
        $gemini_key       = $this->manager->get_stored_gemini_key();
        $endpoint         = home_url('/llms.txt');
        $action           = admin_url('admin-post.php');
        $merge_fields     = $this->manager->get_merge_field_descriptions();
        $default_provider = get_option(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER, 'none');
        $openai_models    = $this->manager->prepare_models_for_settings('openai', 'gpt-4o-mini');
        $gemini_models    = $this->manager->prepare_models_for_settings('gemini', 'gemini-1.5-pro');
        $openai_model     = get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL, 'gpt-4o-mini');
        $gemini_model     = get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL, 'gemini-1.5-pro');
        if ($openai_model && ! in_array($openai_model, $openai_models, true) ) {
            $openai_models[] = $openai_model;
        }
        if ($gemini_model && ! in_array($gemini_model, $gemini_models, true) ) {
            $gemini_models[] = $gemini_model;
        }
        sort($openai_models, SORT_NATURAL | SORT_FLAG_CASE);
        sort($gemini_models, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
        <div class="wrap md-llms-wrap md-llms-settings">
            <div class="md-llms-header">
                <div class="md-llms-header__content">
                    <div>
                        <span class="md-llms-badge"><?php esc_html_e('LLM Optimizer', 'md-llms-txt'); ?></span>
                        <h1><?php esc_html_e('LLMs.txt Settings', 'md-llms-txt'); ?></h1>
                        <p class="md-llms-subtitle"><?php esc_html_e('Control the public llms.txt output and manage AI provider credentials used by the Builder.', 'md-llms-txt'); ?></p>
                    </div>
                    <div class="md-llms-header__actions">
                        <a class="button button-secondary md-llms-button-ghost" href="<?php echo esc_url($endpoint); ?>" target="_blank" rel="noreferrer">
        <?php esc_html_e('View llms.txt', 'md-llms-txt'); ?>
                        </a>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . MD_LLMs_Builder_Plugin::MENU_SLUG)); ?>">
        <?php esc_html_e('Open Builder', 'md-llms-txt'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url($action); ?>" class="md-llms-settings-form" id="md-llms-settings-form">
        <?php wp_nonce_field(MD_LLMs_Txt_Manager::NONCE_SAVE); ?>
                <input type="hidden" name="action" value="md_llms_txt_save" />

                <div class="md-llms-grid md-llms-settings__grid">
                    <section class="md-llms-card md-llms-card--primary md-llms-settings__content">
                        <header class="md-llms-card__header">
                            <div>
                                <h2><?php esc_html_e('LLMs.txt Output', 'md-llms-txt'); ?></h2>
                                <p><?php esc_html_e('Edit the canonical llms.txt body, master statement, and industry metadata used in merge fields.', 'md-llms-txt'); ?></p>
                            </div>
                        </header>
                        <div class="md-llms-form">
                            <div class="md-llms-field">
                                <label class="md-llms-checkbox">
                                    <input type="checkbox" name="md_llms_txt_enabled" value="1" <?php checked($enabled); ?> />
                                    <span><?php esc_html_e('Serve llms.txt (disable to redirect to the homepage)', 'md-llms-txt'); ?></span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Public endpoint:', 'md-llms-txt'); ?>
                                    <code><?php echo esc_html($endpoint); ?></code>
                                </p>
                            </div>

                            <div class="md-llms-field">
                                <label for="md_llms_txt_content"><?php esc_html_e('LLMs.txt Content', 'md-llms-txt'); ?></label>
                                <textarea
                                    id="md_llms_txt_content"
                                    name="md_llms_txt_content"
                                    rows="16"
                                    class="md-llms-monospace"
                                ><?php echo esc_textarea($content); ?></textarea>
                                <p class="description"><?php esc_html_e('Plain text only. Served verbatim with text/plain headers. LONGTEXT field supports large payloads.', 'md-llms-txt'); ?></p>
                            </div>

                            <div class="md-llms-field">
                                <label for="md_llms_txt_master_statement"><?php esc_html_e('Master LLM Statement', 'md-llms-txt'); ?></label>
                                <textarea
                                    id="md_llms_txt_master_statement"
                                    name="md_llms_txt_master_statement"
                                    rows="6"
                                    class="md-llms-monospace"
                                ><?php echo esc_textarea($master_statement); ?></textarea>
                                <p class="description"><?php esc_html_e('Optional helper comment rendered on every public page for LLM crawlers.', 'md-llms-txt'); ?></p>
                            </div>

                            <div class="md-llms-field">
                                <label for="md_llms_txt_industry"><?php esc_html_e('Organization Category / Industry', 'md-llms-txt'); ?></label>
                                <input type="text" id="md_llms_txt_industry" name="md_llms_txt_industry" value="<?php echo esc_attr($industry); ?>" />
                                <p class="description"><?php esc_html_e('Populates the {{Industry}} merge field across comments and snippets.', 'md-llms-txt'); ?></p>
                            </div>

        <?php if (! empty($merge_fields) ) : ?>
                                <div class="md-llms-field">
                                    <details class="md-llms-merge-fields">
                                        <summary><?php esc_html_e('Available merge fields', 'md-llms-txt'); ?></summary>
                                        <ul>
            <?php foreach ( $merge_fields as $token => $description ) : ?>
                                                <li><code><?php echo esc_html($token); ?></code> — <?php echo esc_html($description); ?></li>
            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                </div>
        <?php endif; ?>
                        </div>
                    </section>
                </div>
            </form>

            <div class="md-llms-settings__actions">
                <button type="submit" form="md-llms-settings-form" class="button button-primary button-hero">
        <?php esc_html_e('Save settings', 'md-llms-txt'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Handle settings form submission.
     */
    public function handle_save(): void
    {
        if (! current_user_can(MD_LLMs_Txt_Manager::CAPABILITY) ) {
            wp_die(esc_html__('Insufficient permissions.', 'md-llms-txt'));
        }
        check_admin_referer(MD_LLMs_Txt_Manager::NONCE_SAVE);

     // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via manager->sanitize_plain_text().
        $raw_content  = isset($_POST['md_llms_txt_content']) ? (string) wp_unslash($_POST['md_llms_txt_content']) : '';
        $raw_master   = isset($_POST['md_llms_txt_master_statement']) ? (string) wp_unslash($_POST['md_llms_txt_master_statement']) : '';
        $raw_industry = isset($_POST['md_llms_txt_industry']) ? (string) wp_unslash($_POST['md_llms_txt_industry']) : '';
     // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content  = $this->manager->sanitize_plain_text($raw_content);
        $master   = $this->manager->sanitize_plain_text($raw_master);
        $industry = $this->manager->sanitize_plain_text($raw_industry);
        $enabled  = isset($_POST['md_llms_txt_enabled']) ? 1 : 0;

        if (! defined('MD_LLMS_OPENAI_API_KEY') ) {
            $openai_key = isset($_POST['md_llms_openai_api_key']) ? sanitize_text_field(wp_unslash($_POST['md_llms_openai_api_key'])) : '';
            if ('' !== $openai_key ) {
                $this->manager->set_openai_key($openai_key);
            } elseif (isset($_POST['md_llms_openai_api_key_delete']) ) {
                $this->manager->set_openai_key('');
            }
        }

        if (! defined('MD_LLMS_GEMINI_API_KEY') ) {
            $gemini_key = isset($_POST['md_llms_gemini_api_key']) ? sanitize_text_field(wp_unslash($_POST['md_llms_gemini_api_key'])) : '';
            if ('' !== $gemini_key ) {
                $this->manager->set_gemini_key($gemini_key);
            } elseif (isset($_POST['md_llms_gemini_api_key_delete']) ) {
                $this->manager->set_gemini_key('');
            }
        }

        $default_provider = isset($_POST['md_llms_default_provider']) ? sanitize_text_field(wp_unslash($_POST['md_llms_default_provider'])) : 'none';
        $this->manager->set_default_provider($default_provider);

        if (isset($_POST['md_llms_openai_model']) ) {
            $openai_model = sanitize_text_field(wp_unslash($_POST['md_llms_openai_model']));
            $this->manager->set_provider_model('openai', $openai_model);
            $this->manager->persist_model_choice('openai', $openai_model);
        }

        if (isset($_POST['md_llms_gemini_model']) ) {
            $gemini_model = sanitize_text_field(wp_unslash($_POST['md_llms_gemini_model']));
            $this->manager->set_provider_model('gemini', $gemini_model);
            $this->manager->persist_model_choice('gemini', $gemini_model);
        }

        $this->manager->set_content($content);
        $this->manager->set_enabled($enabled);
        $this->manager->set_master_statement($master);
        $this->manager->set_industry($industry);

        // Clear all caches and transients on save.
        $this->manager->bust_cache();
        $this->manager->prime_cache();
        $this->manager->purge_edge_cache();
        $this->manager->clear_transients();

        // Redirect to Builder page if requested, otherwise Settings page.
        $redirect_to_builder = isset($_POST['redirect_to_builder']) && '1' === $_POST['redirect_to_builder'];
        $redirect_page       = $redirect_to_builder ? MD_LLMs_Builder_Plugin::MENU_SLUG : MD_LLMs_Txt_Manager::MENU_SLUG;

        wp_safe_redirect(
            add_query_arg(
                array( 'updated' => '1' ),
                admin_url('admin.php?page=' . rawurlencode($redirect_page))
            )
        );
        exit;
    }

    /**
     * Handle clear content action.
     */
    public function handle_clear(): void
    {
        if (! current_user_can(MD_LLMs_Txt_Manager::CAPABILITY) ) {
            wp_die(esc_html__('Insufficient permissions.', 'md-llms-txt'));
        }
        check_admin_referer(MD_LLMs_Txt_Manager::NONCE_CLEAR);

        $this->manager->set_content('');
        $this->manager->bust_cache();
        $this->manager->prime_cache();
        $this->manager->purge_edge_cache();

        wp_safe_redirect(
            add_query_arg(
                array( 'cleared' => '1' ),
                admin_url('admin.php?page=' . rawurlencode(MD_LLMs_Txt_Manager::MENU_SLUG))
            )
        );
        exit;
    }

    /**
     * Handle cache refresh action.
     */
    public function handle_cache(): void
    {
        if (! current_user_can(MD_LLMs_Txt_Manager::CAPABILITY) ) {
            wp_die(esc_html__('Insufficient permissions.', 'md-llms-txt'));
        }
        check_admin_referer(MD_LLMs_Txt_Manager::NONCE_CACHE);

        $this->manager->bust_cache();
        $this->manager->prime_cache();
        $this->manager->purge_edge_cache();

        wp_safe_redirect(
            add_query_arg(
                array( 'cache' => '1' ),
                admin_url('admin.php?page=' . rawurlencode(MD_LLMs_Txt_Manager::MENU_SLUG))
            )
        );
        exit;
    }

    /**
     * AJAX: Refresh the model list for a provider.
     */
    public function ajax_fetch_models(): void
    {
        if (! current_user_can(MD_LLMs_Txt_Manager::CAPABILITY) ) {
            wp_send_json_error(array( 'message' => __('Insufficient permissions.', 'md-llms-txt') ), 403);
        }

        check_ajax_referer('md_llms_fetch_models');

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        if (! in_array($provider, array( 'openai', 'gemini' ), true) ) {
            wp_send_json_error(array( 'message' => __('Invalid provider.', 'md-llms-txt') ), 400);
        }

     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API key is trimmed and validated below.
        $api_key = isset($_POST['apiKey']) ? (string) wp_unslash($_POST['apiKey']) : '';
        if ('__use_stored__' === $api_key ) {
            $api_key = '';
        }
        $api_key = trim($api_key);

        if ('' === $api_key ) {
            $api_key = 'openai' === $provider ? $this->manager->get_stored_openai_key() : $this->manager->get_stored_gemini_key();
        }

        if ('' === $api_key ) {
            wp_send_json_error(array( 'message' => __('Add your API key first, then refresh the model list.', 'md-llms-txt') ), 400);
        }

        $result = 'openai' === $provider ? $this->manager->fetch_openai_models($api_key) : $this->manager->fetch_gemini_models($api_key);

        if (empty($result['models']) ) {
            wp_send_json_error(array( 'message' => __('No models were returned for this provider.', 'md-llms-txt') ), 500);
        }

        $option = 'openai' === $provider ? MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS : MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS;
        update_option($option, $result['models'], false);

        if (! empty($result['default']) ) {
            $this->manager->set_provider_model($provider, $result['default']);
        }

        wp_send_json_success(
            array(
            'models'  => $result['models'],
            'default' => $result['default'],
            )
        );
    }
}
