<?php

namespace Doctrine\Tests\CouchDB\Functional;

use Doctrine\CouchDB\Mango\MangoQuery;

class MangoTest extends \Doctrine\Tests\CouchDB\CouchDBFunctionalTestCase
{
      public function setUp()
      {
          $client = $this->createCouchDBClient();
          $client->deleteDatabase($this->getTestDatabase());
          sleep(0.5);
          $client->createDatabase($this->getTestDatabase());
      }

      public function testFind()
      {
          $client = $this->createMangoClient();

          $string = file_get_contents(__DIR__.'/../../datasets/shows.json');
          $shows = json_decode($string, true);

          $updater = $client->createBulkUpdater();
          $updater->updateDocuments($shows);
          $response = $updater->execute();

          foreach ($response->body as $key=>$row) {
              $shows[$key] = ['_id'=>$row['id'], '_rev'=>$row['rev']] + $shows[$key];
          }

          //Everything
          $query = new MangoQuery();
          $response = $client->find($query->limit(999));

          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);

          $this->assertEquals($shows, $response->body['docs']);

          //Query by a field with no index
          $response = $client->find(new MangoQuery(['name'=>['$eq'=>'Under the Dome']]));

          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          //No index found warning
          $this->assertArrayHasKey('warning', $response->body);
          $this->assertEquals([$shows[0]], $response->body['docs']);


          //Nothing
          $response = $client->find(new MangoQuery(['_id'=>['$eq'=>null]]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);

          $this->assertEquals([], $response->body['docs']);

          //Selector Basics
          $response = $client->find(new MangoQuery(['name'=>'Person of Interest']));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals([$shows[1]], $response->body['docs']);

          //Selector with 2 fields
          $response = $client->find(new MangoQuery(['name'=>'Person of Interest', 'language'=>'English']));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals([$shows[1]], $response->body['docs']);

          //Condition Operators
          $response = $client->find(new MangoQuery(['runtime'=>['$gt'=>60]]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(2, count($response->body['docs']));

          //Subfields
          $response = $client->find(new MangoQuery(['rating'=>['average'=>8]]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(9, count($response->body['docs']));

          //Subfield with dot notation
          $response = $client->find(new MangoQuery(['rating.average'=>8]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(9, count($response->body['docs']));

        //Explicit $and
        $response = $client->find(new MangoQuery(
          ['$and'=> [
              [
                'name'=> [
                  '$eq'=> 'Under the Dome',
                ],
                'genres'=> [
                  '$in'=> ['Drama'],
                ],
              ],
            ],
          ]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(1, count($response->body['docs']));
          $this->assertEquals([$shows[0]], $response->body['docs']);

        //$or operator
        $response = $client->find(new MangoQuery(
          [
            'runtime'=> 60,
            '$or'    => [
              [
                'name'=> 'Under the Dome',
              ],
              [
                'name'=> 'Person of Interest',
              ],
            ],
          ]));

          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(2, count($response->body['docs']));
          $this->assertEquals([$shows[0], $shows[1]], $response->body['docs']);

          //repeated key
          $response = $client->find(new MangoQuery(
          ['$and'=> [
              ['rating.average'=>['$gte'=>9]],
              ['rating.average'=> ['$lte'=>10]],
            ],
          ]));

          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(17, count($response->body['docs']));

          //Limits and skips

          //Get first
          $response = $client->find((new MangoQuery())->limit(1));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(1, count($response->body['docs']));
          $this->assertEquals([$shows[0]], $response->body['docs']);

          //Get second
          $response = $client->find((new MangoQuery())->limit(1)->skip(1));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(1, count($response->body['docs']));
          $this->assertEquals([$shows[1]], $response->body['docs']);

          //Select fields
          $expected = [
            [
              'id'  => $shows[1]['id'],
              'name'=> $shows[1]['name'],
            ],
          ];

          $query = new MangoQuery();
          $query->select(['id', 'name'])->skip(1)->limit(1);

          $response = $client->find($query);
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertEquals(1, count($response->body['docs']));
          $this->assertEquals($expected, $response->body['docs']);
      }

      public function testMangoIndexAndSort()
      {
          //Fill database
          $client = $this->createMangoClient();
          $string = file_get_contents(__DIR__.'/../../datasets/shows.json');
          $shows = json_decode($string, true);
          $updater = $client->createBulkUpdater();
          $updater->updateDocuments($shows);
          $response = $updater->execute();

          //create index
          $fields = [['name'=>'desc']];
          $response = $client->createMangoIndex($fields, 'index-test', 'name-desc');

          $this->assertObjectHasAttribute('body', $response);
          $this->assertEquals('created', $response->body['result']);
          $this->assertEquals('_design/index-test', $response->body['id']);
          $this->assertEquals('name-desc', $response->body['name']);

          $response = $client->find(new MangoQuery(['name'=>['$eq'=>'Under the Dome']]));
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertArrayNotHasKey('warning', $response->body);

          //Test sort
          $query = new MangoQuery(['name'=>['$gt'=>null]]);
          $query->sort([['name'=>'desc']]);
          $response = $client->find($query);
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertArrayNotHasKey('warning', $response->body);

          $this->assertEquals('Z Nation', $response->body['docs'][0]['name']);

          $deleted = $client->deleteMangoIndex('index-test', 'name-desc');
          $this->assertTrue($deleted);

          //create subdocument index
          $fields = [['rating.average'=>'desc'], ['name'=>'desc']];
          $response = $client->createMangoIndex($fields, 'index-test', 'rating.average-desc');
          $query = new MangoQuery(['rating.average'=>['$gt'=>null]]);
          $query->sort($fields);

          $response = $client->find($query);
          $this->assertInstanceOf('\Doctrine\CouchDB\HTTP\Response', $response);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertArrayNotHasKey('warning', $response->body);
          $this->assertEquals('The Wire', $response->body['docs'][0]['name']);

          //Create another index in the same document
          $fields = [['type'=>'asc'], ['name'=>'asc']];
          $response = $client->createMangoIndex($fields, 'index-test', 'type-asc&name-asc');
          $this->assertObjectHasAttribute('body', $response);
          $this->assertEquals('created', $response->body['result']);
          $this->assertEquals('_design/index-test', $response->body['id']);
          $this->assertEquals('type-asc&name-asc', $response->body['name']);
          $query = new MangoQuery(['type'=>['$gt'=>null]]);
          $query->sort($fields);
          $response = $client->find($query);
          $this->assertEquals('American Dad!', $response->body['docs'][0]['name']);

          //Find for impacts
          $query = new MangoQuery(['rating.average'=>['$gt'=>null]]);
          $query->sort([['rating.average'=>'desc'], ['name'=>'desc']]);
          $response = $client->find($query);
          $this->assertObjectHasAttribute('body', $response);
          $this->assertArrayHasKey('docs', $response->body);
          $this->assertArrayNotHasKey('warning', $response->body);
          $this->assertEquals('The Wire', $response->body['docs'][0]['name']);
      }
}
