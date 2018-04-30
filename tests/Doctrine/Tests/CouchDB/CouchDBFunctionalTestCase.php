<?php

namespace Doctrine\Tests\CouchDB;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\MangoClient;
use Doctrine\CouchDB\HTTP\SocketClient;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    protected function tearDown()
    {
        parent::tearDown();
        $this->createCouchDBClient()->deleteDatabase($this->getTestDatabase());
    }

    /**
     * @return \Doctrine\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            if (isset($GLOBALS['DOCTRINE_COUCHDB_CLIENT'])) {
                $this->httpClient = new $GLOBALS['DOCTRINE_COUCHDB_CLIENT']();
            } else {
                $this->httpClient = new SocketClient();
            }
        }

        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }

    public function getBulkTestDatabase()
    {
        return TestUtil::getBulkTestDatabase();
    }

    public function createCouchDBClient()
    {
        return new CouchDBClient($this->getHttpClient(), $this->getTestDatabase());
    }

    public function createMangoClient(){
      return new MangoClient($this->getHttpClient(), $this->getTestDatabase());
    }

    public function createCouchDBClientForBulkTest()
    {
        return new CouchDBClient($this->getHttpClient(), $this->getBulkTestDatabase());
    }

    public function createMangoClientForBulkTest()
    {
        return new MangoClient($this->getHttpClient(), $this->getBulkTestDatabase());
    }
}
