<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$docxPath = './docx-converter/tests/Documents/61305-MBR.docx';
$phpWord = IOFactory::load($docxPath);

$section = $phpWord->getSections()[0];
$elements = $section->getElements();

// Find the third table (index 15 based on previous inspection)
$table = null;
$tableIndex = 0;
foreach ($elements as $i => $element) {
    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        if ($tableIndex === 2) { // Third table (0-indexed)
            $table = $element;
            echo "Found third table at element index {$i}\n\n";
            break;
        }
        $tableIndex++;
    }
}

if ($table) {
    $rows = $table->getRows();
    echo "Examining row 17 (TableNote row):\n";
    echo "=" . str_repeat("=", 60) . "\n\n";
    
    $row = $rows[17];
    $cells = $row->getCells();
    $cell = $cells[0];
    
    echo "Cell elements:\n";
    foreach ($cell->getElements() as $i => $element) {
        $className = get_class($element);
        echo "\nElement {$i}: {$className}\n";
        
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $style = $element->getParagraphStyle();
            $styleId = '';
            if (is_string($style)) {
                $styleId = $style;
            } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                $styleId = $style->getStyleName() ?? '';
            }
            echo "  Paragraph Style: {$styleId}\n";
            echo "  Contains " . count($element->getElements()) . " text elements:\n";
            
            foreach ($element->getElements() as $j => $textElement) {
                $textClass = get_class($textElement);
                echo "    [{$j}] {$textClass}";
                
                if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                    $text = $textElement->getText();
                    echo " - Text: " . substr($text, 0, 50);
                    if (strlen($text) > 50) echo "...";
                    
                    // Check for formatting
                    $fontStyle = $textElement->getFontStyle();
                    if (is_object($fontStyle)) {
                        $formats = [];
                        if (method_exists($fontStyle, 'isBold') && $fontStyle->isBold()) {
                            $formats[] = "bold";
                        }
                        if (method_exists($fontStyle, 'isItalic') && $fontStyle->isItalic()) {
                            $formats[] = "italic";
                        }
                        if (!empty($formats)) {
                            echo " [" . implode(", ", $formats) . "]";
                        }
                    }
                }
                echo "\n";
            }
        }
    }
}
