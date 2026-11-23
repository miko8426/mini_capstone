<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsumptionSnapshot;
use App\Models\Database;
use App\Models\Notification;
use App\Models\Threshold;

/**
 * Monitors consumption against administrator-configured thresholds and
 * generates one alert per user, threshold and reporting period.
 */
class NotificationService
{
    private Threshold $thresholds;
    private ConsumptionSnapshot $snapshots;
    private Notification $notifications;

    public function __construct(
        ?Threshold $thresholds = null,
        ?ConsumptionSnapshot $snapshots = null,
        ?Notification $notifications = null
    ) {
        $this->thresholds = $thresholds ?? new Threshold();
        $this->snapshots = $snapshots ?? new ConsumptionSnapshot();
        $this->notifications = $notifications ?? new Notification();
    }

    /**
     * Evaluate every threshold for a reporting month (YYYY-MM).
     *
     * @return array{checked: int, created: int, exceeded: array<int, array<string, mixed>>}
     */
    public function evaluate(string $monthKey): array
    {
        [$start, $end] = CalculationService::monthRange($monthKey);
        $created = 0;
        $checked = 0;
        $exceeded = [];

        foreach ($this->thresholds->all() as $threshold) {
            $checked++;
            $used = $this->snapshots->scopeTotalKwh(
                (string) $threshold['scope_type'],
                (int) $threshold['scope_id'],
                $start,
                $end
            );
            $limit = (float) $threshold['monthly_kwh_limit'];
            if ($limit <= 0 || $used <= 0) {
                continue;
            }

            $percentage = ($used / $limit) * 100;
            if ($percentage < (float) $threshold['alert_percentage']) {
                continue;
            }

            $type = $percentage >= 100 ? 'critical' : 'warning';
            $scopeLabel = ucfirst((string) $threshold['scope_type']);
            $title = sprintf('%s consumption threshold %s', $scopeLabel, $percentage >= 100 ? 'exceeded' : 'warning');
            $message = sprintf(
                '%s "%s" used %s kWh in %s (%.1f%% of the %s kWh monthly limit).',
                $scopeLabel,
                $threshold['scope_name'],
                number_format($used, 2),
                $monthKey,
                $percentage,
                number_format($limit, 2)
            );

            $exceeded[] = [
                'threshold' => $threshold,
                'used_kwh' => $used,
                'percentage' => $percentage,
                'type' => $type,
            ];

            foreach ($this->recipients($threshold) as $userId) {
                if ($this->notifications->createOnce(
                    $userId,
                    (int) $threshold['id'],
                    $monthKey,
                    $title,
                    $message,
                    $type
                )) {
                    $created++;
                }
            }
        }

        return ['checked' => $checked, 'created' => $created, 'exceeded' => $exceeded];
    }

    /**
     * Administrator recipients plus users scoped to the threshold's organization.
     *
     * @param array<string, mixed> $threshold
     * @return array<int, int>
     */
    private function recipients(array $threshold): array
    {
        $pdo = Database::getInstance()->getConnection();
        [$campusId, $officeId] = $this->scopeContext(
            (string) $threshold['scope_type'],
            (int) $threshold['scope_id']
        );

        $sql = "SELECT DISTINCT u.id
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.status = 'active' AND u.deleted_at IS NULL AND (
                    r.name = 'Super Admin'";
        $params = [];

        foreach (['campus_id' => $campusId, 'office_id' => $officeId] as $column => $value) {
            if ($value !== null) {
                $sql .= " OR u.{$column} = ?";
                $params[] = $value;
            }
        }
        $sql .= ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return array{0: ?int, 1: ?int} */
    private function scopeContext(string $scopeType, int $scopeId): array
    {
        $pdo = Database::getInstance()->getConnection();

        if ($scopeType === 'campus') {
            return [$scopeId, null];
        }
        if ($scopeType === 'office') {
            $stmt = $pdo->prepare('SELECT campus_id FROM offices WHERE id = ?');
            $stmt->execute([$scopeId]);
            $row = $stmt->fetch();
            return [
                $row && $row['campus_id'] !== null ? (int) $row['campus_id'] : null,
                $scopeId,
            ];
        }
        if ($scopeType === 'appliance') {
            $stmt = $pdo->prepare(
                'SELECT o.campus_id, o.id AS office_id
                 FROM appliances a
                 JOIN offices o ON o.id = a.office_id
                 WHERE a.id = ?'
            );
            $stmt->execute([$scopeId]);
            $row = $stmt->fetch();
            return [
                $row && $row['campus_id'] !== null ? (int) $row['campus_id'] : null,
                $row && $row['office_id'] !== null ? (int) $row['office_id'] : null,
            ];
        }

        return [null, null];
    }
}
