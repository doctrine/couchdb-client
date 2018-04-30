<?php

namespace Doctrine\CouchDB\HTTP;

interface Client
{
    /**
     * Perform a request to the server and return the result.
     *
     * Perform a request to the server and return the result converted into a
     * Response object. If you do not expect a JSON structure, which
     * could be converted in such a response object, set the fourth parameter to
     * true, and you get a response object returned, containing the raw body.
     * Optional HTTP request headers can be passed in an array using the fifth
     * parameter.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @param bool   $raw
     * @param array  $headers
     *
     * @return Response
     */
    public function request($method, $path, $data = null, $raw = false, array $headers = []);

    /**
     * Return the connection pointer or connection socket after setting up the
     * connection.
     *
     * Return the connection pointer (for stream connection) or connection
     * socket (for socket connection) after setting up the connection. The
     * returned resource can be used to read and write data in small chunks
     * reducing the memory usage.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @param array  $headers
     *
     * @return resource
     */
    public function getConnection($method, $path, $data = null, array $headers = []);
}
