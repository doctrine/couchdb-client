# Doctrine CouchDB Client

[![Build Status](https://travis-ci.org/robsonvn/couchdb-client.png?branch=master)](https://travis-ci.org/robsonvn/couchdb-client)

Simple API that wraps around CouchDBs v2.0.0 HTTP API.

## Features

* Create, Delete, List Databases
* Create, Update, Delete Documents
* Bulk API for Creating/Updating Documents
* Find Documents by ID
* Generate UUIDs
* Design Documents
* Query `_all_docs` view
* Query Changes Feed
* Query Views
* Compaction Info and Triggering APIs
* Replication API
* Symfony Console Commands
* Find Documents using Mango Query

## Installation

With Composer:

    {
        "require": {
            "doctrine/couchdb": "@dev"
        }
    }

## Usage

### Basic Operations

Covering the basic CRUD Operations for databases and documents:

```php
<?php
$client = \Doctrine\CouchDB\CouchDBClient::create(array('dbname' => 'doctrine_example'));

// Create a database.
$client->createDatabase($client->getDatabase());

// Create a new document.
list($id, $rev) = $client->postDocument(array('foo' => 'bar'));

// Update a existing document. This will increment the revision.
list($id, $rev) = $client->putDocument(array('foo' => 'baz'), $id, $rev);

// Fetch single document by id.
$doc = $client->findDocument($id);

// Fetch multiple documents at once.
$docs = $client->findDocuments(array($id));

// Return all documents from database (_all_docs?include_docs=true).
$allDocs = $client->allDocs();

// Delete a single document.
$client->deleteDocument($id, $rev);

// Delete a database.
$client->deleteDatabase($client->getDatabase());

//Search documents using Mango Query CouchDB v2.0.0
$allDocs = $client->find(['_id'=>['$gt'=>null]]);

```

### Views

A simple example demonstrating how to create views and query them:

```php
<?php
class ArticlesDesignDocument implements \Doctrine\CouchDB\View\DesignDocument
{
    public function getData()
    {
        return array(
            'language' => 'javascript',
            'views' => array(
                'by_author' => array(
                    'map' => 'function(doc) {
                        if(\'article\' == doc.type) {
                            emit(doc.author, doc._id);
                        }
                    }',
                    'reduce' => '_count'
                ),
            ),
        );
    }
}

$client->createDesignDocument('articles', new ArticlesDesignDocument());

// Fill database with some data.
foreach (array('Alice', 'Bob', 'Bob') as $author) {
    $client->postDocument(array(
        'type' => 'article',
        'author' => $author,
        'content' => 'Lorem ipsum'
    ));
}

// Query all articles.
$query = $client->createViewQuery('articles', 'by_author');
$query->setReduce(false);
$query->setIncludeDocs(true);
$result = $query->execute();
foreach ($result as $row) {
    $doc = $row['doc'];
    echo 'Article by ', $doc['author'], ': ', $doc['content'], "\n";
}
// Article by Alice: Lorem ipsum
// Article by Bob: Lorem ipsum
// Article by Bob: Lorem ipsum


// Query all articles written by bob.
$query = $client->createViewQuery('articles', 'by_author');
$query->setKey('Bob');
// ...


// Query the _count of articles each author has written.
$query = $client->createViewQuery('articles', 'by_author');
$query->setReduce(true);
$query->setGroupLevel(1); // group_level=1 means grouping by author.
$result = $query->execute();
foreach ($result as $row) {
    echo 'Author ', $row['key'], ' has written ', $row['value'], ' articles', "\n";
}
// Author Alice has written 1 articles
// Author Bob has written 2 articles
```
