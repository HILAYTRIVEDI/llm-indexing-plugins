<?php
declare(strict_types=1);

/**
 * Convert HTML fragments to Markdown.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Markdown_Converter
{


    /**
     * @var MD_LLMs_Builder_Content_Extractor
     */
    private $extractor;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->extractor = new MD_LLMs_Builder_Content_Extractor();
    }

    /**
     * Convert HTML into Markdownish text.
     */
    public function convert( string $html ): string
    {
        $content = $this->extractor->extract($html);
        if ('' === $content ) {
            return '';
        }

        $text = $html;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        $map = array(
        '#<h1[^>]*>(.*?)</h1>#is'                 => "\n# $1\n\n",
        '#<h2[^>]*>(.*?)</h2>#is'                 => "\n## $1\n\n",
        '#<h3[^>]*>(.*?)</h3>#is'                 => "\n### $1\n\n",
        '#<h4[^>]*>(.*?)</h4>#is'                 => "\n#### $1\n\n",
        '#<strong[^>]*>(.*?)</strong>#is'         => '**$1**',
        '#<em[^>]*>(.*?)</em>#is'                 => '*$1*',
        '#<code[^>]*>(.*?)</code>#is'             => '`$1`',
        '#<pre[^>]*>(.*?)</pre>#is'               => "\n```\n$1\n```\n\n",
        '#<li[^>]*>(.*?)</li>#is'                 => "- $1\n",
        '#<blockquote[^>]*>(.*?)</blockquote>#is' => "\n> $1\n\n",
        '#<p[^>]*>(.*?)</p>#is'                   => "$1\n\n",
        '#<br\s*/?>#i'                            => "\n",
        '#<hr\s*/?>#i'                            => "\n---\n\n",
        );

        foreach ( $map as $pattern => $replacement ) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        $text = preg_replace('#<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '[$2]($1)', $text);
        $text = preg_replace('#<img[^>]*alt=["\']([^"\']+)["\'][^>]*>#i', '![$1]', $text);
        $text = preg_replace('#<img[^>]*>#i', '', $text);
        $text = preg_replace('#<[^>]+>#', '', $text);

        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }
}
