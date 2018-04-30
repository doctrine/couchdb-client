<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompactDatabaseCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:maintenance:compact-database')
             ->setDescription('Compact the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $couchClient = $this->getHelper('couchdb')->getCouchDBClient();
        /* @var $couchClient \Doctrine\CouchDB\CouchDBClient */

        $data = $couchClient->compactDatabase();
        $output->writeln('Database compact started.');
    }
}
