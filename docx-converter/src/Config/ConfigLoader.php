<?php

namespace DocxConverter\Config;

use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    public function loadFromYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Configuration file not found: {$filePath}");
        }
        return Yaml::parseFile($filePath);
    }

    public function validateConfig(array $config): void
    {
        // Validate configuration structure and required fields
        // Throw exceptions for invalid configurations
    }
}
