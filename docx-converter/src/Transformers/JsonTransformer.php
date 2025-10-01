<?php

namespace DocxConverter\Transformers;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use PhpOffice\PhpWord\Element\Section;

class JsonTransformer implements TransformerInterface
{
    private $styleMap;
    private $transformationRules;

    public function __construct(StyleMap $styleMap, TransformationRules $transformationRules)
    {
        $this->styleMap = $styleMap;
        $this->transformationRules = $transformationRules;
    }

    /**
     * Transform an array of PHPWord Section objects to JSON.
     * 
     * @param array $sections Array of \PhpOffice\PhpWord\Element\Section objects
     * @return string The JSON output with document structure and metadata
     */
    public function transform(array $sections): string
    {
        $result = [];
        foreach ($sections as $section) {
            $sectionData = [];
            foreach ($section->getElements() as $element) {
                $elementType = get_class($element);
                $elementData = [
                    'type' => $elementType,
                ];
                // Example: extract text from Paragraphs
                if (method_exists($element, 'getElements')) {
                    $texts = [];
                    foreach ($element->getElements() as $subElement) {
                        if (method_exists($subElement, 'getText')) {
                            $texts[] = $subElement->getText();
                        }
                    }
                    $elementData['text'] = implode('', $texts);
                } elseif (method_exists($element, 'getText')) {
                    $elementData['text'] = $element->getText();
                }
                $sectionData[] = $elementData;
            }
            $result[] = $sectionData;
        }
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
