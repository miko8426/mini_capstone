<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;

/**
 * Official electricity / cost / carbon calculation engine.
 *
 * Daily consumption formula:
 *   (quantity x wattage x daily operating hours) / 1000
 *
 * Voltage is intentionally NOT part of the wattage-based kWh formula.
 */
class CalculationService
{
    public static function dailyKwh(int|float $quantity, int|float $wattage, int|float $hours): float
    {
        return ((float) $quantity * (float) $wattage * (float) $hours) / 1000;
    }

    public static function weeklyKwh(int|float $quantity, int|float $wattage, int|float $hours): float
    {
        return self::dailyKwh($quantity, $wattage, $hours) * 7;
    }

    /**
     * Monthly consumption for the actual reporting period (inclusive dates).
     */
    public static function periodKwh(int|float $quantity, int|float $wattage, int|float $hours, string $start, string $end): float
    {
        return self::dailyKwh($quantity, $wattage, $hours) * self::daysInPeriod($start, $end);
    }

    public static function daysInPeriod(string $start, string $end): int
    {
        $from = new DateTimeImmutable($start);
        $to = new DateTimeImmutable($end);

        if ($to < $from) {
            return 0;
        }

        return (int) $from->diff($to)->days + 1;
    }

    public static function cost(float $kwh, float $ratePerKwh): float
    {
        return round($kwh * $ratePerKwh, 2);
    }

    public static function emissions(float $kwh, float $factorPerKwh): float
    {
        return round($kwh * $factorPerKwh, 4);
    }

    /**
     * Validate calculation inputs. Returns a list of human-readable errors.
     *
     * @return array<int, string>
     */
    public static function validateInputs(int|float $quantity, int|float $wattage, int|float $hours): array
    {
        $errors = [];

        if ((int) $quantity < 1) {
            $errors[] = 'Quantity must be at least 1.';
        }
        if ((float) $wattage <= 0) {
            $errors[] = 'Wattage must be greater than 0.';
        }
        if ((float) $hours < 0) {
            $errors[] = 'Operating hours cannot be negative.';
        }
        if ((float) $hours > 24) {
            $errors[] = 'Operating hours cannot exceed 24 hours per day.';
        }

        return $errors;
    }

    /**
     * Compute a full result set for one appliance.
     *
     * @return array{daily_kwh: float, weekly_kwh: float, total_kwh: float, days: int,
     *               cost: float, co2_kg: float}
     */
    public static function calculate(
        int|float $quantity,
        int|float $wattage,
        int|float $hours,
        string $start,
        string $end,
        float $ratePerKwh,
        float $emissionFactor
    ): array {
        $days = self::daysInPeriod($start, $end);
        $daily = self::dailyKwh($quantity, $wattage, $hours);
        $total = $daily * $days;

        return [
            'daily_kwh' => round($daily, 4),
            'weekly_kwh' => round($daily * 7, 4),
            'total_kwh' => round($total, 4),
            'days' => $days,
            'cost' => self::cost($total, $ratePerKwh),
            'co2_kg' => self::emissions($total, $emissionFactor),
        ];
    }

    /**
     * Inclusive date range for a month key (YYYY-MM), or the current month.
     *
     * @return array{0: string, 1: string}
     */
    public static function monthRange(string $monthKey): array
    {
        $month = DateTimeImmutable::createFromFormat('Y-m', $monthKey) ?: new DateTimeImmutable('first day of this month');
        $start = $month->modify('first day of this month');
        $end = $month->modify('last day of this month');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    public static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->add(new DateInterval('P' . max(0, $days) . 'D'))->format('Y-m-d');
    }
}
