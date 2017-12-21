<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Command\Command;

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
        $output->writeln("Database compact started.");
    }
}
