<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appliance;
use App\Models\ConsumptionSnapshot;
use App\Models\Database;
use App\Models\ElectricityRate;
use App\Models\EmissionFactor;
use DateTimeImmutable;

/**
 * Records appliance usage and stores the official calculation snapshot.
 */
class UsageService
{
    private ConsumptionSnapshot $snapshots;
    private ElectricityRate $rates;
    private EmissionFactor $factors;

    public function __construct(
        ?ConsumptionSnapshot $snapshots = null,
        ?ElectricityRate $rates = null,
        ?EmissionFactor $factors = null
    ) {
        $this->snapshots = $snapshots ?? new ConsumptionSnapshot();
        $this->rates = $rates ?? new ElectricityRate();
        $this->factors = $factors ?? new EmissionFactor();
    }

    /**
     * Validate and record usage for one appliance. Returns errors or the snapshot id.
     *
     * @param array<string, mixed> $appliance
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, snapshot_id: ?int, result: ?array<string, mixed>}
     */
    public function record(array $appliance, array $input, ?int $userId): array
    {
        $hours = (float) ($input['hours_per_day'] ?? $appliance['daily_operating_hours']);
        $daysPerWeek = (float) ($input['days_per_week'] ?? 5);
        $start = trim((string) ($input['period_start'] ?? ''));
        $end = trim((string) ($input['period_end'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $errors = [];

        $errors = array_merge(
            $errors,
            CalculationService::validateInputs((int) $appliance['quantity'], (float) $appliance['wattage'], $hours)
        );

        if ($daysPerWeek < 1 || $daysPerWeek > 7) {
            $errors[] = 'Usage days per week must be between 1 and 7.';
        }

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if (!$startDate || $startDate->format('Y-m-d') !== $start) {
            $errors[] = 'Select a valid reporting period start date.';
        }
        if (!$endDate || $endDate->format('Y-m-d') !== $end) {
            $errors[] = 'Select a valid reporting period end date.';
        }
        if ($startDate && $endDate) {
            if ($endDate < $startDate) {
                $errors[] = 'Reporting period end date must be on or after the start date.';
            } elseif (CalculationService::daysInPeriod($start, $end) > 366) {
                $errors[] = 'A reporting period cannot be longer than 366 days.';
            }
        }

        if (mb_strlen($notes) > 255) {
            $errors[] = 'Notes must not exceed 255 characters.';
        }

        $rate = $this->rates->applicableOn($start);
        $factor = $this->factors->applicableOn($start);
        if ($rate === null) {
            $errors[] = 'No active electricity rate is configured for this period. Ask an administrator to add one.';
        }
        if ($factor === null) {
            $errors[] = 'No active emission factor is configured for this period. Ask an administrator to add one.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'snapshot_id' => null, 'result' => null];
        }

        // Scale hours by the days-per-week ratio so the kWh reflects
        // actual usage days, not a full 7-day week.
        $effectiveHours = $hours * ($daysPerWeek / 7.0);

        $result = CalculationService::calculate(
            (int) $appliance['quantity'],
            (float) $appliance['wattage'],
            $effectiveHours,
            $start,
            $end,
            (float) $rate['rate_per_kwh'],
            (float) $factor['kg_co2_per_kwh']
        );

        $pdo = Database::getInstance()->getConnection();
        $usageStmt = $pdo->prepare(
            'INSERT INTO appliance_usage_records (appliance_id, recorded_by, hours_per_day, days_per_week, usage_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        $usageStmt->execute([(int) $appliance['id'], $userId, $hours, $daysPerWeek, $start]);

        $snapshotId = $this->snapshots->create([
            'appliance_id' => (int) $appliance['id'],
            'campus_id' => (int) $appliance['campus_id'],
            'office_id' => (int) $appliance['office_id'],
            'recorded_by' => $userId,
            'period_type' => 'monthly',
            'period_start' => $start,
            'period_end' => $end,
            'appliance_name' => $appliance['name'],
            'category_name' => $appliance['category_name'],
            'campus_name' => $appliance['campus_name'],
            'office_name' => $appliance['office_name'],
            'quantity' => (int) $appliance['quantity'],
            'wattage' => (float) $appliance['wattage'],
            'daily_operating_hours' => $effectiveHours,
            'days_in_period' => $result['days'],
            'total_kwh' => $result['total_kwh'],
            'rate_per_kwh' => (float) $rate['rate_per_kwh'],
            'electricity_cost' => $result['cost'],
            'emission_factor' => (float) $factor['kg_co2_per_kwh'],
            'co2_kg' => $result['co2_kg'],
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return [
            'errors' => [],
            'snapshot_id' => $snapshotId,
            'result' => array_merge($result, [
                'rate_per_kwh' => (float) $rate['rate_per_kwh'],
                'emission_factor' => (float) $factor['kg_co2_per_kwh'],
                'appliance_name' => $appliance['name'],
            ]),
        ];
    }
}
