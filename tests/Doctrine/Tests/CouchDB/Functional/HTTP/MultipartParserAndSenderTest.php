<?php

namespace Doctrine\Tests\CouchDB\Functional\HTTP;


use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\ErrorResponse;
use Doctrine\CouchDB\HTTP\MultipartParserAndSender;
use Doctrine\CouchDB\HTTP\StreamClient;

class MultipartParserAndSenderTest extends \Doctrine\Tests\CouchDB\CouchDBFunctionalTestCase
{
    protected $parserAndSender;
    protected $sourceMethod;
    protected $sourcePath;
    protected $sourceParams;
    protected $targetPath;
    protected $sourceHeaders;
    protected $docId;
    protected $streamClientMock;

    public function setUp()
    {
        // Use mocked StreamClient to generate desired source data stream for
        // testing.
        $this->streamClientMock = $this->getMockBuilder(
            'Doctrine\CouchDB\HTTP\StreamClient'
        )->disableOriginalConstructor()->getMock();

        $this->parserAndSender = new MultipartParserAndSender(
            $this->getHttpClient(),
            $this->getHttpClient()
        );
        // Set the protected $sourceClient of parserAndSender to the
        // streamClientMock.
        $reflector = new \ReflectionProperty(
            'Doctrine\CouchDB\HTTP\MultipartParserAndSender',
            'sourceClient'
        );
        $reflector->setAccessible(true);
        $reflector->setValue($this->parserAndSender, $this->streamClientMock);

        // Params for the request.
        $this->sourceParams = array('revs' => true, 'latest' => true);
        $this->sourceMethod = 'GET';
        $this->docId = 'multipartTestDoc';
        $this->sourcePath = '/' . $this->getTestDatabase() . '/' . $this->docId;
        $this->targetPath = '/' .$this->getTestDatabase() . '_multipart_copy'
            . '/' . $this->docId . '?new_edits=false';
        $this->sourceHeaders = array('Accept' => 'multipart/mixed');


    }

