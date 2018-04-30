<?php

namespace Doctrine\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ViewCleanupCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:maintenance:view-cleanup')
             ->setDescription('Cleanup deleted views');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $couchClient = $this->getHelper('couchdb')->getCouchDBClient();
        /* @var $couchClient \Doctrine\CouchDB\CouchDBClient */

        $data = $couchClient->viewCleanup();
        $output->writeln('View cleanup started.');
    }
}
