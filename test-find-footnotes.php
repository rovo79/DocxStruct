<?php

require_once __DIR__ . '/vendor/autoload.php';

use DocxConverter\Readers\DocxReader;
use PhpOffice\PhpWord\Element\Footnote;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;

$reader = new DocxReader('./docx-converter/tests/Documents/61305-MBR.docx');
$sections = $reader->getSections();

echo "Searching for Footnote elements...\n\n";

foreach ($sections as $sectionIndex => $section) {
    $elements = $section->getElements();
    
    foreach ($elements as $i => $element) {
        // Check for Footnote elements
        if ($element instanceof Footnote) {
            echo "Found Footnote at section {$sectionIndex}, element {$i}\n";
            echo "Footnote content:\n";
            
            foreach ($element->getElements() as $fnElement) {
                if ($fnElement instanceof Text) {
                    echo "  Text: " . $fnElement->getText() . "\n";
                } elseif ($fnElement instanceof TextRun) {
                    $text = '';
                    foreach ($fnElement->getElements() as $textEl) {
                        if ($textEl instanceof Text) {
                            $text .= $textEl->getText();
                        }
                    }
                    echo "  TextRun: " . $text . "\n";
                    
                    // Check style
                    $style = $fnElement->getParagraphStyle();
                    $styleId = '';
                    if (is_string($style)) {
                        $styleId = $style;
                    } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                        $styleId = $style->getStyleName() ?? '';
                    }
                    if ($styleId) {
                        echo "    Style ID: '{$styleId}'\n";
                    }
                }
            }
            echo "\n";
        }
        
        // Also check within TextRun elements for embedded footnotes
        if ($element instanceof TextRun) {
            foreach ($element->getElements() as $subElement) {
                if ($subElement instanceof Footnote) {
                    echo "Found embedded Footnote in TextRun at section {$sectionIndex}, element {$i}\n";
                    echo "Footnote content:\n";
                    
                    foreach ($subElement->getElements() as $fnElement) {
                        if ($fnElement instanceof Text) {
                            echo "  Text: " . $fnElement->getText() . "\n";
                        } elseif ($fnElement instanceof TextRun) {
                            $text = '';
                            foreach ($fnElement->getElements() as $textEl) {
                                if ($textEl instanceof Text) {
                                    $text .= $textEl->getText();
                                }
                            }
                            echo "  TextRun: " . $text . "\n";
                            
                            // Check style
                            $style = $fnElement->getParagraphStyle();
                            $styleId = '';
                            if (is_string($style)) {
                                $styleId = $style;
                            } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                                $styleId = $style->getStyleName() ?? '';
                            }
                            if ($styleId) {
                                echo "    Style ID: '{$styleId}'\n";
                            }
                        }
                    }
                    echo "\n";
                }
            }
        }
    }
}

echo "Search complete.\n";
