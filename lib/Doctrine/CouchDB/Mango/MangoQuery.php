<?php

namespace Doctrine\CouchDB\Mango;

use JsonSerializable;

class MangoQuery implements JsonSerializable{

  protected $selector;
  protected $options;
  protected $fields;
  protected $sort;
  protected $skip;
  protected $limit;
  protected $use_index;

  public function __construct(array $selector = [], array $options = []){
    $this->selector = $selector;
    $this->options = $options;

    if (isset($this->options['fields'])) {
        $this->fields = $this->options['fields'];
    }

    if (isset($this->options['sort'])) {
        $this->sort = $this->options['sort'];
    }

    if (isset($this->options['skip'])) {
        $this->skip = $this->options['skip'];
    }

    if (isset($this->options['limit'])) {
        $this->limit = $this->options['limit'];
    }

    if (isset($this->options['use_index'])) {
        $this->use_index = $this->options['use_index'];
    }

  }

  public function where(array $selector){
    return $this->selector($selector);
  }

  public function selector(array $selector = null){
    if($selector !== null){
      $this->selector = $selector;
      return $this;
    }else{
      return $this->selector;
    }
  }

  public function select(array $fields){
    return $this->fields($fields);
  }

  public function fields(array $fields = null){
    if($fields !== null){
      $this->fields = $fields;
      return $this;
    }else{
      return $this->fields;
    }
  }

  public function sort(array $sort = null){
    if($sort !== null){
      $this->sort = $sort;
      return $this;
    }else{
      return $this->sort;
    }
  }

  public function skip($skip = null){
    if($skip !== null){
      $this->skip = $skip;
      return $this;
    }else{
      return $this->skip;
    }
  }

  public function use_index(array $use_index = null){
    if($use_index !== null){
      $this->use_index = $use_index;
      return $this;
    }else{
      return $this->use_index;
    }
  }

  public function limit($limit = null){
    if($limit !== null){
      $this->limit = $limit;
      return $this;
    }else{
      return $this->limit;
    }
  }

  public function asArray(){

    $params = array();
    $params['selector'] = ($this->selector) ? $this->selector : new \StdClass();

    if ($this->fields) {
        $params['fields'] = $this->fields;
    }

    if ($this->sort) {
        $params['sort'] = $this->sort;
    }

    if ($this->skip) {
        $params['skip'] = $this->skip;
    }

    if ($this->limit) {
        $params['limit'] = $this->limit;
    }

    if ($this->use_index) {
        $params['use_index'] = $this->use_index;
    }

    return $params;
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
