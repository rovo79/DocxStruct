<?php

namespace DocxConverter\Utils;

class ImageExtractor
{
    private string $docxPath;
    private string $assetsDir;

    public function __construct(string $docxPath, string $assetsDir)
    {
        $this->docxPath = $docxPath;
        $this->assetsDir = rtrim($assetsDir, DIRECTORY_SEPARATOR);

        if (!file_exists($this->assetsDir)) {
            mkdir($this->assetsDir, 0775, true);
        }
    }

    /**
     * Extract images from the docx package and return local path for a provided internal image path if found.
     * If the image was already extracted, returns the existing path.
     *
     * @param string $internalPath e.g. 'word/media/image1.png' or a path returned by PHPWord image->getSource()
     * @return string|null Local filesystem path to extracted asset or null if not extractable
     */
    public function extract(string $internalPath): ?string
    {
        // If the path is already a filesystem path, simply return it if it exists
        if (file_exists($internalPath)) {
            return $internalPath;
        }

        // If docx source doesn't exist, can't extract
        if (!file_exists($this->docxPath)) {
            return null;
        }

        // Open the docx (zip) and locate the internal path
        $zip = new \ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return null;
        }

        // Normalize internal path: many PHPWord Image sources are just filenames (e.g., 'image1.png') or full 'word/media/image1.png'
        $candidates = [$internalPath];
        if (!str_starts_with($internalPath, 'word/')) {
            $candidates[] = 'word/media/' . ltrim($internalPath, '/');
        }

        $foundIndex = null;
        foreach ($candidates as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                $foundIndex = $candidate;
                break;
            }
        }

        if ($foundIndex === null) {
            $zip->close();
            return null;
        }

        $stream = $zip->getStream($foundIndex);
        if ($stream === false) {
            $zip->close();
            return null;
        }

        $contents = stream_get_contents($stream);
        fclose($stream);

        // Name the file by sha1 of content to avoid duplicates, keep original extension
        $ext = pathinfo($foundIndex, PATHINFO_EXTENSION) ?: 'bin';
        $hash = sha1($contents);
        $filename = $hash . '.' . $ext;
        $localPath = $this->assetsDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($localPath)) {
            file_put_contents($localPath, $contents);
        }

        $zip->close();

        return $localPath;
    }
}
