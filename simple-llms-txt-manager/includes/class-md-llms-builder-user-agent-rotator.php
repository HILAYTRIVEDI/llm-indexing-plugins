<?php
declare(strict_types=1);

/**
 * Rotate modern browser user agents for fetchers.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

class MD_LLMs_Builder_User_Agent_Rotator
{


    /**
     * @var array<int, string>
     */
    private $agents = array(
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0.1 Safari/605.1.15',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:144.0) Gecko/20100101 Firefox/144.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    );

    /**
     * @var int
     */
    private $index = 0;

    /**
     * Get the next agent.
     */
    public function next(): string
    {
        $agent       = $this->agents[ $this->index ];
        $this->index = ( $this->index + 1 ) % count($this->agents);
        return $agent;
    }
}
