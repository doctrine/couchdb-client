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
            '/' . $this->getBulkTestDatabase() . '/_bulk_docs',
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
        $this->assertEquals(array(), $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testSetAllOrNothing()
    {
        $docs[] = array("_id" => "test1", "foo" => "bar");
        $docs[] = array("_id" => "test2", "bar" => "baz");
        $this->bulkUpdater->updateDocuments($docs);
        $response = $this->bulkUpdater->execute();
        $revTest1 = $response->body[0]["rev"];

        // Test with all_or_nothing=false.
        // Try to update one doc with wrong or no _rev say test2. Only test1 should be updated.
        $bulkUpdater2 = $this->couchClient->createBulkUpdater();
        $bulkUpdater2->setAllOrNothing(false);
        $docs2 = $docs;
        $docs2[0]["should_be_updated"] = "true";
        $docs2[0]["_rev"] = $revTest1;
        $docs2[1]["should_be_updated"] = "false";
        $bulkUpdater2->updateDocuments($docs2);
        $response = $bulkUpdater2->execute();
        $this->assertEquals(2, count($response->body));
        $this->assertEquals(true, isset($response->body[0]["ok"]));
        $this->assertEquals(false, isset($response->body[1]["ok"]));

        // Test with all_or_nothing=true.
        // Try to update one doc with wrong or no _rev say test2. Still both doc should get updated in case if there is
        // any update at all.
        $bulkUpdater3 = $this->couchClient->createBulkUpdater();
        $bulkUpdater3->setAllOrNothing(true);
        $docs3 = $docs;
        $docs3[0]["should_be_updated"] = "true";
        $docs3[0]["_rev"] = $revTest1;
        $docs3[1]["should_be_updated"] = "true";
        $bulkUpdater3->updateDocuments($docs3);
        $response = $bulkUpdater3->execute();
        $this->assertEquals(2, count($response->body));
        $this->assertEquals(true, isset($response->body[0]["ok"]));
        $this->assertEquals(true, isset($response->body[1]["ok"]));
    }

    /**
     * @depends testExecute
     */
    public function testSetNewEdits()
    {
        $this->bulkUpdater->setNewEdits(false);
        $doc = array("_id" => "test1", "foo" => "bar", "_rev" => "10-gsoc");
        $this->bulkUpdater->updateDocument($doc);
        $response = $this->bulkUpdater->execute();
        $response = $this->couchClient->findDocument("test1");
        // _rev remains same.
        $this->assertEquals($doc, $response->body);

    }

    /**
     * @depends testExecute
     */
    public function testUpdateDocument()
    {
        $docs["test1"] = array("_id" => "test1", "foo" => "bar");
        $docs["test2"] = array("_id" => "test2", "bar" => "baz");
        $this->bulkUpdater->updateDocument($docs["test1"]);
        $this->bulkUpdater->updateDocument($docs["test2"]);
        $response = $this->bulkUpdater->execute();

        // Insert the rev values.
        foreach ($response->body as $res) {
            $docs[$res['id']]['_rev'] = $res['rev'];
        }

        $response = $this->couchClient->findDocument("test1");
        $this->assertEquals($docs['test1'], $response->body);
        $response = $this->couchClient->findDocument("test2");
        $this->assertEquals($docs['test2'], $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testUpdateDocuments()
    {
        $docs[] = array("_id" => "test1", "foo" => "bar");
        $docs[] = '{"_id": "test2","baz": "foo"}';

        $this->bulkUpdater->updateDocuments($docs);
        $response = $this->bulkUpdater->execute();

        // Insert the rev values.
        foreach ($response->body as $res) {
            $id = $res['id'];
            if ($id == 'test1') {
                $docs[0]['_rev'] = $res['rev'];
            } elseif ($id == 'test2') {
                $docs[1] = substr($docs[1], 0, strlen($docs[1])-1) . ',"_rev": "'. $res['rev'] . '"}';
            }
        }

        $response = $this->couchClient->findDocument("test1");
        $this->assertEquals($docs[0], $response->body);
        $response = $this->couchClient->findDocument("test2");
        $this->assertEquals(json_decode($docs[1], true), $response->body);
    }

    /**
     * @depends testExecute
     */
    public function testDeleteDocument()
    {
        $doc = array("_id" => "test1", "foo" => "bar");
        $this->bulkUpdater->updateDocument($doc);
        $response = $this->bulkUpdater->execute();
        $rev = $response->body[0]["rev"];

        $bulkUpdater2 = $this->couchClient->createBulkUpdater();
        $bulkUpdater2->deleteDocument("test1", $rev);
        $response = $bulkUpdater2->execute();
        $response = $this->couchClient->findDocument("test1");
        $this->assertEquals(404, $response->status);

    }

    public function tearDown()
    {
        $this->couchClient->deleteDatabase($this->getBulkTestDatabase());
    }

}