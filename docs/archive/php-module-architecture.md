# PHP DOCX to Structured Data Converter - Architecture Blueprint

## 1. Module Overview

This module will provide enhanced DOCX document parsing functionality by extending PHPWord capabilities, with special focus on robust table handling and custom style-based transformations.

### 1.1 Key Features

- Convert DOCX documents to structured data (JSON, HTML, etc.)
- Enhanced table handling with grid span and class support
- Custom transformations based on paragraph style IDs
- Style tracking and mapping to output formats
- Support for converting specific styles to semantic elements (e.g., lists)

## 2. Core Architecture

### 2.1 Module Structure

```
docx-converter/
├── bin/
│   └── docx-converter                     # CLI executable
├── src/
│   ├── DocxConverter.php                  # Main entry point
│   ├── Readers/
│   │   ├── DocxReader.php                 # Builds on PHPWord reader
│   │   └── StyleReader.php                # Extract and interpret styles
│   ├── Parsers/
│   │   ├── DocumentParser.php             # High-level document parser
│   │   ├── TableParser.php                # Enhanced table parsing
│   │   ├── ParagraphParser.php            # Paragraph element parsing
│   │   └── ListParser.php                 # List element detection/parsing
│   ├── Transformers/
│   │   ├── TransformerInterface.php       # Transformer contract
│   │   ├── HtmlTransformer.php            # DOCX to HTML transformer
│   │   ├── JsonTransformer.php            # DOCX to JSON transformer
│   │   └── TransformerFactory.php         # Creates appropriate transformer
│   ├── Config/
│   │   ├── StyleMap.php                   # Style ID to output mapping
│   │   ├── ConfigLoader.php               # YAML config loader
│   │   └── TransformationRules.php        # Rules for custom transformations
│   ├── Console/
│   │   ├── ConvertCommand.php             # Single file conversion command
│   │   └── BatchCommand.php               # Batch processing command
│   └── Utils/
│       ├── StyleHelper.php                # Style processing utilities
│       └── TableGridCalculator.php        # Table grid span calculations
├── tests/                                 # Unit and integration tests
├── examples/
│   ├── config/                            # Example YAML configurations
│   │   ├── styles.yaml                    # Style mapping examples
│   │   └── batch.yaml                     # Batch processing config
│   └── cli/                               # CLI usage examples
├── composer.json
└── README.md
```

### 2.2 Core Components

1. **DocxConverter**: Main entry point for the module providing a fluent interface
2. **Readers**: Components for reading DOCX structure and styles
3. **Parsers**: Components for parsing different document elements
4. **Transformers**: Handle transformation to various output formats
5. **Config**: Configuration for style mapping and transformation rules

## 3. Component Details

### 3.1 DocxConverter (Main Class)

// Implementation moved to codebase.

### 3.2 Enhanced Table Parser

```php
class TableParser {
    private $config;
    private $styleReader;

    public function __construct(StyleReader $styleReader, Config $config) {
        $this->styleReader = $styleReader;
        $this->config = $config;
    }

    public function parse(\PhpOffice\PhpWord\Element\Table $table): array {
        $tableData = [
            'structure' => $this->parseTableStructure($table),
            'styles' => $this->styleReader->extractTableStyles($table),
            'gridSpans' => $this->calculateGridSpans($table),
            'cells' => $this->parseCells($table)
        ];

        return $tableData;
    }

    private function calculateGridSpans(\PhpOffice\PhpWord\Element\Table $table): array {
        // Enhanced algorithm to correctly handle grid spans
        $calculator = new TableGridCalculator();
        return $calculator->calculateSpans($table);
    }

    private function parseCells(\PhpOffice\PhpWord\Element\Table $table): array {
        // Logic to parse cell contents while preserving structure and style info
    }
}
```

### 3.3 Style Mapping System

