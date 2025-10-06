<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

// Create a simple image for testing
$imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$imagePath = __DIR__ . '/test-image.png';
file_put_contents($imagePath, $imageData);

// Create DOCX with image
$phpWord = new PhpWord();
$section = $phpWord->addSection();

$section->addText('This is a test document with an image:');
$section->addImage($imagePath, [
    'width' => 100,
    'height' => 100,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
]);
$section->addText('This is text after the image.');

// Add duplicate image to test deduplication
$section->addText('This is the same image again:');
$section->addImage($imagePath, [
    'width' => 50,
    'height' => 50
]);

// Save the document
$outputPath = __DIR__ . '/docx-converter/tests/Documents/test-with-images.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($outputPath);

echo "Created test DOCX with images at: {$outputPath}\n";
unlink($imagePath);
