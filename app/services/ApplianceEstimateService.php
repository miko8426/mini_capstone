<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appliance;
use App\Models\ElectricityRate;
use App\Models\EmissionFactor;
use DateTimeImmutable;

/**
 * Live consumption estimate for appliances that still exist in the inventory.
 *
 * Unlike ConsumptionSnapshot (which only knows about usage that was explicitly
 * recorded), this projects consumption for every appliance that is "active".
 * Each appliance accrues from the later of the range start and the date it was
 * added, through the range end or today, whichever comes first.
 *
 * Daily consumption formula (same as CalculationService):
 *   (quantity x wattage x daily operating hours) / 1000
 */
class ApplianceEstimateService
{
    private Appliance $appliances;
    private ElectricityRate $rates;
    private EmissionFactor $factors;

    public function __construct(
        ?Appliance $appliances = null,
        ?ElectricityRate $rates = null,
        ?EmissionFactor $factors = null
    ) {
        $this->appliances = $appliances ?? new Appliance();
        $this->rates = $rates ?? new ElectricityRate();
        $this->factors = $factors ?? new EmissionFactor();
    }

    /**
     * Estimate for a whole calendar month (defaults to the current month).
     *
     * @param array<string, mixed> $filters Scope/filters applied to the inventory.
     * @return array<string, mixed>
     */
    public function estimate(array $filters = [], ?string $monthKey = null, ?string $asOf = null): array
    {
        $today = new DateTimeImmutable($asOf ?? 'today');
        $monthKey = $monthKey !== null && $monthKey !== '' ? $monthKey : $today->format('Y-m');
        [$start, $end] = CalculationService::monthRange($monthKey);

        $result = $this->range($filters, $start, $end, $asOf);
        $result['month'] = $monthKey;

        return $result;
    }

    /**
     * Estimate for an arbitrary inclusive date range.
     *
     * @param array<string, mixed> $filters Scope/filters applied to the inventory (campus_id, office_id, ...).
     * @return array{period_start: string, period_end: string, days: int,
     *               total_kwh: float, total_cost: float, total_co2: float,
     *               rate_per_kwh: float, emission_factor: float,
     *               appliances: array<int, array<string, mixed>>}
     */
    public function range(array $filters = [], ?string $from = null, ?string $to = null, ?string $asOf = null): array
    {
        $today = new DateTimeImmutable($asOf ?? 'today');
        $windowStart = new DateTimeImmutable($from !== null && $from !== '' ? $from : $today->format('Y-m-01'));
        $windowEnd = new DateTimeImmutable($to !== null && $to !== '' ? $to : $today->format('Y-m-d'));

        if ($windowEnd < $windowStart) {
            [$windowStart, $windowEnd] = [$windowEnd, $windowStart];
        }
        if ($windowEnd > $today) {
            $windowEnd = $today;
        }

        $rate = $this->rates->applicableOn($windowEnd->format('Y-m-d'));
        $factor = $this->factors->applicableOn($windowEnd->format('Y-m-d'));
        $ratePerKwh = (float) ($rate['rate_per_kwh'] ?? 0.0);
        $emissionFactor = (float) ($factor['kg_co2_per_kwh'] ?? 0.0);

        $result = [
            'period_start' => $windowStart->format('Y-m-d'),
            'period_end' => $windowEnd->format('Y-m-d'),
            'days' => 0,
            'total_kwh' => 0.0,
            'total_cost' => 0.0,
            'total_co2' => 0.0,
            'rate_per_kwh' => $ratePerKwh,
            'emission_factor' => $emissionFactor,
            'appliances' => [],
        ];

        if ($windowEnd < $windowStart) {
            return $result;
        }

        foreach ($this->appliances->allActive($filters) as $appliance) {
            $added = $this->addedOn($appliance);
            $from = $added > $windowStart ? $added : $windowStart;
            if ($from > $windowEnd) {
                continue;
            }

            $days = CalculationService::daysInPeriod($from->format('Y-m-d'), $windowEnd->format('Y-m-d'));
            $dailyKwh = CalculationService::dailyKwh(
                (int) $appliance['quantity'],
                (float) $appliance['wattage'],
                (float) $appliance['daily_operating_hours']
            );
            $kwh = $dailyKwh * $days;

            $result['appliances'][] = [
                'id' => (int) $appliance['id'],
                'name' => (string) $appliance['name'],
                'campus_id' => (int) ($appliance['campus_id'] ?? 0),
                'campus_name' => (string) ($appliance['campus_name'] ?? ''),
                'office_id' => (int) ($appliance['office_id'] ?? 0),
                'office_name' => (string) ($appliance['office_name'] ?? ''),
                'category_id' => (int) ($appliance['category_id'] ?? 0),
                'category_name' => (string) ($appliance['category_name'] ?? ''),
                'daily_kwh' => round($dailyKwh, 4),
                'days' => $days,
                'active_from' => $from->format('Y-m-d'),
                'total_kwh' => round($kwh, 4),
                'total_cost' => round($kwh * $ratePerKwh, 2),
                'total_co2' => round($kwh * $emissionFactor, 4),
            ];

            $result['total_kwh'] += $kwh;
            $result['total_cost'] += $kwh * $ratePerKwh;
            $result['total_co2'] += $kwh * $emissionFactor;
            $result['days'] = max($result['days'], $days);
        }

        $result['total_kwh'] = round($result['total_kwh'], 4);
        $result['total_cost'] = round($result['total_cost'], 2);
        $result['total_co2'] = round($result['total_co2'], 4);

        usort($result['appliances'], static fn (array $a, array $b): int => $b['total_kwh'] <=> $a['total_kwh']);

        return $result;
    }

    /**
     * Date the appliance was added to the inventory. Falls back to the start of
     * the current month when the column is missing or empty.
     *
     * @param array<string, mixed> $appliance
     */
    private function addedOn(array $appliance): DateTimeImmutable
    {
        $created = trim((string) ($appliance['created_at'] ?? ''));
        if ($created !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $created)
                ?: DateTimeImmutable::createFromFormat('Y-m-d', substr($created, 0, 10));

            if ($date instanceof DateTimeImmutable) {
                return $date->setTime(0, 0);
            }
        }

        return new DateTimeImmutable('first day of this month');
    }
}
