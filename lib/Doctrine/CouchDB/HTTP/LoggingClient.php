<?php

namespace Doctrine\CouchDB\HTTP;

class LoggingClient implements Client
{
    /**
     * @var Client
     */
    private $client;

    /**
     * Array of requests made to CouchDB with this client.
     *
     * Contains the following keys:
     * - duration - Microseconds it took to execute and process the request
     * - method (GET, POST, ..)
     * - path - The requested url path on the server including parameters
     * - request - The request body if its size is smaller than 10000 chars.
     * - request_size - The size of the request body
     * - response_status - The response HTTP status
     * - response - The body of the response.
     * - response_headers
     *
     * @var array
     */
    public $requests = [];

    /**
     * @var float
     */
    public $totalDuration = 0;

    /**
     * Construct new logging client wrapping the real client.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function request($method, $path, $data = null, $raw = false, array $headers = [])
    {
        $start = microtime(true);

        $response = $this->client->request($method, $path, $data, $raw, $headers);

        $duration = microtime(true) - $start;
        $this->requests[] = [
            'duration'         => $duration,
            'method'           => $method,
            'path'             => rawurldecode($path),
            'request'          => $data,
            'request_size'     => strlen($data),
            'response_status'  => $response->status,
            'response'         => $response->body,
            'response_headers' => $response->headers,
        ];
        $this->totalDuration += $duration;

        return $response;
    }

    public function getConnection(
        $method,
        $path,
        $data = null,
        array $headers = []
    ) {
        $start = microtime(true);

        $response = $this->client->getConnection(
            $method,
            $path,
            $data,
            $headers
        );

        $duration = microtime(true) - $start;
        $this->requests[] = [
            'duration'         => $duration,
            'method'           => $method,
            'path'             => rawurldecode($path),
            'request'          => $data,
            'request_size'     => strlen($data),
            'response_status'  => $response->status,
            'response'         => $response->body,
            'response_headers' => $response->headers,
        ];
        $this->totalDuration += $duration;

        return $response;
    }
}
