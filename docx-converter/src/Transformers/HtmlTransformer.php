<?php

namespace DocxConverter\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Readers\DocxReader;
use DocxConverter\Utils\ImageExtractor;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Footnote;
use PhpOffice\PhpWord\Element\Endnote;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\TextBreak;

class HtmlTransformer implements TransformerInterface
{
    private StyleMap $styleMap;
    private TransformationRules $transformationRules;
    private bool $debug;
    private ?DocxReader $reader = null;
    private string $assetsDir = '';
    private ?ImageExtractor $extractor = null;
    
    /**
     * Collected footnotes for rendering at the end
     * @var array
     */
    private array $footnotes = [];
    
    /**
     * Collected endnotes for rendering at the end
     * @var array
     */
    private array $endnotes = [];

    public function __construct(StyleMap $styleMap, TransformationRules $transformationRules, bool $debug = false, ?DocxReader $reader = null, string $assetsDir = '')
    {
        $this->styleMap = $styleMap;
        $this->transformationRules = $transformationRules;
        $this->debug = $debug;
        $this->reader = $reader;
        $this->assetsDir = $assetsDir ?: '';

        if ($this->reader && $this->assetsDir) {
            // If assets dir provided, initialize extractor with source docx path
            try {
                $this->extractor = new ImageExtractor($this->reader->getSourcePath(), $this->assetsDir);
            } catch (\Throwable $e) {
                // Fail silently - image extraction remains disabled
                $this->extractor = null;
            }
        }
    }

    /**
     * Transform an array of PHPWord Section objects to HTML.
     * 
     * @param array $sections Array of \PhpOffice\PhpWord\Element\Section objects
     * @return string The HTML output
     */
    public function transform(array $sections): string
    {
        // Reset footnote/endnote collections for this transform
        $this->footnotes = [];
        $this->endnotes = [];
        
        // Early exit: if there is no content in any section, return empty string
        $hasContent = false;
        foreach ($sections as $section) {
            if (!$section instanceof Section) {
                continue;
            }
            if (!empty($section->getElements())) {
                $hasContent = true;
                break;
            }
            // Check headers/footers for any elements
            foreach ($section->getHeaders() as $header) {
                if (!empty($header->getElements())) {
                    $hasContent = true;
                    break 2; // break out of both loops
                }
            }
            foreach ($section->getFooters() as $footer) {
                if (!empty($footer->getElements())) {
                    $hasContent = true;
                    break 2;
                }
            }
        }
        if (!$hasContent) {
            return '';
        }

        $html = '';
        
        // Add HTML document structure
        $html .= "<!DOCTYPE html>\n";
        $html .= "<html lang=\"en\">\n";
        $html .= "<head>\n";
        $html .= "  <meta charset=\"UTF-8\">\n";
        $html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "  <title>Document</title>\n";
        $html .= "</head>\n";
        $html .= "<body>\n\n";
        
        foreach ($sections as $section) {
            if (!$section instanceof Section) {
                continue;
            }
            
            $html .= $this->transformSection($section);
        }
        
        // Append collected footnotes at the end
        if (!empty($this->footnotes)) {
            $html .= $this->renderFootnotes();
        }
        
        // Append collected endnotes at the end
        if (!empty($this->endnotes)) {
            $html .= $this->renderEndnotes();
        }
        
        $html .= "\n</body>\n";
        $html .= "</html>\n";
        
        return $html;
    }

    private function transformSection(Section $section): string
    {
        $html = '';
        
        // Process headers (if any)
        $headers = $section->getHeaders();
        if (!empty($headers)) {
            foreach ($headers as $headerType => $header) {
                $html .= $this->transformHeaderFooter($header, 'header', $headerType);
            }
        }
        
        // Process main content
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
        
        // Process footers (if any)
        $footers = $section->getFooters();
        if (!empty($footers)) {
            foreach ($footers as $footerType => $footer) {
                $html .= $this->transformHeaderFooter($footer, 'footer', $footerType);
            }
        }
        
        return $html;
    }
    
