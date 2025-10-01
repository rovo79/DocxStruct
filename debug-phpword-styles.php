<?php

require 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$filePath = $argv[1] ?? './docx-converter/tests/Documents/61305-MBR.docx';

echo "Loading: {$filePath}\n\n";

$phpWord = IOFactory::load($filePath, 'Word2007');

echo "=== PHPWord Methods ===\n";
echo implode(', ', get_class_methods($phpWord)) . "\n\n";

echo "=== Section Elements ===\n";
foreach ($phpWord->getSections() as $sectionIndex => $section) {
    echo "Section {$sectionIndex}:\n";
    
    $elementIndex = 0;
    foreach ($section->getElements() as $element) {
        $elementIndex++;
        $elementClass = get_class($element);
        echo "  Element {$elementIndex}: {$elementClass}\n";
        
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            echo "    getParagraphStyle() returns: ";
            $style = $element->getParagraphStyle();
            if (is_string($style)) {
                echo "string: '{$style}'\n";
            } elseif (is_object($style)) {
                echo get_class($style) . "\n";
                if (method_exists($style, 'getStyleName')) {
                    echo "      getStyleName(): " . var_export($style->getStyleName(), true) . "\n";
                }
                // Dump all methods
                echo "      Available methods: " . implode(', ', get_class_methods($style)) . "\n";
            } elseif (is_array($style)) {
                echo "array: " . json_encode($style) . "\n";
            } else {
                echo var_export($style, true) . "\n";
            }
            
            // Check if element has any other style-related methods
            echo "    Element methods containing 'style': ";
            $methods = get_class_methods($element);
            $styleMethods = array_filter($methods, function($m) {
                return stripos($m, 'style') !== false;
            });
            echo implode(', ', $styleMethods) . "\n";
            
            // Try to access the underlying container/paragraph if available
            if (method_exists($element, 'getStyle')) {
                echo "    getStyle() returns: ";
                $generalStyle = $element->getStyle();
                if (is_string($generalStyle)) {
                    echo "string: '{$generalStyle}'\n";
                } elseif (is_object($generalStyle)) {
                    echo get_class($generalStyle) . "\n";
                } else {
                    echo var_export($generalStyle, true) . "\n";
                }
            }
        }
        
        if ($elementIndex >= 5) {
            echo "  (showing first 5 elements only)\n";
            break;
        }
    }
    
    if ($sectionIndex >= 0) {
        echo "\n(showing first section only)\n";
        break;
    }
}
