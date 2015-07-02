<?php

namespace Doctrine\Tests\CouchDB\Functional;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\View\FolderDesignDocument;

class CouchDBClientTest extends \Doctrine\Tests\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var CouchDBClient
     */
    private $couchClient;

    public function setUp()
    {
        $this->couchClient = $this->createCouchDBClient();
    }

    public function testGetUuids()
    {
        $uuids = $this->couchClient->getUuids();
        $this->assertEquals(1, count($uuids));
        $this->assertEquals(32, strlen($uuids[0]));

        $uuids = $this->couchClient->getUuids(10);
        $this->assertEquals(10, count($uuids));
    }

    public function testGetVersion()
    {
        $version = $this->couchClient->getVersion();
        $this->assertEquals(3, count(explode(".", $version)));
    }

    public function testGetAllDatabases()
    {
        $dbs = $this->couchClient->getAllDatabases();
        $this->assertContains($this->getTestDatabase(), $dbs);
    }

    public function testDeleteDatabase()
    {
        $this->couchClient->deleteDatabase($this->getTestDatabase());

        $dbs = $this->couchClient->getAllDatabases();
        $this->assertNotContains($this->getTestDatabase(), $dbs);
    }

    /**
     * @depends testDeleteDatabase
     */
    public function testCreateDatabase()
    {
        $dbName2 = $this->getTestDatabase() . "2";
        $this->couchClient->deleteDatabase($dbName2);
        $this->couchClient->createDatabase($dbName2);

        $dbs = $this->couchClient->getAllDatabases();
        $this->assertContains($dbName2, $dbs);
    }

    public function testDropMultipleTimesSkips()
    {
        $this->couchClient->deleteDatabase($this->getTestDatabase());
        $this->couchClient->deleteDatabase($this->getTestDatabase());
    }

    /**
     * @depends testCreateDatabase
     */
    public function testCreateDuplicateDatabaseThrowsException()
    {
        $this->couchClient->createDatabase($this->getTestDatabase());
        $this->setExpectedException('Doctrine\CouchDB\HTTP\HTTPException', 'HTTP Error with status 412 occoured while requesting /'.$this->getTestDatabase().'. Error: file_exists The database could not be created, the file already exists.');
        $this->couchClient->createDatabase($this->getTestDatabase());
    }

    public function testGetDatabaseInfo()
    {
        $data = $this->couchClient->getDatabaseInfo($this->getTestDatabase());

        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('db_name', $data);
        $this->assertEquals($this->getTestDatabase(), $data['db_name']);
    }

    public function testCreateBulkUpdater()
    {
        $updater = $this->couchClient->createBulkUpdater();
        $this->assertInstanceOf('Doctrine\CouchDB\Utils\BulkUpdater', $updater);
    }

    /**
     * @depends testCreateBulkUpdater
     */
    public function testGetChanges()
    {
        $updater = $this->couchClient->createBulkUpdater();
        $updater->updateDocument(array("_id" => "test1", "foo" => "bar"));
        $updater->updateDocument(array("_id" => "test2", "bar" => "baz"));
        $updater->execute();

        $changes = $this->couchClient->getChanges();
        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(2, count($changes['results']));
        $this->assertEquals(2, $changes['last_seq']);

        // Check the doc_ids parameter.
        $changes = $this->couchClient->getChanges(array(
            'doc_ids' => array('test1')
        ));
        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(1, count($changes['results']));
        $this->assertArrayHasKey('id', $changes['results'][0]);
        $this->assertEquals('test1', $changes['results'][0]['id']);
        $this->assertEquals(2, $changes['last_seq']);

        $changes = $this->couchClient->getChanges(array(
            'doc_ids' => null
        ));
        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(2, count($changes['results']));
        $this->assertEquals(2, $changes['last_seq']);

        // Check the limit parameter.
        $changes = $this->couchClient->getChanges(array(
            'limit' => 1,
        ));
        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(1, count($changes['results']));
        $this->assertEquals(1, $changes['last_seq']);

        // Checks the descending parameter.
        $changes = $this->couchClient->getChanges(array(
            'descending' => true,
        ));

        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(2, count($changes['results']));
        $this->assertEquals(1, $changes['last_seq']);

        // Checks the since parameter.
        $changes = $this->couchClient->getChanges(array(
            'since' => 1,
        ));

        $this->assertArrayHasKey('results', $changes);
        $this->assertEquals(1, count($changes['results']));
        $this->assertEquals(2, $changes['last_seq']);

        // Checks the filter parameter.
        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        // Create a filter, that filters the only doc with {"_id":"test1"}
        $client = $this->couchClient;
        $client->createDesignDocument('test-filter', new FolderDesignDocument($designDocPath));

        $changes = $this->couchClient->getChanges(array(
            'filter' => 'test-filter/my_filter'
        ));
        $this->assertEquals(1, count($changes['results']));
        $this->assertEquals(3, $changes['last_seq']);
    }

    public function testPostDocument()
    {
        $client = $this->couchClient;
        list($id, $rev) = $client->postDocument(array("foo" => "bar"));

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "bar"), $response->body);
    }

    public function testPutDocument()
    {
        $id = "foo-bar-baz";
        $client = $this->couchClient;
        list($id, $rev) = $client->putDocument(array("foo" => "bar"), $id);

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "bar"), $response->body);

        list($id, $rev) = $client->putDocument(array("foo" => "baz"), $id, $rev);

        $response = $client->findDocument($id);
        $this->assertEquals(array("_id" => $id, "_rev" => $rev, "foo" => "baz"), $response->body);
    }

    public function testDeleteDocument()
    {
        $client = $this->couchClient;
        list($id, $rev) = $client->postDocument(array("foo" => "bar"));

        $client->deleteDocument($id, $rev);

        $response = $client->findDocument($id);
        $this->assertEquals(404, $response->status);
    }

    public function testCreateDesignDocument()
    {
        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        $client = $this->couchClient;
        $client->createDesignDocument('test-design-doc-create', new FolderDesignDocument($designDocPath));

        $response = $client->findDocument('_design/test-design-doc-create');
        $this->assertEquals(200, $response->status);
    }

    public function testCreateViewQuery()
    {
        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        $client = $this->couchClient;
        $designDoc = new FolderDesignDocument($designDocPath);

        $query = $client->createViewQuery('test-design-doc-query', 'username', $designDoc);
        $this->assertInstanceOf('Doctrine\CouchDB\View\Query', $query);

        $result = $query->execute();
        $this->assertInstanceOf('Doctrine\CouchDB\View\Result', $result);
    }

    public function testCompactDatabase()
    {
        $client = $this->couchClient;
        $client->compactDatabase();
    }

    public function testCompactView()
    {
        $client = $this->couchClient;

        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        $client = $this->couchClient;
        $designDoc = new FolderDesignDocument($designDocPath);

        $query = $client->createViewQuery('test-design-doc-query', 'username', $designDoc);
        $result = $query->execute();

        $client->compactView('test-design-doc-query');
    }

    public function testFindDocuments()
    {
        $client = $this->couchClient;

        // Recreate DB
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        $ids = array();
        $expectedRows = array();
        foreach (range(1, 3) as $i) {
            list($id, $rev) = $client->postDocument(array('foo' => 'bar' . $i));
            $ids[] = $id;
            // This structure might be dependent from couchdb version. Tested against v1.6.1
            $expectedRows[] = array(
                'id' => $id,
                'value' => array(
                    'rev' => $rev,
                ),
                'doc' => array(
                    '_id' => $id,
                    '_rev' => $rev,
                    'foo' => 'bar' . $i,
                ),
                'key' => $id,
            );
        }

        $response = $client->findDocuments($ids);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        $response = $client->findDocuments($ids, 0);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        $response = $client->findDocuments($ids, 1);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0])), $response->body);

        $response = $client->findDocuments($ids, 0, 2);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[2])), $response->body);

        $response = $client->findDocuments($ids, 1, 1);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[1])), $response->body);
    }

    public function testAllDocs()
    {
        $client = $this->couchClient;

        // Recreate DB
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        $ids = array();
        $expectedRows = array();
        foreach (range(1, 3) as $i) {
            list($id, $rev) = $client->postDocument(array('foo' => 'bar' . $i));
            $ids[] = $id;
            // This structure might be dependent from couchdb version. Tested against v1.6.1
            $expectedRows[] = array(
                'id' => $id,
                'value' => array(
                    'rev' => $rev,
                ),
                'doc' => array(
                    '_id' => $id,
                    '_rev' => $rev,
                    'foo' => 'bar' . $i,
                ),
                'key' => $id,
            );
        }

        $response = $client->allDocs();
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        $response = $client->allDocs(0);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        $response = $client->allDocs(1);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0])), $response->body);

        $response = $client->allDocs(2);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0], $expectedRows[1])), $response->body);

        $response = $client->allDocs(0, $ids[1]);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 1, 'rows' => array($expectedRows[1], $expectedRows[2])), $response->body);

        $response = $client->allDocs(1, $ids[2]);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 2, 'rows' => array($expectedRows[2])), $response->body);
    }

    public function testGetActiveTasks()
    {
        $client = $this->couchClient;
        $active_tasks = $client->getActiveTasks();
        $this->assertEquals(array(), $active_tasks);

        $sourceDatabase = $this->getTestDatabase();
        $targetDatabase1 = $this->getTestDatabase() . 'target1';
        $targetDatabase2 = $this->getTestDatabase() . 'target2';
        $this->couchClient->createDatabase($targetDatabase1);
        $this->couchClient->createDatabase($targetDatabase2);

        $client->replicate($sourceDatabase, $targetDatabase1, null, true);
        $active_tasks = $client->getActiveTasks();
        $this->assertTrue(count($active_tasks) == 1);

        $client->replicate($sourceDatabase, $targetDatabase2, null, true);
        $active_tasks = $client->getActiveTasks();
        $this->assertTrue(count($active_tasks) == 2);

        $client->replicate($sourceDatabase, $targetDatabase1, true, true);
        $client->replicate($sourceDatabase, $targetDatabase2, true, true);
        $active_tasks = $client->getActiveTasks();
        $this->assertEquals(array(), $active_tasks);
    }

    public function testGetRevisionDifference()
    {
        $client = $this->couchClient;
        $mapping = array (
            'baz' =>
                array (
                    0 => '2-7051cbe5c8faecd085a3fa619e6e6337',
                ),
            'foo' =>
                array (
                    0 => '3-6a540f3d701ac518d3b9733d673c5484',
                ),
            'bar' =>
                array (
                    0 => '1-d4e501ab47de6b2000fc8a02f84a0c77',
                    1 => '1-967a00dff5e02add41819138abb3284d',
                ),
        );
        $revisionDifference = array (
            'baz' =>
                array (
                    'missing' =>
                        array (
                            0 => '2-7051cbe5c8faecd085a3fa619e6e6337',
                        ),
                ),
            'foo' =>
                array (
                    'missing' =>
                        array (
                            0 => '3-6a540f3d701ac518d3b9733d673c5484',
                        ),
                ),
            'bar' =>
                array (
                    'missing' =>
                        array (
                            0 => '1-d4e501ab47de6b2000fc8a02f84a0c77',
                            1 => '1-967a00dff5e02add41819138abb3284d',
                        ),
                ),
        );

        list($id, $rev) = $client->putDocument(array("name" => "test"), 'foo');
        $mapping['foo'][] = $rev;
        $revDiff = $client->getRevisionDifference($mapping);
        if (isset($revDiff['foo']['possible_ancestors'])) {
            $revisionDifference['foo']['possible_ancestors'] = $revDiff['foo']['possible_ancestors'];
        }
        $this->assertEquals($revisionDifference, $revDiff);
    }
}