    /**
     * Transform a header or footer element
     * 
     * @param \PhpOffice\PhpWord\Element\Header|\PhpOffice\PhpWord\Element\Footer $container
     * @param string $type 'header' or 'footer'
     * @param int $pageType 1=first, 2=default, 3=even
     * @return string
     */
    private function transformHeaderFooter($container, string $type, int $pageType): string
    {
        // Map type to class names
        $typeClasses = [
            1 => 'first-page',
            2 => 'default',
            3 => 'even-page'
        ];
        
        $typeClass = $typeClasses[$pageType] ?? 'unknown';
        $html = "<{$type} class=\"{$type}-{$typeClass}\">\n";
        
        // Process elements in header/footer
        foreach ($container->getElements() as $element) {
            $html .= $this->transformElement($element);
        }
        
        $html .= "</{$type}>\n";
        
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
                } elseif ($textElement instanceof Footnote) {
                    $content .= $this->transformFootnote($textElement);
                } elseif ($textElement instanceof Endnote) {
                    $content .= $this->transformEndnote($textElement);
                } elseif ($textElement instanceof Link) {
                    $content .= $this->transformLink($textElement);
                } elseif ($textElement instanceof Image) {
                    $content .= $this->transformImage($textElement);
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
            $element instanceof Title => $this->transformTitle($element),
            $element instanceof Table => $this->transformTable($element),
            $element instanceof ListItem => $this->transformListItem($element),
            $element instanceof Footnote => $this->transformFootnote($element),
            $element instanceof Endnote => $this->transformEndnote($element),
            $element instanceof Link => $this->transformLink($element),
            $element instanceof Image => $this->transformImage($element),
            $element instanceof TextBreak => $this->transformTextBreak($element),
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

        // Process inline elements (text with formatting, footnotes, links, etc.)
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->formatInlineText($element);
            } elseif ($element instanceof Footnote) {
                $html .= $this->transformFootnote($element);
            } elseif ($element instanceof Endnote) {
                $html .= $this->transformEndnote($element);
            } elseif ($element instanceof Link) {
                $html .= $this->transformLink($element);
            } elseif ($element instanceof Image) {
                $html .= $this->transformImage($element);
            }
            // Skip other element types that don't make sense inline
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

    private function transformTitle(Title $title): string
    {
        // Get the depth to determine heading level (1-6)
        $depth = $title->getDepth();
        $level = min(max(1, $depth), 6); // Clamp between 1 and 6
        
        // Get style ID for class attribution
        $style = $title->getStyle();
        $styleId = '';
        
        if (is_string($style)) {
            $styleId = $style;
        } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
            $styleId = $style->getStyleName() ?? '';
        }
        
        // Build classes array
        $classes = [];
        
        // Add mapped class names if they exist
        if ($styleId) {
            $mappedClasses = $this->styleMap->getClassNames($styleId);
            if ($mappedClasses) {
                $classes[] = $mappedClasses;
            }
            
            // Always add the style ID itself as a class (kebab-case)
            $classes[] = $this->normalizeStyleIdForClass($styleId);
        }
        
        $classAttr = !empty($classes) ? ' class="' . htmlspecialchars(implode(' ', $classes)) . '"' : '';
        
        // Extract text content from title
        // Title.getText() returns a TextRun object
        $content = '';
        $textRun = $title->getText();
        
        if ($textRun instanceof TextRun) {
            foreach ($textRun->getElements() as $textElement) {
                if ($textElement instanceof Text) {
                    // Don't apply bold/italic formatting - headings are already semantic
                    $content .= htmlspecialchars($textElement->getText());
                }
            }
        }
        
