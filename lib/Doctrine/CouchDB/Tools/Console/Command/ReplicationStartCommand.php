<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReplicationStartCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:replication:start')
             ->setDescription('Start replication from a given source to target.')
             ->setDefinition([
                new InputArgument('source', InputArgument::REQUIRED, 'Source Database', null),
                new InputArgument('target', InputArgument::REQUIRED, 'Target Database', null),
                new InputOption('continuous', 'c', InputOption::VALUE_NONE, 'Enable continuous replication', null),
                new InputOption('proxy', 'p', InputOption::VALUE_REQUIRED, 'Proxy server to replicate through', null),
                new InputOption('id', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Ids for named replication', null),
                new InputOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Replication-Filter Document', null),
             ])->setHelp(<<<'EOT'
With this command you start the replication between a given source and target.
All the options to POST /db/_replicate are available. Example usage:

    doctrine-couchdb couchdb:replication:start example-source-db example-target-db
    doctrine-couchdb couchdb:replication:start example-source-db http://example.com:5984/example-target-db

EOT
                );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $couchClient = $this->getHelper('couchdb')->getCouchDBClient();
        /* @var $couchClient \Doctrine\CouchDB\CouchDBClient */
        $data = $couchClient->replicate(
            $input->getArgument('source'),
            $input->getArgument('target'), null,
            $input->getOption('continuous') ? true : false,
            $input->getOption('filter') ?: null,
            $input->getOption('id') ?: null,
            $input->getOption('proxy') ?: null
        );

        $output->writeln('Replication started.');
    }
}
