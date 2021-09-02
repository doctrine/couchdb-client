<?php

namespace Doctrine\CouchDB\HTTP;

/**
 * This class uses a custom HTTP client, which may have more bugs then the
 * default PHP HTTP clients, but supports keep alive connections without any
 * extension dependencies.
 *
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link        www.doctrine-project.com
 * @since       1.0
 *
 * @author      Kore Nordmann <kore@arbitracker.org>
 */
class SocketClient extends AbstractHTTPClient
{
    /**
     * Connection pointer for connections, once keep alive is working on the
     * CouchDb side.
     *
     * @var resource
     */
    protected $connection;

    /**
     * Return the socket after setting up the connection to server and writing
     * the headers. The returned resource can later be used to read and
     * write data in chunks. These should be done without much delay as the
     * connection might get closed.
     *
     * @throws HTTPException
     *
     * @return resource
     */
    public function getConnection(
        $method,
        $path,
        $data = null,
        array $headers = []
    ) {
        $fullPath = $path;
        if ($this->options['path']) {
            $fullPath = '/'.$this->options['path'].$path;
        }

        $this->checkConnection();
        $stringHeader = $this->buildRequest($method, $fullPath, $data, $headers);
        // Send the build request to the server
        if (fwrite($this->connection, $stringHeader) === false) {
            // Reestablish which seems to have been aborted
            //
            // The recursion in this method might be problematic if the
            // connection establishing mechanism does not correctly throw an
            // exception on failure.
            $this->connection = null;

            return $this->getConnection($method, $path, $data, $headers);
        }

        return $this->connection;
    }

    /**
     * Check for server connection.
     *
     * Checks if the connection already has been established, or tries to
     * establish the connection, if not done yet.
     *
     * @throws HTTPException
     *
     * @return void
     */
    protected function checkConnection()
    {
        // Setting Connection scheme according ssl support
        if ($this->options['ssl']) {
            if (!extension_loaded('openssl')) {
                // no openssl extension loaded.
                // This is a bit hackisch...
                $this->connection = null;

                throw HTTPException::connectionFailure(
                    $this->options['ip'],
                    $this->options['port'],
                    'ssl activated without openssl extension loaded',
                    0
                );
            }

            $host = 'ssl://'.$this->options['host'];
        } else {
            $host = $this->options['ip'];
        }

        // If the connection could not be established, fsockopen sadly does not
        // only return false (as documented), but also always issues a warning.
        if (($this->connection === null) &&
             (($this->connection = @fsockopen($host, $this->options['port'], $errno, $errstr, $this->options['timeout'])) === false)) {
            // This is a bit hackisch...
            $this->connection = null;
            throw HTTPException::connectionFailure(
                $this->options['ip'],
                $this->options['port'],
                $errstr,
                $errno
            );
        }
    }

    /**
     * Build a HTTP 1.1 request.
     *
     * Build the HTTP 1.1 request headers from the given input.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @param array  $headers
     *
     * @return string
     */
    protected function buildRequest(
        $method,
        $path,
        $data = null,
        array $headers = []
    ) {
        // Create basic request headers
        $host = "Host: {$this->options['host']}";
        if ($this->options['port'] != 80) {
            $host .= ":{$this->options['port']}";
        }
        $request = "$method $path HTTP/1.1\r\n$host\r\n";

        // Add basic auth if set
        if ($this->options['username']) {
            $request .= sprintf("Authorization: Basic %s\r\n",
                base64_encode($this->options['username'].':'.$this->options['password'])
            );
        }

        // Set keep-alive header, which helps to keep to connection
        // initialization costs low, especially when the database server is not
        // available in the locale net.
        $request .= 'Connection: '.($this->options['keep-alive'] ? 'Keep-Alive' : 'Close')."\r\n";

        if ($this->options['headers']) {
            $headers = array_merge($this->options['headers'], $headers);
        }
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        foreach ($headers as $key => $value) {
            if (is_bool($value) === true) {
                $value = ($value) ? 'true' : 'false';
            }
            $request .= $key.': '.$value."\r\n";
        }

        // Also add headers and request body if data should be sent to the
        // server. Otherwise just add the closing mark for the header section
        // of the request.
        if ($data !== null) {
            $request .= 'Content-Length: '.strlen($data)."\r\n\r\n";
            $request .= $data;
        } else {
            $request .= "\r\n";
        }

        return $request;
    }