        return "<h{$level}{$classAttr}>{$content}</h{$level}>\n";
    }

    private function formatInlineText(Text $text): string
    {
        $content = htmlspecialchars($text->getText());
        $fontStyle = $text->getFontStyle();
        
        if (!$fontStyle) {
            return $content;
        }
        
        // Apply inline formatting (order matters - inner to outer)
        
        // Superscript and subscript (mutually exclusive, superscript takes precedence)
        if ($fontStyle->isSuperScript()) {
            $content = "<sup>{$content}</sup>";
        } elseif ($fontStyle->isSubScript()) {
            $content = "<sub>{$content}</sub>";
        }
        
        // Strikethrough (single or double)
        if ($fontStyle->isStrikethrough()) {
            $content = "<s>{$content}</s>";
        } elseif ($fontStyle->isDoubleStrikethrough()) {
            $content = "<s class=\"double-strike\">{$content}</s>";
        }
        
        // Bold, italic, underline
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
        
        // Small caps and all caps (mutually exclusive)
        if ($fontStyle->isSmallCaps()) {
            $content = "<span class=\"small-caps\">{$content}</span>";
        } elseif ($fontStyle->isAllCaps()) {
            $content = "<span class=\"all-caps\">{$content}</span>";
        }
        
        // Text color (foreground) — only apply for valid, non-default colors
        $color = $fontStyle->getColor();
        if ($this->shouldApplyTextColor($color)) {
            $hex = strtolower(ltrim((string)$color, '#'));
            $content = "<span style=\"color: #{$hex};\">{$content}</span>";
        }
        
        // Highlighting (background color) — only apply if valid hex color
        $bgColor = $fontStyle->getBgColor();
        if ($this->isValidHexColor($bgColor)) {
            $hexBg = strtolower(ltrim((string)$bgColor, '#'));
            $content = "<mark style=\"background-color: #{$hexBg};\">{$content}</mark>";
        }
        
        // Foreground/highlight color (Word's highlight feature)
        $fgColor = $fontStyle->getFgColor();
        if ($fgColor !== null && $fgColor !== '') {
            // Map Word color names to hex or use as-is
            $content = "<mark class=\"highlight-{$fgColor}\">{$content}</mark>";
        }
        
        return $content;
    }

    /**
     * Format multiple inline Text elements, merging adjacent ones with identical formatting.
     * This prevents span fragmentation like <span>3</span><span>5</span> → <span>35</span>.
     */
    private function formatMergedInlineText(array $elements): string
    {
        $textGroups = [];
        $currentGroup = null;
        
        foreach ($elements as $element) {
            if (!($element instanceof Text)) {
                // Non-text elements break the grouping - flush current group and process element
                if ($currentGroup !== null) {
                    $textGroups[] = $currentGroup;
                    $currentGroup = null;
                }
                
                // Handle non-text elements (footnotes, links, etc.) directly
                if ($element instanceof Footnote) {
                    $textGroups[] = ['type' => 'footnote', 'element' => $element];
                } elseif ($element instanceof Endnote) {
                    $textGroups[] = ['type' => 'endnote', 'element' => $element];
                } elseif ($element instanceof Link) {
                    $textGroups[] = ['type' => 'link', 'element' => $element];
                } elseif ($element instanceof Image) {
                    $textGroups[] = ['type' => 'image', 'element' => $element];
                }
                continue;
            }
            
            // Get formatting signature for this Text element
            $fontStyle = $element->getFontStyle();
            $signature = $this->getFormattingSignature($fontStyle);
            
            // If this is the first text or has different formatting, start a new group
            if ($currentGroup === null || $currentGroup['signature'] !== $signature) {
                if ($currentGroup !== null) {
                    $textGroups[] = $currentGroup;
                }
                $currentGroup = [
                    'type' => 'text',
                    'signature' => $signature,
                    'fontStyle' => $fontStyle,
                    'texts' => [$element->getText()]
                ];
            } else {
                // Same formatting - merge text content
                $currentGroup['texts'][] = $element->getText();
            }
        }
        
        // Don't forget the last group
        if ($currentGroup !== null) {
            $textGroups[] = $currentGroup;
        }
        
        // Now format each group
        $html = '';
        foreach ($textGroups as $group) {
            if ($group['type'] === 'text') {
                // Merge all texts in this group and format as one
                $mergedText = implode('', $group['texts']);
                $html .= $this->formatSingleText($mergedText, $group['fontStyle']);
            } elseif ($group['type'] === 'footnote') {
                $html .= $this->transformFootnote($group['element']);
            } elseif ($group['type'] === 'endnote') {
                $html .= $this->transformEndnote($group['element']);
            } elseif ($group['type'] === 'link') {
                $html .= $this->transformLink($group['element']);
            } elseif ($group['type'] === 'image') {
                $html .= $this->transformImage($group['element']);
            }
        }
        
        return $html;
    }
    
    /**
     * Generate a unique signature for a font style to determine if two Text elements can be merged.
     */
    private function getFormattingSignature($fontStyle): string
    {
        if (!$fontStyle) {
            return 'no-style';
        }
        
        // Create signature from all formatting properties that affect HTML output
        $props = [
            'bold' => $fontStyle->isBold(),
            'italic' => $fontStyle->isItalic(),
            'underline' => $fontStyle->getUnderline(),
            'strikethrough' => $fontStyle->isStrikethrough(),
            'doubleStrike' => $fontStyle->isDoubleStrikethrough(),
            'superScript' => $fontStyle->isSuperScript(),
            'subScript' => $fontStyle->isSubScript(),
            'smallCaps' => $fontStyle->isSmallCaps(),
            'allCaps' => $fontStyle->isAllCaps(),
            'color' => $fontStyle->getColor(),
            'bgColor' => $fontStyle->getBgColor(),
            'fgColor' => $fontStyle->getFgColor()
        ];
        
        return md5(serialize($props));
    }
    
    /**
     * Format a single text string with font styling (extracted from formatInlineText).
     */
    private function formatSingleText(string $text, $fontStyle): string
    {
        $content = htmlspecialchars($text);
        
        if (!$fontStyle) {
            return $content;
        }
        
        // Apply inline formatting (order matters - inner to outer)
        
        // Superscript and subscript (mutually exclusive, superscript takes precedence)
        if ($fontStyle->isSuperScript()) {
            $content = "<sup>{$content}</sup>";
        } elseif ($fontStyle->isSubScript()) {
            $content = "<sub>{$content}</sub>";
        }
        
        // Strikethrough (single or double)
        if ($fontStyle->isStrikethrough()) {
            $content = "<s>{$content}</s>";
        } elseif ($fontStyle->isDoubleStrikethrough()) {
            $content = "<s class=\"double-strike\">{$content}</s>";
        }
        
        // Bold, italic, underline
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
        
        // Small caps and all caps (mutually exclusive)
        if ($fontStyle->isSmallCaps()) {
            $content = "<span class=\"small-caps\">{$content}</span>";
        } elseif ($fontStyle->isAllCaps()) {
            $content = "<span class=\"all-caps\">{$content}</span>";
        }
        
        // Text color (foreground) — only apply for valid, non-default colors
        $color = $fontStyle->getColor();
        if ($this->shouldApplyTextColor($color)) {
            $hex = strtolower(ltrim((string)$color, '#'));
            $content = "<span style=\"color: #{$hex};\">{$content}</span>";
        }
        
        // Highlighting (background color) — only apply if valid hex color
        $bgColor = $fontStyle->getBgColor();
        if ($this->isValidHexColor($bgColor)) {
            $hexBg = strtolower(ltrim((string)$bgColor, '#'));
            $content = "<mark style=\"background-color: #{$hexBg};\">{$content}</mark>";
        }
        
        // Foreground/highlight color (Word's highlight feature)
        $fgColor = $fontStyle->getFgColor();
        if ($fgColor !== null && $fgColor !== '') {
            // Map Word color names to hex or use as-is
            $content = "<mark class=\"highlight-{$fgColor}\">{$content}</mark>";
        }
        
        return $content;
    }

    /**
     * Determine if a text color should be applied.
     * Skip 'auto' and default black ('000000'/'000').
     */
    private function shouldApplyTextColor($color): bool
    {
        if ($color === null) {
            return false;
        }
        $color = (string)$color;
        if ($color === '' || strtolower($color) === 'auto') {
            return false;
        }
        // Normalize by stripping '#'
        $hex = strtolower(ltrim($color, '#'));
        // Skip default black
        if ($hex === '000000' || $hex === '000') {
            return false;
        }
        return $this->isValidHexColor($hex);
    }

    /**
     * Validate a hex color string (3 or 6 hex digits). Accept either with or without '#'.
     */
    private function isValidHexColor($color): bool
    {
        if ($color === null) {
            return false;
        }
        $color = (string)$color;
        if ($color === '' || strtolower($color) === 'auto') {
            return false;
        }
        $hex = ltrim($color, '#');
        return (bool)preg_match('/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $hex);
    }

    private function transformTable(Table $table): string
    {
        // Extract table style ID
        $tableStyle = $table->getStyle();
        $styleId = '';
        
        if (is_string($tableStyle)) {
            $styleId = $tableStyle;
        } elseif (is_object($tableStyle) && method_exists($tableStyle, 'getStyleName')) {
            $styleId = $tableStyle->getStyleName() ?? '';
        }
        
        // Build table classes
        $classes = [];
        if ($styleId) {
            // Add mapped class names if they exist
            $mappedClasses = $this->styleMap->getClassNames($styleId);
            if ($mappedClasses) {
                $classes[] = $mappedClasses;
            }
            // Always add the style ID itself as a class
            $classes[] = $this->normalizeStyleIdForClass($styleId);
        }
        
        $tableClassAttr = !empty($classes) ? ' class="' . htmlspecialchars(implode(' ', $classes)) . '"' : '';
        
        $html = "<table{$tableClassAttr}>\n";
        
        // Separate table notes/footnotes from regular rows
        $regularRows = [];
        $tableNotes = [];
        $tableFootnotes = [];
        
        foreach ($table->getRows() as $row) {
            // Check if this row contains only table notes or footnotes
            $cells = $row->getCells();
            if (count($cells) === 1) {
                $cell = $cells[0];
                $hasNoteOrFootnote = false;
                $noteParagraphs = []; // Store each TextRun as [content, styleId]
                $isFootnote = false;
                
                foreach ($cell->getElements() as $element) {
                    if ($element instanceof TextRun) {
                        $style = $element->getParagraphStyle();
                        $styleId = '';
                        if (is_string($style)) {
                            $styleId = $style;
                        } elseif (is_object($style) && method_exists($style, 'getStyleName')) {
                            $styleId = $style->getStyleName() ?? '';
                        }
                        
                        if ($styleId === 'TableNote' || $styleId === 'TableFootnote') {
                            $hasNoteOrFootnote = true;
                            $isFootnote = ($styleId === 'TableFootnote');
                            
                            // Extract content for this paragraph
                            $paragraphContent = '';
                            foreach ($element->getElements() as $textElement) {
                                if ($textElement instanceof Text) {
                                    $paragraphContent .= $this->formatInlineText($textElement);
                                } elseif ($textElement instanceof Footnote) {
                                    $paragraphContent .= $this->transformFootnote($textElement);
                                } elseif ($textElement instanceof Link) {
                                    $paragraphContent .= $this->transformLink($textElement);
                                }
                            }
                            
                            if ($paragraphContent) {
                                // Store both content and style ID
                                $noteParagraphs[] = [
                                    'content' => $paragraphContent,
                                    'styleId' => $styleId
                                ];
                            }
                        }
                    }
                }
                
                if ($hasNoteOrFootnote && !empty($noteParagraphs)) {
                    if ($isFootnote) {
                        $tableFootnotes[] = $noteParagraphs;
                    } else {
                        $tableNotes[] = $noteParagraphs;
                    }
                    continue; // Skip this row in table rendering
                }
            }
            
            // This is a regular data row
            $regularRows[] = $row;
        }
        
        // Render regular table rows in tbody
        $html .= "  <tbody>\n";
        foreach ($regularRows as $row) {
            $html .= "    <tr>\n";
            
            foreach ($row->getCells() as $cell) {
                // Get grid span from PHPWord
                $cellStyle = $cell->getStyle();
                $gridSpan = $cellStyle->getGridSpan() ?? 1;
                
                // Extract cell style ID
                $cellStyleId = '';
                if (method_exists($cellStyle, 'getStyleName')) {
                    $cellStyleId = $cellStyle->getStyleName() ?? '';
                }
                
                // Build cell attributes
                $cellAttrs = [];
                if ($gridSpan > 1) {
                    $cellAttrs[] = 'colspan="' . $gridSpan . '"';
                }
                
                // Add cell style ID as class
                $cellClasses = [];
                if ($cellStyleId) {
                    $mappedCellClasses = $this->styleMap->getClassNames($cellStyleId);
                    if ($mappedCellClasses) {
                        $cellClasses[] = $mappedCellClasses;
                    }
                    $cellClasses[] = $this->normalizeStyleIdForClass($cellStyleId);
                }
                
                if (!empty($cellClasses)) {
                    $cellAttrs[] = 'class="' . htmlspecialchars(implode(' ', $cellClasses)) . '"';
                }
                
                $cellAttrStr = !empty($cellAttrs) ? ' ' . implode(' ', $cellAttrs) : '';
                
                $html .= "    <td{$cellAttrStr}>";
                
                // Process cell content
                foreach ($cell->getElements() as $element) {
                    if ($element instanceof Text) {
                        $html .= htmlspecialchars($element->getText());
                    } elseif ($element instanceof TextRun) {
                        // Extract TextRun style for cell content
                        $textRunStyle = $element->getParagraphStyle();
                        $textRunStyleId = '';
                        if (is_string($textRunStyle)) {
                            $textRunStyleId = $textRunStyle;
                        } elseif (is_object($textRunStyle) && method_exists($textRunStyle, 'getStyleName')) {
                            $textRunStyleId = $textRunStyle->getStyleName() ?? '';
                        }
                        
                        // If TextRun has a style, wrap in span
                        if ($textRunStyleId) {
                            $textRunClasses = [];
                            $mappedTextRunClasses = $this->styleMap->getClassNames($textRunStyleId);
                            if ($mappedTextRunClasses) {
                                $textRunClasses[] = $mappedTextRunClasses;
                            }
                            $textRunClasses[] = $this->normalizeStyleIdForClass($textRunStyleId);
                            $spanClass = ' class="' . htmlspecialchars(implode(' ', $textRunClasses)) . '"';
                            $html .= "<span{$spanClass}>";
                        }
                        
                        // Merge adjacent Text elements with identical formatting to avoid span fragmentation
                        $html .= $this->formatMergedInlineText($element->getElements());
                        
                        if ($textRunStyleId) {
                            $html .= "</span>";
                        }
                    }
                }
                
                $html .= "</td>\n";
            }
            
            $html .= "    </tr>\n";
        }
        
        $html .= "  </tbody>\n";
        
        // Render table notes and footnotes in tfoot if present
        if (!empty($tableNotes) || !empty($tableFootnotes)) {
            $html .= "  <tfoot>\n";
            
            // Render table notes first - each note is an array of paragraphs
            foreach ($tableNotes as $noteParagraphs) {
                $html .= "    <tr>\n";
                $html .= "      <td colspan=\"999\" class=\"table-note\">";
                // Render each paragraph in a <p> tag with its style ID as class
                foreach ($noteParagraphs as $paragraphData) {
                    $content = $paragraphData['content'];
                    $styleId = $paragraphData['styleId'];
                    
                    // Normalize style ID for CSS class
                    $cssClass = $this->normalizeStyleIdForClass($styleId);
                    
                    // Check if there's a mapped class name
                    $mappedClasses = $this->styleMap->getClassNames($styleId);
                    $classes = [];
                    if ($mappedClasses) {
                        $classes[] = $mappedClasses;
                    }
                    $classes[] = $cssClass;
                    
                    $classAttr = ' class="' . htmlspecialchars(implode(' ', $classes)) . '"';
                    $html .= "<p{$classAttr}>{$content}</p>";
                }
                $html .= "</td>\n";
                $html .= "    </tr>\n";
            }
            
            // Render table footnotes after notes - each footnote is an array of paragraphs
            foreach ($tableFootnotes as $footnoteParagraphs) {
                $html .= "    <tr>\n";
                $html .= "      <td colspan=\"999\" class=\"table-footnote\">";
                // Render each paragraph in a <p> tag with its style ID as class
                foreach ($footnoteParagraphs as $paragraphData) {
                    $content = $paragraphData['content'];
                    $styleId = $paragraphData['styleId'];
                    
                    // Normalize style ID for CSS class
                    $cssClass = $this->normalizeStyleIdForClass($styleId);
                    
                    // Check if there's a mapped class name
                    $mappedClasses = $this->styleMap->getClassNames($styleId);
                    $classes = [];
                    if ($mappedClasses) {
                        $classes[] = $mappedClasses;
                    }
                    $classes[] = $cssClass;
                    
                    $classAttr = ' class="' . htmlspecialchars(implode(' ', $classes)) . '"';
                    $html .= "<p{$classAttr}>{$content}</p>";
                }
                $html .= "</td>\n";
                $html .= "    </tr>\n";
            }
            
            $html .= "  </tfoot>\n";
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

    private function transformFootnote(Footnote $footnote): string
    {
        // Collect footnote content and return inline reference
        $footnoteId = count($this->footnotes) + 1;
        
        // Extract footnote content with formatting preserved
        $content = '';
        foreach ($footnote->getElements() as $element) {
            if ($element instanceof Text) {
                // Use formatInlineText to preserve bold, italic, etc.
                $content .= $this->formatInlineText($element);
            } elseif ($element instanceof TextRun) {
                foreach ($element->getElements() as $textElement) {
                    if ($textElement instanceof Text) {
                        $content .= $this->formatInlineText($textElement);
                    }
                }
            } elseif ($element instanceof Link) {
                // Handle links in footnotes
                $content .= $this->transformLink($element);
            }
        }
        
        // Clean up the content: trim whitespace and remove leading/trailing punctuation
        $content = trim($content);
        $content = preg_replace('/^[.\s]+/', '', $content); // Remove leading periods and spaces
        $content = preg_replace('/[.\s]+$/', '', $content); // Remove trailing periods and spaces
        $content = trim($content);
        
        // Store the footnote
        $this->footnotes[$footnoteId] = $content;
        
        // Return inline reference
        return '<sup class="footnote-ref"><a href="#fn' . $footnoteId . '" id="fnref' . $footnoteId . '">[' . $footnoteId . ']</a></sup>';
    }

    private function transformEndnote(Endnote $endnote): string
    {
        // Collect endnote content and return inline reference
        $endnoteId = count($this->endnotes) + 1;
        
        // Extract endnote content with formatting preserved
        $content = '';
        foreach ($endnote->getElements() as $element) {
            if ($element instanceof Text) {
                // Use formatInlineText to preserve bold, italic, etc.
                $content .= $this->formatInlineText($element);
            } elseif ($element instanceof TextRun) {
                foreach ($element->getElements() as $textElement) {
                    if ($textElement instanceof Text) {
                        $content .= $this->formatInlineText($textElement);
                    }
                }
            } elseif ($element instanceof Link) {
                // Handle links in endnotes
                $content .= $this->transformLink($element);
            }
        }
        
        // Clean up the content: trim whitespace and remove leading/trailing punctuation
        $content = trim($content);
        $content = preg_replace('/^[.\s]+/', '', $content); // Remove leading periods and spaces
        $content = preg_replace('/[.\s]+$/', '', $content); // Remove trailing periods and spaces
        $content = trim($content);
        
        // Store the endnote
        $this->endnotes[$endnoteId] = $content;
        
        // Return inline reference
        return '<sup class="endnote-ref"><a href="#en' . $endnoteId . '" id="enref' . $endnoteId . '">[' . $endnoteId . ']</a></sup>';
    }
    
    /**
     * Render collected footnotes as an HTML list
     */
    private function renderFootnotes(): string
    {
        if (empty($this->footnotes)) {
            return '';
        }
        
        $html = "\n<hr />\n";
        $html .= "<section class=\"footnotes\">\n";
        $html .= "  <h2>Footnotes</h2>\n";
        $html .= "  <ol>\n";
        
        foreach ($this->footnotes as $id => $content) {
            $html .= "    <li id=\"fn{$id}\">{$content} <a href=\"#fnref{$id}\">↩</a></li>\n";
        }
        
        $html .= "  </ol>\n";
        $html .= "</section>\n";
        
        return $html;
    }
    
    /**
     * Render collected endnotes as an HTML list
     */
    private function renderEndnotes(): string
    {
        if (empty($this->endnotes)) {
            return '';
        }
        
        $html = "\n<hr />\n";
        $html .= "<section class=\"endnotes\">\n";
        $html .= "  <h2>Endnotes</h2>\n";
        $html .= "  <ol>\n";
        
        foreach ($this->endnotes as $id => $content) {
            $html .= "    <li id=\"en{$id}\">{$content} <a href=\"#enref{$id}\">↩</a></li>\n";
        }
        
        $html .= "  </ol>\n";
        $html .= "</section>\n";
        
        return $html;
    }

    private function transformLink(Link $link): string
    {
        // Get link source (URL) and text
        $source = $link->getSource();
        $text = $link->getText();
        
        // Build anchor tag
        $html = '<a href="' . htmlspecialchars($source ?? '') . '"';
        
        // Add rel attribute for external links
        if ($source && (str_starts_with($source, 'http://') || str_starts_with($source, 'https://'))) {
            $html .= ' rel="noopener noreferrer"';
        }
        
        $html .= '>' . htmlspecialchars($text ?? $source ?? '') . '</a>';
        
        return $html;
    }

    private function transformImage(Image $image): string
    {
        // Get image properties
        $source = $image->getSource();
        $name = $image->getName();

        $localSrc = null;

        // If we have an extractor, try to extract the image from the docx package
        if ($this->extractor && $source) {
            $local = $this->extractor->extract($source);
            if ($local) {
                $localSrc = $local;
            }
        }

        // If extractor didn't return anything, try raw source
        if (!$localSrc && $source && file_exists($source)) {
            $localSrc = $source;
        }

        // Build img tag
        $srcAttr = $localSrc ? htmlspecialchars($localSrc) : htmlspecialchars($source ?? '');

        $html = '<img src="' . $srcAttr . '"';

        if ($name) {
            $html .= ' alt="' . htmlspecialchars($name) . '"';
        } else {
            $html .= ' alt=""';
        }

        // Get style for dimensions
        $style = $image->getStyle();
        if ($style && is_object($style)) {
            $width = method_exists($style, 'getWidth') ? $style->getWidth() : null;
            $height = method_exists($style, 'getHeight') ? $style->getHeight() : null;

            if ($width) {
                $html .= ' width="' . htmlspecialchars((string)$width) . '"';
            }
            if ($height) {
                $html .= ' height="' . htmlspecialchars((string)$height) . '"';
            }
        }

        $html .= ' />';

        return $html;
    }

    private function transformTextBreak(TextBreak $textBreak): string
    {
        // Line break
        return "<br />\n";
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
        // Note: Do not append the raw style ID as a class for mapped conversions.
        
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
