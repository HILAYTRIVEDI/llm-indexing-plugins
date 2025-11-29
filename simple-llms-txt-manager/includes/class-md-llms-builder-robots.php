<?php
declare(strict_types=1);

/**
 * Parse robots.txt content and enforce crawl rules.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Robots
{


    /**
     * @var string
     */
    private $user_agent;

    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private $rules = array();

    /**
     * @var array<int, string>
     */
    private $sitemaps = array();

    /**
     * @var bool
     */
    private $disallow_all = false;

    /**
     * Constructor.
     */
    public function __construct( string $user_agent = 'llms-txt-bot' )
    {
        $this->user_agent = $user_agent;
    }

    /**
     * Fetch and parse robots.txt from a site.
     */
    public function fetch( string $base_url ): void
    {
        $parts = wp_parse_url($base_url);
        if (empty($parts['scheme']) || empty($parts['host']) ) {
            return;
        }

        $url      = $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
        $url      = esc_url_raw($url);
        $response = wp_safe_remote_get(
            $url,
            array(
            'timeout'     => 10,
            'user-agent'  => $this->user_agent,
            'redirection' => 3,
            'httpversion' => '2.0',
            )
        );

        if (is_wp_error($response) ) {
            return;
        }

        if (200 !== wp_remote_retrieve_response_code($response) ) {
            return;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === $body ) {
            return;
        }

        $this->parse($body, $base_url);
    }

    /**
     * Parse robots content.
     */
    private function parse( string $content, string $base_url ): void
    {
        $current_agents       = array();
        $include_current_rule = false;

        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ( $lines as $line ) {
            $line = trim((string) $line);
            if ('' === $line || 0 === strpos($line, '#') ) {
                continue;
            }

            if (stripos($line, 'user-agent:') === 0 ) {
                $agent                = trim(substr($line, 11));
                $current_agents       = array( $agent );
                $include_current_rule = ( '*' === $agent || 0 === strcasecmp($agent, $this->user_agent) );
                continue;
            }

            if (stripos($line, 'allow:') === 0 && $include_current_rule ) {
                $path = trim(substr($line, 7));
                foreach ( $current_agents as $agent ) {
                    if (! isset($this->rules[ $agent ]) ) {
                        $this->rules[ $agent ] = array(
                         'allow'    => array(),
                         'disallow' => array(),
                        );
                    }
                    $this->rules[ $agent ]['allow'][] = $path;
                }
                continue;
            }

            if (stripos($line, 'disallow:') === 0 && $include_current_rule ) {
                $path = trim(substr($line, 10));
                if ('' === $path ) {
                    $this->disallow_all = true;
                    continue;
                }

                foreach ( $current_agents as $agent ) {
                    if (! isset($this->rules[ $agent ]) ) {
                        $this->rules[ $agent ] = array(
                        'allow'    => array(),
                        'disallow' => array(),
                        );
                    }
                    $this->rules[ $agent ]['disallow'][] = $path;
                }
                continue;
            }

            if (stripos($line, 'sitemap:') === 0 ) {
                $path = trim(substr($line, 8));
                if ('' !== $path ) {
                    if (! preg_match('#^https?://#i', $path) ) {
                        $path = wp_make_link_relative($path);
                        $path = trailingslashit(untrailingslashit($base_url)) . ltrim($path, '/');
                    }
                    $this->sitemaps[] = esc_url_raw($path);
                }
            }
        }
    }

    /**
     * Determine if URL allowed.
     */
    public function is_allowed( string $url ): bool
    {
        if ($this->disallow_all ) {
            return false;
        }

        $parts = wp_parse_url($url);
        $path  = isset($parts['path']) ? (string) $parts['path'] : '/';

        $agents = array( $this->user_agent, '*' );
        foreach ( $agents as $agent ) {
            if (! isset($this->rules[ $agent ]) ) {
                continue;
            }

            $rules = $this->rules[ $agent ];

            if (! empty($rules['allow']) ) {
                foreach ( $rules['allow'] as $pattern ) {
                    if ($this->matches_pattern($path, $pattern) ) {
                        return true;
                    }
                }
            }

            if (! empty($rules['disallow']) ) {
                foreach ( $rules['disallow'] as $pattern ) {
                    if ($this->matches_pattern($path, $pattern) ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Sitemap list.
     *
     * @return array<int, string>
     */
    public function get_sitemaps(): array
    {
        return array_values(array_unique($this->sitemaps));
    }

    /**
     * Robots pattern match.
     */
    private function matches_pattern( string $path, string $pattern ): bool
    {
        if ('' === $pattern ) {
            return false;
        }

        $pattern = str_replace(array( '*', '?' ), array( '.*', '.' ), preg_quote($pattern, '#'));
        return (bool) preg_match('#^' . $pattern . '#', $path);
    }
}
