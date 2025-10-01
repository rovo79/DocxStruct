<?php

namespace DocxConverter\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DocxConverter\DocxConverter;
use DocxConverter\Config\ConfigLoader;

class ConvertCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('convert')
            ->setDescription('Convert a DOCX file to another format')
            ->addArgument('input', InputArgument::REQUIRED, 'Path to input DOCX file')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (html, json)', 'html')
            ->addOption('style-map', 's', InputOption::VALUE_OPTIONAL, 'Path to YAML style mapping file')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Path to YAML configuration file')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Enable debug output');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument('input');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $styleMapFile = $input->getOption('style-map');
        $configFile = $input->getOption('config');

        // Load config if provided (currently only used for future extension)
        if ($configFile) {
            $configLoader = new ConfigLoader();
            $config = $configLoader->loadFromYaml($configFile);
            // If config contains styleMap or transformationRules, apply them
        }

        $converter = new DocxConverter();
        $converter->loadDocument($inputFile);

        if ($styleMapFile) {
            $styleMapArr = (new ConfigLoader())->loadFromYaml($styleMapFile);
            $styleMap = new \DocxConverter\Config\StyleMap($styleMapArr);
            $converter->withCustomStyleMap($styleMap);
        }

        if ($input->getOption('debug')) {
            $converter->withDebug(true);
        }

        $result = match($format) {
            'html' => $converter->toHtml(),
            'json' => $converter->toJson(),
            default => throw new \InvalidArgumentException('Unsupported format: ' . $format)
        };

        if ($outputFile) {
            file_put_contents($outputFile, $result);
            $output->writeln("Conversion complete. Output saved to: {$outputFile}");
        } else {
            $output->write($result);
        }

        return Command::SUCCESS;
    }
}
