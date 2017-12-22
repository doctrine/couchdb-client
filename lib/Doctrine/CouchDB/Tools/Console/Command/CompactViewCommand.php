<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Command\Command;

class CompactViewCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:maintenance:compact-view')
             ->setDescription('Compat the given view')
             ->setDefinition(array(
                 new InputArgument('designdoc', InputArgument::REQUIRED, 'Design document name', null),
             ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $couchClient = $this->getHelper('couchdb')->getCouchDBClient();
        /* @var $couchClient \Doctrine\CouchDB\CouchDBClient */

        $data = $couchClient->compactView($input->getArgument('designdoc'));
        $output->writeln("View compact started.");
    }
}
