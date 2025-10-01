<?php

namespace DocxConverter\Readers;

class StyleReader
{
    public function extractTableStyles($table)
    {
        // Extract and return style information from the table element
        // Placeholder: implement actual extraction logic as needed
        return [
            'id' => method_exists($table, 'getStyle') ? $table->getStyle() : null,
            // Add more style extraction as needed
        ];
    }

    public function extractParagraphStyles($paragraph)
    {
        // Extract and return style information from the paragraph element
        // Placeholder: implement actual extraction logic as needed
        return [
            'id' => method_exists($paragraph, 'getStyle') ? $paragraph->getStyle() : null,
            // Add more style extraction as needed
        ];
    }
}