```php
class StyleMap {
    private $styleMap = [];

    public function __construct(array $initialMap = []) {
        $this->styleMap = $initialMap;
    }

    public function add(string $styleId, array $outputConfig): void {
        $this->styleMap[$styleId] = $outputConfig;
    }

    public function getOutputConfig(string $styleId): ?array {
        return $this->styleMap[$styleId] ?? null;
    }

    // Example method to determine if a paragraph style should be converted to a list
    public function shouldConvertToList(string $styleId): bool {
        $config = $this->getOutputConfig($styleId);
        return $config && isset($config['convertTo']) && $config['convertTo'] === 'list';
    }

    // Method to get class names for HTML output
    public function getClassNames(string $styleId): string {
        $config = $this->getOutputConfig($styleId);
        return $config && isset($config['className']) ? $config['className'] : '';
    }
}
```

### 3.4 HTML Transformer

```php
class HtmlTransformer implements TransformerInterface {
    private $config;
    private $styleMap;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->styleMap = $config->getStyleMap();
    }

    public function transform(array $documentData): string {
        $html = '';

        foreach ($documentData['elements'] as $element) {
            switch ($element['type']) {
                case 'paragraph':
                    $html .= $this->transformParagraph($element);
                    break;
                case 'table':
                    $html .= $this->transformTable($element);
                    break;
                // Other element types
            }
        }

        return $html;
    }

    private function transformParagraph(array $paragraph): string {
        $styleId = $paragraph['styleId'] ?? '';
        $classes = $this->styleMap->getClassNames($styleId);

        // Check if this paragraph should be transformed to a different element
        if ($this->styleMap->shouldConvertToList($styleId)) {
            return $this->createListElement($paragraph, $classes);
        }

        $attributes = $classes ? ' class="' . htmlspecialchars($classes) . '"' : '';
        $html = "<p{$attributes}>";
        $html .= $this->processInlineContent($paragraph['content']);
        $html .= "</p>";

        return $html;
    }

    private function transformTable(array $table): string {
        $tableClasses = $this->styleMap->getClassNames($table['styles']['id'] ?? '');
        $attributes = $tableClasses ? ' class="' . htmlspecialchars($tableClasses) . '"' : '';

        $html = "<table{$attributes}>";

        // Add thead if there's a header row
        if (!empty($table['structure']['hasHeader'])) {
            $html .= "<thead>";
            $html .= $this->renderTableRow($table['cells'][0], $table['gridSpans'], true);
            $html .= "</thead><tbody>";
            $startRow = 1;
        } else {
            $html .= "<tbody>";
            $startRow = 0;
        }

        // Add the main table body
        for ($i = $startRow; $i < count($table['cells']); $i++) {
            $html .= $this->renderTableRow($table['cells'][$i], $table['gridSpans']);
        }

        $html .= "</tbody></table>";

        return $html;
    }

    private function renderTableRow(array $row, array $gridSpans, bool $isHeader = false): string {
        $html = "<tr>";

        foreach ($row as $cellIndex => $cell) {
            $tagName = $isHeader ? 'th' : 'td';
            $span = $gridSpans[$cellIndex] ?? 1;
            $spanAttr = $span > 1 ? ' colspan="' . $span . '"' : '';

            $cellClasses = $this->styleMap->getClassNames($cell['styleId'] ?? '');
            $classAttr = $cellClasses ? ' class="' . htmlspecialchars($cellClasses) . '"' : '';

            $html .= "<{$tagName}{$spanAttr}{$classAttr}>";
            $html .= $this->processCellContent($cell['content']);
            $html .= "</{$tagName}>";
        }

        $html .= "</tr>";
        return $html;
    }
}
```

## 4. Usage Examples

### 4.1 Basic Usage

```php
// Create a new converter instance
$converter = new DocxConverter();

// Convert a document to HTML
$html = $converter->loadDocument('/path/to/document.docx')
                 ->toHtml();

// Convert a document to JSON
$json = $converter->loadDocument('/path/to/document.docx')
                 ->toJson();
```

### 4.2 Custom Style Mapping

