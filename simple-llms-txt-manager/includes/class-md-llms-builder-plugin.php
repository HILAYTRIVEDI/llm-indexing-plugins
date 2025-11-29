<?php
declare(strict_types=1);

/**
 * Admin UI + orchestration for the LLM Builder.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

/**
 * Handles menus, AJAX, and job orchestration.
 */
class MD_LLMs_Builder_Plugin
{


    const MENU_SLUG        = 'md-llm-optimizer-builder';
    const JOB_HOOK         = 'md_llms_builder_process_job';
    const CAPABILITY       = 'manage_options';
    const NONCE_ACTION     = 'md_llms_builder_nonce';
    const STATUS_TRANSIENT = 'md_llms_builder_status';

    /**
     * @var MD_LLMs_Builder_Job_Store
     */
    private $job_store;

    /**
     * @var array<int, string>|null
     */
    private $cached_user_agents = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->job_store = new MD_LLMs_Builder_Job_Store();
    }

    /**
     * Register hooks.
     */
    public function hooks(): void
    {
        $this->job_store->ensure_schema();

        add_action('admin_menu', array( $this, 'register_menu' ), 15);
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_assets' ));
        add_action('wp_ajax_md_llms_builder_create', array( $this, 'handle_create_job' ));
        add_action('wp_ajax_md_llms_builder_status', array( $this, 'handle_status' ));
        add_action('wp_ajax_md_llms_builder_download', array( $this, 'handle_download' ));
        add_action('wp_ajax_md_llms_builder_job_pages', array( $this, 'handle_job_pages' ));
        add_action(self::JOB_HOOK, array( $this, 'process_job' ), 10, 1);
    }

    /**
     * Add submenu entry. Top-level menu is injected by main plugin.
     */
    public function register_menu(): void
    {
        add_submenu_page(
            'md-llm-optimizer',
            __('LLM.txt Builder', 'md-llms-txt'),
            __('Builder', 'md-llms-txt'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Admin assets.
     */
    public function enqueue_assets( string $hook ): void
    {
        if (false === strpos($hook, self::MENU_SLUG) ) {
            return;
        }

        $style_path = plugin_dir_path(MD_LLMS_TXT_PLUGIN_FILE) . 'assets/css/llm-builder-admin.css';
        $style_url  = plugins_url('assets/css/llm-builder-admin.css', MD_LLMS_TXT_PLUGIN_FILE);
        $style_ver  = file_exists($style_path) ? (string) filemtime($style_path) : ( MD_LLMs_Txt_Manager::VERSION ?? '1.0.0' );

        wp_enqueue_style(
            'md-llms-builder-admin',
            $style_url,
            array(),
            $style_ver
        );

        $asset_path = plugin_dir_path(MD_LLMS_TXT_PLUGIN_FILE) . 'assets/js/llm-builder-admin.js';
        $asset_url  = plugins_url('assets/js/llm-builder-admin.js', MD_LLMS_TXT_PLUGIN_FILE);
        $version    = file_exists($asset_path) ? (string) filemtime($asset_path) : MD_LLMs_Txt_Manager::VERSION ?? '1.0.0';

        wp_enqueue_script(
            'md-llms-builder-admin',
            $asset_url,
            array( 'jquery' ),
            $version,
            true
        );

        $preferences = $this->get_provider_preferences();

        wp_localize_script(
            'md-llms-builder-admin',
            'mdLlmsBuilder',
            array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'i18n'     => array(
            'starting'      => __('Job queued. This page will refresh automatically.', 'md-llms-txt'),
            'complete'      => __('Build finished. Download your llms.txt file.', 'md-llms-txt'),
            'download'      => __('Download', 'md-llms-txt'),
            'view'          => __('View', 'md-llms-txt'),
            'viewTitle'     => __('Pages included in this build', 'md-llms-txt'),
            'copyAll'       => __('Copy All', 'md-llms-txt'),
            'copied'        => __('Copied to clipboard.', 'md-llms-txt'),
            'copyError'     => __('Unable to copy to clipboard.', 'md-llms-txt'),
            'empty'         => __('This job has not produced any URLs yet.', 'md-llms-txt'),
            'loading'       => __('Loading…', 'md-llms-txt'),
            'notCaptured'   => __('Not captured', 'md-llms-txt'),
            'metaOnlyModel' => __('Meta description fallback', 'md-llms-txt'),
            ),
            'models'   => $preferences['models'],
            'defaults' => array(
                    'provider' => $preferences['default_provider'],
                    'models'   => $preferences['selected_models'],
            ),
            )
        );
    }

    /**
     * Render builder admin page.
     */
    public function render_page(): void
    {
        if (! current_user_can(self::CAPABILITY) ) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'md-llms-txt'));
        }

        $jobs             = array_map(
            function ( $job ) {
                return $this->ensure_job_page_index($job);
            },
            $this->job_store->recent(10)
        );
        $preferences      = $this->get_provider_preferences();
        $current_provider = $preferences['default_provider'] ?: 'none';
        $selected_models  = $preferences['selected_models'];
        $current_models   = $preferences['models'][ $current_provider ] ?? array();
        $current_model    = $selected_models[ $current_provider ] ?? 'gpt-4o-mini';

        if (empty($current_models) && $current_model ) {
            $current_models = array( $current_model );
        }

        $user_agents      = $this->get_user_agent_choices();
        $user_agent_label = __('Rotate Safari, Firefox, and Edge automatically (default)', 'md-llms-txt');

        // Get provider data for AI Provider Preferences section.
        $openai_defined   = defined('MD_LLMS_OPENAI_API_KEY');
        $gemini_defined   = defined('MD_LLMS_GEMINI_API_KEY');
        $openai_key       = $openai_defined ? '' : get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_API_KEY, '');
        $gemini_key       = $gemini_defined ? '' : get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_API_KEY, '');
        $default_provider = get_option(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER, 'none');
        $openai_models    = get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS, array());
        $gemini_models    = get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS, array());
        $openai_model     = get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL, 'gpt-4o-mini');
        $gemini_model     = get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL, 'gemini-1.5-pro');
        $settings_action  = admin_url('admin-post.php');

        // Ensure models arrays are not empty and include current selections.
        if (empty($openai_models) ) {
            $openai_models = array( $openai_model );
        } elseif (! in_array($openai_model, $openai_models, true) ) {
            $openai_models[] = $openai_model;
        }
        if (empty($gemini_models) ) {
            $gemini_models = array( $gemini_model );
        } elseif (! in_array($gemini_model, $gemini_models, true) ) {
            $gemini_models[] = $gemini_model;
        }
        sort($openai_models, SORT_NATURAL | SORT_FLAG_CASE);
        sort($gemini_models, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
        <div class="wrap md-llms-wrap">
            <div class="md-llms-header">
                <div class="md-llms-header__content">
                    <div>
                        <span class="md-llms-badge"><?php esc_html_e('LLM Optimizer', 'md-llms-txt'); ?></span>
                        <h1><?php esc_html_e('LLM.txt Builder', 'md-llms-txt'); ?></h1>
                        <p class="md-llms-subtitle"><?php esc_html_e('Crawl your sitemap, extract high-signal content, and publish a spec-compliant llms.txt file for AI crawlers.', 'md-llms-txt'); ?></p>
                    </div>
                </div>
            </div>

            <div class="md-llms-grid md-llms-grid--builder">
                <section class="md-llms-card">
                    <header class="md-llms-card__header">
                        <div>
                            <h2><?php esc_html_e('Recent Jobs', 'md-llms-txt'); ?></h2>
                            <p><?php esc_html_e('Track completed builds, download outputs, and preview the URLs included in each llms.txt.', 'md-llms-txt'); ?></p>
                        </div>
                    </header>
                    <div class="md-llms-table-wrapper">
                        <table class="md-llms-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Job', 'md-llms-txt'); ?></th>
                                    <th><?php esc_html_e('Status', 'md-llms-txt'); ?></th>
                                    <th><?php esc_html_e('Unique URLs', 'md-llms-txt'); ?></th>
                                    <th><?php esc_html_e('Updated', 'md-llms-txt'); ?></th>
                                    <th><?php esc_html_e('Actions', 'md-llms-txt'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
        <?php if (empty($jobs) ) : ?>
                                <tr><td colspan="5"><?php esc_html_e('No jobs yet.', 'md-llms-txt'); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $jobs as $job ) : ?>
                                    <?php
                                    $page_count  = isset($job['page_count']) ? (int) $job['page_count'] : 0;
                                    $is_complete = ( 'completed' === $job['status'] );
                                    $site        = isset($job['args']['site']) && is_string($job['args']['site']) ? trim($job['args']['site']) : '';
                                    $domain      = $site;
                                    if ('' !== $site ) {
                                        $parts = wp_parse_url($site);
                                        if (! empty($parts['host']) ) {
                                            $domain = strtolower((string) $parts['host']);
                                            $domain = preg_replace('/^www\./', '', $domain);
                                        }
                                    }
                                    if ('' === $domain ) {
                                        $domain = sprintf('#%d', (int) $job['id']);
                                    }
                                    ?>
                                    <tr data-job-id="<?php echo esc_attr($job['id']); ?>">
                                        <td><?php echo esc_html($domain); ?></td>
                                        <td><span class="status status-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html(ucfirst($job['status'])); ?></span></td>
                                        <td>
                                    <?php
                                    if ($page_count > 0 ) {
                                        printf(
                                            esc_html(_n('%d URL', '%d URLs', $page_count, 'md-llms-txt')),
                                            $page_count
                                        );
                                    } else {
                                        echo $is_complete ? esc_html__('Not captured', 'md-llms-txt') : '—';
                                    }
                                    ?>
                                        </td>
                                        <td><?php echo esc_html(get_date_from_gmt($job['updated_at'], 'M j, Y H:i')); ?></td>
                                        <td class="md-llms-table__actions">
                                    <?php if ($is_complete && ! empty($job['output_file']) ) : ?>
                                                <button class="button button-small md-llms-builder-download" data-job-id="<?php echo esc_attr($job['id']); ?>"><?php esc_html_e('Download', 'md-llms-txt'); ?></button>
                                        <?php if ($page_count > 0 ) : ?>
                                                    <button class="button button-small button-secondary md-llms-builder-view" data-job-id="<?php echo esc_attr($job['id']); ?>"><?php esc_html_e('View', 'md-llms-txt'); ?></button>
                                        <?php endif; ?>
                                            <?php else : ?>
                                                <span class="md-llms-pill"><?php esc_html_e('In progress', 'md-llms-txt'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="md-llms-card md-llms-card--primary">
                    <header class="md-llms-card__header">
                        <div>
                            <h2><?php esc_html_e('Build llms.txt', 'md-llms-txt'); ?></h2>
                            <p><?php esc_html_e('Configure crawl settings, pick your AI provider, and queue a new builder job.', 'md-llms-txt'); ?></p>
                        </div>
                    </header>
                    <form id="md-llms-builder-form" class="md-llms-form">
        <?php wp_nonce_field(self::NONCE_ACTION); ?>
                        <div class="md-llms-field-row">
                            <div class="md-llms-field md-llms-field--grow">
                                <label for="md_llms_site"><?php esc_html_e('Site URL', 'md-llms-txt'); ?></label>
                                <input type="url" id="md_llms_site" name="site" placeholder="https://example.com" required />
                                <label class="md-llms-checkbox">
                                    <input type="checkbox" name="respect_robots" value="1" checked />
                                    <span><?php esc_html_e('Obey robots.txt when crawling', 'md-llms-txt'); ?></span>
                                </label>
                            </div>
                            <div class="md-llms-field">
                                <label for="md_llms_user_agent"><?php esc_html_e('User Agent', 'md-llms-txt'); ?></label>
                                <select id="md_llms_user_agent" name="user_agent">
                                    <option value=""><?php echo esc_html($user_agent_label); ?></option>
        <?php foreach ( $user_agents as $agent ) : ?>
                                        <option value="<?php echo esc_attr($agent); ?>"><?php echo esc_html($agent); ?></option>
        <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Select a single user agent or rotate automatically to mimic real visitors.', 'md-llms-txt'); ?></p>
                            </div>
                        </div>

                        <div class="md-llms-field-row md-llms-field-row--stack">
                            <div class="md-llms-field">
                                <span class="md-llms-label"><?php esc_html_e('AI Provider', 'md-llms-txt'); ?></span>
                                <div class="md-llms-radio-group">
                                    <label class="md-llms-radio">
                                        <input type="radio" name="provider" value="none" <?php checked('none', $current_provider); ?> />
                                        <span><?php esc_html_e('Meta only', 'md-llms-txt'); ?></span>
                                        <small><?php esc_html_e('No API key required', 'md-llms-txt'); ?></small>
                                    </label>
                                    <label class="md-llms-radio">
                                        <input type="radio" name="provider" value="openai" <?php checked('openai', $current_provider); ?> />
                                        <span><?php esc_html_e('OpenAI', 'md-llms-txt'); ?></span>
                                        <small><?php esc_html_e('Uses your saved API key', 'md-llms-txt'); ?></small>
                                    </label>
                                    <label class="md-llms-radio">
                                        <input type="radio" name="provider" value="gemini" <?php checked('gemini', $current_provider); ?> />
                                        <span><?php esc_html_e('Google Gemini', 'md-llms-txt'); ?></span>
                                        <small><?php esc_html_e('Uses your saved API key', 'md-llms-txt'); ?></small>
                                    </label>
                                </div>
                            </div>
                            <div class="md-llms-field">
                                <label for="md_llms_model"><?php esc_html_e('Model', 'md-llms-txt'); ?></label>
                                <select id="md_llms_model" name="model">
        <?php foreach ( $current_models as $model ) : ?>
                                        <option value="<?php echo esc_attr($model); ?>" <?php selected($model, $current_model); ?>><?php echo esc_html($model); ?></option>
        <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Refresh available models from the Settings screen after updating your API keys.', 'md-llms-txt'); ?></p>
                            </div>
                        </div>

                        <div class="md-llms-field-row">
                            <div class="md-llms-field">
                                <label for="md_llms_site_name"><?php esc_html_e('Site Name', 'md-llms-txt'); ?></label>
                                <input type="text" id="md_llms_site_name" name="site_name" placeholder="<?php esc_attr_e('Example Inc.', 'md-llms-txt'); ?>" />
                            </div>
                            <div class="md-llms-field">
                                <label for="md_llms_overview"><?php esc_html_e('Overview Text', 'md-llms-txt'); ?></label>
                                <textarea id="md_llms_overview" name="overview" rows="2" placeholder="<?php esc_attr_e('High-level description shown as a blockquote.', 'md-llms-txt'); ?>"></textarea>
                            </div>
                        </div>

                        <div class="md-llms-field-row">
                            <div class="md-llms-field">
                                <label for="md_llms_sections"><?php esc_html_e('Custom Sections', 'md-llms-txt'); ?></label>
                                <input type="text" id="md_llms_sections" name="sections" placeholder="Docs:/docs/**,API:/api/**" />
                                <p class="description"><?php esc_html_e('Comma-separated list. Leave blank to auto-detect top folders.', 'md-llms-txt'); ?></p>
                            </div>
                            <div class="md-llms-field">
                                <label for="md_llms_optional"><?php esc_html_e('Optional Paths', 'md-llms-txt'); ?></label>
                                <input type="text" id="md_llms_optional" name="optional" placeholder="/blog/**,/releases/**" />
                            </div>
                        </div>

                        <div class="md-llms-field-row md-llms-field-row--limits">
                            <div class="md-llms-field">
                                <label><?php esc_html_e('Max Pages', 'md-llms-txt'); ?></label>
                                <input type="number" min="10" step="10" name="max_pages" value="500" />
                            </div>
                            <div class="md-llms-field">
                                <label><?php esc_html_e('Concurrency', 'md-llms-txt'); ?></label>
                                <input type="number" min="1" max="16" name="concurrency" value="8" />
                            </div>
                            <div class="md-llms-field">
                                <label><?php esc_html_e('Rate (req/sec)', 'md-llms-txt'); ?></label>
                                <input type="number" min="0.2" step="0.1" name="rate" value="1" />
                            </div>
                            <div class="md-llms-field">
                                <label><?php esc_html_e('Timeout (s)', 'md-llms-txt'); ?></label>
                                <input type="number" min="5" step="1" name="timeout" value="15" />
                            </div>
                        </div>

                        <div class="md-llms-field md-llms-field--options">
                            <label class="md-llms-checkbox">
                                <input type="checkbox" name="emit_ctx" value="1" />
                                <span><?php esc_html_e('Emit llms-ctx.txt files (context summaries)', 'md-llms-txt'); ?></span>
                            </label>
                            <label class="md-llms-checkbox">
                                <input type="checkbox" name="cloudflare_bypass" value="1" />
                                <span><?php esc_html_e('Enable Cloudflare bypass hook (requires integration)', 'md-llms-txt'); ?></span>
                            </label>
                        </div>

                        <div class="md-llms-actions">
        <?php submit_button(esc_html__('Run Builder', 'md-llms-txt'), 'primary', 'submit', false); ?>
                            <span class="spinner" id="md-llms-builder-spinner" style="float:none;"></span>
                        </div>
                        <div id="md-llms-builder-messages" class="notice" style="display:none;"></div>
                        <pre id="md-llms-builder-log" class="md-llms-log" aria-live="polite"></pre>
                    </form>
                </section>
            </div>

            <section class="md-llms-card">
                <header class="md-llms-card__header">
                    <div>
                        <h2><?php esc_html_e('AI Provider Preferences', 'md-llms-txt'); ?></h2>
                        <p><?php esc_html_e('Store API keys securely, refresh supported models, and choose the default provider for Builder jobs.', 'md-llms-txt'); ?></p>
                    </div>
                </header>
                <form method="post" action="<?php echo esc_url($settings_action); ?>" class="md-llms-form">
        <?php wp_nonce_field(MD_LLMs_Txt_Manager::NONCE_SAVE); ?>
                    <input type="hidden" name="action" value="md_llms_txt_save" />
                    <input type="hidden" name="redirect_to_builder" value="1" />
                    <div class="md-llms-field">
                        <span class="md-llms-label"><?php esc_html_e('Default provider', 'md-llms-txt'); ?></span>
                        <div class="md-llms-radio-group">
        <?php
        $providers = array(
        'none'   => __('Meta Only', 'md-llms-txt'),
        'openai' => __('OpenAI', 'md-llms-txt'),
        'gemini' => __('Google Gemini', 'md-llms-txt'),
        );
        foreach ( $providers as $value => $label ) :
            $is_active = $value === $default_provider;
            ?>
                                <label class="md-llms-radio <?php echo $is_active ? 'is-active' : ''; ?>">
                                    <input type="radio" name="md_llms_default_provider" value="<?php echo esc_attr($value); ?>" <?php checked($value, $default_provider); ?> />
                                    <span><?php echo esc_html($label); ?></span>
                                    <small>
            <?php
            if ('none' === $value ) {
                esc_html_e('Use meta descriptions only (no API cost).', 'md-llms-txt');
            } elseif ('openai' === $value ) {
                esc_html_e('Requires an OpenAI API key.', 'md-llms-txt');
            } else {
                esc_html_e('Requires a Google Gemini API key.', 'md-llms-txt');
            }
            ?>
                                    </small>
                                </label>
        <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="md-llms-provider-card">
                        <header>
                            <div>
                                <h3><?php esc_html_e('OpenAI', 'md-llms-txt'); ?></h3>
                                <p><?php esc_html_e('Recommended for high-quality summaries. Keys are stored with autoload disabled.', 'md-llms-txt'); ?></p>
                            </div>
                        </header>
                        <div class="md-llms-provider-card__body">
        <?php if ($openai_defined ) : ?>
                                <p class="description"><?php esc_html_e('API key defined via MD_LLMS_OPENAI_API_KEY constant.', 'md-llms-txt'); ?></p>
                                <button type="button" class="button button-secondary md-llms-refresh-models" data-provider="openai" data-target="#md_llms_openai_model">
            <?php esc_html_e('Refresh models', 'md-llms-txt'); ?>
                                </button>
                            <?php else : ?>
                                <div class="md-llms-key-row">
                                    <input
                                        type="password"
                                        id="md_llms_openai_api_key"
                                        name="md_llms_openai_api_key"
                                        placeholder="sk-********************************"
                                        autocomplete="off"
                                    />
                                    <button type="button" class="button button-secondary md-llms-refresh-models" data-provider="openai" data-target="#md_llms_openai_model" data-key="#md_llms_openai_api_key">
                                <?php esc_html_e('Refresh models', 'md-llms-txt'); ?>
                                    </button>
                                </div>
                                <?php if ('' !== $openai_key ) : ?>
                                    <label class="md-llms-checkbox md-llms-delete-key">
                                        <input type="checkbox" name="md_llms_openai_api_key_delete" value="1" />
                                        <span><?php esc_html_e('Delete stored key', 'md-llms-txt'); ?></span>
                                    </label>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e('Enter a key then click "Refresh models" to sync supported engines.', 'md-llms-txt'); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="md-llms-field">
                                <label for="md_llms_openai_model"><?php esc_html_e('Model', 'md-llms-txt'); ?></label>
                                <select id="md_llms_openai_model" name="md_llms_openai_model">
        <?php foreach ( $openai_models as $model ) : ?>
                                        <option value="<?php echo esc_attr($model); ?>" <?php selected($model, $openai_model); ?>><?php echo esc_html($model); ?></option>
        <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="md-llms-provider-card">
                        <header>
                            <div>
                                <h3><?php esc_html_e('Google Gemini', 'md-llms-txt'); ?></h3>
                                <p><?php esc_html_e('Use for fast, cost-effective summaries or fallback coverage.', 'md-llms-txt'); ?></p>
                            </div>
                        </header>
                        <div class="md-llms-provider-card__body">
        <?php if ($gemini_defined ) : ?>
                                <p class="description"><?php esc_html_e('API key defined via MD_LLMS_GEMINI_API_KEY constant.', 'md-llms-txt'); ?></p>
                                <button type="button" class="button button-secondary md-llms-refresh-models" data-provider="gemini" data-target="#md_llms_gemini_model">
            <?php esc_html_e('Refresh models', 'md-llms-txt'); ?>
                                </button>
                            <?php else : ?>
                                <div class="md-llms-key-row">
                                    <input
                                        type="password"
                                        id="md_llms_gemini_api_key"
                                        name="md_llms_gemini_api_key"
                                        placeholder="AIzaSy********************************"
                                        autocomplete="off"
                                    />
                                    <button type="button" class="button button-secondary md-llms-refresh-models" data-provider="gemini" data-target="#md_llms_gemini_model" data-key="#md_llms_gemini_api_key">
                                <?php esc_html_e('Refresh models', 'md-llms-txt'); ?>
                                    </button>
                                </div>
                                <?php if ('' !== $gemini_key ) : ?>
                                    <label class="md-llms-checkbox md-llms-delete-key">
                                        <input type="checkbox" name="md_llms_gemini_api_key_delete" value="1" />
                                        <span><?php esc_html_e('Delete stored key', 'md-llms-txt'); ?></span>
                                    </label>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e('Paste your API key then refresh to fetch the latest Gemini models.', 'md-llms-txt'); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="md-llms-field">
                                <label for="md_llms_gemini_model"><?php esc_html_e('Model', 'md-llms-txt'); ?></label>
                                <select id="md_llms_gemini_model" name="md_llms_gemini_model">
        <?php foreach ( $gemini_models as $model ) : ?>
                                        <option value="<?php echo esc_attr($model); ?>" <?php selected($model, $gemini_model); ?>><?php echo esc_html($model); ?></option>
        <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="md-llms-actions">
        <?php submit_button(esc_html__('Save Provider Settings', 'md-llms-txt'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </section>

            <div class="md-llms-modal" id="md-llms-modal" aria-hidden="true">
                <div class="md-llms-modal__overlay" data-md-llms-close></div>
                <div class="md-llms-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="md-llms-modal-title">
                    <header class="md-llms-modal__header">
                        <h2 id="md-llms-modal-title"><?php esc_html_e('Pages included in this build', 'md-llms-txt'); ?></h2>
                        <button type="button" class="md-llms-modal__close" data-md-llms-close aria-label="<?php esc_attr_e('Close modal', 'md-llms-txt'); ?>">&times;</button>
                    </header>
                    <div class="md-llms-modal__actions">
                        <button type="button" class="button button-secondary" id="md-llms-modal-copy"><?php esc_html_e('Copy All', 'md-llms-txt'); ?></button>
                    </div>
                    <div class="md-llms-modal__body" id="md-llms-modal-body">
                        <p><?php esc_html_e('Select a completed job and click “View” to preview its URLs.', 'md-llms-txt'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: create job.
     */
    public function handle_create_job(): void
    {
        $this->verify_request();

        $data = array(
        'site'              => isset($_POST['site']) ? esc_url_raw(wp_unslash($_POST['site'])) : '',
        'provider'          => isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : 'none',
        'model'             => isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : 'gpt-4o-mini',
        'sections'          => isset($_POST['sections']) ? sanitize_text_field(wp_unslash($_POST['sections'])) : '',
        'optional'          => isset($_POST['optional']) ? sanitize_text_field(wp_unslash($_POST['optional'])) : '',
        'max_pages'         => isset($_POST['max_pages']) ? (int) $_POST['max_pages'] : 500,
        'concurrency'       => isset($_POST['concurrency']) ? (int) $_POST['concurrency'] : 8,
        'rate'              => isset($_POST['rate']) ? (float) $_POST['rate'] : 1.0,
        'timeout'           => isset($_POST['timeout']) ? (int) $_POST['timeout'] : 15,
        'site_name'         => isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '',
        'overview'          => isset($_POST['overview']) ? wp_kses_post(wp_unslash($_POST['overview'])) : '',
        'emit_ctx'          => isset($_POST['emit_ctx']) ? 1 : 0,
        'cloudflare_bypass' => isset($_POST['cloudflare_bypass']) ? 1 : 0,
        'user_agent'        => $this->sanitize_user_agent_choice(
            isset($_POST['user_agent']) ? (string) wp_unslash($_POST['user_agent']) : ''
        ),
        'respect_robots'    => isset($_POST['respect_robots']) ? 1 : 0,
        );

        if ('' === $data['site'] || ! filter_var($data['site'], FILTER_VALIDATE_URL) ) {
            wp_send_json_error(array( 'message' => __('Please enter a valid site URL.', 'md-llms-txt') ), 400);
        }

        $data['openai_key'] = $this->get_provider_key('openai');
        $data['gemini_key'] = $this->get_provider_key('gemini');

        $job_id = $this->job_store->create($data);
        if (0 === $job_id ) {
            wp_send_json_error(array( 'message' => __('Unable to create job.', 'md-llms-txt') ), 500);
        }

        $this->schedule_job($job_id);

        wp_send_json_success(
            array(
            'jobId' => $job_id,
            )
        );
    }

    /**
     * AJAX: status polling.
     */
    public function handle_status(): void
    {
        $this->verify_request();

        $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        if ($job_id <= 0 ) {
            wp_send_json_error(array( 'message' => __('Missing job ID.', 'md-llms-txt') ), 400);
        }

        $job = $this->job_store->get($job_id);
        if (! $job ) {
            wp_send_json_error(array( 'message' => __('Job not found.', 'md-llms-txt') ), 404);
        }

        $job = $this->ensure_job_page_index($job);

        wp_send_json_success(
            array(
            'job' => array(
                    'id'         => $job['id'],
                    'status'     => $job['status'],
                    'logs'       => $job['logs'],
                    'output'     => $job['output_file'],
                    'error'      => $job['error_message'],
                    'updated_at' => get_date_from_gmt($job['updated_at'], 'M j, Y H:i'),
                    'page_count' => isset($job['page_count']) ? (int) $job['page_count'] : 0,
            ),
            )
        );
    }

    /**
     * AJAX: download output file.
     */
    public function handle_download(): void
    {
        $this->verify_request();

        $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        if ($job_id <= 0 ) {
            wp_die(esc_html__('Invalid job.', 'md-llms-txt'));
        }
        $job = $this->job_store->get($job_id);
        if (! $job || 'completed' !== $job['status'] || empty($job['output_file']) ) {
            wp_die(esc_html__('Job not ready.', 'md-llms-txt'));
        }

        $file = $job['output_file'];
        if (! file_exists($file) ) {
            wp_die(esc_html__('File missing.', 'md-llms-txt'));
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /**
     * AJAX: return page URLs for a job.
     */
    public function handle_job_pages(): void
    {
        $this->verify_request();

        $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        if ($job_id <= 0 ) {
            wp_send_json_error(array( 'message' => __('Invalid job.', 'md-llms-txt') ), 400);
        }

        $job = $this->job_store->get($job_id);
        if (! $job ) {
            wp_send_json_error(array( 'message' => __('Job not found.', 'md-llms-txt') ), 404);
        }

        $job   = $this->ensure_job_page_index($job);
        $pages = is_array($job['page_urls']) ? $job['page_urls'] : array();

        wp_send_json_success(
            array(
            'pages'      => array_slice($pages, 0, 500),
            'page_count' => count($pages),
            )
        );
    }

    /**
     * Cron/async processor.
     */
    public function process_job( int $job_id ): void
    {
        $job = $this->job_store->get($job_id);
        if (! $job || ! in_array($job['status'], array( 'pending', 'running' ), true) ) {
            return;
        }

        $this->job_store->update(
            $job_id,
            array(
            'status' => 'running',
            ),
            array( '%s' )
        );

        $args   = $job['args'];
        $logger = function ( $message ) use ( $job_id ) {
            $this->job_store->append_log($job_id, $message);
        };

        try {
            $pipeline = new MD_LLMs_Builder_Pipeline($args, $logger);
            $result   = $pipeline->run();

            $this->job_store->update(
                $job_id,
                array(
                'status'        => 'completed',
                'output_file'   => $result['output_path'],
                'artifacts_dir' => $result['artifacts_dir'],
                'error_message' => null,
                'page_count'    => isset($result['page_count']) ? (int) $result['page_count'] : 0,
                'page_urls'     => wp_json_encode($result['page_urls'] ?? array()),
                ),
                array( '%s', '%s', '%s', '%s', '%d', '%s' )
            );
        } catch ( Throwable $e ) {
            $this->job_store->update(
                $job_id,
                array(
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                ),
                array( '%s', '%s' )
            );
            $this->job_store->append_log($job_id, '❌ ' . $e->getMessage());
        }
    }

    /**
     * Schedule background runner.
     */
    private function schedule_job( int $job_id ): void
    {
        wp_schedule_single_event(time() + 1, self::JOB_HOOK, array( $job_id ));
        if (function_exists('spawn_cron') ) {
            spawn_cron();
        }
    }

    /**
     * Validate capability + nonce.
     */
    private function verify_request(): void
    {
        if (! current_user_can(self::CAPABILITY) ) {
            wp_send_json_error(array( 'message' => __('Insufficient permissions.', 'md-llms-txt') ), 403);
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json_error(array( 'message' => __('Nonce verification failed.', 'md-llms-txt') ), 403);
        }
    }

    /**
     * Fetch provider key from constants or options.
     *
     * @param string $provider Provider slug.
     * @param bool   $masked   Whether to mask for UI.
     */
    private function get_provider_key( string $provider, bool $masked = false ): string
    {
        $option_map   = array(
        'openai' => 'md_llms_openai_api_key',
        'gemini' => 'md_llms_gemini_api_key',
        );
        $constant_map = array(
        'openai' => 'MD_LLMS_OPENAI_API_KEY',
        'gemini' => 'MD_LLMS_GEMINI_API_KEY',
        );

        if (isset($constant_map[ $provider ]) && defined($constant_map[ $provider ]) ) {
            $value = constant($constant_map[ $provider ]);
        } else {
            $option = $option_map[ $provider ] ?? '';
            $value  = $option ? get_option($option, '') : '';
        }

        $value = is_string($value) ? trim($value) : '';

        if ($masked && '' !== $value ) {
            return str_repeat('•', max(4, strlen($value) - 4)) . substr($value, -4);
        }

        return $value;
    }

    /**
     * Retrieve saved provider preferences (models + defaults).
     *
     * @return array{
     *     default_provider:string,
     *     models:array<string,array<int,string>>,
     *     selected_models:array<string,string>
     * }
     */
    private function get_provider_preferences(): array
    {
        $default = get_option(MD_LLMs_Txt_Manager::OPTION_DEFAULT_PROVIDER, 'none');
        if (! in_array($default, array( 'none', 'openai', 'gemini' ), true) ) {
            $default = 'none';
        }

        $openai_models = $this->prepare_model_list(
            get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODELS, array()),
            array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4o-mini-128k' )
        );

        $gemini_models = $this->prepare_model_list(
            get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODELS, array()),
            array( 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro' )
        );

        return array(
        'default_provider' => $default,
        'models'           => array(
        'none'   => array(),
        'openai' => $openai_models,
        'gemini' => $gemini_models,
        ),
        'selected_models'  => array(
        'none'   => '',
        'openai' => (string) get_option(MD_LLMs_Txt_Manager::OPTION_OPENAI_MODEL, 'gpt-4o-mini'),
        'gemini' => (string) get_option(MD_LLMs_Txt_Manager::OPTION_GEMINI_MODEL, 'gemini-1.5-pro'),
        ),
        );
    }

    /**
     * Normalize a model list.
     *
     * @param  mixed             $list      Stored models.
     * @param  array<int,string> $fallbacks Default suggestions.
     * @return array<int,string>
     */
    private function prepare_model_list( $list, array $fallbacks ): array
    {
        if (! is_array($list) ) {
            $list = array();
        }

        $list = array_filter(
            array_map(
                static function ( $model ) {
                    $model = is_string($model) ? trim($model) : '';
                    return '' === $model ? null : $model;
                },
                $list
            )
        );

        if (empty($list) ) {
            $list = $fallbacks;
        }

        return array_values(array_unique($list));
    }

    /**
     * Fetch top user agents for dropdown.
     *
     * @return array<int, string>
     */
    private function get_user_agent_choices(): array
    {
        if (null !== $this->cached_user_agents ) {
            return $this->cached_user_agents;
        }

        $cached = get_transient('md_llms_user_agents');
        if (is_array($cached) && ! empty($cached) ) {
            $this->cached_user_agents = $cached;
            return $cached;
        }

        $response = wp_safe_remote_get(
            'https://cdn.jsdelivr.net/gh/microlinkhq/top-user-agents@master/src/index.json',
            array(
            'timeout'            => 10,
            'user-agent'         => 'md-llm-optimizer/' . ( MD_LLMs_Txt_Manager::VERSION ?? '1.0.0' ),
            'reject_unsafe_urls' => true,
            )
        );

        $list = array();
        if (! is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response) ) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($body) ) {
                foreach ( $body as $entry ) {
                    if (is_string($entry) ) {
                        $list[] = trim($entry);
                    } elseif (is_array($entry) ) {
                        if (isset($entry['value']) && is_string($entry['value']) ) {
                                $list[] = trim($entry['value']);
                                continue;
                        }
                        if (isset($entry['userAgent']) && is_string($entry['userAgent']) ) {
                                $list[] = trim($entry['userAgent']);
                                continue;
                        }
                        if (isset($entry['ua']) && is_string($entry['ua']) ) {
                            $list[] = trim($entry['ua']);
                        }
                    }
                }
            }
        }

        if (empty($list) ) {
            $list = $this->get_default_user_agents();
        }

        $list = array_values(array_unique(array_filter($list)));
        set_transient('md_llms_user_agents', $list, DAY_IN_SECONDS);
        $this->cached_user_agents = $list;

        return $list;
    }

    /**
     * Default rotating user agents.
     */
    private function get_default_user_agents(): array
    {
        return array(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0.1 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:144.0) Gecko/20100101 Firefox/144.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
        );
    }

    /**
     * Ensure the submitted user agent is allowed.
     */
    private function sanitize_user_agent_choice( string $choice ): string
    {
        $choice = trim($choice);
        if ('' === $choice ) {
            return '';
        }

        return in_array($choice, $this->get_user_agent_choices(), true) ? $choice : '';
    }

    /**
     * Extract URLs from emitted content.
     *
     * @param  string $content llms.txt content.
     * @return array<int,array{url:string,title:string}>
     */
    private function parse_page_urls_from_string( string $content ): array
    {
        $pattern = '/-\s*\[(?P<title>[^\]]+)\]\((?P<url>https?:\/\/[^\)]+)\)/';
        $matches = array();
        $found   = array();

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) ) {
            foreach ( $matches as $match ) {
                $url = esc_url_raw($match['url'] ?? '');
                if ('' === $url || isset($found[ $url ]) ) {
                    continue;
                }

                $title         = isset($match['title']) ? sanitize_text_field(html_entity_decode($match['title'], ENT_QUOTES | ENT_HTML5)) : '';
                $found[ $url ] = array(
                 'url'   => $url,
                 'title' => $title,
                );
            }
        }

        return array_values($found);
    }

    /**
     * Ensure the job record has cached page URLs + counts.
     *
     * @param  array<string,mixed> $job Job row.
     * @return array<string,mixed>
     */
    private function ensure_job_page_index( array $job ): array
    {
        $page_count = isset($job['page_count']) ? (int) $job['page_count'] : 0;
        if ($page_count > 0 && ! empty($job['page_urls']) ) {
            return $job;
        }

        if (empty($job['output_file']) || ! file_exists($job['output_file']) ) {
            return $job;
        }

        $contents = file_get_contents($job['output_file']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if (false === $contents ) {
            return $job;
        }

        $pages = $this->parse_page_urls_from_string($contents);
        $count = count($pages);

        $this->job_store->update(
            $job['id'],
            array(
            'page_urls'  => wp_json_encode($pages),
            'page_count' => $count,
            ),
            array( '%s', '%d' )
        );

        $job['page_urls']  = $pages;
        $job['page_count'] = $count;

        return $job;
    }
}

