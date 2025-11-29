<?php
declare(strict_types=1);

/**
 * Save markdown artifacts to disk.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Artifacts
{


    /**
     * Persist a markdown artifact with YAML header.
     *
     * @param string                             $url       Source URL.
     * @param string                             $html      Raw HTML.
     * @param string                             $directory Base directory.
     * @param MD_LLMs_Builder_Markdown_Converter $converter Converter.
     *
     * @return string File path.
     */
    public function save( string $url, string $html, string $directory, MD_LLMs_Builder_Markdown_Converter $converter ): string
    {
        MD_LLMs_Builder_Util::ensure_dir($directory);
        $extractor = new MD_LLMs_Builder_Content_Extractor();
        $metadata  = $extractor->extract_metadata($html);
        $markdown  = $converter->convert($html);

        $header = array(
        'source'      => $url,
        'title'       => $metadata['title'] ?? 'Untitled',
        'description' => $metadata['description'] ?? '',
        'extracted'   => current_time('mysql', true),
        );

        $front_matter = "---\n";
        foreach ( $header as $key => $value ) {
            $front_matter .= sprintf("%s: %s\n", $key, $value);
        }
        $front_matter .= "---\n\n";

        $content = $front_matter . $markdown;

        $filename = MD_LLMs_Builder_Util::sanitize_filename($url, '.md');
        $path     = trailingslashit($directory) . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}