    /**
     * Perform a request to the server and return the result.
     *
     * Perform a request to the server and return the result converted into a
     * Response object. If you do not expect a JSON structure, which
     * could be converted in such a response object, set the forth parameter to
     * true, and you get a response object returned, containing the raw body.
     *
     * @param string $method
     * @param string $path
     * @param string $data
     * @param bool   $raw
     * @param array  $headers
     *
     * @return Response
     */
    public function request($method, $path, $data = null, $raw = false, array $headers = [])
    {
        $fullPath = $path;
        if ($this->options['path']) {
            $fullPath = '/'.$this->options['path'].$path;
        }

        // Try establishing the connection to the server
        $this->checkConnection();

        // Send the build request to the server
        if (fwrite($this->connection, $request = $this->buildRequest($method, $fullPath, $data, $headers)) === false) {
            // Reestablish which seems to have been aborted
            //
            // The recursion in this method might be problematic if the
            // connection establishing mechanism does not correctly throw an
            // exception on failure.
            $this->connection = null;

            return $this->request($method, $path, $data, $raw, $headers);
        }

        // Read server response headers
        $rawHeaders = '';
        $headers = [
            'connection' => ($this->options['keep-alive'] ? 'Keep-Alive' : 'Close'),
        ];

        // Remove leading newlines, should not occur at all, actually.
        while ((($line = fgets($this->connection)) !== false) &&
                (($lineContent = rtrim($line)) === ''));

        // Throw exception, if connection has been aborted by the server, and
        // leave handling to the user for now.
        if ($line === false) {
            // Reestablish which seems to have been aborted
            //
            // The recursion in this method might be problematic if the
            // connection establishing mechanism does not correctly throw an
            // exception on failure.
            //
            // An aborted connection seems to happen here on long running
            // requests, which cause a connection timeout at server side.
            $this->connection = null;

            return $this->request($method, $path, $data, $raw, $headers);
        }

        do {
            // Also store raw headers for later logging
            $rawHeaders .= $lineContent."\n";

            // Extract header values
            if (preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $lineContent, $match)) {
                $headers['version'] = $match['version'];
                $headers['status'] = (int) $match['status'];
            } else {
                list($key, $value) = explode(':', $lineContent, 2);
                $headers[strtolower($key)] = ltrim($value);
            }
        } while ((($line = fgets($this->connection)) !== false) &&
                   (($lineContent = rtrim($line)) !== ''));

        $body = '';
        // Read response body for NON-HEAD requests
        if(strtoupper($method) !== 'HEAD'){
            if (!isset($headers['transfer-encoding']) ||
                 ($headers['transfer-encoding'] !== 'chunked')) {
                // HTTP 1.1 supports chunked transfer encoding, if the according
                // header is not set, just read the specified amount of bytes.
                $bytesToRead = (int) (isset($headers['content-length']) ? $headers['content-length'] : 0);

                // Read body only as specified by chunk sizes, everything else
                // are just footnotes, which are not relevant for us.
                while ($bytesToRead > 0) {
                    $body .= $read = fgets($this->connection, $bytesToRead + 1);
                    $bytesToRead -= strlen($read);
                }
            } else {
                // When transfer-encoding=chunked has been specified in the
                // response headers, read all chunks and sum them up to the body,
                // until the server has finished. Ignore all additional HTTP
                // options after that.
                do {
                    $line = rtrim(fgets($this->connection));

                    // Get bytes to read, with option appending comment
                    if (preg_match('(^([0-9a-f]+)(?:;.*)?$)', $line, $match)) {
                        $bytesToRead = hexdec($match[1]);

                        // Read body only as specified by chunk sizes, everything else
                        // are just footnotes, which are not relevant for us.
                        $bytesLeft = $bytesToRead;
                        while ($bytesLeft > 0) {
                            $body .= $read = fread($this->connection, $bytesLeft + 2);
                            $bytesLeft -= strlen($read);
                        }
                    }
                } while ($bytesToRead > 0);

                // Chop off \r\n from the end.
                $body = substr($body, 0, -2);
            }
        }

        // Reset the connection if the server asks for it.
        if ($headers['connection'] !== 'Keep-Alive') {
            fclose($this->connection);
            $this->connection = null;
        }

        // Handle some response state as special cases
        switch ($headers['status']) {
            case 301:
            case 302:
            case 303:
            case 307:
                $path = parse_url($headers['location'], PHP_URL_PATH);

                return $this->request($method, $path, $data, $raw, $headers);
        }

        // Create response object from couch db response
        if ($headers['status'] >= 400) {
            return new ErrorResponse($headers['status'], $headers, $body);
        }

        return new Response($headers['status'], $headers, $body, $raw);
    }
}
