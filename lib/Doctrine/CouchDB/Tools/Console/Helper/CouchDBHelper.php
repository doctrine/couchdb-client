<?php

namespace Doctrine\CouchDB\Tools\Console\Helper;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\ODM\CouchDB\DocumentManager;
use Symfony\Component\Console\Helper\Helper;

/**
 * Doctrine CLI Connection Helper.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link    www.doctrine-project.org
 * @since   2.0
 *
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class CouchDBHelper extends Helper
{
    protected $couchDBClient;

    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * Constructor.
     *
     * @param CouchDBClient   $couchDBClient
     * @param DocumentManager $dm
     */
    public function __construct(CouchDBClient $couchDBClient = null, DocumentManager $dm = null)
    {
        if (!$couchDBClient && $dm) {
            $couchDBClient = $dm->getCouchDBClient();
        }

        $this->couchDBClient = $couchDBClient;
        $this->dm = $dm;
    }

    /**
     * Retrieves Doctrine ODM CouchDB Manager.
     *
     * @return \Doctrine\ODM\CouchDB\DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * @return CouchDBClient
     */
    public function getCouchDBClient()
    {
        return $this->couchDBClient;
    }

    /**
     * @see Helper
     */
    public function getName()
    {
        return 'couchdb';
    }
}
