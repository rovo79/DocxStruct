# DOCX to Structured Data Converter

A PHP library that transforms DOCX documents into clean, semantic HTML or JSON. Built on top of PHPWord, it adds style-based transformation, custom class mapping, and powerful CLI tools for batch processing.

## Key Features

- **Document Inspection**: Discover all style IDs in a DOCX file before conversion
- **Style-Based Transformation**: Map DOCX paragraph styles to custom HTML elements (e.g., Quote → blockquote)
- **Nested List Support**: Automatically detects and creates proper HTML5 nested lists
- **Clean Output**: Semantic HTML with CSS classes, not inline styles
- **CLI Tools**: Command-line interface for inspection, single-file and batch conversion
- **YAML Configuration**: Flexible style mapping and transformation rules
- **Leverages PHPWord**: Uses PHPWord's robust DOCX parsing, adds transformation layer

## Quick Start

```bash
# Install dependencies
composer install

# Inspect a DOCX file to see all style IDs
./docx-converter/bin/docx-converter inspect document.docx

# Export style IDs to a YAML template (auto-generates starter config)
./docx-converter/bin/docx-converter inspect document.docx --export styles.yaml

# Convert a DOCX file to HTML
./docx-converter/bin/docx-converter convert document.docx -o output.html

# With custom style mapping
./docx-converter/bin/docx-converter convert document.docx -s styles.yaml -o output.html

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

### Inspect Document Styles

Before converting, inspect a DOCX file to see what style IDs it contains:

```bash
# Basic inspection - shows all style IDs and usage counts
./docx-converter/bin/docx-converter inspect document.docx

# Detailed inspection - includes text previews and locations
./docx-converter/bin/docx-converter inspect document.docx --detailed

# Export to YAML template - auto-generates a starter styles.yaml file
./docx-converter/bin/docx-converter inspect document.docx --export styles.yaml
```

Example output:

```text
Style IDs Found:
+------------------+-------+---------+
| Style ID         | Count | Used In |
+------------------+-------+---------+
| ListParagraph    | 22    | TextRun |
| ListParagraph2   | 8     | TextRun |
| NormalBeforeList | 5     | TextRun |
| Heading1         | 3     | Title   |
+------------------+-------+---------+
```

Use this information to create your `styles.yaml` mapping file, or use `--export` to auto-generate a starter template with intelligent defaults.

The `--export` option automatically:

- Detects list-related styles and suggests `convertTo: list` with appropriate `listType`
- Detects quote styles and suggests `convertTo: blockquote`
- Converts style IDs to kebab-case CSS class names
- Adds usage comments showing how often each style appears

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

### Assets extraction (images)

When converting DOCX files that contain images, you can instruct the converter to extract embedded media into a local assets directory. The CLI `convert` command accepts an `--assets-dir` option. When provided, images referenced in the DOCX will be extracted into the directory and the generated HTML will reference the extracted files.

Example:

```bash
./docx-converter/bin/docx-converter convert document.docx -o output.html --assets-dir output-assets
```

Notes:
- Extraction attempts to read media from the DOCX package and write files into the chosen directory.
- Files are saved using a content-hash filename to avoid duplicates.
- If no assets dir is provided, images will be left as their original source path (if any).



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

## License

[Add license information]

## Acknowledgments

Built on top of [PHPWord](https://github.com/PHPOffice/PHPWord) - A pure PHP library for reading and writing Word documents.
