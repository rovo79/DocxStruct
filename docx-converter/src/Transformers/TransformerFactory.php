<?php

namespace DocxConverter\Transformers;

use DocxConverter\Config\Config;
use DocxConverter\Config\StyleMap;

class TransformerFactory
{
    public static function create(string $format, Config $config)
    {
        return match ($format) {
            'html' => new HtmlTransformer($config),
            'json' => new JsonTransformer($config),
            default => throw new \InvalidArgumentException("Unsupported format: $format")
        };
    }
}