    public function testRequestThrowsHTTPExceptionOnEmptyStatus()
    {
        $this->setExpectedException(
            '\Doctrine\CouchDB\HTTP\HTTPException',
            sprintf(
                "Could read from server at %s:%d: '%d: %s'",
                '127.0.0.1',
                '5984',
                0,
                'Received an empty response or not status code'
            )

        );
        // Return header without status code.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array());

        $this->streamClientMock->expects($this->exactly(2))
            ->method('getOptions')
            ->will($this->onConsecutiveCalls(
                array('ip' => '127.0.0.1'),
                array('port' => '5984')
            ));

        $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders
        );
    }

    public function testRequestReturnsErrorResponseOnWrongStatusCode()
    {
        // Return header without status code > 400.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array('status' => 404));

        $string = 'This is the sample body of the response from the source.\n
         It has two lines.';
        $stream = fopen('data://text/plain,' . $string,'r');
        $this->streamClientMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($stream);

        $response = $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders
        );

        $this->AssertEquals(
            new ErrorResponse(
                '404',
                array('status' => 404),
                $string
            ),
            $response
        );

    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage This value is not supported.
     */
    public function testRequestThrowsExceptionOnUnsupportedContentType()
    {
        // Return header with status code as 200.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array('status' => 200));
        $string = <<<EOT
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: HTML
EOT;
        $stream = fopen('data://text/plain,' . $string,'r');
        $this->streamClientMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($stream);

        $response = $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders
        );
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Unknown parameter with Content-Type.
     */
    public function testRequestThrowsExceptionOnUnknownParamWithContentType()
    {
        // Return header with status code as 200.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array('status' => 200));
        $string = <<<EOT
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json; unknownBlahBlah="true"
EOT;
        $stream = fopen('data://text/plain,' . $string,'r');
        $this->streamClientMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($stream);

        $response = $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders
        );
    }

    public function testRequestSuccessWithoutAttachment()
    {
        // Return header with status code as 200.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array('status' => 200));
        $docs = array(
            '{"_id": "' .$this->docId. '","_rev": "1-abc","foo":"bar"}',
            '{"_id": "' .$this->docId. '","_rev": "1-abcd","foo":"baz"}',
            '{"_id": "' .$this->docId. '","_rev": "1-abcde","foo":"baz"}',
        );
        $string = <<<EOT
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json

$docs[0]
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json

$docs[1]
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json; error="true"

{"missing":"3-6bcedf1"}
--7b1596fc4940bc1be725ad67f11ec1c4--
Content-Type: application/json

$docs[2]
--7b1596fc4940bc1be725ad67f11ec1c4
EOT;
        $stream = fopen('data://text/plain,' . $string,'r');
        $this->streamClientMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($stream);

        $response = $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders
        );
        // The returned response should have the JSON docs. The missing
        // revision at the source will be skipped.
        $this->AssertEquals(2, count($response));
        $this->AssertEquals(3, count($response[0]));
        $this->AssertEquals($docs[0], $response[0][0]);
        $this->AssertEquals($docs[1], $response[0][1]);
        $this->AssertEquals($docs[2], $response[0][2]);
    }

    public function testRequestSuccessWithAttachments()
    {
        $client = new CouchDBClient(
            $this->getHttpClient(),
            $this->getTestDatabase()
        );
        // Recreate DB
        $client->deleteDatabase($this->getTestDatabase());
        $client->createDatabase($this->getTestDatabase());

        // Doc id.
        $id = $this->docId;
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
        $updater = $client->createBulkUpdater();
        $updater->updateDocument($docWithAttachment);
        $updater->updateDocument($doc);
        // Set newedits to false to use the supplied _rev instead of assigning
        // new ones.
        $updater->setNewEdits(false);
        $response = $updater->execute();

        // Create the copy database and a copyClient to interact with it.
        $copyDb = $this->getTestDatabase() . '_multipart_copy';
        $client->createDatabase($copyDb);
        $copyClient = new CouchDBClient($client->getHttpClient(), $copyDb);

        // Missing revisions in the $copyDb.
        $missingRevs = array('1-abc', '1-bcd');
        $this->sourceParams['open_revs'] = json_encode($missingRevs);
        $query = http_build_query($this->sourceParams);
        $this->sourcePath .= '?' . $query;

        // Get the multipart data stream from real CouchDB instance.
        $stream = (new StreamClient())->getConnection(
            $this->sourceMethod,
            $this->sourcePath,
            null,
            $this->sourceHeaders
        );

        // Set the return values for the mocked StreamClient.
        $this->streamClientMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($stream);
        // Return header with status code as 200.
        $this->streamClientMock->expects($this->once())
            ->method('getStreamHeaders')
            ->willReturn(array('status' => 200));

        // Transfer the missing revisions from the source to the target.
        list($docStack, $responses) = $this->parserAndSender->request(
            $this->sourceMethod,
            $this->sourcePath,
            $this->targetPath,
            null,
            $this->sourceHeaders

        );
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
     * Test multipart request with body size in the request body.
     */
    public function testMultipartRequestWithSize()
    {
        $this->streamClientMock->expects($this->once())
          ->method('getStreamHeaders')
          ->willReturn(array('status' => 200));
        $docs = array(
          '{"_id": "' . $this->docId . '","_rev": "1-abc","foo":"bar"}',
          '{"_id": "' . $this->docId . '","_rev": "1-abcd","foo":"baz"}',
          '{"_id": "' . $this->docId . '","_rev": "1-abcde","foo":"baz"}',
        );
        $string = <<<EOT
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json

$docs[0]
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json

$docs[1]
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json

$docs[2]
--7b1596fc4940bc1be725ad67f11ec1c4
EOT;
        $size = strlen($string);
        // Add the size.
        $string = <<<EOT
$size
$string
EOT;

        $stream = fopen('data://text/plain,' . $string,'r');
        $this->streamClientMock->expects($this->once())
          ->method('getConnection')
          ->willReturn($stream);

        $response = $this->parserAndSender->request(
          $this->sourceMethod,
          $this->sourcePath,
          $this->targetPath,
          null,
          $this->sourceHeaders
        );
        // The returned response should have the JSON docs.
        $this->AssertEquals(2, count($response));
        $this->AssertEquals(3, count($response[0]));
        $this->AssertEquals($docs[0], $response[0][0]);
        $this->AssertEquals($docs[1], $response[0][1]);
        $this->AssertEquals($docs[2], $response[0][2]);
    }

}
