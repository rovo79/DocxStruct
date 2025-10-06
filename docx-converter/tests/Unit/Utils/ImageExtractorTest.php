<?php

namespace DocxConverter\Tests\Unit\Utils;

use DocxConverter\Utils\ImageExtractor;
use PHPUnit\Framework\TestCase;

class ImageExtractorTest extends TestCase
{
    private string $testDocxPath;
    private string $tempAssetsDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use the test document with images
        $this->testDocxPath = __DIR__ . '/../../Documents/test-with-images.docx';
        
        // Create a temporary assets directory
        $this->tempAssetsDir = sys_get_temp_dir() . '/phpunit-image-test-' . uniqid();
        
        if (!file_exists($this->testDocxPath)) {
            $this->markTestSkipped('Test document not found: ' . $this->testDocxPath);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up temporary assets directory
        if (is_dir($this->tempAssetsDir)) {
            $files = glob($this->tempAssetsDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempAssetsDir);
        }
    }

    public function testExtractImageFromDocx(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Extract an image
        $result = $extractor->extract('word/media/section_image1.png');
        
        $this->assertNotNull($result, 'Extracted image path should not be null');
        $this->assertStringContainsString('.png', $result, 'Result should contain .png extension');
        
        // Check that the file was actually created
        $files = glob($this->tempAssetsDir . '/*.png');
        $this->assertCount(1, $files, 'Should have exactly one PNG file in assets directory');
    }

    public function testExtractImageFromZipUrl(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Test with zip:// URL format (as PHPWord provides)
        $zipUrl = 'zip://' . $this->testDocxPath . '#word/media/section_image1.png';
        $result = $extractor->extract($zipUrl);
        
        $this->assertNotNull($result, 'Extracted image path should not be null');
        
        // Check that the file was created
        $files = glob($this->tempAssetsDir . '/*.png');
        $this->assertCount(1, $files, 'Should have exactly one PNG file in assets directory');
    }

    public function testManifestCreation(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Extract an image
        $extractor->extract('word/media/section_image1.png');
        
        // Check that manifest was created
        $manifestPath = $this->tempAssetsDir . '/assets-manifest.json';
        $this->assertFileExists($manifestPath, 'Manifest file should be created');
        
        // Check manifest content
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        $this->assertIsArray($manifest, 'Manifest should be a valid JSON array');
        $this->assertCount(1, $manifest, 'Manifest should have one entry');
        $this->assertArrayHasKey('contentHash', $manifest[0], 'Manifest entry should have contentHash');
        $this->assertArrayHasKey('filename', $manifest[0], 'Manifest entry should have filename');
        $this->assertArrayHasKey('internalPaths', $manifest[0], 'Manifest entry should have internalPaths');
    }

    public function testDeduplication(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Extract the same image twice
        $result1 = $extractor->extract('word/media/section_image1.png');
        $result2 = $extractor->extract('word/media/section_image1.png');
        
        // Both should return the same path
        $this->assertEquals($result1, $result2, 'Same image should return the same path');
        
        // Should only have one file
        $files = glob($this->tempAssetsDir . '/*.png');
        $this->assertCount(1, $files, 'Should have only one PNG file despite extracting twice');
        
        // Check manifest has one entry with the path listed
        $manifestPath = $this->tempAssetsDir . '/assets-manifest.json';
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        $this->assertCount(1, $manifest, 'Manifest should have one entry');
        $this->assertContains('word/media/section_image1.png', $manifest[0]['internalPaths']);
    }

    public function testRelativePathGeneration(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Set an output file path in a different directory
        $outputFilePath = sys_get_temp_dir() . '/output/test.html';
        $extractor->setOutputFilePath($outputFilePath);
        
        // Extract an image
        $result = $extractor->extract('word/media/section_image1.png');
        
        $this->assertNotNull($result, 'Extracted image path should not be null');
        
        // Result should be a relative path
        $this->assertStringNotContainsString($this->tempAssetsDir, $result, 'Result should not contain absolute path');
        $this->assertStringStartsWith('..', $result, 'Relative path should start with ..');
    }

    public function testAbsolutePathWhenNoOutputFileSet(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Don't set output file path
        
        // Extract an image
        $result = $extractor->extract('word/media/section_image1.png');
        
        $this->assertNotNull($result, 'Extracted image path should not be null');
        
        // Result should be an absolute path
        $this->assertStringContainsString($this->tempAssetsDir, $result, 'Result should contain absolute path');
    }

    public function testParseRelationships(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        
        $relationships = $extractor->parseRelationships();
        
        $this->assertIsArray($relationships, 'Relationships should be an array');
        $this->assertNotEmpty($relationships, 'Should have at least one image relationship');
        
        // Check that at least one relationship points to an image
        $hasImageRelationship = false;
        foreach ($relationships as $rId => $target) {
            if (strpos($target, 'media/') !== false && strpos($target, '.png') !== false) {
                $hasImageRelationship = true;
                break;
            }
        }
        
        $this->assertTrue($hasImageRelationship, 'Should have at least one image relationship');
    }

    public function testExtractNonexistentImage(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        $extractor->setAssetsDir($this->tempAssetsDir);
        
        // Try to extract a non-existent image
        $result = $extractor->extract('word/media/nonexistent.png');
        
        $this->assertNull($result, 'Should return null for non-existent image');
    }

    public function testExtractWithoutAssetsDir(): void
    {
        $extractor = new ImageExtractor($this->testDocxPath);
        
        // Don't set assets directory
        
        // Try to extract an image
        $result = $extractor->extract('word/media/section_image1.png');
        
        $this->assertNull($result, 'Should return null when assets directory is not set');
    }
}