```php
// Define custom style mapping
$styleMap = [
    'paragraph-list' => [
        'convertTo' => 'list',
        'listType' => 'ul',
        'className' => 'custom-list'
    ],
    'Heading1' => [
        'convertTo' => 'heading',
        'level' => 1,
        'className' => 'main-heading'
    ],
    'TableGrid' => [
        'className' => 'data-table striped'
    ]
];

// Apply the style map
$html = $converter->loadDocument('/path/to/document.docx')
                 ->withCustomStyleMap($styleMap)
                 ->toHtml();
```

### 4.3 Custom Transformation Rules

```php
// Define transformation rules
$rules = [
    'paragraphs' => [
        // Apply rules to paragraphs with specific style IDs
        'Quote' => function($content, $styleData) {
            return '<blockquote class="elegant-quote">' . $content . '</blockquote>';
        }
    ],
    'tables' => [
        // Apply rules to tables with specific style IDs
        'DataTable' => function($tableData) {
            // Custom table transformation logic
            return $customTableHtml;
        }
    ]
];

// Apply transformation rules
$html = $converter->loadDocument('/path/to/document.docx')
                 ->withTransformationRules($rules)
                 ->toHtml();
```

## 5. Extension Points

### 5.1 Custom Transformers

The module supports creating custom transformers by implementing the `TransformerInterface`:

```php
interface TransformerInterface {
    public function transform(array $documentData): mixed;
}

// Example: Create a custom transformer for XML output
class XmlTransformer implements TransformerInterface {
    public function transform(array $documentData): string {
        // Implementation for XML transformation
    }
}

// Usage:
$transformer = new XmlTransformer($config);
$xml = $transformer->transform($documentData);
```

### 5.2 Custom Parsers

You can extend existing parsers or create new ones for specialized document elements:

```php
// Example: Create a specialized parser for mathematical equations
class EquationParser {
    public function parse(\PhpOffice\PhpWord\Element\Text $element): array {
        // Custom parsing logic for math equations
    }
}
```

## 6. Integration with PHPWord

### 6.1 PHPWord Dependencies

The module uses PHPWord for the initial document reading but extends its capabilities:

```php
class DocxReader {
    private $phpWord;

    public function __construct(string $filePath) {
        $this->phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
    }

    public function getDocument() {
        return $this->phpWord;
    }

    public function getSections() {
        return $this->phpWord->getSections();
    }

    public function getStyles() {
        return $this->phpWord->getStyles();
    }
}
```

### 6.2 Extending PHPWord Table Capabilities

The module will enhance PHPWord's table handling with additional functionality:

```php
class TableGridCalculator {
    public function calculateSpans(\PhpOffice\PhpWord\Element\Table $table): array {
        $spans = [];

        // Process all rows and cells to accurately map grid spans
        foreach ($table->getRows() as $rowIndex => $row) {
            foreach ($row->getCells() as $cellIndex => $cell) {
                $gridSpan = $cell->getStyle()->getGridSpan() ?? 1;
                $spans[$rowIndex][$cellIndex] = $gridSpan;
            }
        }

        // Further processing to handle complex spanning scenarios
        $this->normalizeGridSpans($spans);

        return $spans;
    }

    private function normalizeGridSpans(array &$spans): void {
        // Enhanced algorithm to correctly calculate and normalize grid spans
        // Deals with cases that PHPWord does not handle well
    }
}
```

## 7. Testing Strategy

### 7.1 Unit Tests

```
tests/
├── Unit/
│   ├── Parsers/
│   │   ├── TableParserTest.php
│   │   └── ParagraphParserTest.php
│   ├── Transformers/
│   │   ├── HtmlTransformerTest.php
│   │   └── JsonTransformerTest.php
│   └── Utils/
│       └── TableGridCalculatorTest.php
```

### 7.2 Integration Tests

```
tests/
├── Integration/
│   ├── DocxToHtmlTest.php
│   ├── DocxToJsonTest.php
│   └── CustomStyleMappingTest.php
```

