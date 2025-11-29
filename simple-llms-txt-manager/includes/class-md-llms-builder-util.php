<?php
declare(strict_types=1);

/**
 * Helper utilities shared across builder components.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Util
{


    /**
     * Convert a URL into a filesystem-safe filename.
     */
    public static function sanitize_filename( string $url, string $suffix = '' ): string
    {
        $parts = wp_parse_url($url);
        $path  = isset($parts['path']) ? trim((string) $parts['path']) : '';

        if ('' === $path || '/' === $path ) {
            $filename = 'index';
        } else {
            $filename = trim(str_replace('/', '_', $path), '_');
        }

        $filename = preg_replace('/[<>:"|?*]/', '', $filename);
        if ('' === $filename ) {
            $filename = 'page';
        }

        return $suffix ? "{$filename}{$suffix}" : $filename;
    }

    /**
     * Ensure directory exists.
     */
    public static function ensure_dir( string $path ): string
    {
        wp_mkdir_p($path);
        return $path;
    }

    /**
     * Normalize whitespace.
     */
    public static function normalize_whitespace( string $text ): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return is_string($text) ? trim($text) : '';
    }

    /**
     * Truncate to character count without splitting graphemes.
     */
    public static function truncate_grapheme( string $text, int $max ): string
    {
        if (mb_strlen($text, 'UTF-8') <= $max ) {
            return $text;
        }

        if (function_exists('normalizer_normalize') ) {
            $text = normalizer_normalize($text, Normalizer::FORM_C);
            if (false === $text ) {
                $text = '';
            }
        }

        return trim(mb_substr($text, 0, $max, 'UTF-8'));
    }

    /**
     * Remove brand suffix from titles.
     */
    public static function clean_title( string $title, string $site_url = '' ): string
    {
        if ('' === $title ) {
            return $title;
        }

        $brand_keywords = array();
        if ('' !== $site_url ) {
            $parts  = wp_parse_url($site_url);
            $domain = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
            $domain = str_replace('www.', '', $domain);
            if ('' !== $domain ) {
                $brand_keywords[] = $domain;
                $segments         = preg_split('/[\.\-]/', $domain);
                foreach ( $segments as $segment ) {
                    if (strlen($segment) >= 3 ) {
                        $brand_keywords[] = $segment;
                    }
                }
            }
        }

        $separators = array( ' - ', ' | ', ' – ', ' — ', ' :: ', ' / ', ' : ' );
        foreach ( $separators as $separator ) {
            if (false === strpos($title, $separator) ) {
                continue;
            }

            $parts = explode($separator, $title);
            if (count($parts) < 2 ) {
                continue;
            }

            $suffix = strtolower(array_pop($parts));
            foreach ( $brand_keywords as $keyword ) {
                if (false !== strpos($suffix, $keyword) ) {
                    return trim(implode($separator, $parts));
                }
            }

            if (strlen($suffix) < 30 ) {
                return trim(implode($separator, $parts));
            }
        }

        return trim($title);
    }
}
