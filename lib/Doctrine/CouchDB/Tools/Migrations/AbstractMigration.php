<?php
namespace Doctrine\CouchDB\Tools\Migrations;

use Doctrine\CouchDB\CouchDBClient;

/**
 * Migration base class.
 */
abstract class AbstractMigration
{
    private $client;

    public function __construct(CouchDBClient $client)
    {
        $this->client = $client;
    }

    /**
     * Execute migration by iterating over all documents in batches of 100.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function execute()
    {
        $response = $this->client->allDocs(100);
        $lastKey = null;

        do {
            if ($response->status !== 200) {
                throw new \RuntimeException('Error while migrating at offset '.$offset);
            }

            $bulkUpdater = $this->client->createBulkUpdater();
            foreach ($response->body['rows'] as $row) {
                $doc = $this->migrate($row['doc']);
                if ($doc) {
                    $bulkUpdater->updateDocument($doc);
                }
                $lastKey = $row['key'];
            }

            $bulkUpdater->execute();
            $response = $this->client->allDocs(100, $lastKey);
        } while (count($response->body['rows']) > 1);
    }

    /**
     * Return an array of to migrate to document data or null if this document should not be migrated.
     *
     * @param array $docData
     *
     * @return array|bool|null $docData
     */
    abstract protected function migrate(array $docData);
}
