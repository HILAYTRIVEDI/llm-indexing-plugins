<?php
declare(strict_types=1);

/**
 * Grouping logic ported from CLI.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Page_Grouper
{


    /**
     * @var array<string, string>
     */
    private $sections;

    /**
     * @var array<int, string>
     */
    private $optional;

    /**
     * Constructor.
     */
    public function __construct( array $sections = array(), array $optional = array() )
    {
        $this->sections = $sections;
        $this->optional = $optional;
    }

    /**
     * Create groups.
     *
     * @param array<int, array{0:string,1:string,2:string}> $pages                Pages.
     * @param bool                                          $use_folder_structure Auto sections.
     *
     * @return array<int, MD_LLMs_Builder_Page_Group>
     */
    public function group( array $pages, bool $use_folder_structure = true ): array
    {
        if ($use_folder_structure && empty($this->sections) ) {
            $this->sections = $this->extract_folder_sections($pages);
        }

        $groups = array();
        foreach ( $this->sections as $title => $pattern ) {
            $groups[ $title ] = new MD_LLMs_Builder_Page_Group($title, $pattern);
        }
        $groups['Optional'] = new MD_LLMs_Builder_Page_Group('Optional');

        foreach ( $pages as $page ) {
            list( $url, $title, $summary ) = $page;
            $matched                       = false;

            foreach ( $groups as $group_title => $group ) {
                if ('Optional' === $group_title ) {
                    continue;
                }

                if ($group->matches($url) ) {
                    $group->add_page($url, $title, $summary);
                    $matched = true;
                    break;
                }
            }

            if ($matched ) {
                continue;
            }

            $path = isset(wp_parse_url($url)['path']) ? (string) wp_parse_url($url)['path'] : '/';

            if ($this->matches_optional($path) ) {
                $groups['Optional']->add_page($url, $title, $summary);
                continue;
            }

            if (! isset($groups['Other']) ) {
                $groups['Other'] = new MD_LLMs_Builder_Page_Group('Other');
            }
            $groups['Other']->add_page($url, $title, $summary);
        }

        $ordered = array();
        foreach ( array_keys($this->sections) as $title ) {
            if (isset($groups[ $title ]) && ! empty($groups[ $title ]->pages) ) {
                $ordered[] = $groups[ $title ];
            }
        }

        if (isset($groups['Other']) && ! empty($groups['Other']->pages) ) {
            $ordered[] = $groups['Other'];
        }

        if (isset($groups['Optional']) && ! empty($groups['Optional']->pages) ) {
            $ordered[] = $groups['Optional'];
        }

        return $ordered;
    }

    /**
     * Parse CLI style string into sections array.
     */
    public static function parse_sections( string $input ): array
    {
        if ('' === trim($input) ) {
            return array();
        }

        $sections = array();
        $entries  = explode(',', $input);
        foreach ( $entries as $entry ) {
            if (false === strpos($entry, ':') ) {
                continue;
            }

            list( $label, $pattern ) = array_map('trim', explode(':', $entry, 2));
            if ('' === $label || '' === $pattern ) {
                continue;
            }

            $sections[ $label ] = $pattern;
        }

        return $sections;
    }

    /**
     * Derive sections from folder structure.
     *
     * @param array<int, array{0:string,1:string,2:string}> $pages Pages.
     *
     * @return array<string, string>
     */
    private function extract_folder_sections( array $pages ): array
    {
        $folders = array();
        foreach ( $pages as $page ) {
            $url  = $page[0];
            $path = isset(wp_parse_url($url)['path']) ? trim((string) wp_parse_url($url)['path'], '/') : '';
            if ('' === $path ) {
                $folders['Home'][] = $page;
                continue;
            }

            $segments = explode('/', $path);
            if (empty($segments[0]) ) {
                continue;
            }

            $key               = strtolower($segments[0]);
            $folders[ $key ][] = $page;
        }

        $sections = array();
        foreach ( $folders as $folder => $folder_pages ) {
            if (count($folder_pages) < 2 ) {
                continue;
            }

            $title              = ucwords(str_replace(array( '-', '_' ), ' ', $folder));
            $sections[ $title ] = '/' . $folder . '/**';
        }

        return $sections;
    }

    /**
     * Check optional paths.
     */
    private function matches_optional( string $path ): bool
    {
        foreach ( $this->optional as $pattern ) {
            $pattern = '#^' . str_replace(array( '\*\*', '\*' ), array( '.*', '[^/]*' ), preg_quote($pattern, '#')) . '$#i';
            if (preg_match($pattern, $path) ) {
                return true;
            }
        }

        return false;
    }
}
