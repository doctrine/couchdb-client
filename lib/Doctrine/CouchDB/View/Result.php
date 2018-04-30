<?php

namespace Doctrine\CouchDB\View;

class Result implements \IteratorAggregate, \Countable, \ArrayAccess
{
    protected $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function getTotalRows()
    {
        return $this->result['total_rows'];
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->result['rows']);
    }

    public function count()
    {
        return count($this->result['rows']);
    }

    public function offsetExists($offset)
    {
        return isset($this->result['rows'][$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->result['rows'][$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Result is immutable and cannot be changed.');
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Result is immutable and cannot be changed.');
    }

    public function toArray()
    {
        return $this->result['rows'];
    }

    public function getOffset()
    {
        return $this->result['offset'];
    }
}
