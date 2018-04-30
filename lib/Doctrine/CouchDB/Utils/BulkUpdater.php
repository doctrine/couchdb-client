<?php

namespace Doctrine\CouchDB\Utils;

use Doctrine\CouchDB\HTTP\Client;

/**
 * Bulk updater class.
 *
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link        www.doctrine-project.com
 * @since       1.0
 *
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class BulkUpdater
{
    private $data = ['docs' => []];

    private $requestHeaders = [];

    private $httpClient;

    private $databaseName;

    public function __construct(Client $httpClient, $databaseName)
    {
        $this->httpClient = $httpClient;
        $this->databaseName = $databaseName;
    }

    public function updateDocument($data)
    {
        $this->data['docs'][] = $data;
    }

    public function updateDocuments(array $docs)
    {
        foreach ($docs as $doc) {
            $this->data['docs'][] = (is_array($doc) ? $doc : json_decode($doc, true));
        }
    }

    public function deleteDocument($id, $rev)
    {
        $this->data['docs'][] = ['_id' => $id, '_rev' => $rev, '_deleted' => true];
    }

    public function setNewEdits($newEdits)
    {
        $this->data['new_edits'] = (bool) $newEdits;
    }

    public function setFullCommitHeader($commit)
    {
        $this->requestHeaders['X-Couch-Full-Commit'] = (bool) $commit;
    }

    public function execute()
    {
        return $this->httpClient->request('POST', $this->getPath(), json_encode($this->data), false, $this->requestHeaders);
    }

    public function getPath()
    {
        return '/'.$this->databaseName.'/_bulk_docs';
    }
}
