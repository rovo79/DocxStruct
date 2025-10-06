<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$testFilePath = __DIR__ . '/docx-converter/tests/Documents/test-with-images.docx';
$phpWord = IOFactory::load($testFilePath);

echo "Document sections and elements:\n";
echo "================================\n\n";

foreach ($phpWord->getSections() as $sectionIndex => $section) {
    echo "Section {$sectionIndex}:\n";
    
    foreach ($section->getElements() as $elementIndex => $element) {
        $className = get_class($element);
        echo "  Element {$elementIndex}: {$className}\n";
        
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            echo "    - Image source: " . $element->getSource() . "\n";
            echo "    - Image type: " . $element->getImageType() . "\n";
            echo "    - Is local: " . ($element->isLocal() ? 'yes' : 'no') . "\n";
            echo "    - Relation ID: " . ($element->getRelationId() ?? 'N/A') . "\n";
        }
    }
}
