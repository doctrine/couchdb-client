<?php

namespace Doctrine\Tests\CouchDB;

class TestUtil
{
    public static function getTestDatabase()
    {
        if (isset($GLOBALS['DOCTRINE_COUCHDB_DATABASE'])) {
            return $GLOBALS['DOCTRINE_COUCHDB_DATABASE'];
        }

        return 'doctrine_test_database';
    }

    public static function getBulkTestDatabase()
    {
        if (isset($GLOBALS['DOCTRINE_COUCHDB_BULK_DATABASE'])) {
            return $GLOBALS['DOCTRINE_COUCHDB_BULK_DATABASE'];
        }

        return 'doctrine_test_database_bulk';
    }
}
