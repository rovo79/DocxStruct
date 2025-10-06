# DOCX to Structured Data Converter

A PHP library that transforms DOCX documents into clean, semantic HTML or JSON. Built on top of PHPWord, it adds style-based transformation, custom class mapping, and powerful CLI tools for batch processing.

## Key Features

- **Style-Based Transformation**: Map DOCX paragraph styles to custom HTML elements (e.g., Quote → blockquote)
- **Clean Output**: Semantic HTML with CSS classes, not inline styles
- **Image Extraction**: Extract images to separate assets directory with automatic deduplication and relative path references
- **CLI Tools**: Command-line interface for single-file and batch conversion
- **YAML Configuration**: Flexible style mapping and transformation rules
- **Leverages PHPWord**: Uses PHPWord's robust DOCX parsing, adds transformation layer

## Quick Start

```bash
# Install dependencies
composer install

# Convert a DOCX file to HTML
./docx-converter/bin/docx-converter convert document.docx -o output.html

# With custom style mapping
./docx-converter/bin/docx-converter convert document.docx -s styles.yaml -o output.html

# Extract images to an assets directory
./docx-converter/bin/docx-converter convert document.docx -o output.html --assets-dir ./assets

# JSON output
./docx-converter/bin/docx-converter convert document.docx -f json -o output.json
```

## Documentation

**📚 Complete documentation is in the [`docs/`](docs/) folder:**

- **[Documentation Index](docs/README.md)** - Start here for navigation
- **[Product Requirements](docs/product-requirements.md)** - Features, priorities, roadmap
- **[Technical Specification](docs/technical-specification.md)** - Architecture and implementation
- **[PHPWord Integration Strategy](docs/prd-addendum-phpword-integration.md)** - How we leverage PHPWord
- **[CLI Implementation Guide](docs/cli-implementation-guide.md)** - Step-by-step development guide
- **[Project Tracker](docs/project_tracker.md)** - Current tasks and milestones

## Project Status

**Phase**: 🟡 Phase 1 - CLI-First MVP (Active Development)  
**Target**: November 2025  
**Progress**: See [Project Tracker](docs/project_tracker.md)

## Requirements

- PHP 8.0 or higher
- Composer
- PHPWord 1.1+
- Symfony Console 6.0+
- Symfony YAML 6.0+

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd DocxStruct

# Install dependencies
composer install

# Make CLI executable
chmod +x docx-converter/bin/docx-converter
```

## Usage Examples

### Basic Conversion

```php
use DocxConverter\DocxConverter;

$converter = new DocxConverter();
$html = $converter->loadDocument('input.docx')->toHtml();
file_put_contents('output.html', $html);
```

### With Style Mapping

```php
$styleMap = [
    'Quote' => [
        'convertTo' => 'blockquote',
        'className' => 'pullquote'
    ],
    'Heading1' => [
        'className' => 'section-title'
    ]
];

$html = $converter->loadDocument('input.docx')
                  ->withCustomStyleMap($styleMap)
                  ->toHtml();
```

### Image Extraction

When converting documents with images, you can extract them to a separate assets directory. The converter will:
- Extract all images to the specified directory
- Use relative paths in the generated HTML
- Create a manifest file for deduplication (same image used multiple times is only extracted once)
- Hash image content to ensure uniqueness

```php
$converter = new DocxConverter();
$converter->loadDocument('document.docx')
          ->withAssetsDir('./output/assets')
          ->setOutputFilePath('./output/document.html')
          ->toHtml();
```

The assets directory will contain:
- Image files named by their content hash (e.g., `f829b914fc47cfc9c0747c119c27cf1b.png`)
- `assets-manifest.json` - A manifest mapping content hashes to filenames

**CLI Usage:**
```bash
./docx-converter/bin/docx-converter convert document.docx \
  -o output/document.html \
  --assets-dir output/assets
```

**Relative Paths:**  
When an output file path is provided (via `-o` option or `setOutputFilePath()`), image references in the HTML will be relative to the output file location. Without an output file path, absolute paths are used.

### CLI Batch Processing

```bash
# Create batch config (batch-config.yaml)
# files:
#   - input: doc1.docx
#     output: doc1.html
#   - input: doc2.docx
#     output: doc2.json
#     format: json

./docx-converter/bin/docx-converter batch --config batch-config.yaml
```

## Development

See the [CLI Implementation Guide](docs/cli-implementation-guide.md) for detailed development instructions.

```bash
# Run tests
vendor/bin/phpunit

# Regenerate autoloader
composer dump-autoload
```

## Contributing

1. Review the [Product Requirements Document](docs/product-requirements.md)
2. Check [Project Tracker](docs/project_tracker.md) for current tasks
3. Follow coding guidelines in [Technical Specification](docs/technical-specification.md)
4. Reference [AI Coding Instructions](.github/copilot-instructions.md) for patterns

### GitHub Copilot Configuration

If you're using GitHub Copilot to work on this project, see [GitHub Copilot Tools Configuration](docs/copilot-tools-configuration.md) to learn how to configure which tools are enabled by default.

## License

[Add license information]

## Acknowledgments

Built on top of [PHPWord](https://github.com/PHPOffice/PHPWord) - A pure PHP library for reading and writing Word documents.
