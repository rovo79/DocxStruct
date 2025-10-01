<?php

namespace DocxConverter\Tests\Integration;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Readers\DocxReader;
use DocxConverter\Transformers\HtmlTransformer;
use DocxConverter\Transformers\JsonTransformer;
use PHPUnit\Framework\TestCase;

class TransformerIntegrationTest extends TestCase
{
    private string $testDocPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDocPath = __DIR__ . '/../Documents/sample-test.docx';
        
        if (!file_exists($this->testDocPath)) {
            $this->markTestSkipped('Test document not found: ' . $this->testDocPath);
        }
    }

    public function testHtmlTransformerWithRealDocument(): void
    {
        $reader = new DocxReader($this->testDocPath);
        $sections = $reader->getSections();
        
        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections, 'Document should have at least one section');
        
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new HtmlTransformer($styleMap, $rules);
        
        $html = $transformer->transform($sections);
        
        $this->assertIsString($html);
        $this->assertNotEmpty($html, 'HTML output should not be empty');
        
        // Verify HTML contains expected elements
        $this->assertStringContainsString('<p', $html, 'HTML should contain paragraph tags');
    }

    public function testJsonTransformerWithRealDocument(): void
    {
        $reader = new DocxReader($this->testDocPath);
        $sections = $reader->getSections();
        
        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections, 'Document should have at least one section');
        
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new JsonTransformer($styleMap, $rules);
        
        $json = $transformer->transform($sections);
        
        $this->assertIsString($json);
        $this->assertNotEmpty($json, 'JSON output should not be empty');
        
        // Verify JSON is valid
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, 'Output should be valid JSON');
        $this->assertNotEmpty($decoded, 'JSON should contain data');
    }

    public function testTransformMethodSignatureWithPHPWordSections(): void
    {
        $reader = new DocxReader($this->testDocPath);
        $sections = $reader->getSections();
        
        $this->assertIsArray($sections);
        
        // Verify each section is a PHPWord Section object
        foreach ($sections as $section) {
            $this->assertInstanceOf(
                \PhpOffice\PhpWord\Element\Section::class,
                $section,
                'Each section should be an instance of PHPWord Section'
            );
        }
        
        // Test that transformer accepts this array of sections
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $htmlTransformer = new HtmlTransformer($styleMap, $rules);
        $jsonTransformer = new JsonTransformer($styleMap, $rules);
        
        $html = $htmlTransformer->transform($sections);
        $json = $jsonTransformer->transform($sections);
        
        $this->assertIsString($html);
        $this->assertIsString($json);
    }
}
