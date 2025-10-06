<?php
require 'vendor/autoload.php';

// Read the HTML output and check structure
$html = file_get_contents('output-table-notes.html');

// Count tbody elements
$tbodyCount = substr_count($html, '<tbody>');
echo "Found {$tbodyCount} <tbody> elements\n";

// Count tfoot elements
$tfootCount = substr_count($html, '<tfoot>');
echo "Found {$tfootCount} <tfoot> elements\n";

// Find tables with tfoot
preg_match_all('/<table[^>]*>.*?<\/table>/s', $html, $tables);
echo "\nTotal tables: " . count($tables[0]) . "\n";

$tablesWithTfoot = 0;
$tablesWithTableNote = 0;
$tablesWithTableFootnote = 0;

foreach ($tables[0] as $table) {
    if (strpos($table, '<tfoot>') !== false) {
        $tablesWithTfoot++;
        if (strpos($table, 'class="table-note"') !== false) {
            $tablesWithTableNote++;
        }
        if (strpos($table, 'class="table-footnote"') !== false) {
            $tablesWithTableFootnote++;
        }
    }
}

echo "Tables with <tfoot>: {$tablesWithTfoot}\n";
echo "Tables with table-note: {$tablesWithTableNote}\n";
echo "Tables with table-footnote: {$tablesWithTableFootnote}\n";

// Show a sample tfoot structure
echo "\n=== Sample tfoot structure ===\n";
if (preg_match('/<tfoot>.*?<\/tfoot>/s', $html, $match)) {
    echo $match[0] . "\n";
}
