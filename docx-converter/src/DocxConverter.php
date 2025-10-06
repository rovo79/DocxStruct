<?php

namespace DocxConverter;

use DocxConverter\Config\StyleMap;
use DocxConverter\Config\TransformationRules;
use DocxConverter\Readers\DocxReader;
use DocxConverter\Transformers\HtmlTransformer;
use DocxConverter\Transformers\JsonTransformer;
use DocxConverter\Utils\ImageExtractor;

class DocxConverter
{
    private $styleMap;
    private $transformationRules;
    private $reader;
    private $transformer;
    private $assetsDir;
    private $outputFilePath;

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

    /**
     * Set the assets directory for image extraction.
     *
     * @param string $assetsDir Path to assets directory
     * @return self
     */
    public function withAssetsDir(string $assetsDir): self
    {
        $this->assetsDir = $assetsDir;
        return $this;
    }

    /**
     * Set the output file path (used for computing relative asset paths).
     *
     * @param string $outputFilePath Path to output file
     * @return self
     */
    public function setOutputFilePath(string $outputFilePath): self
    {
        $this->outputFilePath = $outputFilePath;
        return $this;
    }

    public function toHtml(): string
    {
        $this->transformer = new HtmlTransformer($this->styleMap, $this->transformationRules);
        
        // Set up image extractor if assets directory is configured
        if ($this->assetsDir !== null) {
            $imageExtractor = new ImageExtractor($this->reader->getFilePath());
            $imageExtractor->setAssetsDir($this->assetsDir);
            
            if ($this->outputFilePath !== null) {
                $imageExtractor->setOutputFilePath($this->outputFilePath);
            }
            
            $this->transformer->setImageExtractor($imageExtractor);
        }
        
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
