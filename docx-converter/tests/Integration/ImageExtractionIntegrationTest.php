<?php

namespace DocxConverter\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ImageExtractionIntegrationTest extends TestCase
{
    private string $testDocxPath;
    private string $tempOutputFile;
    private string $tempAssetsDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use the test document with images
        $this->testDocxPath = __DIR__ . '/../Documents/test-with-images.docx';
        
        // Create temporary paths
        $uniqueId = uniqid();
        $this->tempOutputFile = sys_get_temp_dir() . '/phpunit-integration-output-' . $uniqueId . '.html';
        $this->tempAssetsDir = sys_get_temp_dir() . '/phpunit-integration-assets-' . $uniqueId;
        
        if (!file_exists($this->testDocxPath)) {
            $this->markTestSkipped('Test document not found: ' . $this->testDocxPath);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up temporary output file
        if (file_exists($this->tempOutputFile)) {
            unlink($this->tempOutputFile);
        }
        
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

    public function testCliConvertsDocxWithAssetsDir(): void
    {
        // Get the path to the CLI script
        $cliScript = __DIR__ . '/../../bin/docx-converter';
        $this->assertFileExists($cliScript, 'CLI script should exist');
        
        // Run the conversion command
        $process = new Process([
            'php',
            $cliScript,
            'convert',
            $this->testDocxPath,
            '-o', $this->tempOutputFile,
            '--assets-dir', $this->tempAssetsDir
        ]);
        
        $process->run();
        
        // Check that the command succeeded
        $this->assertTrue(
            $process->isSuccessful(),
            'CLI command should succeed. Output: ' . $process->getOutput() . ' Error: ' . $process->getErrorOutput()
        );
        
        // Check that the output file was created
        $this->assertFileExists($this->tempOutputFile, 'Output HTML file should be created');
        
        // Check that the assets directory was created
        $this->assertDirectoryExists($this->tempAssetsDir, 'Assets directory should be created');
        
        // Check that image files were extracted
        $imageFiles = glob($this->tempAssetsDir . '/*.png');
        $this->assertNotEmpty($imageFiles, 'Should have at least one image file in assets directory');
        
        // Check that manifest was created
        $manifestPath = $this->tempAssetsDir . '/assets-manifest.json';
        $this->assertFileExists($manifestPath, 'Manifest file should be created');
        
        // Verify manifest content
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        $this->assertIsArray($manifest, 'Manifest should be valid JSON');
        $this->assertNotEmpty($manifest, 'Manifest should not be empty');
    }

    public function testHtmlContainsImageReferences(): void
    {
        // Get the path to the CLI script
        $cliScript = __DIR__ . '/../../bin/docx-converter';
        
        // Run the conversion command
        $process = new Process([
            'php',
            $cliScript,
            'convert',
            $this->testDocxPath,
            '-o', $this->tempOutputFile,
            '--assets-dir', $this->tempAssetsDir
        ]);
        
        $process->run();
        
        $this->assertTrue($process->isSuccessful(), 'CLI command should succeed');
        
        // Read the HTML output
        $htmlContent = file_get_contents($this->tempOutputFile);
        
        // Check that HTML contains img tags
        $this->assertStringContainsString('<img', $htmlContent, 'HTML should contain img tags');
        
        // Check that img tags reference files in the assets directory
        $this->assertMatchesRegularExpression(
            '/src="[^"]*\.png"/',
            $htmlContent,
            'HTML should contain img tags with PNG sources'
        );
        
        // Verify that paths are relative
        $assetsDirName = basename($this->tempAssetsDir);
        $this->assertStringContainsString(
            $assetsDirName,
            $htmlContent,
            'HTML should reference assets using directory name'
        );
    }

    public function testDeduplicationInIntegration(): void
    {
        // Get the path to the CLI script
        $cliScript = __DIR__ . '/../../bin/docx-converter';
        
        // Run the conversion command
        $process = new Process([
            'php',
            $cliScript,
            'convert',
            $this->testDocxPath,
            '-o', $this->tempOutputFile,
            '--assets-dir', $this->tempAssetsDir
        ]);
        
        $process->run();
        
        $this->assertTrue($process->isSuccessful(), 'CLI command should succeed');
        
        // Check that only one image file was created (despite being used twice in the document)
        $imageFiles = glob($this->tempAssetsDir . '/*.png');
        $this->assertCount(1, $imageFiles, 'Should have exactly one image file (deduplication working)');
        
        // Read the HTML output
        $htmlContent = file_get_contents($this->tempOutputFile);
        
        // Count the number of img tags (should be more than one since image is used twice)
        $imgCount = substr_count($htmlContent, '<img');
        $this->assertGreaterThanOrEqual(2, $imgCount, 'HTML should contain at least 2 img tags');
        
        // Verify both img tags reference the same file
        preg_match_all('/src="([^"]+)"/', $htmlContent, $matches);
        $sources = $matches[1];
        
        // Filter to only image sources
        $imageSources = array_filter($sources, function($src) {
            return strpos($src, '.png') !== false;
        });
        
        // All image sources should be the same (deduplication)
        $uniqueSources = array_unique($imageSources);
        $this->assertCount(
            1,
            $uniqueSources,
            'All image references should point to the same file (deduplication)'
        );
    }

    public function testConversionWithoutAssetsDir(): void
    {
        // Get the path to the CLI script
        $cliScript = __DIR__ . '/../../bin/docx-converter';
        
        // Run the conversion command WITHOUT --assets-dir
        $process = new Process([
            'php',
            $cliScript,
            'convert',
            $this->testDocxPath,
            '-o', $this->tempOutputFile
        ]);
        
        $process->run();
        
        $this->assertTrue($process->isSuccessful(), 'CLI command should succeed even without assets dir');
        
        // Check that the output file was created
        $this->assertFileExists($this->tempOutputFile, 'Output HTML file should be created');
        
        // Read the HTML output
        $htmlContent = file_get_contents($this->tempOutputFile);
        
        // HTML should still contain img tags (with zip:// URLs or similar)
        $this->assertStringContainsString('<img', $htmlContent, 'HTML should contain img tags');
    }
}
