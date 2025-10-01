<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$testFilePath = __DIR__ . '/docx-converter/tests/Documents/sample-test.docx';
$phpWord = IOFactory::load($testFilePath);

echo "PHPWord object methods:\n";
echo "======================\n\n";

$methods = get_class_methods($phpWord);
sort($methods);

foreach ($methods as $method) {
    echo "- {$method}()\n";
}

echo "\n\nChecking for style-related methods:\n";
echo "====================================\n";
foreach ($methods as $method) {
    if (stripos($method, 'style') !== false) {
        echo "- {$method}()\n";
    }
}
