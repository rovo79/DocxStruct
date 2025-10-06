<?php
$html = file_get_contents('output-table-notes-v3.html');

// Extract all tfoot sections
preg_match_all('/<tfoot>.*?<\/tfoot>/s', $html, $tfoots);

echo "Found " . count($tfoots[0]) . " <tfoot> sections\n\n";

foreach ($tfoots[0] as $i => $tfoot) {
    echo "=== TFOOT " . ($i + 1) . " ===\n";
    
    // Count paragraphs
    $pCount = substr_count($tfoot, '<p>');
    echo "Contains {$pCount} paragraph(s)\n";
    
    // Check for formatting
    $hasEm = strpos($tfoot, '<em>') !== false;
    $hasStrong = strpos($tfoot, '<strong>') !== false;
    $hasLinks = strpos($tfoot, '<a ') !== false;
    
    $formatting = [];
    if ($hasEm) $formatting[] = 'italic';
    if ($hasStrong) $formatting[] = 'bold';
    if ($hasLinks) $formatting[] = 'links';
    
    if (!empty($formatting)) {
        echo "Has formatting: " . implode(', ', $formatting) . "\n";
    }
    
    // Show first 150 chars
    $text = strip_tags($tfoot);
    $text = preg_replace('/\s+/', ' ', $text);
    echo "Preview: " . substr(trim($text), 0, 150) . "...\n\n";
}
