<?php
declare(strict_types=1);

/**
 * Emit llms.txt output + context files.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Emitter
{


    /**
     * @var string
     */
    private $site_name;

    /**
     * @var string
     */
    private $overview;

    /**
     * @var string
     */
    private $brand_summary;

    /**
     * Constructor.
     */
    public function __construct( string $site_name, string $overview, string $brand_summary )
    {
        $this->site_name     = $site_name ?: __('Generated llms.txt', 'md-llms-txt');
        $this->overview      = $overview ?: __('Documentation and resources.', 'md-llms-txt');
        $this->brand_summary = $brand_summary;
    }

    /**
     * Emit to disk.
     *
     * @param array<int, MD_LLMs_Builder_Page_Group> $groups Groups.
     * @param string                                 $path   Output path.
     *
     * @return string Content.
     */
    public function emit( array $groups, string $path ): string
    {
        $content = $this->render($groups);
        MD_LLMs_Builder_Util::ensure_dir(dirname($path));
        file_put_contents($path, $content);
        return $content;
    }

    /**
     * Emit context files.
     */
    public function emit_context_files( array $groups, string $base_path, string $artifacts_dir ): void
    {
        $summary_path = trailingslashit(dirname($base_path)) . 'llms-ctx.txt';
        $full_path    = trailingslashit(dirname($base_path)) . 'llms-ctx-full.txt';

        file_put_contents($summary_path, $this->render_context_summary($groups));
        file_put_contents($full_path, $this->render_context_full($groups, $artifacts_dir));
    }

    /**
     * Render llms.txt markup.
     */
    private function render( array $groups ): string
    {
        $lines   = array();
        $lines[] = '# ' . $this->site_name;
        $lines[] = '';

        if ('' !== $this->brand_summary ) {
            $lines[] = $this->brand_summary;
            $lines[] = '';
        }

        if ('' !== $this->overview ) {
            $lines[] = '> ' . $this->overview;
            $lines[] = '';
        }

        foreach ( $groups as $group ) {
            $lines[] = '## ' . $group->title;
            if ($group->description ) {
                $lines[] = '*' . $group->description . '*';
                $lines[] = '';
            }

            foreach ( $group->pages as $page ) {
                list( $url, $title, $summary ) = $page;
                $line                          = sprintf('- [%s](%s)', $title, $url);
                if ($summary ) {
                    $line .= ': ' . $summary;
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Context summary file.
     */
    private function render_context_summary( array $groups ): string
    {
        $lines = array( '# Context Summary', '' );
        foreach ( $groups as $group ) {
            $lines[] = '## ' . $group->title;
            foreach ( $group->pages as $page ) {
                list( $url, $title, $summary ) = $page;
                $lines[]                       = sprintf('- [%s](%s): %s', $title, $url, $summary);
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    /**
     * Context full file using artifacts.
     */
    private function render_context_full( array $groups, string $artifacts_dir ): string
    {
        $lines = array( '# Full Context', '' );

        foreach ( $groups as $group ) {
            $lines[] = '## ' . $group->title;
            foreach ( $group->pages as $page ) {
                list( $url, $title, $summary ) = $page;
                $lines[]                       = '### ' . $title;
                $lines[]                       = 'URL: ' . $url;
                $lines[]                       = 'Summary: ' . $summary;
                $filename                      = MD_LLMs_Builder_Util::sanitize_filename($url, '.md');
                $file                          = trailingslashit($artifacts_dir) . $filename;
                if (file_exists($file) ) {
                    $lines[] = file_get_contents($file);
                }
                $lines[] = '';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