### 7.3 Test Documents

```
tests/
├── Documents/
│   ├── simple.docx
│   ├── complex-tables.docx
│   ├── custom-styles.docx
│   └── list-paragraphs.docx
```

## 8. Production Considerations

### 8.1 Performance Optimization

- Use caching for parsed document structures
- Implement lazy loading of document sections
- Optimize memory usage for large documents

### 8.2 Error Handling

- Implement robust error handling for malformed DOCX files
- Provide detailed error messages and fallback strategies
- Log parsing issues for debugging

### 8.3 Documentation

- API documentation with PHPDoc comments
- Usage examples for common scenarios
- Troubleshooting guide

## 9. CLI Interface and Configuration

### 9.1 CLI Command Structure

The module will include a command-line interface for easy integration with scripts and automation workflows:

```
bin/
└── docx-converter             # Main CLI executable
```

**Basic CLI Usage:**

```bash
# Convert DOCX to HTML
./bin/docx-converter convert /path/to/document.docx --output=/path/to/output.html --format=html

# Convert DOCX to JSON with custom style mapping
./bin/docx-converter convert /path/to/document.docx --output=/path/to/output.json --format=json --style-map=/path/to/styles.yaml

# Process multiple files using a configuration file
./bin/docx-converter batch --config=/path/to/batch-config.yaml
```

### 9.2 Command Implementation

// Implementation moved to codebase.
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DocxConverter\DocxConverter;
use DocxConverter\Config\ConfigLoader;

class ConvertCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('convert')
            ->setDescription('Convert a DOCX file to another format')
            ->addArgument('input', InputArgument::REQUIRED, 'Path to input DOCX file')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (html, json)', 'html')
            ->addOption('style-map', 's', InputOption::VALUE_OPTIONAL, 'Path to YAML style mapping file')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Path to YAML configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument('input');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $styleMapFile = $input->getOption('style-map');
        $configFile = $input->getOption('config');

        // Load configuration if provided
        $config = [];
        if ($configFile) {
            $configLoader = new ConfigLoader();
            $config = $configLoader->loadFromYaml($configFile);
        }

        // Create converter instance
        $converter = new DocxConverter($config);
        $converter->loadDocument($inputFile);

        // Apply style mapping if provided
        if ($styleMapFile) {
            $styleMap = (new ConfigLoader())->loadFromYaml($styleMapFile);
            $converter->withCustomStyleMap($styleMap);
        }

        // Convert to specified format
        $result = match($format) {
            'html' => $converter->toHtml(),
            'json' => $converter->toJson(),
            default => throw new \InvalidArgumentException('Unsupported format: ' . $format)
        };

        // Output result
        if ($outputFile) {
            file_put_contents($outputFile, $result);
            $output->writeln("Conversion complete. Output saved to: {$outputFile}");
        } else {
            $output->write($result);
        }

        return Command::SUCCESS;
    }
}

```

### 9.3 YAML Configuration Support

The module will support YAML configuration files for both general settings and style mappings:

#### Example Style Mapping YAML

```yaml
# styles.yaml
paragraph-list:
  convertTo: list
  listType: ul
  className: custom-list

Heading1:
  convertTo: heading
  level: 1
  className: main-heading

TableGrid:
  className: data-table striped
```

#### Example Batch Processing YAML

```yaml
# batch-config.yaml
default_format: html
output_directory: ./output
style_map: ./styles.yaml

transformations:
  paragraphs:
    Quote:
      convertTo: blockquote
      className: elegant-quote

files:
  - input: ./documents/report.docx
    output: ./output/report.html
  - input: ./documents/data.docx
    output: ./output/data.json
    format: json
```

### 9.4 Configuration Loader

```php
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
```

## 10. Future Expansion Possibilities

- Add support for additional output formats (e.g., PDF, Markdown)
- Implement two-way conversion (structured data back to DOCX)
- Create a template system for consistent document transformation
- Add support for document comparison and change tracking
- Develop a web interface/API for the converter
