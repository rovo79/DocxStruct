<?php

namespace DocxConverter\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\ListItem;

class HtmlTransformer implements TransformerInterface
{
    private StyleMap $styleMap;
    private TransformationRules $transformationRules;

    public function __construct(StyleMap $styleMap, TransformationRules $transformationRules)
    {
        $this->styleMap = $styleMap;
        $this->transformationRules = $transformationRules;
    }

    /**
     * Transform an array of PHPWord Section objects to HTML.
     * 
     * @param array $sections Array of \PhpOffice\PhpWord\Element\Section objects
     * @return string The HTML output
     */
    public function transform(array $sections): string
    {
        $html = '';
        
        foreach ($sections as $section) {
            if (!$section instanceof Section) {
                continue;
            }
            
            $html .= $this->transformSection($section);
        }
        
        return $html;
    }

    private function transformSection(Section $section): string
    {
        $html = '';
        
        foreach ($section->getElements() as $element) {
            $html .= $this->transformElement($element);
        }
        
        return $html;
    }

    private function transformElement($element): string
    {
        return match (true) {
            $element instanceof TextRun => $this->transformTextRun($element),
            $element instanceof Text => $this->transformText($element),
            $element instanceof Table => $this->transformTable($element),
            $element instanceof ListItem => $this->transformListItem($element),
            default => '' // Skip unknown elements
        };
    }

    private function transformTextRun(TextRun $textRun): string
    {
        // Get paragraph style ID
        $style = $textRun->getParagraphStyle();
        $styleId = is_string($style) ? $style : '';
        
        // Check for custom transformation rule
        $customRule = $this->transformationRules->getRuleFor('paragraphs', $styleId);
        if ($customRule && is_callable($customRule)) {
            return $customRule($textRun, ['styleId' => $styleId]);
        }
        
        // Check for style mapping (e.g., convert to blockquote)
        $config = $this->styleMap->getOutputConfig($styleId);
        if ($config && isset($config['convertTo'])) {
            return $this->convertElement($textRun, $config);
        }
        
        // Default: output as paragraph
        $classes = $this->styleMap->getClassNames($styleId);
        $attributes = $classes ? ' class="' . htmlspecialchars($classes) . '"' : '';
        
        $html = "<p{$attributes}>";
        
        // Process inline elements (text with formatting)
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->formatInlineText($element);
            }
        }
        
        $html .= "</p>\n";
        
        return $html;
    }

    private function transformText(Text $text): string
    {
        return "<p>" . $this->formatInlineText($text) . "</p>\n";
    }

    private function formatInlineText(Text $text): string
    {
        $content = htmlspecialchars($text->getText());
        $fontStyle = $text->getFontStyle();
        
        if (!$fontStyle) {
            return $content;
        }
        
        // Apply inline formatting
        if ($fontStyle->isBold()) {
            $content = "<strong>{$content}</strong>";
        }
        if ($fontStyle->isItalic()) {
            $content = "<em>{$content}</em>";
        }
        if ($fontStyle->isUnderline()) {
            $content = "<u>{$content}</u>";
        }
        
        return $content;
    }

    private function transformTable(Table $table): string
    {
        $html = "<table>\n";
        
        foreach ($table->getRows() as $row) {
            $html .= "  <tr>\n";
            
            foreach ($row->getCells() as $cell) {
                // Get grid span from PHPWord
                $gridSpan = $cell->getStyle()->getGridSpan() ?? 1;
                $colspanAttr = $gridSpan > 1 ? ' colspan="' . $gridSpan . '"' : '';
                
                $html .= "    <td{$colspanAttr}>";
                
                // Process cell content
                foreach ($cell->getElements() as $element) {
                    if ($element instanceof Text) {
                        $html .= htmlspecialchars($element->getText());
                    } elseif ($element instanceof TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof Text) {
                                $html .= $this->formatInlineText($textElement);
                            }
                        }
                    }
                }
                
                $html .= "</td>\n";
            }
            
            $html .= "  </tr>\n";
        }
        
        $html .= "</table>\n";
        
        return $html;
    }

    private function transformListItem(ListItem $listItem): string
    {
        // Simple list item handling
        $html = "<li>";
        
        foreach ($listItem->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= htmlspecialchars($element->getText());
            }
        }
        
        $html .= "</li>\n";
        
        return $html;
    }

    private function convertElement(TextRun $element, array $config): string
    {
        $convertTo = $config['convertTo'];
        $className = $config['className'] ?? '';
        $classAttr = $className ? ' class="' . htmlspecialchars($className) . '"' : '';
        
        // Extract text content
        $content = '';
        foreach ($element->getElements() as $textElement) {
            if ($textElement instanceof Text) {
                $content .= $this->formatInlineText($textElement);
            }
        }
        
        return match ($convertTo) {
            'blockquote' => "<blockquote{$classAttr}>{$content}</blockquote>\n",
            'div' => "<div{$classAttr}>{$content}</div>\n",
            default => "<p{$classAttr}>{$content}</p>\n"
        };
    }
}
