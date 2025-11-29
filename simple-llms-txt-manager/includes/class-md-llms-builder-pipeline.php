<?php
declare(strict_types=1);

/**
 * End-to-end builder pipeline.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Pipeline
{


    /**
     * @var array<string, mixed>
     */
    private $args;

    /**
     * @var callable|null
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $args   Arguments.
     * @param callable|null        $logger Logger.
     */
    public function __construct( array $args, $logger = null )
    {
        $defaults = array(
        'site'              => '',
        'provider'          => 'none',
        'model'             => 'gpt-4o-mini',
        'sections'          => '',
        'optional'          => array(),
        'max_pages'         => 5000,
        'concurrency'       => 8,
        'rate'              => 1.0,
        'timeout'           => 15,
        'site_name'         => '',
        'overview'          => '',
        'emit_ctx'          => false,
        'cloudflare_bypass' => false,
        'openai_key'        => '',
        'gemini_key'        => '',
        'user_agent'        => '',
        'respect_robots'    => true,
        );

        $this->args   = wp_parse_args($args, $defaults);
        $this->logger = $logger;
    }

    /**
     * Execute pipeline.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $site = $this->args['site'];
        if (! filter_var($site, FILTER_VALIDATE_URL) ) {
            throw new InvalidArgumentException(__('A valid site URL is required.', 'md-llms-txt'));
        }

        $uploads = wp_upload_dir();
        if (! empty($uploads['error']) ) {
            throw new RuntimeException($uploads['error']);
        }

        $domain = $this->normalize_domain($site);
        $date   = gmdate('Y-m-d');

        $base_dir      = trailingslashit($uploads['basedir']) . 'llm-optimizer/' . $domain . '/' . $date;
        $base_url      = trailingslashit($uploads['baseurl']) . 'llm-optimizer/' . $domain . '/' . $date;
        $output_file   = trailingslashit($base_dir) . sprintf('llms-%s-%s.txt', $domain, $date);
        $artifacts_dir = trailingslashit($base_dir) . 'artifacts';
        MD_LLMs_Builder_Util::ensure_dir($artifacts_dir);

        $user_agent     = isset($this->args['user_agent']) ? trim((string) $this->args['user_agent']) : '';
        $respect_robots = array_key_exists('respect_robots', $this->args) ? (bool) $this->args['respect_robots'] : true;

        $this->log('📍 Step 1/6: Discovering sitemaps…');
        $sitemap = new MD_LLMs_Builder_Sitemap($user_agent);
        $urls    = $sitemap->crawl($site, (int) $this->args['max_pages'], array( $this, 'log' ));
        if (empty($urls) ) {
            throw new RuntimeException(__('No URLs were found in the sitemap.', 'md-llms-txt'));
        }

        $this->log(sprintf(__('Found %d URLs', 'md-llms-txt'), count($urls)));

        if ($respect_robots ) {
            $this->log('🤖 Step 2/6: Checking robots.txt…');
            $robots = new MD_LLMs_Builder_Robots($user_agent ?: 'llms-txt-bot');
            $robots->fetch($site);

            $allowed = array_values(
                array_filter(
                    $urls,
                    static function ( $url ) use ( $robots ) {
                        return $robots->is_allowed($url);
                    }
                )
            );
            $this->log(sprintf(__('Allowed %d URLs after robots filtering', 'md-llms-txt'), count($allowed)));

            if (empty($allowed) ) {
                throw new RuntimeException(__('No URLs allowed by robots.txt.', 'md-llms-txt'));
            }
        } else {
            $this->log('🤖 Step 2/6: Skipping robots.txt enforcement (unchecked by user)…');
            $allowed = $urls;
        }

        $this->log('📥 Step 3/6: Fetching pages…');
        $fetcher = new MD_LLMs_Builder_Fetcher(
            (float) $this->args['rate'],
            (int) $this->args['timeout'],
            3,
            (bool) $this->args['cloudflare_bypass'],
            $user_agent
        );

        $results = $fetcher->fetch_batch(
            $allowed,
            (int) $this->args['concurrency'],
            function ( $completed, $total, $url ) {
                $this->log(sprintf('[%d/%d] %s', $completed, $total, $url));
            }
        );

        $html_results = array_filter(
            $results,
            static function ( $result ) {
                return $result instanceof MD_LLMs_Builder_Fetch_Result && $result->is_success() && $result->is_html();
            }
        );

        $this->log(sprintf(__('Fetched %d HTML pages', 'md-llms-txt'), count($html_results)));

        if (empty($html_results) ) {
            throw new RuntimeException(__('No HTML pages could be fetched.', 'md-llms-txt'));
        }

        $this->log('📝 Step 4/6: Extracting & summarizing…');
        $extractor  = new MD_LLMs_Builder_Content_Extractor();
        $converter  = new MD_LLMs_Builder_Markdown_Converter();
        $artifacts  = new MD_LLMs_Builder_Artifacts();
        $summarizer = new MD_LLMs_Builder_Summarizer(
            (string) $this->args['provider'],
            (string) $this->args['model'],
            (string) $this->args['openai_key'],
            (string) $this->args['gemini_key']
        );

        $pages         = array();
        $page_metadata = array();
        $brand_context = '';

        foreach ( $html_results as $result ) {
            $metadata = $extractor->extract_metadata($result->body);
            $markdown = $converter->convert($result->body);

            if (strlen($markdown) < 120 ) {
                continue;
            }

            $artifact_path = $artifacts->save($result->url, $result->body, $artifacts_dir, $converter);

            $title = $metadata['title'] ?: ( isset(wp_parse_url($result->url)['path']) ? basename((string) wp_parse_url($result->url)['path']) : $result->url );
            $title = MD_LLMs_Builder_Util::clean_title($title, $site);

            $summary = $summarizer->summarize($markdown, 256, $metadata);

            $pages[]                       = array( $result->url, $title, $summary );
            $page_metadata[ $result->url ] = array(
            'metadata'  => $metadata,
            'published' => $this->parse_datetime($metadata['published'] ?? ''),
            'artifact'  => $artifact_path,
            );

            if ($brand_context === '' && false !== stripos($result->url, 'about') ) {
                $brand_context = $markdown;
            }
        }

        if (empty($pages) ) {
            throw new RuntimeException(__('No pages contained enough content to include.', 'md-llms-txt'));
        }

        $this->log(sprintf(__('Processed %d pages with content', 'md-llms-txt'), count($pages)));

        $sections = array();
        if (! empty($this->args['sections']) ) {
            $sections = MD_LLMs_Builder_Page_Grouper::parse_sections((string) $this->args['sections']);
        }

        $optional = $this->args['optional'];
        if (is_string($optional) ) {
            $optional = array_map('trim', explode(',', $optional));
        }
        if (! is_array($optional) ) {
            $optional = array();
        }

        $this->log('📑 Step 5/6: Grouping pages…');
        $grouper = new MD_LLMs_Builder_Page_Grouper($sections, $optional);
        $groups  = $grouper->group($pages, empty($sections));

        $brand_label = $this->args['site_name'] ?: $domain;
        $groups      = MD_LLMs_Builder_Group_Helper::post_process($groups, $page_metadata, $brand_label);
        $this->log(sprintf(__('Created %d groups', 'md-llms-txt'), count($groups)));

        if ($summarizer->has_ai() ) {
            $this->log('📋 Generating section summaries…');
            foreach ( $groups as $group ) {
                $summaries = array();
                foreach ( $group->pages as $page ) {
                    if ('' !== $page[2] ) {
                        $summaries[] = $page[2];
                    }
                }
                if (! empty($summaries) ) {
                    $group->description = $summarizer->section_summary($group->title, $summaries);
                }
            }
        }

        $brand_summary = '';
        if ($summarizer->has_ai() ) {
            $this->log('🏢 Generating brand summary…');
            $brand_summary = $summarizer->brand_summary($brand_label, $brand_context ?: implode("\n", array_column($pages, 2)));
        }

        list( $groups, $brand_summary ) = MD_LLMs_Builder_Summary_Validator::validate($groups, $brand_summary);

        $this->log('📤 Step 6/6: Emitting llms.txt…');
        $emitter = new MD_LLMs_Builder_Emitter(
            $this->args['site_name'] ?: $domain,
            $this->args['overview'] ?: __('Documentation and resources.', 'md-llms-txt'),
            $brand_summary
        );

        $content = $emitter->emit($groups, $output_file);
        if (! empty($this->args['emit_ctx']) ) {
            $emitter->emit_context_files($groups, $output_file, $artifacts_dir);
        }

        $page_urls = $this->extract_page_urls($content);

        return array(
        'output_path'   => $output_file,
        'output_url'    => trailingslashit($base_url) . basename($output_file),
        'artifacts_dir' => $artifacts_dir,
        'artifacts_url' => trailingslashit($base_url) . 'artifacts',
        'pages'         => count($pages),
        'groups'        => count($groups),
        'content'       => $content,
        'page_urls'     => $page_urls,
        'page_count'    => count($page_urls),
        );
    }

    /**
     * Helper logger.
     */
    public function log( string $message ): void
    {
        if (is_callable($this->logger) ) {
            call_user_func($this->logger, $message);
        }
    }

    /**
     * Normalize domain for filenames.
     */
    private function normalize_domain( string $site ): string
    {
        $parts = wp_parse_url($site);
        $host  = isset($parts['host']) ? strtolower((string) $parts['host']) : 'site';
        $host  = str_replace('www.', '', $host);
        return preg_replace('/[^a-z0-9\.-]/', '-', $host);
    }

    /**
     * Parse datetime string.
     */
    private function parse_datetime( string $value ): ?string
    {
        $value = trim($value);
        if ('' === $value ) {
            return null;
        }

        $timestamp = strtotime($value);
        if (false === $timestamp ) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Extract unique page URLs from emitted content.
     *
     * @param  string $content Emitted llms.txt body.
     * @return array<int, array{url:string,title:string}>
     */
    private function extract_page_urls( string $content ): array
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

                $title = isset($match['title']) ? sanitize_text_field(html_entity_decode($match['title'], ENT_QUOTES | ENT_HTML5)) : '';

                $found[ $url ] = array(
                 'url'   => $url,
                 'title' => $title,
                );
            }
        }

        return array_values($found);
    }
}
