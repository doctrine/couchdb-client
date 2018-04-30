<?php

namespace Doctrine\CouchDB;

use Doctrine\CouchDB\Mango\MangoQuery;

class MangoClient extends CouchDBClient{
  /**
   * Create a mango query index and return the HTTP response.
   *
   * @param array  $fields - index fields
   * @param string $ddoc   - design document name
   * @param string $name   - view name
   */
  public function createMangoIndex($fields, $ddoc = null, $name = null)
  {
      $documentPath = '/'.$this->databaseName.'/_index';

      $params = ['index'=>['fields'=>$fields]];

      if ($ddoc) {
          $params['ddoc'] = $ddoc;
      }
      if ($name) {
          $params['name'] = $name;
      }

      return $this->httpClient->request('POST', $documentPath, json_encode($params));
  }

  /**
   * Delete a mango query index and return the HTTP response.
   *
   * @param string $ddoc - design document name
   * @param string $name - view name
   */
  public function deleteMangoIndex($ddoc, $name)
  {
      $documentPath = '/'.$this->databaseName.'/_index/_design/'.$ddoc.'/json/'.$name;
      $response = $this->httpClient->request('DELETE', $documentPath);

      return (isset($response->body['ok'])) ? true : false;
  }

  /**
   * Find documents using Mango Query.
   *
   * @param MangoQuery $query
   *
   * @return HTTP\Response
   */
  public function find(MangoQuery $query)
  {
      $documentPath = '/'.$this->databaseName.'/_find';
      return $this->httpClient->request('POST', $documentPath, json_encode($query));
  }
}
