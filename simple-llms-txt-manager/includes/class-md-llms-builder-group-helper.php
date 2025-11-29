<?php
declare(strict_types=1);

/**
 * Additional grouping heuristics for consistent output.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Group_Helper
{


    /**
     * Post-process groups similar to CLI implementation.
     *
     * @param array<int, MD_LLMs_Builder_Page_Group> $groups        Groups.
     * @param array<string, array<string,mixed>>     $page_metadata Metadata keyed by URL.
     * @param string                                 $brand_name    Brand label.
     *
     * @return array<int, MD_LLMs_Builder_Page_Group>
     */
    public static function post_process( array $groups, array $page_metadata, string $brand_name ): array
    {
        if (empty($groups) ) {
            return $groups;
        }

        $brand_label  = '' !== trim($brand_name) ? $brand_name : __('Our Brand', 'md-llms-txt');
        $engage_title = sprintf(__('Engage with %s', 'md-llms-txt'), $brand_label);

        $map = array();
        foreach ( $groups as $group ) {
            $map[ $group->title ] = $group;
        }

        $required = array( 'Case Studies', 'Products', 'Integrations', 'Workout Marketplace', 'Blog Posts', $engage_title );
        foreach ( $required as $title ) {
            if (! isset($map[ $title ]) ) {
                $map[ $title ] = new MD_LLMs_Builder_Page_Group($title);
                $groups[]      = $map[ $title ];
            }
        }

        foreach ( $groups as $group ) {
            $new_pages = array();

            foreach ( $group->pages as $page ) {
                list( $url, $title, $summary ) = $page;
                $target                        = self::determine_target_group($group->title, $url, $engage_title);

                if ($target === $group->title ) {
                    $new_pages[] = $page;
                    continue;
                }

                $map[ $target ]->add_page($url, $title, $summary);
            }

            $group->pages = $new_pages;
        }

        $cleaned = array();
        foreach ( $groups as $group ) {
            if (empty($group->pages) ) {
                continue;
            }
            $cleaned[ $group->title ] = $group;
        }

        $ordered = array();

        if (isset($cleaned[ $engage_title ]) ) {
            $ordered[] = $cleaned[ $engage_title ];
            unset($cleaned[ $engage_title ]);
        }

        foreach ( $cleaned as $title => $group ) {
            $lower = strtolower($title);
            if (in_array($lower, array( 'blog', 'blog posts', 'guides' ), true) ) {
                continue;
            }
            $ordered[] = $group;
            unset($cleaned[ $title ]);
        }

        if (isset($cleaned['Guides']) ) {
            $ordered[] = $cleaned['Guides'];
            unset($cleaned['Guides']);
        }

        $blog_key = isset($cleaned['Blog Posts']) ? 'Blog Posts' : ( isset($cleaned['Blog']) ? 'Blog' : null );
        if ($blog_key ) {
            $blog_group = $cleaned[ $blog_key ];
            usort(
                $blog_group->pages,
                function ( $a, $b ) use ( $page_metadata ) {
                    $url_a  = $a[0];
                    $url_b  = $b[0];
                    $time_a = isset($page_metadata[ $url_a ]['published']) ? strtotime((string) $page_metadata[ $url_a ]['published']) : 0;
                    $time_b = isset($page_metadata[ $url_b ]['published']) ? strtotime((string) $page_metadata[ $url_b ]['published']) : 0;
                    return $time_b <=> $time_a;
                }
            );
            $blog_group->title = 'Blog Posts';
            $ordered[]         = $blog_group;
            unset($cleaned[ $blog_key ]);
        }

        foreach ( $cleaned as $group ) {
            $ordered[] = $group;
        }

        return $ordered;
    }

    /**
     * Determine target group.
     */
    private static function determine_target_group( string $current, string $url, string $engage_title ): string
    {
        $path = isset(wp_parse_url($url)['path']) ? strtolower((string) wp_parse_url($url)['path']) : '/';
        $text = strtolower($current . ' ' . $path);

        if (0 === strpos($path, '/blog') ) {
            return 'Blog Posts';
        }

        $engage_keywords = array( 'demo', 'pricing', 'contact', 'support', 'refer' );
        foreach ( $engage_keywords as $keyword ) {
            if (false !== strpos($text, $keyword) ) {
                return $engage_title;
            }
        }

        if (0 === strpos($path, '/case-studies') || false !== strpos($path, 'customer-review') ) {
            return 'Case Studies';
        }

        if (false !== strpos($path, 'product-roadmap') ) {
            return 'Products';
        }

        if (0 === strpos($path, '/integrations') ) {
            return 'Integrations';
        }

        if (0 === strpos($path, '/workout-marketplace') ) {
            return 'Workout Marketplace';
        }

        return $current;
    }
}
