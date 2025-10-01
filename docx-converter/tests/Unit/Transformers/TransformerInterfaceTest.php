<?php

namespace DocxConverter\Tests\Unit\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Transformers\HtmlTransformer;
use DocxConverter\Transformers\JsonTransformer;
use DocxConverter\Transformers\TransformerInterface;
use PHPUnit\Framework\TestCase;

class TransformerInterfaceTest extends TestCase
{
    public function testHtmlTransformerImplementsInterface(): void
    {
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new HtmlTransformer($styleMap, $rules);
        
        $this->assertInstanceOf(TransformerInterface::class, $transformer);
    }

    public function testJsonTransformerImplementsInterface(): void
    {
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new JsonTransformer($styleMap, $rules);
        
        $this->assertInstanceOf(TransformerInterface::class, $transformer);
    }

    public function testTransformMethodAcceptsArrayParameter(): void
    {
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new HtmlTransformer($styleMap, $rules);
        
        // Test with empty array
        $result = $transformer->transform([]);
        $this->assertIsString($result);
        $this->assertSame('', $result);
    }

    public function testTransformMethodReturnsString(): void
    {
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $transformer = new JsonTransformer($styleMap, $rules);
        
        // Test with empty array
        $result = $transformer->transform([]);
        $this->assertIsString($result);
        
        // JSON transformer should return valid JSON
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
    }
}
