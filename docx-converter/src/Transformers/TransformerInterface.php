<?php

namespace DocxConverter\Transformers;

/**
 * Interface for transforming PHPWord document sections into various output formats.
 * 
 * Transformers receive an array of PHPWord Section objects from DocxReader
 * and convert them to the desired output format (HTML, JSON, etc.).
 */
interface TransformerInterface
{
    /**
     * Transform an array of PHPWord Section objects to the target format.
     * 
     * @param array $sections Array of \PhpOffice\PhpWord\Element\Section objects
     *                        Each section contains document elements (paragraphs, tables, etc.)
     * @return string The transformed output in the target format (HTML, JSON, etc.)
     */
    public function transform(array $sections): string;
}
