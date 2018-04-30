<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompactViewCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:maintenance:compact-view')
             ->setDescription('Compat the given view')
             ->setDefinition([
                 new InputArgument('designdoc', InputArgument::REQUIRED, 'Design document name', null),
             ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $couchClient = $this->getHelper('couchdb')->getCouchDBClient();
        /* @var $couchClient \Doctrine\CouchDB\CouchDBClient */

        $data = $couchClient->compactView($input->getArgument('designdoc'));
        $output->writeln('View compact started.');
    }
}
