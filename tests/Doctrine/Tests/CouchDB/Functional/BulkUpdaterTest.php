<?php

namespace Doctrine\Tests\CouchDB\Functional;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\Utils\BulkUpdater;

class BulkUpdaterTest extends \Doctrine\Tests\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var CouchDBClient
     */
    private $couchClient;

    /**
     * @var BulkUpdater
     */
    private $bulkUpdater;

    public function setUp()
    {
        $this->couchClient = $this->createCouchDBClientForBulkTest();
        $this->couchClient->createDatabase($this->getBulkTestDatabase());
        $this->bulkUpdater = $this->couchClient->createBulkUpdater();
    }

    public function testGetPath()
    {
        $this->assertEquals(
            '/'.$this->getBulkTestDatabase().'/_bulk_docs',
            $this->bulkUpdater->getpath()
        );
    }

    /**
     * @depends testGetPath
     */
    public function testExecute()
    {
        $response = $this->bulkUpdater->execute();
        $this->assertEquals(201, $response->status);
        $this->assertEquals([], $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testSetNewEdits()
    {
        $this->bulkUpdater->setNewEdits(false);
        $doc = ['_id' => 'test1', 'foo' => 'bar', '_rev' => '10-gsoc'];
        $this->bulkUpdater->updateDocument($doc);
        $response = $this->bulkUpdater->execute();
        $response = $this->couchClient->findDocument('test1');
        // _rev remains same.
        $this->assertEquals($doc, $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testUpdateDocument()
    {
        $docs['test1'] = ['_id' => 'test1', 'foo' => 'bar'];
        $docs['test2'] = ['_id' => 'test2', 'bar' => 'baz'];
        $this->bulkUpdater->updateDocument($docs['test1']);
        $this->bulkUpdater->updateDocument($docs['test2']);
        $response = $this->bulkUpdater->execute();

        // Insert the rev values.
        foreach ($response->body as $res) {
            $docs[$res['id']]['_rev'] = $res['rev'];
        }

        $response = $this->couchClient->findDocument('test1');
        $this->assertEquals($docs['test1'], $response->body);
        $response = $this->couchClient->findDocument('test2');
        $this->assertEquals($docs['test2'], $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testUpdateDocuments()
    {
        $docs[] = ['_id' => 'test1', 'foo' => 'bar'];
        $docs[] = '{"_id": "test2","baz": "foo"}';

        $this->bulkUpdater->updateDocuments($docs);
        $response = $this->bulkUpdater->execute();

        // Insert the rev values.
        foreach ($response->body as $res) {
            $id = $res['id'];
            if ($id == 'test1') {
                $docs[0]['_rev'] = $res['rev'];
            } elseif ($id == 'test2') {
                $docs[1] = substr($docs[1], 0, strlen($docs[1]) - 1).',"_rev": "'.$res['rev'].'"}';
            }
        }

        $response = $this->couchClient->findDocument('test1');
        $this->assertEquals($docs[0], $response->body);
        $response = $this->couchClient->findDocument('test2');
        $this->assertEquals(json_decode($docs[1], true), $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testDeleteDocument()
    {
        $doc = ['_id' => 'test1', 'foo' => 'bar'];
        $this->bulkUpdater->updateDocument($doc);
        $response = $this->bulkUpdater->execute();
        $rev = $response->body[0]['rev'];

        $bulkUpdater2 = $this->couchClient->createBulkUpdater();
        $bulkUpdater2->deleteDocument('test1', $rev);
        $response = $bulkUpdater2->execute();
        $response = $this->couchClient->findDocument('test1');
        $this->assertEquals(404, $response->status);
    }

    public function tearDown()
    {
        $this->couchClient->deleteDatabase($this->getBulkTestDatabase());
    }
}
