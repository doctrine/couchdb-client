<?php

namespace Doctrine\Tests\CouchDB\Functional;

use Doctrine\CouchDB\MangoClient;
use Doctrine\CouchDB\Mango\MangoQuery;


class MangoQueryTest extends \Doctrine\Tests\CouchDB\CouchDBFunctionalTestCase
{
    public function testSelector(){
      $selector = ['id'=>['$gt'=>null]];
      $query = new MangoQuery($selector);
      $query = $query->asArray();
      $this->assertEquals($selector,$query['selector']);
    }

    public function testEmptySelector(){
      $query = new MangoQuery([]);
      $query = $query->asArray();
      $this->assertInstanceOf('StdClass',$query['selector']);
    }

    public function testSelectorMethod(){
      $query = new MangoQuery([]);
      $selector = ['id'=>['$gt'=>null]];
      $query->selector($selector);
      $this->assertTrue(array_key_exists('selector',$query->asArray()));
      $this->assertEquals($selector,$query->selector());
    }

    public function testSetEmptySelector(){
      $query = new MangoQuery([]);
      $query->selector([]);
      $query = $query->asArray();
      $this->assertInstanceOf('StdClass',$query['selector']);
    }

    public function testLimitOption(){
      $query = new MangoQuery([],['limit'=>1]);
      $query = $query->asArray();
      $this->assertTrue(array_key_exists('limit',$query));
      $this->assertEquals(1,$query['limit']);
    }

    public function testLimitMethod(){
      $query = new MangoQuery([]);
      $query->limit(1);
      $this->assertTrue(array_key_exists('limit',$query->asArray()));
      $this->assertEquals(1,$query->limit());
    }

    public function testSkipOption(){
      $query = new MangoQuery([],['skip'=>1]);
      $query = $query->asArray();
      $this->assertTrue(array_key_exists('skip',$query));
      $this->assertEquals(1,$query['skip']);
    }

    public function testSkipMethod(){
      $query = new MangoQuery([]);
      $query->skip(1);
      $this->assertTrue(array_key_exists('skip',$query->asArray()));
      $this->assertEquals(1,$query->skip());
    }

    public function testFieldsOption(){
      $query = new MangoQuery([],['fields'=>['_id','_rev']]);
      $query = $query->asArray();
      $this->assertTrue(array_key_exists('fields',$query));
      $this->assertEquals(['_id','_rev'],$query['fields']);
    }

    public function testFieldsMethod(){
      $query = new MangoQuery([]);
      $query->fields(['_id','_rev']);
      $this->assertTrue(array_key_exists('fields',$query->asArray()));
      $this->assertEquals(['_id','_rev'],$query->fields());
    }

    public function testSortOption(){
      $query = new MangoQuery([],['sort'=>[['name'=>'desc']]]);
      $query = $query->asArray();
      $this->assertTrue(array_key_exists('sort',$query));
      $this->assertEquals([['name'=>'desc']],$query['sort']);
    }

    public function testSortMethod(){
      $query = new MangoQuery([]);
      $query->sort([['name'=>'desc']]);
      $this->assertTrue(array_key_exists('sort',$query->asArray()));
      $this->assertEquals([['name'=>'desc']],$query->sort());
    }

    public function testUseIndexOption(){
      $query = new MangoQuery([],['use_index'=>['document','index']]);
      $query = $query->asArray();
      $this->assertTrue(array_key_exists('use_index',$query));
      $this->assertEquals(['document','index'],$query['use_index']);
    }

    public function testUseIndexMethod(){
      $query = new MangoQuery([]);
      $query->use_index(['document','index']);
      $this->assertTrue(array_key_exists('use_index',$query->asArray()));
      $this->assertEquals(['document','index'],$query->use_index());
    }

    public function testJsonSerialize(){
      $query = new MangoQuery();

      $params =  [
        'selector'=>['_id'=>['$gt'=>null]],
        'fields'=>['_id'],
        'sort'=>[['name'=>'desc']],
        'skip'=>1,
        'limit'=>10,
        'use_index'=>['design','document']
      ];

      $query->select($params['fields'])->where($params['selector'])->limit($params['limit'])->skip($params['skip'])->sort($params['sort'])->use_index($params['use_index']);

      $this->assertEquals(json_encode($params),json_encode($query));
    }
}
