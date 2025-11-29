<?php
declare(strict_types=1);

/**
 * Represents a group of related pages.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Page_Group
{


    /**
     * @var string
     */
    public $title;

    /**
     * @var string|null
     */
    public $pattern;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var array<int, array{0:string,1:string,2:string}>
     */
    public $pages = array();

    /**
     * Constructor.
     */
    public function __construct( string $title, ?string $pattern = null )
    {
        $this->title   = $title;
        $this->pattern = $pattern;
    }

    /**
     * Add page tuple.
     */
    public function add_page( string $url, string $title, string $summary ): void
    {
        $this->pages[] = array( $url, $title, $summary );
    }

    /**
     * Determine if URL matches this group pattern.
     */
    public function matches( string $url ): bool
    {
        if (null === $this->pattern || '' === $this->pattern ) {
            return false;
        }

        $path    = isset(wp_parse_url($url)['path']) ? (string) wp_parse_url($url)['path'] : '/';
        $pattern = '#^' . str_replace(array( '\*\*', '\*' ), array( '.*', '[^/]*' ), preg_quote($this->pattern, '#')) . '$#i';
        return (bool) preg_match($pattern, $path);
    }
}
