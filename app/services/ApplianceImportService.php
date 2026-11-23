<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Auth;
use App\Models\Appliance;
use App\Models\ApplianceCategory;
use App\Models\Database;
use App\Models\Organization;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Bulk appliance import from CSV or Excel (.xlsx) files.
 *
 * Files are parsed into rows and validated row by row against the same rules
 * the manual form applies, so nothing is written until the data is confirmed.
 */
class ApplianceImportService
{
    public const MAX_ROWS = 500;
    public const MAX_BYTES = 2097152; // 2 MB

    /** Canonical column => heading shown in templates and documentation. */
    public const COLUMNS = [
        'name' => 'Appliance Name',
        'brand_model' => 'Brand / Model',
        'campus' => 'Campus',
        'office' => 'Office',
        'category' => 'Category',
        'quantity' => 'Quantity',
        'voltage' => 'Voltage (V)',
        'wattage' => 'Wattage (W)',
        'daily_operating_hours' => 'Daily Hours',
        'usage_frequency' => 'Frequency',
        'status' => 'Status',
    ];

    public const REQUIRED_COLUMNS = [
        'name',
        'campus',
        'office',
        'category',
        'quantity',
        'voltage',
        'wattage',
        'daily_operating_hours',
    ];

    /** Accepted heading aliases, already normalized. */
    private const ALIASES = [
        'name' => ['name', 'appliance', 'appliance name', 'item', 'item name'],
        'brand_model' => ['brand', 'brand model', 'model', 'brand or model'],
        'campus' => ['campus', 'campus name'],
        'office' => ['office', 'office name', 'department'],
        'category' => ['category', 'appliance category', 'category name', 'type'],
        'quantity' => ['quantity', 'qty', 'units', 'unit count', 'count'],
        'voltage' => ['voltage', 'voltage v', 'volts'],
        'wattage' => ['wattage', 'wattage w', 'watts', 'power', 'power w'],
        'daily_operating_hours' => ['daily hours', 'hours', 'hours per day', 'daily operating hours', 'operating hours', 'hrs per day'],
        'usage_frequency' => ['usage frequency', 'frequency'],
        'status' => ['status', 'condition'],
    ];

    private Appliance $appliances;
    private ApplianceCategory $categories;
    private Organization $organization;

    public function __construct(
        ?Appliance $appliances = null,
        ?ApplianceCategory $categories = null,
        ?Organization $organization = null
    ) {
        $this->appliances = $appliances ?? new Appliance();
        $this->categories = $categories ?? new ApplianceCategory();
        $this->organization = $organization ?? new Organization();
    }

    /**
     * Parse an uploaded CSV or XLSX file into raw rows.
     *
     * @return array{rows: array<int, array{line: int, values: array<string, string>}>, errors: array<int, string>}
     */
    public function parse(string $path, string $filename): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        try {
            $table = match ($extension) {
                'csv', 'txt' => $this->readCsv($path),
                'xlsx' => $this->readXlsx($path),
                default => throw new RuntimeException('Only .csv and .xlsx files can be imported.'),
            };
        } catch (RuntimeException $e) {
            return ['rows' => [], 'errors' => [$e->getMessage()]];
        }

