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
    private bool $debug;

    public function __construct(StyleMap $styleMap, TransformationRules $transformationRules, bool $debug = false)
    {
        $this->styleMap = $styleMap;
        $this->transformationRules = $transformationRules;
        $this->debug = $debug;
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
        $elements = $section->getElements();
        $i = 0;
        $count = count($elements);
        
        while ($i < $count) {
            $element = $elements[$i];
            
            // Check if this is a list item and gather consecutive list items
            if ($element instanceof TextRun && $this->isListItem($element)) {
                $html .= $this->transformListGroup($elements, $i);
                // $i is updated by reference in transformListGroup
            } else {
                $html .= $this->transformElement($element);
                $i++;
            }
        }
        
        return $html;
    }
    
    /**
     * Check if a TextRun should be treated as a list item
     */
    private function isListItem(TextRun $textRun): bool
    {
        $style = $textRun->getParagraphStyle();
        $styleId = '';
        
        if (is_string($style)) {
            $styleId = $style;
        } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
            $styleId = $style->getStyleName() ?? '';
        }
        
        // Check if style map indicates this should be a list
        $config = $this->styleMap->getOutputConfig($styleId);
        if ($config && isset($config['convertTo']) && $config['convertTo'] === 'list') {
            return true;
        }
        
        // Check if this is a variant of a mapped list style (e.g., ListParagraph2, ListParagraph3)
        // Remove trailing numbers and check the base style
        $baseStyleId = preg_replace('/\d+$/', '', $styleId);
        if ($baseStyleId !== $styleId) {
            $baseConfig = $this->styleMap->getOutputConfig($baseStyleId);
            if ($baseConfig && isset($baseConfig['convertTo']) && $baseConfig['convertTo'] === 'list') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Transform a group of consecutive list items into a wrapped list
     */
    private function transformListGroup(array $elements, int &$startIndex): string
    {
        $listItems = [];
        $i = $startIndex;
        $count = count($elements);
        
        // Gather consecutive list items with their levels
        while ($i < $count && $elements[$i] instanceof TextRun && $this->isListItem($elements[$i])) {
            $textRun = $elements[$i];
            $style = $textRun->getParagraphStyle();
            $styleId = '';
            
            if (is_string($style)) {
                $styleId = $style;
            } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                $styleId = $style->getStyleName() ?? '';
            }
            
            // Try to get config for this style, or fall back to base style
            $config = $this->styleMap->getOutputConfig($styleId);
            if (!$config) {
                $baseStyleId = preg_replace('/\d+$/', '', $styleId);
                $config = $this->styleMap->getOutputConfig($baseStyleId);
            }
            
            $listType = $config['listType'] ?? 'ul';
            $level = $this->detectListLevel($styleId);
            
            $listItems[] = [
                'element' => $textRun,
                'styleId' => $styleId,
                'level' => $level,
                'listType' => $listType,
                'config' => $config
            ];
            
            $i++;
        }
        
        // Update the index for the caller
        $startIndex = $i;
        
        // Build nested list HTML
        return $this->buildNestedList($listItems);
    }
    
    /**
     * Detect list nesting level from style ID (e.g., ListParagraph2 = level 2)
     */
    private function detectListLevel(string $styleId): int
    {
        // Check for numeric suffix indicating level
        if (preg_match('/(\d+)$/', $styleId, $matches)) {
            return (int)$matches[1];
        }
        
        // Default to level 1
        return 1;
    }
    
    /**
     * Build nested list HTML from flat list of items with levels
     */
    private function buildNestedList(array $listItems): string
    {
        if (empty($listItems)) {
            return '';
        }
        
        $html = '';
        $stack = []; // Stack to track open tags (list types and 'li')
        $currentLevel = 0;
        
        foreach ($listItems as $index => $item) {
            $level = $item['level'];
            $listType = $item['listType'];
            $config = $item['config'];
            $styleId = $item['styleId'];
            
            // Look ahead to see if next item is deeper (has children)
            $hasChildren = false;
            if (isset($listItems[$index + 1])) {
                $nextLevel = $listItems[$index + 1]['level'];
                $hasChildren = $nextLevel > $level;
            }
            
            // Build classes for the list container (only on the outermost ul/ol)
            $containerClasses = [];
            if ($config && !empty($config['className'])) {
                $containerClasses[] = $config['className'];
            }
            
            // Build classes for the list item
            $itemClasses = [];
            if ($styleId) {
                $itemClasses[] = $this->normalizeStyleIdForClass($styleId);
            }
            
            $itemClassAttr = !empty($itemClasses) ? ' class="' . htmlspecialchars(implode(' ', $itemClasses)) . '"' : '';
            
            // Close lists and list items if we're going back to a lower level
            while ($currentLevel > $level) {
                $closingTag = array_pop($stack);
                if ($closingTag === 'li') {
                    $indent = str_repeat('  ', max(0, $currentLevel));
                    $html .= "{$indent}</li>\n";
                } else {
                    $indent = str_repeat('  ', max(0, $currentLevel - 1));
                    $html .= "{$indent}</{$closingTag}>\n";
                }
                $currentLevel--;
            }
            
            // Open new nested list if we're going deeper
            while ($currentLevel < $level) {
                // For the first level, include container classes
                if ($currentLevel === 0 && !empty($containerClasses)) {
                    $containerClassAttr = ' class="' . htmlspecialchars(implode(' ', $containerClasses)) . '"';
                    $indent = str_repeat('  ', max(0, $currentLevel));
                    $html .= "{$indent}<{$listType}{$containerClassAttr}>\n";
                } else {
                    // Nested list - add newline and indent
                    $indent = str_repeat('  ', max(0, $currentLevel));
                    $html .= "\n{$indent}<{$listType}>\n";
                }
                $stack[] = $listType;
                $currentLevel++;
            }
            
            // Add the list item content
            $content = '';
            foreach ($item['element']->getElements() as $textElement) {
                if ($textElement instanceof Text) {
                    $content .= $this->formatInlineText($textElement);
                }
            }
            
            $indent = str_repeat('  ', max(0, $currentLevel));
            $html .= "{$indent}<li{$itemClassAttr}>{$content}";
            
            // If this item has children, keep the <li> tag open
            if ($hasChildren) {
                $stack[] = 'li';
            } else {
                // Close the li tag immediately if no children
                $html .= "</li>\n";
            }
        }
        
        // Close any remaining open tags
        while (!empty($stack)) {
            $closingTag = array_pop($stack);
            $currentLevel--;
            if ($closingTag === 'li') {
                $indent = str_repeat('  ', max(0, $currentLevel + 1));
                $html .= "{$indent}</li>\n";
            } else {
                $indent = str_repeat('  ', max(0, $currentLevel));
                $html .= "{$indent}</{$closingTag}>\n";
            }
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
        $styleId = '';
        
        if (is_string($style)) {
            $styleId = $style;
        } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
            $styleId = $style->getStyleName() ?? '';
        }

        if ($this->debug) {
            // Print debug info to STDERR
            $msg = '[DEBUG] TextRun styleId: ' . var_export($styleId, true);
            $msg .= ' | Style object type: ' . (is_object($style) ? get_class($style) : gettype($style));
            $config = $this->styleMap->getOutputConfig($styleId);
            if ($config) {
                $msg .= ' | StyleMap: ' . json_encode($config);
            } else {
                $msg .= ' | StyleMap: (none)';
            }
            file_put_contents('php://stderr', $msg . "\n");
        }

        // Check for custom transformation rule
        $customRule = $this->transformationRules->getRuleFor('paragraphs', $styleId);
        if ($customRule && is_callable($customRule)) {
            return $customRule($textRun, ['styleId' => $styleId]);
        }

        // Check for style mapping (e.g., convert to blockquote, heading)
        $config = $this->styleMap->getOutputConfig($styleId);
        if ($config && isset($config['convertTo'])) {
            // Note: 'list' type is handled in transformSection, not here
            if ($config['convertTo'] !== 'list') {
                return $this->convertElement($textRun, $config);
            }
        }

        // Default: output as paragraph with style ID as class
        $classes = [];
        
        // Add mapped class names if they exist
        $mappedClasses = $this->styleMap->getClassNames($styleId);
        if ($mappedClasses) {
            $classes[] = $mappedClasses;
        }
        
        // Always add the style ID itself as a class (kebab-case for CSS)
        if ($styleId) {
            $classes[] = $this->normalizeStyleIdForClass($styleId);
        }
        
        $attributes = !empty($classes) ? ' class="' . htmlspecialchars(implode(' ', $classes)) . '"' : '';

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
    
    /**
     * Normalize style ID to a valid CSS class name (kebab-case)
     */
    private function normalizeStyleIdForClass(string $styleId): string
    {
        // Convert camelCase or PascalCase to kebab-case
        $normalized = preg_replace('/([a-z])([A-Z])/', '$1-$2', $styleId);
        $normalized = strtolower($normalized);
        
        // Replace any non-alphanumeric characters (except hyphens) with hyphens
        $normalized = preg_replace('/[^a-z0-9-]/', '-', $normalized);
        
        // Remove consecutive hyphens
        $normalized = preg_replace('/-+/', '-', $normalized);
        
        // Remove leading/trailing hyphens
        $normalized = trim($normalized, '-');
        
        return $normalized;
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
        
        // Get style ID for preservation
        $style = $element->getParagraphStyle();
        $styleId = '';
        if (is_string($style)) {
            $styleId = $style;
        } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
            $styleId = $style->getStyleName() ?? '';
        }
        
        // Build class attribute with both mapped classes and style ID
        $classes = [];
        if (!empty($config['className'])) {
            $classes[] = $config['className'];
        }
        if ($styleId) {
            $classes[] = $this->normalizeStyleIdForClass($styleId);
        }
        
        $classAttr = !empty($classes) ? ' class="' . htmlspecialchars(implode(' ', $classes)) . '"' : '';
        
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
            'list' => "<li{$classAttr}>{$content}</li>\n",
            'heading' => $this->convertToHeading($content, $config, $classAttr),
            default => "<p{$classAttr}>{$content}</p>\n"
        };
    }

    private function convertToHeading(string $content, array $config, string $classAttr): string
    {
        $level = $config['level'] ?? 2;
        $level = max(1, min(6, $level)); // Clamp between 1 and 6
        return "<h{$level}{$classAttr}>{$content}</h{$level}>\n";
    }
}
