<?php

namespace Doctrine\Tests\CouchDB;

use Doctrine\CouchDB\CouchDBException;

/**
 * @covers \Doctrine\CouchDB\CouchDBException
 */
class CouchDBExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider staticFactoryDataProvider
     */
    public function testStaticFactory($factoryMethod, $expectedMessage)
    {
        $exception = CouchDBException::$factoryMethod('a', 'b', 'c', 'd', 'w');
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function staticFactoryDataProvider()
    {
        return array(
            array('unknownDocumentNamespace', 'Unknown Document namespace alias \'a\'.'),
            array('unregisteredDesignDocument', 'No design document with name \'a\' was registered with the DocumentManager.'),
            array('invalidAttachment', 'Trying to save invalid attachment with filename c in document a with id b'),
            array('detachedDocumentFound', 'Found a detached or new document at property a::c of document with ID b, but the assocation is not marked as cascade persist.'),
            array('persistRemovedDocument', 'Trying to persist document that is scheduled for removal.'),
            array('luceneNotConfigured', 'CouchDB Lucene is not configured. You have to configure the handler name to enable support for Lucene Queries.'),
        );
    }
}
