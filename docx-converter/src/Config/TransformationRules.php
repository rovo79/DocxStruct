<?php

namespace DocxConverter\Config;

class TransformationRules
{
    private $rules = [];

    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getRuleFor(string $elementType, string $styleId)
    {
        return $this->rules[$elementType][$styleId] ?? null;
    }
}
