<?php

namespace DocxConverter;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Readers\DocxReader;
use DocxConverter\Transformers\HtmlTransformer;
use DocxConverter\Transformers\JsonTransformer;

class DocxConverter
{
    private $styleMap;
    private $transformationRules;
    private $reader;
    private $transformer;

    public function __construct()
    {
        $this->styleMap = new StyleMap();
        $this->transformationRules = new TransformationRules();
    }

    public function loadDocument(string $path): self
    {
        $this->reader = new DocxReader($path);
        return $this;
    }

    public function withCustomStyleMap(StyleMap $styleMap): self
    {
        $this->styleMap = $styleMap;
        return $this;
    }

    public function withTransformationRules(TransformationRules $rules): self
    {
        $this->transformationRules = $rules;
        return $this;
    }

    public function toHtml(): string
    {
        $this->transformer = new HtmlTransformer($this->styleMap, $this->transformationRules);
        $sections = $this->reader->getSections();
        return $this->transformer->transform($sections);
    }

    public function toJson(): string
    {
        $this->transformer = new JsonTransformer($this->styleMap, $this->transformationRules);
        $sections = $this->reader->getSections();
        return $this->transformer->transform($sections);
    }
}
