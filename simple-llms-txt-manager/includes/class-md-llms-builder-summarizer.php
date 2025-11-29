<?php
declare(strict_types=1);

/**
 * AI + heuristic summarization.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_Summarizer
{


    /**
     * @var string
     */
    private $provider;

    /**
     * @var string
     */
    private $model;

    /**
     * @var string
     */
    private $openai_key;

    /**
     * @var string
     */
    private $gemini_key;

    /**
     * Constructor.
     */
    public function __construct( string $provider = 'none', string $model = 'gpt-4o-mini', string $openai_key = '', string $gemini_key = '' )
    {
        $this->provider   = $provider;
        $this->model      = $model ?: 'gpt-4o-mini';
        $this->openai_key = $openai_key;
        $this->gemini_key = $gemini_key;
    }

    /**
     * Whether AI provider is available.
     */
    public function has_ai(): bool
    {
        if ('openai' === $this->provider && '' !== $this->openai_key ) {
            return true;
        }
        if ('gemini' === $this->provider && '' !== $this->gemini_key ) {
            return true;
        }
        return false;
    }

    /**
     * Summarize markdown text.
     *
     * @param string               $text     Markdown content.
     * @param int                  $max      Max length.
     * @param array<string,string> $metadata Metadata (title, description).
     */
    public function summarize( string $text, int $max = 256, array $metadata = array() ): string
    {
        $text = trim($text);
        if (strlen($text) < 120 ) {
            return $this->heuristic_summary($text, $max, $metadata);
        }

        $ai_summary = $this->maybe_ai_summary($text, $max);
        if ('' !== $ai_summary ) {
            return $ai_summary;
        }

        return $this->heuristic_summary($text, $max, $metadata);
    }

    /**
     * Generate brand summary.
     */
    public function brand_summary( string $site_name, string $content, int $max = 255 ): string
    {
        if ('openai' === $this->provider && $this->openai_key ) {
            $prompt = sprintf(
                "Write a 230-%d character brand summary for %s using complete sentences in active voice. Mention what they do, who they serve, and any location or credential. Content:\n%s",
                $max,
                $site_name,
                mb_substr($content, 0, 3000, 'UTF-8')
            );

            $response = $this->call_openai($prompt, 150);
            if ('' !== $response ) {
                    return $this->ensure_complete_sentence($response, 230, $max);
            }
        }

        if ('gemini' === $this->provider && $this->gemini_key ) {
            $prompt   = sprintf(
                "Write a 230-%d character brand summary for %s. Use complete sentences, active voice, and include offerings, audience, and proof points.\n\nContent:\n%s",
                $max,
                $site_name,
                mb_substr($content, 0, 3000, 'UTF-8')
            );
            $response = $this->call_gemini($prompt, 256);
            if ('' !== $response ) {
                return $this->ensure_complete_sentence($response, 230, $max);
            }
        }

        return '';
    }

    /**
     * Section description.
     */
    public function section_summary( string $title, array $summaries, int $max = 64 ): string
    {
        if (empty($summaries) ) {
            return '';
        }

        $joined = implode("\n- ", array_slice($summaries, 0, 20));

        $prompt = sprintf(
            'Write a %1$d-character description of what the "%2$s" section covers. Complete sentence. Data:%3$s- %4$s',
            $max,
            $title,
            PHP_EOL,
            $joined
        );

        if ('openai' === $this->provider && $this->openai_key ) {
            $response = $this->call_openai($prompt, 80);
            if ('' !== $response ) {
                return $this->ensure_complete_sentence($response, 30, $max);
            }
        }

        if ('gemini' === $this->provider && $this->gemini_key ) {
            $response = $this->call_gemini($prompt, 80);
            if ('' !== $response ) {
                return $this->ensure_complete_sentence($response, 30, $max);
            }
        }

        return '';
    }

    /**
     * Use AI if configured.
     */
    private function maybe_ai_summary( string $text, int $max ): string
    {
        if ('openai' === $this->provider && $this->openai_key ) {
            $prompt   = sprintf(
                "Write a %d character summary (complete sentences, active voice, no authors, mention brand if possible). Content:\n%s",
                $max,
                mb_substr($text, 0, 2000, 'UTF-8')
            );
            $response = $this->call_openai($prompt, 150);
            if ('' !== $response ) {
                    $response = $this->ensure_complete_sentence($response, 120, $max);
                    return MD_LLMs_Builder_Util::truncate_grapheme($response, $max);
            }
        }

        if ('gemini' === $this->provider && $this->gemini_key ) {
            $prompt   = sprintf(
                "Summarize this content in %d characters. Use complete sentences and active voice. Content:\n%s",
                $max,
                mb_substr($text, 0, 2000, 'UTF-8')
            );
            $response = $this->call_gemini($prompt, $max);
            if ('' !== $response ) {
                $response = $this->ensure_complete_sentence($response, 120, $max);
                return MD_LLMs_Builder_Util::truncate_grapheme($response, $max);
            }
        }

        return '';
    }

    /**
     * Heuristic fallback.
     */
    private function heuristic_summary( string $text, int $max, array $metadata ): string
    {
        if (! empty($metadata['description']) ) {
            return MD_LLMs_Builder_Util::truncate_grapheme($metadata['description'], $max);
        }

        $text = trim($text);
        if ('' === $text ) {
            return __('Content summary unavailable', 'md-llms-txt');
        }

        $sentences = preg_split('/\.\s+/', $text);
        if (! empty($sentences[0]) && strlen($sentences[0]) > 40 ) {
            $summary = $sentences[0] . '.';
        } else {
            $summary = substr($text, 0, $max);
        }

        return MD_LLMs_Builder_Util::truncate_grapheme($summary, $max);
    }

    /**
     * Ensure full sentence boundaries.
     */
    private function ensure_complete_sentence( string $text, int $min, int $max ): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ('' === $text ) {
            return '';
        }

        if (strlen($text) <= $max && preg_match('/[.!?]$/', $text) ) {
            return $text;
        }

        $sentences = preg_split('/([.!?])\s+/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output    = '';

        for ( $i = 0; $i < count($sentences); $i += 2 ) {
            $sentence = $sentences[ $i ];
            $punct    = isset($sentences[ $i + 1 ]) ? $sentences[ $i + 1 ] : '.';
            $next     = trim($output . ' ' . $sentence . $punct);

            if (strlen($next) > $max ) {
                break;
            }

            $output = $next;
            if (strlen($output) >= $min ) {
                break;
            }
        }

        return '' !== $output ? $output : substr($text, 0, $max);
    }

    /**
     * Call OpenAI chat completions.
     */
    private function call_openai( string $prompt, int $max_tokens ): string
    {
        if ('' === $this->openai_key ) {
            return '';
        }

        $response = wp_safe_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
            'timeout' => 45,
            'headers' => array(
                    'Authorization' => 'Bearer ' . $this->openai_key,
                    'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(
                array(
                        'model'       => $this->model,
                        'temperature' => 0.3,
                        'max_tokens'  => $max_tokens,
                        'messages'    => array(
                            array(
                                'role'    => 'system',
                                'content' => 'You are a professional summarizer. Write factual, complete sentences.',
                            ),
                            array(
                                'role'    => 'user',
                                'content' => $prompt,
                            ),
                ),
                )
            ),
            )
        );

        if (is_wp_error($response) ) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! isset($body['choices'][0]['message']['content']) ) {
            return '';
        }

        return trim((string) $body['choices'][0]['message']['content']);
    }

    /**
     * Call Gemini models.
     */
    private function call_gemini( string $prompt, int $max ): string
    {
        if ('' === $this->gemini_key ) {
            return '';
        }

        $url  = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($this->model), rawurlencode($this->gemini_key));
        $url  = esc_url_raw($url);
        $body = array(
        'contents'         => array(
        array(
                    'parts' => array(
                        array( 'text' => $prompt ),
        ),
        ),
        ),
        'generationConfig' => array(
        'temperature'     => 0.2,
        'maxOutputTokens' => $max,
        ),
        );

        $response = wp_safe_remote_post(
            $url,
            array(
            'timeout' => 45,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode($body),
            )
        );

        if (is_wp_error($response) ) {
            return '';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['candidates'][0]['content']['parts'][0]['text']) ) {
            return '';
        }

        return trim((string) $data['candidates'][0]['content']['parts'][0]['text']);
    }
}
