<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;

echo "Creating test DOCX file...\n";

// Create new PHPWord instance
$phpWord = new PhpWord();

// Add a section to the document
$section = $phpWord->addSection();

// Add content with different styles
$section->addText(
    'Sample Document Title',
    ['name' => 'Arial', 'size' => 16, 'bold' => true],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
);

$section->addTextBreak(2);

$section->addText(
    'This is a sample paragraph with normal text formatting. This document is used to test the DocxReader implementation.',
    ['name' => 'Arial', 'size' => 12]
);

$section->addTextBreak(1);

$section->addText(
    'Here is some bold text with italic formatting.',
    ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true]
);

$section->addTextBreak(1);

// Add a simple table
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '006699',
    'cellMargin' => 50,
]);

$table->addRow();
$table->addCell(2000)->addText('Header 1', ['bold' => true]);
$table->addCell(2000)->addText('Header 2', ['bold' => true]);
$table->addCell(2000)->addText('Header 3', ['bold' => true]);

$table->addRow();
$table->addCell(2000)->addText('Row 1, Cell 1');
$table->addCell(2000)->addText('Row 1, Cell 2');
$table->addCell(2000)->addText('Row 1, Cell 3');

$table->addRow();
$table->addCell(2000)->addText('Row 2, Cell 1');
$table->addCell(2000)->addText('Row 2, Cell 2');
$table->addCell(2000)->addText('Row 2, Cell 3');

// Add a list
$section->addTextBreak(1);
$section->addText('Sample List:', ['bold' => true]);
$section->addListItem('First list item');
$section->addListItem('Second list item');
$section->addListItem('Third list item');

// Save the document
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$testFilePath = __DIR__ . '/docx-converter/tests/Documents/sample-test.docx';

// Create directory if it doesn't exist
$testDir = dirname($testFilePath);
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}

$writer->save($testFilePath);

echo "Test DOCX file created: {$testFilePath}\n";
echo "File size: " . number_format(filesize($testFilePath)) . " bytes\n";