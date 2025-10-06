<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$testFilePath = __DIR__ . '/docx-converter/tests/Documents/test-with-images.docx';
$phpWord = IOFactory::load($testFilePath);

foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $element) {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            foreach ($element->getElements() as $subElement) {
                if ($subElement instanceof \PhpOffice\PhpWord\Element\Image) {
                    echo "Image element methods:\n";
                    $methods = get_class_methods($subElement);
                    sort($methods);
                    foreach ($methods as $method) {
                        echo "  - {$method}\n";
                    }
                    
                    echo "\nImage properties:\n";
                    echo "  Source: " . $subElement->getSource() . "\n";
                    echo "  Type: " . $subElement->getImageType() . "\n";
                    echo "  RelationId: " . ($subElement->getRelationId() ?? 'NULL') . "\n";
                    
                    break 3;
                }
            }
        }
    }
}
