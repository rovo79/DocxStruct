<?php

namespace DocxConverter\Config;

class StyleMap
{
    private $styleMap = [];

    public function __construct(array $initialMap = [])
    {
        $this->styleMap = $initialMap;
    }

    public function add(string $styleId, array $outputConfig): void
    {
        $this->styleMap[$styleId] = $outputConfig;
    }

    public function getOutputConfig(string $styleId): ?array
    {
        return $this->styleMap[$styleId] ?? null;
    }

    public function shouldConvertToList(string $styleId): bool
    {
        $config = $this->getOutputConfig($styleId);
        return $config && isset($config['convertTo']) && $config['convertTo'] === 'list';
    }

    public function getClassNames(string $styleId): string
    {
        $config = $this->getOutputConfig($styleId);
        return $config && isset($config['className']) ? $config['className'] : '';
    }
}
