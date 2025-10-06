<?php

namespace DocxConverter\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use DocxConverter\Readers\DocxReader;

class InspectCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('inspect')
            ->setDescription('Inspect style IDs and element types in a DOCX file')
            ->addArgument('input', InputArgument::REQUIRED, 'Path to input DOCX file')
            ->addOption('detailed', 'd', null, 'Show detailed element information with examples')
            ->addOption('export', 'e', InputOption::VALUE_REQUIRED, 'Export style IDs to a YAML template file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument('input');
        $detailed = $input->getOption('detailed');
        $exportFile = $input->getOption('export');

        if (!file_exists($inputFile)) {
            $output->writeln("<error>Error: File not found: {$inputFile}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Inspecting DOCX file: {$inputFile}</info>");
        $output->writeln('');

        try {
            $reader = new DocxReader($inputFile);
            $sections = $reader->getSections();

            $styleStats = [];
            $elementTypes = [];
            $styleDetails = [];
            $totalElements = 0;

            foreach ($sections as $sectionIndex => $section) {
                $elements = $section->getElements();
                
                foreach ($elements as $elementIndex => $element) {
                    $totalElements++;
                    $elementType = $this->getElementType($element);
                    
                    // Count element types
                    if (!isset($elementTypes[$elementType])) {
                        $elementTypes[$elementType] = 0;
                    }
                    $elementTypes[$elementType]++;

                    // Extract style IDs
                    $styleIds = $this->extractStyleIds($element);
                    
                    foreach ($styleIds as $styleId) {
                        if (empty($styleId)) {
                            continue;
                        }

                        // Count style occurrences
                        if (!isset($styleStats[$styleId])) {
                            $styleStats[$styleId] = [
                                'count' => 0,
                                'types' => []
                            ];
                        }
                        $styleStats[$styleId]['count']++;
                        
                        if (!in_array($elementType, $styleStats[$styleId]['types'])) {
                            $styleStats[$styleId]['types'][] = $elementType;
                        }

                        // Store detailed examples for detailed mode
                        if ($detailed && !isset($styleDetails[$styleId])) {
                            $styleDetails[$styleId] = [
                                'section' => $sectionIndex,
                                'element' => $elementIndex,
                                'type' => $elementType,
                                'preview' => $this->getElementPreview($element)
                            ];
                        }
                    }
                }
            }

            // Display element type summary
            $output->writeln("<comment>Document Summary:</comment>");
            $output->writeln("  Total elements: {$totalElements}");
            $output->writeln("  Total sections: " . count($sections));
            $output->writeln('');

            // Display element types table
            $output->writeln("<comment>Element Types:</comment>");
            $table = new Table($output);
            $table->setHeaders(['Element Type', 'Count']);
            arsort($elementTypes);
            foreach ($elementTypes as $type => $count) {
                $table->addRow([$type, $count]);
            }
            $table->render();
            $output->writeln('');

            // Display style IDs table
            if (empty($styleStats)) {
                $output->writeln("<info>No style IDs found in document.</info>");
            } else {
                $output->writeln("<comment>Style IDs Found:</comment>");
                $table = new Table($output);
                $table->setHeaders(['Style ID', 'Count', 'Used In']);
                
                // Sort by count (descending)
                uasort($styleStats, function($a, $b) {
                    return $b['count'] - $a['count'];
                });
                
                foreach ($styleStats as $styleId => $stats) {
                    $table->addRow([
                        $styleId,
                        $stats['count'],
                        implode(', ', $stats['types'])
                    ]);
                }
                $table->render();
                $output->writeln('');
            }

            // Display verbose details if requested
            if ($detailed && !empty($styleDetails)) {
                $output->writeln("<comment>Style ID Details (First Occurrence):</comment>");
                $output->writeln('');
                
                foreach ($styleDetails as $styleId => $detail) {
                    $output->writeln("<info>Style ID: {$styleId}</info>");
                    $output->writeln("  Element Type: {$detail['type']}");
                    $output->writeln("  Section: {$detail['section']}, Element: {$detail['element']}");
                    if (!empty($detail['preview'])) {
                        $output->writeln("  Preview: " . substr($detail['preview'], 0, 100));
                    }
                    $output->writeln('');
                }
            }

            $output->writeln("<info>✓ Inspection complete</info>");

            // Export to YAML if requested
            if ($exportFile) {
                $this->exportToYaml($styleStats, $exportFile, $output);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function getElementType($element): string
    {
        $className = get_class($element);
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function extractStyleIds($element): array
    {
        $styleIds = [];

        // Handle TextRun elements (paragraphs)
        if (method_exists($element, 'getParagraphStyle')) {
            $style = $element->getParagraphStyle();
            
            if (is_string($style) && !empty($style)) {
                $styleIds[] = $style;
            } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                $styleId = $style->getStyleName();
                if (!empty($styleId)) {
                    $styleIds[] = $styleId;
                }
            }
        }

        // Handle Table elements
        if (method_exists($element, 'getStyle')) {
            $style = $element->getStyle();
            if (is_string($style) && !empty($style)) {
                $styleIds[] = $style;
            } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                $styleId = $style->getStyleName();
                if (!empty($styleId)) {
                    $styleIds[] = $styleId;
                }
            }
        }

        // Handle nested elements (e.g., table cells contain elements)
        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $styleIds = array_merge($styleIds, $this->extractStyleIds($cellElement));
                    }
                }
            }
        }

        return array_unique($styleIds);
    }

    private function getElementPreview($element): string
    {
        // Try to extract text content for preview
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            return is_string($text) ? $text : '';
        }

        if (method_exists($element, 'getElements')) {
            $texts = [];
            $elements = $element->getElements();
            if (is_array($elements)) {
                foreach ($elements as $subElement) {
                    if (method_exists($subElement, 'getText')) {
                        $text = $subElement->getText();
                        if (is_string($text)) {
                            $texts[] = $text;
                        }
                    }
                }
            }
            return implode(' ', $texts);
        }

        return '';
    }

    private function exportToYaml(array $styleStats, string $outputFile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln("<comment>Exporting style template to: {$outputFile}</comment>");

        // Sort style IDs alphabetically for better readability
        ksort($styleStats);

        $yamlContent = "# Style Mapping Configuration\n";
        $yamlContent .= "# Generated from inspection\n";
        $yamlContent .= "# Customize the settings below for your conversion needs\n\n";

        foreach ($styleStats as $styleId => $stats) {
            $yamlContent .= "{$styleId}:\n";
            
            // Add intelligent defaults based on style name patterns
            $suggestions = $this->suggestMapping($styleId, $stats['types']);
            
            if ($suggestions['convertTo']) {
                $yamlContent .= "  convertTo: {$suggestions['convertTo']}\n";
            }
            
            $yamlContent .= "  className: {$suggestions['className']}\n";
            
            if ($suggestions['listType']) {
                $yamlContent .= "  listType: {$suggestions['listType']}\n";
            }
            
            // Add usage comment
            $yamlContent .= "  # Used {$stats['count']} time(s) in: " . implode(', ', $stats['types']) . "\n";
            $yamlContent .= "\n";
        }

        if (file_put_contents($outputFile, $yamlContent) !== false) {
            $output->writeln("<info>✓ Template exported successfully</info>");
            $output->writeln("<comment>Edit {$outputFile} to customize your style mappings</comment>");
        } else {
            $output->writeln("<error>✗ Failed to write to {$outputFile}</error>");
        }
    }

    private function suggestMapping(string $styleId, array $elementTypes): array
    {
        $normalizedId = strtolower($styleId);
        $className = $this->normalizeStyleIdForClass($styleId);
        
        $suggestions = [
            'convertTo' => null,
            'className' => $className,
            'listType' => null
        ];

        // Suggest list conversion for list-related styles
        if (preg_match('/list|bullet|number/i', $styleId)) {
            $suggestions['convertTo'] = 'list';
            
            // Suggest list type based on name
            if (preg_match('/bullet/i', $styleId)) {
                $suggestions['listType'] = 'ul';
            } elseif (preg_match('/number/i', $styleId)) {
                $suggestions['listType'] = 'ol';
            } else {
                $suggestions['listType'] = 'ul'; // Default to unordered
            }
        }
        
        // Suggest blockquote for quote styles
        if (preg_match('/quote/i', $styleId)) {
            $suggestions['convertTo'] = 'blockquote';
        }
        
        // Don't suggest convertTo for heading styles (they're already semantic)
        if (preg_match('/heading|title/i', $styleId)) {
            $suggestions['convertTo'] = null;
        }

        return $suggestions;
    }

    private function normalizeStyleIdForClass(string $styleId): string
    {
        // Convert PascalCase/camelCase to kebab-case
        $normalized = preg_replace('/([a-z])([A-Z])/', '$1-$2', $styleId);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
        $normalized = trim($normalized, '-');
        
        return $normalized;
    }
}
