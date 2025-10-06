<?php

namespace DocxConverter\Utils;

use ZipArchive;

/**
 * Extracts images from DOCX files and manages asset files with deduplication.
 */
class ImageExtractor
{
    private string $docxPath;
    private ?string $assetsDir = null;
    private ?string $outputFilePath = null;
    private array $manifest = [];
    private const MANIFEST_FILE = 'assets-manifest.json';

    /**
     * @param string $docxPath Path to the DOCX file
     */
    public function __construct(string $docxPath)
    {
        $this->docxPath = $docxPath;
    }

    /**
     * Set the assets directory where images will be extracted.
     *
     * @param string $assetsDir Path to assets directory
     * @return self
     */
    public function setAssetsDir(string $assetsDir): self
    {
        $this->assetsDir = $assetsDir;
        
        // Create directory if it doesn't exist
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }
        
        // Load existing manifest if available
        $this->loadManifest();
        
        return $this;
    }

    /**
     * Set the output file path (used to compute relative paths).
     *
     * @param string $outputFilePath Path to the output HTML file
     * @return self
     */
    public function setOutputFilePath(string $outputFilePath): self
    {
        $this->outputFilePath = $outputFilePath;
        return $this;
    }

    /**
     * Extract an image from the DOCX and return the path to use in HTML.
     *
     * @param string $internalPath Internal DOCX path (e.g., "word/media/image1.png") or zip URL
     * @return string|null The path to use in HTML (relative if output file set, otherwise absolute)
     */
    public function extract(string $internalPath): ?string
    {
        if ($this->assetsDir === null) {
            return null;
        }

        // Handle zip:// URLs from PHPWord
        if (strpos($internalPath, 'zip://') === 0) {
            // Extract the internal path from zip URL
            // Format: zip:///path/to/file.docx#word/media/image1.png
            $parts = explode('#', $internalPath);
            if (count($parts) === 2) {
                $internalPath = $parts[1];
            }
        }

        // Check if this image is already in the manifest
        $existingFile = $this->findInManifest($internalPath);
        if ($existingFile !== null) {
            return $this->formatPath($existingFile);
        }

        // Extract the image from DOCX
        $imageData = $this->extractFromZip($internalPath);
        if ($imageData === null) {
            return null;
        }

        // Generate filename based on content hash
        $contentHash = md5($imageData);
        $extension = pathinfo($internalPath, PATHINFO_EXTENSION);
        $filename = $contentHash . '.' . $extension;
        $outputPath = $this->assetsDir . '/' . $filename;

        // Write the file if it doesn't exist
        if (!file_exists($outputPath)) {
            file_put_contents($outputPath, $imageData);
        }

        // Update manifest
        $this->addToManifest($internalPath, $contentHash, $filename);

        return $this->formatPath($filename);
    }

    /**
     * Extract image data from the DOCX zip archive.
     *
     * @param string $internalPath Internal path within the DOCX
     * @return string|null The image data or null if not found
     */
    private function extractFromZip(string $internalPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return null;
        }

        $imageData = $zip->getFromName($internalPath);
        $zip->close();

        return $imageData !== false ? $imageData : null;
    }

    /**
     * Load the manifest from the assets directory.
     */
    private function loadManifest(): void
    {
        $manifestPath = $this->assetsDir . '/' . self::MANIFEST_FILE;
        if (file_exists($manifestPath)) {
            $content = file_get_contents($manifestPath);
            $this->manifest = json_decode($content, true) ?? [];
        }
    }

    /**
     * Save the manifest to the assets directory.
     */
    private function saveManifest(): void
    {
        if ($this->assetsDir === null) {
            return;
        }

        $manifestPath = $this->assetsDir . '/' . self::MANIFEST_FILE;
        file_put_contents(
            $manifestPath,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Find an image in the manifest by internal path or content hash.
     *
     * @param string $internalPath Internal DOCX path
     * @return string|null The filename if found
     */
    private function findInManifest(string $internalPath): ?string
    {
        foreach ($this->manifest as $entry) {
            if (in_array($internalPath, $entry['internalPaths'] ?? [])) {
                return $entry['filename'];
            }
        }
        return null;
    }

    /**
     * Add an image to the manifest.
     *
     * @param string $internalPath Internal DOCX path
     * @param string $contentHash MD5 hash of the content
     * @param string $filename Output filename
     */
    private function addToManifest(string $internalPath, string $contentHash, string $filename): void
    {
        // Check if we already have this content hash
        $found = false;
        foreach ($this->manifest as &$entry) {
            if ($entry['contentHash'] === $contentHash) {
                // Add this internal path to the existing entry
                if (!in_array($internalPath, $entry['internalPaths'])) {
                    $entry['internalPaths'][] = $internalPath;
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->manifest[] = [
                'contentHash' => $contentHash,
                'filename' => $filename,
                'internalPaths' => [$internalPath]
            ];
        }

        $this->saveManifest();
    }

    /**
     * Format the path for use in HTML (relative or absolute).
     *
     * @param string $filename The asset filename
     * @return string The formatted path
     */
    private function formatPath(string $filename): string
    {
        $assetPath = $this->assetsDir . '/' . $filename;

        // If output file path is set, return relative path
        if ($this->outputFilePath !== null) {
            $outputDir = dirname(realpath($this->outputFilePath) ?: $this->outputFilePath);
            $assetsRealPath = realpath($this->assetsDir) ?: $this->assetsDir;
            
            // Compute relative path
            $relativePath = $this->getRelativePath($outputDir, $assetsRealPath);
            return $relativePath . '/' . $filename;
        }

        // Otherwise return absolute path
        return $assetPath;
    }

    /**
     * Compute relative path from one directory to another.
     *
     * @param string $from Starting directory
     * @param string $to Target directory
     * @return string Relative path
     */
    private function getRelativePath(string $from, string $to): string
    {
        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);
        
        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);
        
        // Find common base
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }
        
        // Build relative path
        $relativeParts = [];
        
        // Add .. for each remaining part in from
        for ($i = $commonLength; $i < count($fromParts); $i++) {
            $relativeParts[] = '..';
        }
        
        // Add remaining parts from to
        for ($i = $commonLength; $i < count($toParts); $i++) {
            $relativeParts[] = $toParts[$i];
        }
        
        return count($relativeParts) > 0 ? implode('/', $relativeParts) : '.';
    }

    /**
     * Parse document relationships to get image mappings.
     *
     * @return array Map of relationship IDs to targets (e.g., ['rId7' => 'media/image1.png'])
     */
    public function parseRelationships(): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return [];
        }

        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $zip->close();

        if ($relsXml === false) {
            return [];
        }

        $relationships = [];
        $xml = simplexml_load_string($relsXml);
        if ($xml === false) {
            return [];
        }

        // Register namespace
        $namespaces = $xml->getNamespaces(true);
        $ns = $namespaces[''] ?? 'http://schemas.openxmlformats.org/package/2006/relationships';

        foreach ($xml->children($ns) as $relationship) {
            $attrs = $relationship->attributes();
            $type = (string)$attrs['Type'];
            
            // Only extract image relationships
            if (strpos($type, '/image') !== false) {
                $id = (string)$attrs['Id'];
                $target = (string)$attrs['Target'];
                $relationships[$id] = 'word/' . $target;
            }
        }

        return $relationships;
    }
}
