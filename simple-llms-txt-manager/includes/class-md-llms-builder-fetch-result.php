<?php
declare(strict_types=1);

/**
 * Immutable fetch response.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Fetch_Result
{


    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $body;

    /**
     * @var int
     */
    public $status_code;

    /**
     * @var string
     */
    public $content_type;

    /**
     * @var array<string, string>
     */
    public $headers;

    /**
     * Constructor.
     *
     * @param string               $url          URL.
     * @param string               $body         Body.
     * @param int                  $status_code  HTTP status.
     * @param string               $content_type Content type.
     * @param array<string,string> $headers      Headers.
     */
    public function __construct( string $url, string $body, int $status_code, string $content_type, array $headers = array() )
    {
        $this->url          = $url;
        $this->body         = $body;
        $this->status_code  = $status_code;
        $this->content_type = $content_type;
        $this->headers      = $headers;
    }

    /**
     * Determine if response is HTML.
     */
    public function is_html(): bool
    {
        return in_array($this->content_type, array( 'text/html', 'application/xhtml+xml' ), true);
    }

    /**
     * HTTP success.
     */
    public function is_success(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }
}
