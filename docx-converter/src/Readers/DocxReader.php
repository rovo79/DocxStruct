<?php

namespace DocxConverter\Readers;

use PhpOffice\PhpWord\IOFactory;
use InvalidArgumentException;
use RuntimeException;

class DocxReader
{
    private $phpWord;
    private $filePath;

    /**
     * Load a DOCX document using PHPWord's IOFactory
     * 
     * @param string $filePath Path to the DOCX file
     * @throws InvalidArgumentException If file does not exist or is not readable
     * @throws RuntimeException If file cannot be loaded as a valid DOCX document
     */
    public function __construct(string $filePath)
    {
        // Validate file existence
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("DOCX file not found: {$filePath}");
        }

        // Validate file is readable
        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("DOCX file is not readable: {$filePath}");
        }

        $this->filePath = $filePath;

        // Load document using PHPWord's IOFactory
        try {
            $this->phpWord = IOFactory::load($filePath);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to load DOCX file '{$filePath}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get the file path of the loaded DOCX document
     * 
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the PHPWord document object
     * 
     * @return \PhpOffice\PhpWord\PhpWord
     */
    public function getDocument()
    {
        return $this->phpWord;
    }

    /**
     * Get all sections from the document
     * 
     * @return \PhpOffice\PhpWord\Element\Section[]
     */
    public function getSections()
    {
        return $this->phpWord->getSections();
    }

    /**
     * Get document settings
     * 
     * @return \PhpOffice\PhpWord\Metadata\Settings
     */
    public function getSettings()
    {
        return $this->phpWord->getSettings();
    }

    /**
     * Get document properties/metadata
     * 
     * @return \PhpOffice\PhpWord\Metadata\DocInfo
     */
    public function getDocInfo()
    {
        return $this->phpWord->getDocInfo();
    }
}
