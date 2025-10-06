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
            ->addOption('assets-dir', 'a', InputOption::VALUE_REQUIRED, 'Directory to extract images and other assets');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument('input');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $styleMapFile = $input->getOption('style-map');
        $configFile = $input->getOption('config');
        $assetsDir = $input->getOption('assets-dir');

        $config = [];
        if ($configFile) {
            $configLoader = new ConfigLoader();
            $config = $configLoader->loadFromYaml($configFile);
        }

        $converter = new DocxConverter($config);
        $converter->loadDocument($inputFile);

        if ($styleMapFile) {
            $styleMap = (new ConfigLoader())->loadFromYaml($styleMapFile);
            $converter->withCustomStyleMap($styleMap);
        }

        // Configure assets directory if provided
        if ($assetsDir) {
            $converter->withAssetsDir($assetsDir);
            
            // Set output file path for relative path computation
            if ($outputFile) {
                $converter->setOutputFilePath($outputFile);
            }
        }

        $result = match($format) {
            'html' => $converter->toHtml(),
            'json' => $converter->toJson(),
            default => throw new \InvalidArgumentException('Unsupported format: ' . $format)
        };

        if ($outputFile) {
            file_put_contents($outputFile, $result);
            $output->writeln("Conversion complete. Output saved to: {$outputFile}");
            if ($assetsDir) {
                $output->writeln("Assets extracted to: {$assetsDir}");
            }
        } else {
            $output->write($result);
        }

        return Command::SUCCESS;
    }
}
