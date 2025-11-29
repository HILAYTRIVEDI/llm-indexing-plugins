<?php
declare(strict_types=1);

/**
 * Ensure summaries end with complete sentences.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Summary_Validator
{


    /**
     * Validate groups + brand summary.
     *
     * @param array<int, MD_LLMs_Builder_Page_Group> $groups Groups.
     * @param string                                 $brand  Brand summary.
     *
     * @return array{0:array<int,MD_LLMs_Builder_Page_Group>,1:string}
     */
    public static function validate( array $groups, string $brand ): array
    {
        $fixed_brand = '' !== $brand ? self::truncate_sentence($brand, 256) : '';

        foreach ( $groups as $group ) {
            if ($group->description ) {
                $group->description = self::truncate_sentence($group->description, 64, 30);
            }

            $new_pages = array();
            foreach ( $group->pages as $page ) {
                list( $url, $title, $summary ) = $page;
                if ('' !== $summary ) {
                    $summary = self::truncate_sentence($summary, 256, 80);
                }
                $new_pages[] = array( $url, $title, $summary );
            }
            $group->pages = $new_pages;
        }

        return array( $groups, $fixed_brand );
    }

    /**
     * Truncate to final punctuation.
     */
    public static function truncate_sentence( string $text, int $max, int $min = 0 ): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ('' === $text ) {
            return '';
        }

        if (strlen($text) <= $max && preg_match('/[.!?]$/', $text) ) {
            return $text;
        }

        for ( $i = min(strlen($text) - 1, $max - 1); $i >= $min; $i-- ) {
            if (in_array($text[ $i ], array( '.', '!', '?' ), true) && $i >= $min ) {
                return substr($text, 0, $i + 1);
            }
        }

        return substr($text, 0, $max);
    }
}
