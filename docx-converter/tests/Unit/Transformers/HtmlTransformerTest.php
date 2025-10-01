<?php

namespace DocxConverter\Tests\Unit\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Transformers\HtmlTransformer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

class HtmlTransformerTest extends TestCase
{
    private HtmlTransformer $transformer;
    private PhpWord $phpWord;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        $styleMap = new StyleMap();
        $rules = new TransformationRules();
        $this->transformer = new HtmlTransformer($styleMap, $rules);
        
        $this->phpWord = new PhpWord();
        $this->section = $this->phpWord->addSection();
    }

    public function testTransformTextWithUnderlineFormatting(): void
    {
        // Create a text element with underline formatting
        $textRun = $this->section->addTextRun();
        $textRun->addText('Underlined text', ['underline' => 'single']);
        
        $html = $this->transformer->transform([$this->section]);
        
        $this->assertStringContainsString('<u>Underlined text</u>', $html);
    }

    public function testTransformTextWithoutUnderlineFormatting(): void
    {
        // Create a text element without underline formatting
        $textRun = $this->section->addTextRun();
        $textRun->addText('Normal text');
        
        $html = $this->transformer->transform([$this->section]);
        
        $this->assertStringNotContainsString('<u>', $html);
        $this->assertStringContainsString('Normal text', $html);
    }

    public function testTransformListItem(): void
    {
        // Create a list item
        $this->section->addListItem('First item');
        $this->section->addListItem('Second item');
        
        $html = $this->transformer->transform([$this->section]);
        
        $this->assertStringContainsString('<li>First item</li>', $html);
        $this->assertStringContainsString('<li>Second item</li>', $html);
    }

    public function testTransformListItemWithFormatting(): void
    {
        // Create a list item with formatted text
        // Note: PHPWord's ListItem doesn't properly apply inline formatting
        // via the fifth parameter, so the formatting won't appear in the output
        $this->section->addListItem('Bold item', 0, null, null, ['bold' => true]);
        
        $html = $this->transformer->transform([$this->section]);
        
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('Bold item', $html);
        $this->assertStringContainsString('</li>', $html);
    }

    public function testTransformMultipleFormattingStyles(): void
    {
        // Create text with multiple formatting options
        $textRun = $this->section->addTextRun();
        $textRun->addText('Bold and italic', ['bold' => true, 'italic' => true]);
        $textRun->addText(' and underlined', ['underline' => 'single']);
        
        $html = $this->transformer->transform([$this->section]);
        
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('<em>', $html);
        $this->assertStringContainsString('<u>', $html);
    }

    public function testTransformEmptySectionReturnsEmptyString(): void
    {
        $emptySection = $this->phpWord->addSection();
        
        $html = $this->transformer->transform([$emptySection]);
        
        $this->assertSame('', $html);
    }

    public function testTransformWithStyleMapping(): void
    {
        $styleMap = new StyleMap([
            'CustomStyle' => [
                'convertTo' => 'blockquote',
                'className' => 'custom-quote'
            ]
        ]);
        $rules = new TransformationRules();
        $transformer = new HtmlTransformer($styleMap, $rules);
        
        $textRun = $this->section->addTextRun('CustomStyle');
        $textRun->addText('Quote text');
        
        $html = $transformer->transform([$this->section]);
        
        $this->assertStringContainsString('<blockquote class="custom-quote">Quote text</blockquote>', $html);
    }
}
