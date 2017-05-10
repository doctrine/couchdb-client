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

        // Tidy
        $this->couchClient->deleteDatabase($dbName2);
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
        $this->setExpectedException('Doctrine\CouchDB\HTTP\HTTPException', 'HTTP Error with status 412 occurred while requesting /'.$this->getTestDatabase().'. Error: file_exists The database could not be created, the file already exists.');
        $this->couchClient->createDatabase($this->getTestDatabase());
    }

    public function testGetDatabaseInfo()
    {
        $data = $this->couchClient->getDatabaseInfo($this->getTestDatabase());

        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('db_name', $data);
        $this->assertEquals($this->getTestDatabase(), $data['db_name']);

        $notExistedDb = 'not_existed_db';
        $this->setExpectedException('Doctrine\CouchDB\HTTP\HTTPException','HTTP Error with status 404 occurred while requesting /'.$notExistedDb.'. Error: not_found no_db_file');
        $this->couchClient->getDatabaseInfo($notExistedDb);
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
        $this->assertEquals(0, $result->getOffset());
        $this->assertEquals(0, $result->getTotalRows());
        $this->assertEquals(0, count($result));
    }

    public function testQueryWithKeys()
    {
        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        $client = $this->couchClient;
        $ids = array();
        for ($i = 0; $i < 10; $i++) {
            $data = array(
                'type' => 'Doctrine.Tests.Models.CMS.CmsUser',
                'username' => "user-$i",
            );
            list($id, $rev) = $client->putDocument($data, "query-with-key-$i");
            $ids[] = $id;
        }

        $designDoc = new FolderDesignDocument($designDocPath);

        $query = $client->createViewQuery('test-design-doc-query', 'username', $designDoc);
        $query->setKeys($ids);

        $this->assertInstanceOf('Doctrine\CouchDB\View\Query', $query);

        $result = $query->execute();

        $this->assertEquals(10, $result->getTotalRows());
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

    public function testFindDocument()
    {
        $client = $this->couchClient;

        // Recreate DB.
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());
        // Test fetching of document.
        list($id, $rev) = $client->postDocument(array('foo' => 'bar'));
        $response = $client->findDocument($id);
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
        $this->assertObjectHasAttribute('body', $response);
        $body = $response->body;
        $this->assertEquals(
            array('_id' => $id, '_rev' => $rev, 'foo' => 'bar'),
            $body
        );
    }

    /**
     * @depends testCreateBulkUpdater
     */
    public function testFindRevisions()
    {
        $client = $this->couchClient;

        // Recreate DB.
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        // The _id of all the documents is same. So we will get multiple leaf
        // revisions.
        $id = 'multiple_revisions';
        $docs = array(
            array('_id' => $id, 'foo' => 'bar1', '_rev' => '1-abc'),
            array('_id' => $id, 'foo' => 'bar2', '_rev' => '1-bcd'),
            array('_id' => $id, 'foo' => 'bar3', '_rev' => '1-cde')
        );

        // Add the documents to the test db using Bulk API.
        $updater = $this->couchClient->createBulkUpdater();
        $updater->updateDocuments($docs);
        // Set newedits to false to use the supplied _rev instead of assigning
        // new ones.
        $updater->setNewEdits(false);
        $response = $updater->execute();

        // Test fetching of documents of all revisions. By default all
        // revisions are fetched.
        $response = $client->findRevisions($id);
        $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
        $this->assertObjectHasAttribute('body', $response);
        $expected = array(
            array('ok' => $docs[2]),
            array('ok' => $docs[1]),
            array('ok' => $docs[0])
        );
        $this->assertEquals($expected, $response->body);
        // Test fetching of specific revisions.
        $response = $client->findRevisions(
            $id,
            array('1-abc', '1-cde', '100-ghfgf', '200-blah')
        );
        $body = $response->body;
        $this->assertEquals(4, count($body));
        // Doc with _rev = 1-cde.
        $this->assertEquals($docs[2], $body[0]['ok']);
        // Doc with _rev = 1-abc.
        $this->assertEquals($docs[0], $body[1]['ok']);
        // Missing revisions.
        $this->assertEquals(array('missing' => '100-ghfgf'), $body[2]);
        $this->assertEquals(array('missing' => '200-blah'), $body[3]);
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

        // Everything
        $response = $client->allDocs();
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        // No Limit
        $response = $client->allDocs(0);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => $expectedRows), $response->body);

        // Limit
        $response = $client->allDocs(1);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0])), $response->body);

        // Limit
        $response = $client->allDocs(2);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0], $expectedRows[1])), $response->body);

        // Start Key
        $response = $client->allDocs(0, $ids[1]);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 1, 'rows' => array($expectedRows[1], $expectedRows[2])), $response->body);

        // Start Key with Limit
        $response = $client->allDocs(1, $ids[2]);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 2, 'rows' => array($expectedRows[2])), $response->body);



        // End key
        $response = $client->allDocs(0, null, $ids[0]);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[0])), $response->body);

        // Skip
        $response = $client->allDocs(0, null, null, 1);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 1, 'rows' => array($expectedRows[1], $expectedRows[2])), $response->body);



        // Skip, Descending
        $response = $client->allDocs(null, null, null, 1, true);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 1, 'rows' => array($expectedRows[1], $expectedRows[0])), $response->body);

        // Limit, Descending
        $response = $client->allDocs(1, null, null, null, true);
        $this->assertEquals(array('total_rows' => 3, 'offset' => 0, 'rows' => array($expectedRows[2])), $response->body);


        // tidy
        $client->deleteDocument($expectedRows[0]['id'], $expectedRows[0]['value']['rev']);
        $client->deleteDocument($expectedRows[1]['id'], $expectedRows[1]['value']['rev']);
        $client->deleteDocument($expectedRows[2]['id'], $expectedRows[2]['value']['rev']);
    }

    public function testGetActiveTasks()
    {
        $client = $this->couchClient;
        $active_tasks = $client->getActiveTasks();
        $this->assertEquals(array(), $active_tasks);

        $sourceDatabase = $this->getTestDatabase();
        $targetDatabase1 = $this->getTestDatabase() . 'target1';
        $targetDatabase2 = $this->getTestDatabase() . 'target2';
        $this->couchClient->deleteDatabase($targetDatabase1);
        $this->couchClient->deleteDatabase($targetDatabase2);
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

        // Tidy
        $this->couchClient->deleteDatabase($targetDatabase1);
        $this->couchClient->deleteDatabase($targetDatabase2);
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

    /**
     * @depends testCreateBulkUpdater
     */

    public function testTransferChangedDocuments()
    {
        $client = $this->couchClient;

        // Recreate DB
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        // Doc id.
        $id = 'multiple_attachments';
        // Document with attachments.
        $docWithAttachment = array (
            '_id' => $id,
            '_rev' => '1-abc',
            '_attachments' =>
                array (
                    'foo.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                    'bar.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                ),
        );
        // Doc without any attachment. The id of both the docs is same.
        // So we will get two leaf revisions.
        $doc = array('_id' => $id, 'foo' => 'bar', '_rev' => '1-bcd');

        // Add the documents to the test db using Bulk API.
        $updater = $this->couchClient->createBulkUpdater();
        $updater->updateDocument($docWithAttachment);
        $updater->updateDocument($doc);
        // Set newedits to false to use the supplied _rev instead of assigning
        // new ones.
        $updater->setNewEdits(false);
        $response = $updater->execute();

        // Create the copy database and a copyClient to interact with it.
        $copyDb = $this->getTestDatabase() . '_copy';
        $client->createDatabase($copyDb);
        $copyClient = new CouchDBClient($client->getHttpClient(), $copyDb);

        // Missing revisions in the $copyDb.
        $missingRevs = array('1-abc', '1-bcd');
        // Transfer the missing revisions from the source to the target.
        list($docStack, $responses) = $client->transferChangedDocuments($id, $missingRevs, $copyClient);
        // $docStack should contain the doc that didn't have the attachment.
        $this->assertEquals(1, count($docStack));
        $this->assertEquals($doc, json_decode($docStack[0], true));

        // The doc with attachment should have been copied to the copyDb.
        $this->assertEquals(1, count($responses));
        $this->assertArrayHasKey('ok', $responses[0]);
        $this->assertEquals(true, $responses[0]['ok']);
        // Clean up.
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());
        $client->deleteDatabase($copyDb);
    }

    /**
     * @depends testGetChanges
     */

    public function testGetChangesAsStream()
    {
        $client = $this->couchClient;
        // Recreate DB
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        // Stream of changes feed.
        $stream = $client->getChangesAsStream();
        list($id, $rev) = $client->postDocument(array("_id" => "stream1", "foo" => "bar"));
        // Get the change feed data for stream1.
        while(trim($line = fgets($stream)) == '');
        $this->assertEquals("stream1", json_decode($line, true)["id"]);
        list($id, $rev) = $client->postDocument(array("_id" => "stream2", "foo" => "bar"));
        // Get the change feed data for stream2.
        while(trim($line = fgets($stream)) == '');
        $this->assertEquals("stream2", json_decode($line, true)["id"]);
        fclose($stream);
    }

    public function testEnsureFullCommit()
    {
        $client = $this->couchClient;
        $body = $client->ensureFullCommit();
        $this->assertArrayHasKey('instance_start_time', $body);
        $this->assertArrayHasKey('ok', $body);
        $this->assertEquals(true, $body['ok']);
    }

    public function test404WhenQueryAndNoDesignDocument()
    {
        $client = $this->couchClient;
        $query = $client->createViewQuery('foo', 'not-found');

        $this->setExpectedException(
            'Doctrine\CouchDB\HTTP\HTTPException',
            'HTTP Error with status 404 occurred while requesting /doctrine_test_database/_design/foo/_view/not-found?. Error: not_found missing'
        );

        $query->execute();
    }

    public function testEncodeQueryParamsCorrectly()
    {
        $designDocPath = __DIR__ . "/../../Models/CMS/_files";

        $client = $this->couchClient;
        $designDoc = new FolderDesignDocument($designDocPath);

        $query = $client->createViewQuery('test-design-doc-query', 'username', $designDoc);
        $query->setStartKey(array('foo', 'bar'));
        $query->setEndKey(array('bar', 'baz'));
        $query->setStale(true);
        $query->setDescending(true);

        $result = $query->execute();

        $this->assertEquals(0, $result->getTotalRows());
    }
}
