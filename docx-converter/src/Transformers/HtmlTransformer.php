<?php

namespace DocxConverter\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Utils\ImageExtractor;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Image;

class HtmlTransformer implements TransformerInterface
{
    private StyleMap $styleMap;
    private TransformationRules $transformationRules;
    private ?ImageExtractor $imageExtractor = null;

    public function __construct(StyleMap $styleMap, TransformationRules $transformationRules)
    {
        $this->styleMap = $styleMap;
        $this->transformationRules = $transformationRules;
    }

    /**
     * Set the image extractor for handling images.
     *
     * @param ImageExtractor $imageExtractor
     * @return self
     */
    public function setImageExtractor(ImageExtractor $imageExtractor): self
    {
        $this->imageExtractor = $imageExtractor;
        return $this;
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
        
        // Process inline elements (text with formatting and images)
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->formatInlineText($element);
            } elseif ($element instanceof Image) {
                $html .= $this->transformImage($element);
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
        // Check for underline style (getUnderline returns the underline type or null)
        if ($fontStyle->getUnderline() !== null && $fontStyle->getUnderline() !== 'none') {
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
        
        // ListItem has a single Text object accessible via getTextObject()
        $textObject = $listItem->getTextObject();
        if ($textObject instanceof Text) {
            $html .= $this->formatInlineText($textObject);
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

    /**
     * Transform a PHPWord Image element to HTML.
     *
     * @param Image $image
     * @return string HTML img tag
     */
    private function transformImage(Image $image): string
    {
        // Get image source
        $source = $image->getSource();
        
        // Use ImageExtractor if available
        if ($this->imageExtractor !== null) {
            $extractedPath = $this->imageExtractor->extract($source);
            if ($extractedPath !== null) {
                $source = $extractedPath;
            }
        }
        
        // Get image style for width/height
        $style = $image->getStyle();
        $attributes = '';
        
        if ($style !== null) {
            $width = $style->getWidth();
            $height = $style->getHeight();
            
            if ($width !== null) {
                $attributes .= ' width="' . htmlspecialchars((string)$width) . '"';
            }
            if ($height !== null) {
                $attributes .= ' height="' . htmlspecialchars((string)$height) . '"';
            }
        }
        
        // Get image name/alt text if available
        $name = $image->getName();
        $altText = $name ? htmlspecialchars($name) : '';
        
        return '<img src="' . htmlspecialchars($source) . '" alt="' . $altText . '"' . $attributes . '>';
    }
}
