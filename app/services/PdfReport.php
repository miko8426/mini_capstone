<?php

declare(strict_types=1);

namespace App\Services;

use Fpdf\Fpdf;

/**
 * Shared PDF document with institutional header, generation timestamp and
 * page numbers in the footer.
 */
class PdfReport extends Fpdf
{
    public string $reportTitle = '';
    public string $institution = 'Eco Metrics University';
    public string $generatedAt = '';
    public string $filterSummary = '';
    public string $generatedBy = '';

    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 8, $this->latin($this->institution), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 5, 'Eco Metrics - Energy Consumption & Sustainability Monitoring System', 0, 1, 'C');
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 7, $this->latin($this->reportTitle), 0, 1, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 4, 'Generated: ' . $this->generatedAt . '    By: ' . $this->latin($this->generatedBy), 0, 1, 'L');
        if ($this->filterSummary !== '') {
            $this->MultiCell(0, 4, $this->latin('Filters: ' . $this->filterSummary), 0, 'L');
        }
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
        $this->SetDrawColor(45, 90, 39);
        $this->SetLineWidth(0.5);
        $this->Line($this->GetX(), $this->GetY(), $this->GetPageWidth() - $this->GetX(), $this->GetY());
        $this->Ln(3);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'Eco Metrics - Generated from immutable historical snapshots', 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    /**
     * Draw a table using the current report data.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<int, float> $widths
     * @param array<int, string> $aligns
     */
    public function table(array $headers, array $rows, array $widths, array $aligns): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(45, 90, 39);
        $this->SetTextColor(255, 255, 255);
        foreach ($headers as $index => $header) {
            $this->Cell($widths[$index], 7, $this->latin($header), 1, 0, $aligns[$index] ?? 'L', true);
        }
        $this->Ln();

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', '', 8);
        $fill = false;
        foreach ($rows as $row) {
            $rowHeight = 6;
            // Estimate wrapped height for the first textual columns.
            foreach ($row as $index => $value) {
                $lineCount = $this->getStringWidth($this->latin((string) $value)) / max(1, $widths[$index] - 3);
                $rowHeight = max($rowHeight, 5.5 * ceil($lineCount) + 1);
            }

            if ($this->GetY() + $rowHeight > $this->PageBreakTrigger) {
                $this->AddPage();
                $this->SetFont('Helvetica', 'B', 8);
                $this->SetFillColor(45, 90, 39);
                $this->SetTextColor(255, 255, 255);
                foreach ($headers as $index => $header) {
                    $this->Cell($widths[$index], 7, $this->latin($header), 1, 0, $aligns[$index] ?? 'L', true);
                }
                $this->Ln();
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('Helvetica', '', 8);
            }

            $yStart = $this->GetY();
            $xStart = $this->GetX();
            $maxY = $yStart;

            foreach ($row as $index => $value) {
                $this->MultiCell($widths[$index], 5, $this->latin((string) $value), 1, $aligns[$index] ?? 'L', $fill);
                $maxY = max($maxY, $this->GetY());
                $this->SetXY($xStart + array_sum(array_slice($widths, 0, $index + 1)), $yStart);
            }

            $this->SetXY($xStart, $maxY);
            $fill = !$fill;
        }
    }

    /**
     * FPDF core fonts use Windows-1252; transliterate unsupported symbols.
     */
    private function latin(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text;
    }
}
