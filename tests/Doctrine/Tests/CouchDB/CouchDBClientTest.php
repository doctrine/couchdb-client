<?php

namespace Doctrine\Tests\CouchDB;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\SocketClient;

class CouchDBClientTest extends \PHPUnit_Framework_TestCase
{
    public function testConstants()
    {
        $this->assertEquals("\xEF\xBF\xB0", CouchDBClient::COLLATION_END);
    }

    public function testCreateClient()
    {
        $client = CouchDBClient::create(['dbname' => 'test']);
        $this->assertEquals('test', $client->getDatabase());
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Client', $client->getHttpClient());

        $httpClient = new SocketClient();
        $client->setHttpClient($httpClient);
        $this->assertEquals($httpClient, $client->getHttpClient());
    }

    public function testCreateClientFromUrl()
    {
        $client = CouchDBClient::create(['url' => 'https://foo:bar@localhost:5555/baz']);

        $this->assertEquals('baz', $client->getDatabase());
        $this->assertEquals(
            [
                'host'       => 'localhost',
                'port'       => 5555,
                'ip'         => '127.0.0.1',
                'username'   => 'foo',
                'password'   => 'bar',
                'ssl'        => true,
                'timeout'    => 10,
                'keep-alive' => true,
                'path'       => null,
                'headers'    => [],
            ],
            $client->getHttpClient()->getOptions()
        );
    }

    public function testCreateClientFromUrlWithPath()
    {
        $client = CouchDBClient::create(['url' => 'https://foo:bar@localhost:5555/baz/qux/norf']);

        $this->assertEquals('norf', $client->getDatabase());
        $this->assertEquals(
            [
                'host'       => 'localhost',
                'port'       => 5555,
                'ip'         => '127.0.0.1',
                'username'   => 'foo',
                'password'   => 'bar',
                'ssl'        => true,
                'timeout'    => 10,
                'keep-alive' => true,
                'path'       => 'baz/qux',
                'headers'    => [],
            ],
            $client->getHttpClient()->getOptions()
        );
    }

    public function testCreateClientWithDefaultHeaders()
    {
        $client = CouchDBClient::create(['dbname' => 'test', 'headers' => ['X-Test' => 'test']]);
        $http_client = $client->getHttpClient();
        $connection_options = $http_client->getOptions();
        $this->assertSame(['X-Test' => 'test'], $connection_options['headers']);

        $http_client->setOption('headers', ['X-Test-New' => 'new']);
        $connection_options = $http_client->getOptions();
        $this->assertSame(['X-Test-New' => 'new'], $connection_options['headers']);
    }

    public function testCreateClientWithLogging()
    {
        $client = CouchDBClient::create(['dbname' => 'test', 'logging' => true]);
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\LoggingClient', $client->getHttpClient());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 'dbname' is a required option to create a CouchDBClient
     */
    public function testCreateClientDBNameException()
    {
        CouchDBClient::create([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage There is no client implementation registered for foo, valid options are: socket, stream
     */
    public function testCreateClientMissingClientException()
    {
        CouchDBClient::create(['dbname' => 'test', 'type' => 'foo']);
    }
}
