<?php

require __DIR__ . '/vendor/autoload.php';

use DocxConverter\DocxConverter;

$input = __DIR__ . '/docx-converter/tests/Documents/61305-MBR.docx';
$output = __DIR__ . '/61305-oct6-test-assets.html';
$assets = __DIR__ . '/output-assets';

$converter = new DocxConverter();
$converter->loadDocument($input)->withAssetsDir($assets)->withDebug(false);
$html = $converter->toHtml();
file_put_contents($output, $html);

echo "Wrote: {$output}\n";
echo "Assets dir: {$assets}\n";
