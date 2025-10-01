<?php

namespace DocxConverter\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BatchCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('batch')
            ->setDescription('Batch process multiple DOCX files using a configuration file')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to YAML batch configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Placeholder: implement batch processing logic
        $output->writeln('Batch processing not yet implemented.');
        return Command::SUCCESS;
    }
}
