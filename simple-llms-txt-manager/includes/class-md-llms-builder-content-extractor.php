<?php
declare(strict_types=1);

/**
 * Extract readable content and metadata.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Content_Extractor
{


    /**
     * Extract readable content from HTML.
     */
    public function extract( string $html ): string
    {
        if ('' === $html ) {
            return '';
        }

        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);

        $patterns = array(
        '#<main[^>]*>(.*?)</main>#is',
        '#<article[^>]*>(.*?)</article>#is',
        '#<div[^>]+class="[^"]*content[^"]*"[^>]*>(.*?)</div>#is',
        '#<div[^>]+id="content"[^>]*>(.*?)</div>#is',
        );

        foreach ( $patterns as $pattern ) {
            if (preg_match($pattern, $html, $matches) ) {
                return $this->strip_tags_preserve_text($matches[1]);
            }
        }

        if (preg_match('#<body[^>]*>(.*?)</body>#is', $html, $matches) ) {
            return $this->strip_tags_preserve_text($matches[1]);
        }

        return $this->strip_tags_preserve_text($html);
    }

    /**
     * Extract metadata.
     *
     * @return array<string, string>
     */
    public function extract_metadata( string $html ): array
    {
        $metadata = array(
        'title'       => '',
        'description' => '',
        'published'   => '',
        );

        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $matches) ) {
            $metadata['title'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
        }

        $desc_patterns = array(
        '#<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']#i',
        '#<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']#i',
        '#<meta\s+content=["\']([^"\']+)["\']\s+name=["\']description["\']#i',
        );
        foreach ( $desc_patterns as $pattern ) {
            if (preg_match($pattern, $html, $matches) ) {
                $metadata['description'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
                break;
            }
        }

        $date_patterns = array(
        '#<meta\s+property=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\']#i',
        '#<meta\s+itemprop=["\']datePublished["\'][^>]*content=["\']([^"\']+)["\']#i',
        '#<meta\s+name=["\']publish-date["\'][^>]*content=["\']([^"\']+)["\']#i',
        );
        foreach ( $date_patterns as $pattern ) {
            if (preg_match($pattern, $html, $matches) ) {
                $metadata['published'] = trim($matches[1]);
                break;
            }
        }

        return $metadata;
    }

    /**
     * Strip tags but preserve spacing.
     */
    private function strip_tags_preserve_text( string $html ): string
    {
        $text = preg_replace('#<[^>]+>#', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        return MD_LLMs_Builder_Util::normalize_whitespace($text);
    }
}
