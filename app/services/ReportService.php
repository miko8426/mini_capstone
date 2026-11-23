<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appliance;
use App\Models\ConsumptionSnapshot;
use App\Models\Database;
use PDO;

/**
 * Generates the ten official Eco Metrics PDF reports from database records.
 */
class ReportService
{
    public const REPORTS = [
        'campus' => 'Campus Electricity Consumption Report',
        'office' => 'Office Electricity Consumption Report',
        'inventory' => 'Appliance Inventory Report',
        'expense' => 'Electricity Expense Report',
        'carbon' => 'Carbon Emission Report',
        'history' => 'Historical Consumption Report',
        'high-consumption' => 'High-Consumption Appliance Report',
        'sustainability' => 'Institutional Sustainability Summary',
    ];

    private ConsumptionSnapshot $snapshots;
    private Appliance $appliances;

    public function __construct(?ConsumptionSnapshot $snapshots = null, ?Appliance $appliances = null)
    {
        $this->snapshots = $snapshots ?? new ConsumptionSnapshot();
        $this->appliances = $appliances ?? new Appliance();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function generate(string $type, array $filters, string $userName): void
    {
        $title = self::REPORTS[$type] ?? 'Eco Metrics Report';

        $pdf = new PdfReport('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetMargins(10, 12, 10);
        $pdf->reportTitle = $title;
        $pdf->generatedAt = date('Y-m-d H:i');
        $pdf->generatedBy = $userName !== '' ? $userName : 'System';
        $pdf->filterSummary = $this->filterSummary($filters);
        $pdf->AddPage();

        match ($type) {
            'campus' => $this->summaryReport($pdf, 'campus', 'Consumption by campus', $filters),
            'office' => $this->summaryReport($pdf, 'office', 'Consumption by office', $filters),
            'inventory' => $this->inventoryReport($pdf, $filters),
            'expense' => $this->expenseReport($pdf, $filters),
            'carbon' => $this->carbonReport($pdf, $filters),
            'history' => $this->historyReport($pdf, $filters),
            'high-consumption' => $this->highConsumptionReport($pdf, $filters),
            'sustainability' => $this->sustainabilityReport($pdf, $filters),
            default => $pdf->MultiCell(0, 6, 'Unknown report type.'),
        };

        $filename = 'eco-metrics-' . $type . '-' . date('Ymd-His') . '.pdf';
        $pdf->Output('D', $filename);
    }

    /** @param array<string, mixed> $filters */
    private function summaryReport(PdfReport $pdf, string $level, string $caption, array $filters = []): void
    {
        $rows = [];
        foreach ($this->snapshots->summaryBy($level, $filters) as $row) {
            $name = (string) $row['name'];
            if ($level === 'office' && !empty($row['campus_name'])) {
                $name .= ' (' . $row['campus_name'] . ')';
            }
            $rows[] = [
                $name,
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['total_cost'], 2),
                number_format((float) $row['total_co2'], 2),
            ];
        }
        $this->heading($pdf, $caption);
        $this->renderTable($pdf, ['Name', 'Consumption (kWh)', 'Cost (PHP)', 'Emissions (kg CO2e)'], $rows, [80, 35, 40, 35], ['L', 'R', 'R', 'R']);
        $this->totalsLine($pdf);
    }

    /** @param array<string, mixed> $filters */
    private function inventoryReport(PdfReport $pdf, array $filters): void
    {
        $result = $this->appliances->paginate($filters, 1, 5000);
        $rows = [];
        foreach ($result['items'] as $item) {
            $rows[] = [
                (string) $item['name'],
                (string) $item['category_name'],
                (string) $item['office_name'] . (!empty($item['campus_name']) ? ' (' . $item['campus_name'] . ')' : ''),
                (string) $item['quantity'],
                number_format((float) $item['wattage'], 2),
                number_format((float) $item['daily_operating_hours'], 2),
                ucfirst(str_replace('_', ' ', (string) $item['status'])),
            ];
        }
        $this->heading($pdf, 'Appliance inventory (' . count($rows) . ' appliance records)');
        $this->renderTable(
            $pdf,
            ['Appliance', 'Category', 'Office', 'Qty', 'Watts', 'Hours/day', 'Status'],
            $rows,
            [48, 38, 42, 12, 18, 14, 18],
            ['L', 'L', 'L', 'R', 'R', 'R', 'L']
        );
    }

    /** @param array<string, mixed> $filters */
    private function expenseReport(PdfReport $pdf, array $filters): void
    {
        $rows = [];
        foreach ($this->snapshots->all($filters) as $row) {
            $rows[] = [
                $row['period_start'] . ' to ' . $row['period_end'],
                (string) $row['appliance_name'],
                (string) $row['office_name'] . (!empty($row['campus_name']) ? ' (' . $row['campus_name'] . ')' : ''),
                number_format((float) $row['electricity_cost'], 2),
            ];
        }
        $this->heading($pdf, 'Electricity expenses');
        $this->renderTable($pdf, ['Period', 'Appliance', 'Office', 'Cost (PHP)'], $rows, [48, 65, 45, 32], ['L', 'L', 'L', 'R']);
        $this->totalsLine($pdf);
    }

    /** @param array<string, mixed> $filters */
    private function carbonReport(PdfReport $pdf, array $filters): void
    {
        $rows = [];
        foreach ($this->snapshots->summaryBy('campus', $filters) as $row) {
            $rows[] = [
                (string) $row['name'],
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['total_co2'], 2),
            ];
        }
        $this->heading($pdf, 'Carbon emissions by campus');
        $this->renderTable($pdf, ['Campus', 'Consumption (kWh)', 'Emissions (kg CO2e)'], $rows, [90, 50, 50], ['L', 'R', 'R']);
        $this->totalsLine($pdf);
    }

