<?php

namespace Spammer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VBulletin extends Command
{
    protected function configure()
    {
        $this->setName("spammer:vbulletin")
            ->setDescription("Spammer for VBulletin site");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('ciao');
    }
}