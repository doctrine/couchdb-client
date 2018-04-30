<?php

namespace Doctrine\CouchDB\HTTP;

/**
 * HTTP response.
 *
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link        www.doctrine-project.com
 * @since       1.0
 *
 * @author      Kore Nordmann <kore@arbitracker.org>
 */
class Response
{
    /**
     * HTTP response status.
     *
     * @var int
     */
    public $status;

    /**
     * HTTP response headers.
     *
     * @var array
     */
    public $headers;

    /**
     * Decoded JSON response body.
     *
     * @var array
     */
    public $body;

    /**
     * Construct response.
     *
     * @param $status
     * @param array  $headers
     * @param string $body
     * @param bool   $raw
     *
     * @return void
     */
    public function __construct($status, array $headers, $body, $raw = false)
    {
        $this->status = (int) $status;
        $this->headers = $headers;
        $this->body = $raw ? $body : json_decode($body, true);
    }
}