        return $this->rowsFromTable($table);
    }

    /**
     * Validate parsed rows against the inventory rules and the user's campus scope.
     *
     * @param array<int, array{line: int, values: array<string, string>}> $rows
     * @param array<string, int>|null $scope Empty array means unrestricted (administrator).
     * @return array{rows: array<int, array<string, mixed>>, valid: int, invalid: int}
     */
    public function validate(array $rows, ?array $scope = null): array
    {
        $scope = $scope ?? Auth::scope();
        $lookups = $this->lookups($scope);
        $seen = [];
        $validated = [];
        $valid = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            [$data, $errors] = $this->validateRow($row['values'], $lookups, $seen);

            if ($errors === []) {
                $valid++;
                $seen[$data['_key']] = true;
                unset($data['_key']);
            } else {
                $invalid++;
            }

            $validated[] = [
                'line' => $row['line'],
                'values' => $row['values'],
                'data' => $errors === [] ? $data : null,
                'errors' => $errors,
            ];
        }

        return ['rows' => $validated, 'valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Insert previously validated rows inside a single transaction.
     *
     * @param array<int, array<string, mixed>> $rows Rows as returned by validate().
     * @return array{inserted: int, errors: array<int, string>}
     */
    public function persist(array $rows): array
    {
        $pdo = Database::getInstance()->getConnection();
        $appliance = new Appliance($pdo);
        $inserted = 0;

        try {
            $pdo->beginTransaction();
            foreach ($rows as $row) {
                if (!isset($row['data']) || !is_array($row['data'])) {
                    continue;
                }
                $appliance->create($row['data']);
                $inserted++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['inserted' => 0, 'errors' => ['The import was rolled back: ' . $e->getMessage()]];
        }

        return ['inserted' => $inserted, 'errors' => []];
    }

    public function templateCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return implode(',', array_values(self::COLUMNS)) . PHP_EOL;
        }

        fputcsv($handle, array_values(self::COLUMNS));
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        // UTF-8 BOM so Excel opens the template with the correct encoding.
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * @return array<int, array<int, string>> Grid of raw cells.
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $firstLine = (string) fgets($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $grid = [];
        while (($record = fgetcsv($handle, null, $delimiter)) !== false) {
            if (!is_array($record)) {
                continue;
            }
            $grid[] = array_map(fn ($value): string => $this->toUtf8(trim((string) $value)), $record);
        }
        fclose($handle);

        if ($grid === []) {
            throw new RuntimeException('The uploaded CSV file is empty.');
        }

        return $grid;
    }

    /**
     * @return array<int, array<int, string>> Grid of raw cells.
     */
    private function readXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Excel import needs the PHP zip extension, which is not enabled on this server. Please use a CSV file instead.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The uploaded file is not a readable Excel workbook. Save it as .xlsx or use CSV.');
        }

        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $this->firstWorksheet($zip);
        $zip->close();

        if ($sheetXml === null) {
            throw new RuntimeException('The Excel workbook does not contain a readable worksheet.');
        }

        $shared = $sharedXml !== false ? $this->sharedStrings($sharedXml) : [];
        $grid = $this->sheetCells($sheetXml, $shared);

        if ($grid === []) {
            throw new RuntimeException('The Excel worksheet is empty.');
        }

        return $grid;
    }

    private function firstWorksheet(ZipArchive $zip): ?string
    {
        $direct = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($direct !== false) {
            return $direct;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                $contents = $zip->getFromName($name);

                return $contents === false ? null : $contents;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $xml): array
    {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $strings = [];
        foreach ($xpath->query('//*[local-name()="si"]') ?: [] as $item) {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $item) ?: [] as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $this->toUtf8($text);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $shared
     * @return array<int, array<int, string>>
     */
    private function sheetCells(string $xml, array $shared): array
    {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $grid = [];

        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $rowNode) {
            $cells = [];
            foreach ($xpath->query('./*[local-name()="c"]', $rowNode) ?: [] as $cell) {
                $reference = (string) $cell->attributes->getNamedItem('r')?->nodeValue;
                $type = (string) $cell->attributes->getNamedItem('t')?->nodeValue;
                $column = $this->columnIndex($reference);
                if ($column === null) {
                    continue;
                }

                $cells[$column] = $this->cellValue($xpath, $cell, $type, $shared);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $width = max(array_keys($cells)) + 1;
            $grid[] = array_map(
                fn (int $index): string => $this->toUtf8(trim($cells[$index] ?? '')),
                range(0, $width - 1)
            );
        }

        return $grid;
    }

    /**
     * @param array<int, string> $shared
     */
    private function cellValue(DOMXPath $xpath, \DOMNode $cell, string $type, array $shared): string
    {
        if ($type === 'inlineStr') {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $cell) ?: [] as $node) {
                $text .= $node->textContent;
            }

            return $text;
        }

        $valueNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
        $raw = $valueNode?->textContent ?? '';

        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }
        if ($type === 'b') {
            return $raw === '1' ? 'yes' : 'no';
        }

        return $raw;
    }

    /**
     * "B7" => 1 (zero-based column index).
     */
    private function columnIndex(string $reference): ?int
    {
        if (preg_match('/^([A-Z]+)/i', $reference, $matches) !== 1) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function loadXml(string $xml): ?DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * Turn a raw cell grid into keyed rows using the header row.
     *
     * @param array<int, array<int, string>> $grid
     * @return array{rows: array<int, array{line: int, values: array<string, string>}>, errors: array<int, string>}
     */
    private function rowsFromTable(array $grid): array
    {
        $header = null;
        $headerLine = 0;
        $rows = [];

        foreach ($grid as $index => $cells) {
            $cells = array_map(fn ($value): string => $this->toUtf8(trim((string) $value)), $cells);

            if (implode('', $cells) === '') {
                continue;
            }

            if ($header === null) {
                [$header, $missing] = $this->mapHeaders($cells);
                if ($missing !== []) {
                    $labels = array_map(static fn (string $key): string => self::COLUMNS[$key], $missing);

                    return [
                        'rows' => [],
                        'errors' => ['The file is missing required column(s): ' . implode(', ', $labels) . '. Download the template to see the expected headings.'],
                    ];
                }
                $headerLine = $index + 1;
                continue;
            }

            $values = [];
            foreach ($header as $columnIndex => $column) {
                if ($column !== null) {
                    $values[$column] = $cells[$columnIndex] ?? '';
                }
            }

            if (implode('', $values) === '') {
                continue;
            }

            $rows[] = ['line' => $index + 1, 'values' => $values];

            if (count($rows) > self::MAX_ROWS) {
                return [
                    'rows' => [],
                    'errors' => ['The file contains more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.'],
                ];
            }
        }

        if ($header === null) {
            return ['rows' => [], 'errors' => ['The file does not contain a header row.']];
        }

        if ($rows === []) {
            return ['rows' => [], 'errors' => ['No data rows were found below the header row (line ' . $headerLine . ').']];
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * Map header cells to canonical column names.
     *
     * @param array<int, string> $cells
     * @return array{0: array<int, string|null>, 1: array<int, string>}
     */
    private function mapHeaders(array $cells): array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $normalized = $this->norm($cell);
            $column = null;
            if ($normalized !== '') {
                foreach (self::ALIASES as $key => $aliases) {
                    if (in_array($normalized, $aliases, true)) {
                        $column = $key;
                        break;
                    }
                }
            }
            // Ignore duplicated headings instead of overwriting a column.
            if ($column !== null && in_array($column, $map, true)) {
                $column = null;
            }
            $map[$index] = $column;
        }

        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            static fn (string $key): bool => !in_array($key, $map, true)
        ));

        return [$map, $missing];
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach ($candidates as $delimiter => $_) {
            $candidates[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($candidates);
        $best = (string) array_key_first($candidates);

        return $candidates[$best] > 0 ? $best : ',';
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $lookups
     * @param array<string, bool> $seen
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validateRow(array $values, array $lookups, array $seen): array
    {
        $errors = [];
        $value = static fn (string $key): string => trim((string) ($values[$key] ?? ''));

        $name = $value('name');
        if ($name === '') {
            $errors[] = 'Appliance name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Appliance name must not exceed 100 characters.';
        }

        $brand = $value('brand_model');
        if (mb_strlen($brand) > 100) {
            $errors[] = 'Brand / model must not exceed 100 characters.';
        }

        // Campus
        $campusId = 0;
        $campusName = $value('campus');
        if ($campusName === '') {
            $errors[] = 'Campus is required.';
        } else {
            $campusId = (int) ($lookups['campuses'][$this->norm($campusName)] ?? 0);
            if ($campusId === 0) {
                $errors[] = sprintf(
                    'Campus "%s" does not exist or is outside your assigned campus. Available: %s.',
                    $campusName,
                    $lookups['campus_names'] === [] ? 'none' : implode(', ', $lookups['campus_names'])
                );
            }
        }

        // Office
        $office = null;
        $officeName = $value('office');
        if ($officeName === '') {
            $errors[] = 'Office is required.';
        } else {
            $matches = $lookups['offices'][$this->norm($officeName)] ?? [];
            if ($campusId > 0) {
                $matches = array_values(array_filter(
                    $matches,
                    static fn (array $candidate): bool => $candidate['campus_id'] === $campusId
                ));
            }

            if ($matches === []) {
                $errors[] = sprintf(
                    'Office "%s" was not found%s. Available: %s.',
                    $officeName,
                    $campusId > 0 ? ' in the selected campus' : '',
                    $lookups['office_names'] === [] ? 'none' : implode(', ', $lookups['office_names'])
                );
            } elseif (count($matches) > 1) {
                $errors[] = sprintf('Office "%s" exists in more than one campus. Fill in the Campus column to pick one.', $officeName);
            } else {
                $office = $matches[0];
            }
        }

        // Category
        $categoryId = 0;
        $categoryName = $value('category');
        if ($categoryName === '') {
            $errors[] = 'Category is required.';
        } else {
            $categoryId = (int) ($lookups['categories'][$this->norm($categoryName)] ?? 0);
            if ($categoryId === 0) {
                $errors[] = sprintf(
                    'Category "%s" does not exist. Add it on the Appliance Categories page first. Available: %s.',
                    $categoryName,
                    $lookups['category_names'] === [] ? 'none' : implode(', ', $lookups['category_names'])
                );
            }
        }

        // Numbers
        $quantity = $this->intValue($value('quantity'));
        if ($quantity === null) {
            $errors[] = 'Quantity must be a whole number.';
        } elseif ($quantity < 1 || $quantity > 100000) {
            $errors[] = 'Quantity must be between 1 and 100,000.';
        }

        $voltage = $this->floatValue($value('voltage'));
        if ($voltage === null) {
            $errors[] = 'Voltage must be a number.';
        } elseif ($voltage <= 0 || $voltage > 1000) {
            $errors[] = 'Voltage must be between 1 and 1000 volts.';
        }

        $wattage = $this->floatValue($value('wattage'));
        if ($wattage === null) {
            $errors[] = 'Wattage must be a number.';
        } elseif ($wattage <= 0 || $wattage > 1000000) {
            $errors[] = 'Wattage must be greater than 0.';
        }

        $hours = $this->floatValue($value('daily_operating_hours'));
        if ($hours === null) {
            $errors[] = 'Daily operating hours must be a number.';
        } elseif ($hours < 0 || $hours > 24) {
            $errors[] = 'Daily operating hours must be between 0 and 24.';
        }

        // Optional fields with defaults.
        $frequency = $this->token($value('usage_frequency'));
        if ($frequency === '') {
            $frequency = 'daily';
        } elseif (!in_array($frequency, Appliance::USAGE_FREQUENCIES, true)) {
            $errors[] = sprintf(
                'Usage frequency "%s" is not valid. Use one of: %s.',
                $value('usage_frequency'),
                implode(', ', Appliance::USAGE_FREQUENCIES)
            );
        }

        $status = $this->token($value('status'));
        if ($status === '') {
            $status = 'active';
        } elseif (!in_array($status, Appliance::STATUSES, true)) {
            $errors[] = sprintf(
                'Status "%s" is not valid. Use one of: %s.',
                $value('status'),
                implode(', ', Appliance::STATUSES)
            );
        }

        if ($office !== null && $name !== '') {
            $key = $this->norm($name) . '|' . $office['id'];
            if (isset($seen[$key])) {
                $errors[] = 'This appliance is listed twice for the same office in this file.';
            } elseif ($this->appliances->existsInOffice($name, $office['id'])) {
                $errors[] = sprintf('An appliance named "%s" already exists in %s.', $name, $office['label']);
            }
        }

        $data = [
            'name' => $name,
            'brand_model' => $brand,
            'category_id' => $categoryId,
            'office_id' => $office['id'] ?? 0,
            'campus_id' => $campusId,
            'quantity' => $quantity ?? 0,
            'voltage' => $voltage ?? 0.0,
            'wattage' => $wattage ?? 0.0,
            'daily_operating_hours' => $hours ?? 0.0,
            'usage_frequency' => $frequency,
            'status' => $status,
            '_key' => $this->norm($name) . '|' . ($office['id'] ?? 0),
        ];

        return [$data, $errors];
    }

    /**
     * @param array<string, int> $scope
     * @return array<string, mixed>
     */
    private function lookups(array $scope): array
    {
        $categories = [];
        $categoryNames = [];
        foreach ($this->categories->all() as $category) {
            $categories[$this->norm((string) $category['name'])] = (int) $category['id'];
            $categoryNames[] = (string) $category['name'];
        }

        $campuses = [];
        $campusNames = [];
        foreach ($this->organization->campuses($scope) as $campus) {
            $campuses[$this->norm((string) $campus['name'])] = (int) $campus['id'];
            if (!empty($campus['code'])) {
                $campuses[$this->norm((string) $campus['code'])] = (int) $campus['id'];
            }
            $campusNames[] = (string) $campus['name'];
        }

        $offices = [];
        $officeNames = [];
        foreach ($this->organization->offices($scope) as $office) {
            $offices[$this->norm((string) $office['name'])][] = [
                'id' => (int) $office['id'],
                'campus_id' => (int) $office['campus_id'],
                'label' => (string) $office['name'] . ' (' . (string) $office['campus_name'] . ')',
            ];
            $officeNames[] = (string) $office['name'] . ' (' . (string) $office['campus_name'] . ')';
        }

        return [
            'categories' => $categories,
            'category_names' => $categoryNames,
            'campuses' => $campuses,
            'campus_names' => $campusNames,
            'offices' => $offices,
            'office_names' => $officeNames,
        ];
    }

    private function intValue(string $value): ?int
    {
        $value = str_replace([',', ' '], '', $value);
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function floatValue(string $value): ?float
    {
        $value = str_replace([',', ' '], '', $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Normalize free-typed values such as "Under Maintenance" to "under_maintenance".
     */
    private function token(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * Lowercase, punctuation-insensitive key used to match names from files.
     */
    private function norm(string $value): string
    {
        $value = mb_strtolower($this->toUtf8($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Files exported from Excel are often Windows-1252 encoded.
     */
    private function toUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }
}
