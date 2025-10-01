# DocxStruct AI Coding Instructions

## Architecture Overview

This is a PHP library that converts DOCX documents to structured formats (HTML/JSON) by extending PHPWord capabilities. The architecture follows a **Reader → Parser → Transformer** pipeline with heavy emphasis on **style-based customization**.

### Key Components & Data Flow

1. **DocxConverter** (`src/DocxConverter.php`) - Fluent API entry point that orchestrates the conversion
2. **DocxReader** (`src/Readers/`) - Wraps PHPWord to extract document sections  
3. **Transformers** (`src/Transformers/`) - Convert parsed data to output formats
4. **StyleMap** (`src/Config/StyleMap.php`) - Maps DOCX paragraph styles to output configurations
5. **ConfigLoader** (`src/Config/ConfigLoader.php`) - Handles YAML-based configuration

**Critical Pattern**: Style IDs from DOCX paragraphs drive transformation behavior - paragraphs can be converted to lists, blockquotes, or custom elements based on their style mapping.

## Development Conventions

### Namespace Structure
- All classes use `DocxConverter\` namespace with PSR-4 autoloading
- Directory structure mirrors namespace: `src/Config/` → `DocxConverter\Config\`
- No top-level namespace imports in main DocxConverter class (architectural decision)

### Fluent Interface Pattern
The main API uses method chaining extensively:
```php
$converter->loadDocument($path)
         ->withCustomStyleMap($styleMap)  
         ->withTransformationRules($rules)
         ->toHtml();
```

### Configuration System
- **YAML-first**: All configuration (styles, transformations) uses YAML files
- **StyleMap format**: `styleId → {convertTo, className, listType}` mappings
- **Validation**: ConfigLoader includes validateConfig() for runtime checks
- See `examples/config/` for YAML structure patterns

## PHPWord Integration Points

### Element Processing
- **Core dependency**: PHPWord 1.1+ for initial DOCX reading
- **Extension approach**: Wrap PHPWord objects, don't modify them directly
- **Element types**: Focus on `TextRun`, `Text`, `Table` - these are the primary elements
- **Style extraction**: Use PHPWord's `getStyle()` methods but enhance with custom StyleMap logic

### Table Handling (Special Focus)
- **Grid spans**: `TableGridCalculator` handles complex table layouts PHPWord struggles with
- **Cell processing**: Iterate through `$table->getRows()` → `$row->getCells()` → `$cell->getElements()`
- **Style application**: Table styles come from both PHPWord and StyleMap configurations

## CLI & Batch Processing

### Command Structure
Uses Symfony Console with two main commands:
- `ConvertCommand` - Single file conversion with style mapping options
- `BatchCommand` - Multiple files using YAML configuration

### Key CLI Options
- `--style-map` / `-s`: Path to YAML style mapping file
- `--config` / `-c`: Path to full YAML configuration  
- `--format` / `-f`: Output format (html, json)

**Important**: CLI loads StyleMap separately from main config - they can be combined

## Testing Approach

### Test Structure
- `tests/Unit/` - Component isolation (Parsers, Transformers, Utils)
- `tests/Integration/` - End-to-end conversion workflows  
- `tests/Documents/` - Sample DOCX files for testing different scenarios

### Key Test Scenarios
- Complex table grid spans (`complex-tables.docx`)
- Custom style mappings (`custom-styles.docx`)  
- List paragraph detection (`list-paragraphs.docx`)

## Development Workflows

### Setup & Dependencies
```bash
composer install                    # Install dependencies
composer dump-autoload             # Regenerate autoloader
./bin/docx-converter convert --help # Verify CLI works
```

### Testing
```bash
vendor/bin/phpunit tests/Unit/      # Unit tests only
vendor/bin/phpunit                  # All tests  
```

### Common Extension Points

#### Adding New Output Format
1. Implement `TransformerInterface` in `src/Transformers/`
2. Add format case to `DocxConverter::to*()` method
3. Update `ConvertCommand` format validation

#### Custom Style Transformations  
1. Extend `StyleMap` with new `convertTo` types
2. Update corresponding transformer's element processing logic
3. Add YAML examples to `examples/config/`

## Critical Implementation Notes

### Memory & Performance
- **Large documents**: PHPWord loads entire document into memory - consider streaming for huge files
- **Style caching**: StyleMap lookups are frequent - cache getOutputConfig() results if needed

### Error Handling Patterns
- ConfigLoader throws `InvalidArgumentException` for missing files
- Transformers should gracefully handle unknown PHPWord element types
- CLI commands return proper exit codes (Command::SUCCESS/FAILURE)

### Architecture Decisions
- **No direct PHPWord modification**: Always wrap/extend, never modify PHPWord objects
- **YAML over JSON**: Configuration files use YAML for better readability
- **Style-driven transformation**: Document structure changes based on paragraph styles, not just content