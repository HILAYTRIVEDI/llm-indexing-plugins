<?php
declare(strict_types=1);

/**
 * Fetch URLs with throttling, retries, and UA rotation.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Fetcher
{


    /**
     * @var float
     */
    private $rate_limit;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $max_retries;

    /**
     * @var bool
     */
    private $cloudflare;

    /**
     * @var float
     */
    private $last_request = 0.0;

    /**
     * @var MD_LLMs_Builder_User_Agent_Rotator
     */
    private $rotator;

    /**
     * @var string
     */
    private $forced_user_agent = '';

    /**
     * Constructor.
     */
    public function __construct( float $rate_limit = 1.0, int $timeout = 15, int $max_retries = 3, bool $cloudflare = false, string $user_agent = '' )
    {
        $this->rate_limit        = max(0.1, $rate_limit);
        $this->timeout           = max(5, $timeout);
        $this->max_retries       = max(1, $max_retries);
        $this->cloudflare        = $cloudflare;
        $this->rotator           = new MD_LLMs_Builder_User_Agent_Rotator();
        $this->forced_user_agent = trim($user_agent);
    }

    /**
     * Fetch a single URL.
     */
    public function fetch( string $url ): ?MD_LLMs_Builder_Fetch_Result
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) ) {
            return null;
        }

        for ( $attempt = 0; $attempt < $this->max_retries; $attempt++ ) {
            $this->throttle();

            $url      = esc_url_raw($url);
            $response = wp_safe_remote_get(
                $url,
                array(
                'timeout'     => $this->timeout,
                'user-agent'  => $this->forced_user_agent ?: $this->rotator->next(),
                'redirection' => 5,
                'httpversion' => '2.0',
                'headers'     => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ),
                )
            );

            if (is_wp_error($response) ) {
                if ($attempt === $this->max_retries - 1 ) {
                    return null;
                }
                usleep(( 2 ** $attempt ) * 100000);
                continue;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body   = (string) wp_remote_retrieve_body($response);

            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $content_type = $content_type ? strtolower((string) explode(';', $content_type)[0]) : '';

            $result = new MD_LLMs_Builder_Fetch_Result(
                $url,
                $body,
                $status,
                $content_type,
                (array) wp_remote_retrieve_headers($response)
            );

            if (! $result->is_success() ) {
                continue;
            }

            if ($this->cloudflare && $this->looks_like_cloudflare($body) ) {
                $cf_result = $this->attempt_cloudflare_bypass($url);
                if ($cf_result ) {
                    return $cf_result;
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * Fetch multiple URLs (sequential with best-effort concurrency reporting).
     *
     * @param array<int, string> $urls        URLs.
     * @param int                $concurrency Ignored but retained for compatibility.
     * @param callable|null      $progress    Progress callback.
     *
     * @return array<int, MD_LLMs_Builder_Fetch_Result>
     */
    public function fetch_batch( array $urls, int $concurrency = 8, $progress = null ): array
    {
        $total     = count($urls);
        $completed = 0;
        $results   = array();

        foreach ( $urls as $url ) {
            $result = $this->fetch($url);
            if ($result instanceof MD_LLMs_Builder_Fetch_Result ) {
                $results[] = $result;
            }

            ++$completed;
            if (is_callable($progress) ) {
                call_user_func($progress, $completed, $total, $url);
            }
        }

        return $results;
    }

    /**
     * Rate limiting.
     */
    private function throttle(): void
    {
        $interval = 1 / $this->rate_limit;
        $now      = microtime(true);

        if ($this->last_request > 0 ) {
            $elapsed = $now - $this->last_request;
            if ($elapsed < $interval ) {
                usleep((int) ( ( $interval - $elapsed ) * 1000000 ));
            }
        }

        $this->last_request = microtime(true);
    }

    /**
     * Heuristic for Cloudflare challenge.
     */
    private function looks_like_cloudflare( string $body ): bool
    {
        $needles = array(
        'Checking your browser before accessing',
        'cf-browser-verification',
        '__cf_chl_jschl_tk__',
        'Just a moment...',
        );

        foreach ( $needles as $needle ) {
            if (false !== stripos($body, $needle) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allow integrators to supply a bypass.
     */
    private function attempt_cloudflare_bypass( string $url ): ?MD_LLMs_Builder_Fetch_Result
    {
        /**
         * Allow third-parties to provide Cloudflare bypass capabilities by returning
         * an array with keys body, status_code, headers, content_type.
         */
        $override = apply_filters('md_llms_txt_builder_cloudflare_fetch', null, $url);
        if (! is_array($override) ) {
            return null;
        }

        $body         = isset($override['body']) ? (string) $override['body'] : '';
        $status_code  = isset($override['status_code']) ? (int) $override['status_code'] : 200;
        $content_type = isset($override['content_type']) ? (string) $override['content_type'] : 'text/html';
        $headers      = isset($override['headers']) && is_array($override['headers']) ? $override['headers'] : array();

        return new MD_LLMs_Builder_Fetch_Result($url, $body, $status_code, $content_type, $headers);
    }
}
