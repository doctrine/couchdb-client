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
        $client = CouchDBClient::create(array('dbname' => 'test'));
        $this->assertEquals('test', $client->getDatabase());
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Client', $client->getHttpClient());

        $httpClient = new SocketClient();
        $client->setHttpClient($httpClient);
        $this->assertEquals($httpClient, $client->getHttpClient());
    }

    public function testCreateClientFromUrl()
    {
        $client = CouchDBClient::create(array('url' => 'https://foo:bar@localhost:5555/baz'));

        $this->assertEquals('baz', $client->getDatabase());
        $this->assertEquals(
            array(
                'host' => 'localhost',
                'port' => 5555,
                'ip' => '127.0.0.1',
                'username' => 'foo',
                'password' => 'bar',
                'ssl' => true,
                'timeout' => 10,
                'keep-alive' => true,
                'path' => null,
            ),
            $client->getHttpClient()->getOptions()
        );
    }

    public function testCreateClientFromUrlWithPath()
    {
        $client = CouchDBClient::create(array('url' => 'https://foo:bar@localhost:5555/baz/qux/norf'));

        $this->assertEquals('norf', $client->getDatabase());
        $this->assertEquals(
            array(
                'host' => 'localhost',
                'port' => 5555,
                'ip' => '127.0.0.1',
                'username' => 'foo',
                'password' => 'bar',
                'ssl' => true,
                'timeout' => 10,
                'keep-alive' => true,
                'path' => 'baz/qux',
            ),
            $client->getHttpClient()->getOptions()
        );
    }

    public function testCreateClientWithLogging()
    {
        $client = CouchDBClient::create(array('dbname' => 'test', 'logging' => true));
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\LoggingClient', $client->getHttpClient());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 'dbname' is a required option to create a CouchDBClient
     */
    public function testCreateClientDBNameException()
    {
        CouchDBClient::create(array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage There is no client implementation registered for foo, valid options are: socket, stream
     */
    public function testCreateClientMissingClientException()
    {
        CouchDBClient::create(array('dbname' => 'test', 'type' => 'foo'));
    }
}
