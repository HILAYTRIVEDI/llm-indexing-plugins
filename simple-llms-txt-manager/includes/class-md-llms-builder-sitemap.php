<?php
declare(strict_types=1);

/**
 * Sitemap discovery and parsing.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Sitemap
{


    /**
     * @var MD_LLMs_Builder_User_Agent_Rotator
     */
    private $rotator;

    /**
     * @var array<string, bool>
     */
    private $visited = array();

    /**
     * @var string
     */
    private $forced_user_agent = '';

    /**
     * Constructor.
     */
    public function __construct( string $user_agent = '' )
    {
        $this->rotator           = new MD_LLMs_Builder_User_Agent_Rotator();
        $this->forced_user_agent = trim($user_agent);
    }

    /**
     * Crawl all reachable sitemaps.
     *
     * @param string        $base_url  Base site URL.
     * @param int|null      $max_pages Limit.
     * @param callable|null $logger    Optional logger.
     *
     * @return array<int, string>
     */
    public function crawl( string $base_url, ?int $max_pages = null, $logger = null ): array
    {
        $sitemaps = $this->discover($base_url);
        if (empty($sitemaps) ) {
            return array();
        }

        $urls = array();
        foreach ( $sitemaps as $sitemap_url ) {
            if ($max_pages && count($urls) >= $max_pages ) {
                break;
            }

            if (false !== stripos($sitemap_url, 'video-sitemap') ) {
                $this->log($logger, sprintf(__('Skipping video sitemap: %s', 'md-llms-txt'), $sitemap_url));
                continue;
            }

            $this->log($logger, sprintf(__('Parsing sitemap: %s', 'md-llms-txt'), $sitemap_url));
            $parsed = $this->parse($sitemap_url, $max_pages, $logger);
            if (! empty($parsed) ) {
                $urls = array_merge($urls, $parsed);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Discover sitemap URLs.
     */
    private function discover( string $base_url ): array
    {
        $robots = new MD_LLMs_Builder_Robots($this->forced_user_agent ?: 'llms-txt-bot');
        $robots->fetch($base_url);
        $sitemaps = $robots->get_sitemaps();

        if (empty($sitemaps) ) {
            $sitemaps = array(
            trailingslashit(untrailingslashit($base_url)) . 'sitemap_index.xml',
            trailingslashit(untrailingslashit($base_url)) . 'sitemap.xml',
            );
        }

        return array_map('esc_url_raw', $sitemaps);
    }

    /**
     * Parse a sitemap recursively.
     */
    private function parse( string $sitemap_url, ?int $max_pages, $logger ): array
    {
        if (isset($this->visited[ $sitemap_url ]) ) {
            return array();
        }
        $this->visited[ $sitemap_url ] = true;

        $sitemap_url = esc_url_raw($sitemap_url);
        $response    = wp_safe_remote_get(
            $sitemap_url,
            array(
            'timeout'     => 30,
            'user-agent'  => $this->forced_user_agent ?: $this->rotator->next(),
            'redirection' => 3,
            'httpversion' => '2.0',
            )
        );

        if (is_wp_error($response) ) {
            $this->log($logger, sprintf(__('Unable to load sitemap %1$s: %2$s', 'md-llms-txt'), $sitemap_url, $response->get_error_message()));
            return array();
        }

        if (200 !== wp_remote_retrieve_response_code($response) ) {
            return array();
        }

        $body    = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        if (isset($headers['content-type']) && false !== stripos((string) $headers['content-type'], 'gzip') ) {
            $body = function_exists('gzdecode') ? gzdecode($body) : $body;
        }

        if (false === $body || '' === $body ) {
            return array();
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (false === $xml ) {
            libxml_clear_errors();
            return array();
        }
        libxml_clear_errors();

        $urls = array();
        $node = strtolower($xml->getName());

        if ('sitemapindex' === $node ) {
            foreach ( $xml->sitemap as $child ) {
                if ($max_pages && count($urls) >= $max_pages ) {
                    break;
                }

                $loc = isset($child->loc) ? trim((string) $child->loc) : '';
                if ('' === $loc ) {
                    continue;
                }

                if (false !== stripos($loc, 'video-sitemap') ) {
                    $this->log($logger, sprintf(__('Skipping video sitemap: %s', 'md-llms-txt'), $loc));
                    continue;
                }

                $nested = $this->parse($loc, $max_pages, $logger);
                if (! empty($nested) ) {
                    $urls = array_merge($urls, $nested);
                }
            }
            return $urls;
        }

        if ('urlset' === $node ) {
            foreach ( $xml->url as $child ) {
                if ($max_pages && count($urls) >= $max_pages ) {
                    break;
                }

                $loc = isset($child->loc) ? trim((string) $child->loc) : '';
                if ('' !== $loc ) {
                    $urls[] = esc_url_raw($loc);
                }
            }
        }

        return $urls;
    }

    /**
     * @param callable|null $logger Logger.
     */
    private function log( $logger, string $message ): void
    {
        if (is_callable($logger) ) {
            call_user_func($logger, $message);
        }
    }
}