    /** @param array<string, mixed> $filters */
    private function historyReport(PdfReport $pdf, array $filters): void
    {
        $rows = [];
        foreach ($this->snapshots->all($filters) as $row) {
            $rows[] = [
                $row['period_start'] . ' to ' . $row['period_end'],
                (string) $row['appliance_name'],
                (string) $row['office_name'] . (!empty($row['campus_name']) ? ' (' . $row['campus_name'] . ')' : ''),
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['electricity_cost'], 2),
                number_format((float) $row['co2_kg'], 2),
                number_format((float) $row['rate_per_kwh'], 4),
                number_format((float) $row['emission_factor'], 4),
            ];
        }
        $this->heading($pdf, 'Historical consumption snapshots');
        $this->renderTable(
            $pdf,
            ['Period', 'Appliance', 'Office', 'kWh', 'Cost', 'CO2e', 'Rate', 'Factor'],
            $rows,
            [35, 42, 35, 18, 22, 18, 14, 16],
            ['L', 'L', 'L', 'R', 'R', 'R', 'R', 'R']
        );
        $this->totalsLine($pdf);
    }

    /** @param array<string, mixed> $filters */
    private function highConsumptionReport(PdfReport $pdf, array $filters): void
    {
        $rows = [];
        foreach ($this->snapshots->summaryBy('appliance', $filters, 20) as $row) {
            $rows[] = [
                (string) $row['name'],
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['total_cost'], 2),
                number_format((float) $row['total_co2'], 2),
            ];
        }
        $this->heading($pdf, 'Top 20 appliances by electricity consumption');
        $this->renderTable($pdf, ['Appliance', 'Consumption (kWh)', 'Cost (PHP)', 'Emissions (kg CO2e)'], $rows, [80, 38, 38, 34], ['L', 'R', 'R', 'R']);
        $this->totalsLine($pdf);
    }

    /** @param array<string, mixed> $filters */
    private function sustainabilityReport(PdfReport $pdf, array $filters): void
    {
        $totals = $this->snapshots->totals($filters);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Institutional totals', 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(60, 6, 'Total electricity consumption', 0, 0);
        $pdf->Cell(0, 6, number_format($totals['total_kwh'], 2) . ' kWh', 0, 1);
        $pdf->Cell(60, 6, 'Total electricity expense', 0, 0);
        $pdf->Cell(0, 6, 'PHP ' . number_format($totals['total_cost'], 2), 0, 1);
        $pdf->Cell(60, 6, 'Total carbon emissions', 0, 0);
        $pdf->Cell(0, 6, number_format($totals['total_co2'], 2) . ' kg CO2e', 0, 1);
        $pdf->Cell(60, 6, 'Historical records included', 0, 0);
        $pdf->Cell(0, 6, (string) $totals['records'], 0, 1);
        $pdf->Ln(3);

        $rows = [];
        foreach ($this->snapshots->summaryBy('campus', $filters) as $row) {
            $rows[] = [
                (string) $row['name'],
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['total_cost'], 2),
                number_format((float) $row['total_co2'], 2),
            ];
        }
        $this->heading($pdf, 'Campus contribution');
        $this->renderTable($pdf, ['Campus', 'Consumption (kWh)', 'Cost (PHP)', 'Emissions (kg CO2e)'], $rows, [80, 35, 40, 35], ['L', 'R', 'R', 'R']);

        $rows = [];
        foreach ($this->snapshots->summaryBy('category', $filters) as $row) {
            $rows[] = [
                (string) $row['name'],
                number_format((float) $row['total_kwh'], 2),
                number_format((float) $row['total_co2'], 2),
            ];
        }
        $this->heading($pdf, 'Appliance category contribution');
        $this->renderTable($pdf, ['Category', 'Consumption (kWh)', 'Emissions (kg CO2e)'], $rows, [90, 50, 50], ['L', 'R', 'R']);
    }

    private function heading(PdfReport $pdf, string $text): void
    {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text, 0, 1);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<int, float> $widths
     * @param array<int, string> $aligns
     */
    private function renderTable(PdfReport $pdf, array $headers, array $rows, array $widths, array $aligns): void
    {
        if ($rows === []) {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->MultiCell(0, 6, 'No records match the selected filters.');
            $pdf->Ln(2);
            return;
        }

        $pdf->table($headers, $rows, $widths, $aligns);
        $pdf->Ln(3);
    }

    private function totalsLine(PdfReport $pdf): void
    {
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Amounts are based on the frozen electricity rate and emission factor stored in each historical snapshot.', 0, 1);
        $pdf->Ln(2);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filterSummary(array $filters): string
    {
        $parts = [];
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $parts[] = 'Period ' . ($filters['date_from'] ?: 'beginning') . ' to ' . ($filters['date_to'] ?: 'present');
        }

        $pdo = Database::getInstance()->getConnection();
        if (!empty($filters['campus_id'])) {
            $stmt = $pdo->prepare('SELECT name FROM campuses WHERE id = ?');
            $stmt->execute([(int) $filters['campus_id']]);
            $name = $stmt->fetchColumn();
            $parts[] = 'Campus: ' . ($name !== false ? (string) $name : '#' . (int) $filters['campus_id']);
        }
        if (!empty($filters['office_id'])) {
            $stmt = $pdo->prepare('SELECT o.name, cp.name AS campus_name FROM offices o JOIN campuses cp ON cp.id = o.campus_id WHERE o.id = ?');
            $stmt->execute([(int) $filters['office_id']]);
            $office = $stmt->fetch();
            $parts[] = 'Office: ' . ($office ? $office['name'] . ' (' . $office['campus_name'] . ')' : '#' . (int) $filters['office_id']);
        }

        return $parts === [] ? 'All records' : implode('; ', $parts);
    }
}
