<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$testFilePath = __DIR__ . '/docx-converter/tests/Documents/test-with-images.docx';
$phpWord = IOFactory::load($testFilePath);

echo "Document sections and elements (detailed):\n";
echo "==========================================\n\n";

foreach ($phpWord->getSections() as $sectionIndex => $section) {
    echo "Section {$sectionIndex}:\n";
    
    foreach ($section->getElements() as $elementIndex => $element) {
        $className = get_class($element);
        echo "  Element {$elementIndex}: {$className}\n";
        
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            foreach ($element->getElements() as $subIndex => $subElement) {
                $subClassName = get_class($subElement);
                echo "    Sub-element {$subIndex}: {$subClassName}\n";
                
                if ($subElement instanceof \PhpOffice\PhpWord\Element\Image) {
                    echo "      - Image source: " . $subElement->getSource() . "\n";
                    echo "      - Image type: " . $subElement->getImageType() . "\n";
                    echo "      - Is local: " . ($subElement->isLocal() ? 'yes' : 'no') . "\n";
                    echo "      - Relation ID: " . ($subElement->getRelationId() ?? 'N/A') . "\n";
                    $methods = get_class_methods($subElement);
                    echo "      - Available methods: " . implode(', ', array_filter($methods, fn($m) => strpos($m, 'get') === 0)) . "\n";
                }
            }
        } elseif ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            echo "    - Image source: " . $element->getSource() . "\n";
            echo "    - Image type: " . $element->getImageType() . "\n";
            echo "    - Is local: " . ($element->isLocal() ? 'yes' : 'no') . "\n";
            echo "    - Relation ID: " . ($element->getRelationId() ?? 'N/A') . "\n";
        }
    }
}
