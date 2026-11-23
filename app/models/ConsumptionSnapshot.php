<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Immutable historical consumption records. There is intentionally no update
 * or delete method: saved snapshots never change when appliances or rates do.
 */
class ConsumptionSnapshot
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO consumption_snapshots
                (appliance_id, campus_id, office_id, recorded_by,
                 period_type, period_start, period_end, appliance_name, category_name,
                 campus_name, office_name, quantity, wattage,
                 daily_operating_hours, days_in_period, total_kwh, rate_per_kwh, electricity_cost,
                 emission_factor, co2_kg, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['appliance_id'] ?? null,
            $data['campus_id'] ?? null,
            $data['office_id'] ?? null,
            $data['recorded_by'] ?? null,
            $data['period_type'] ?? 'monthly',
            $data['period_start'],
            $data['period_end'],
            $data['appliance_name'],
            $data['category_name'],
            $data['campus_name'],
            $data['office_name'],
            (int) $data['quantity'],
            $data['wattage'],
            $data['daily_operating_hours'],
            (int) $data['days_in_period'],
            $data['total_kwh'],
            $data['rate_per_kwh'],
            $data['electricity_cost'],
            $data['emission_factor'],
            $data['co2_kg'],
            $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        [$where, $params] = $this->filterClause($filters);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM consumption_snapshots s {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM consumption_snapshots s {$where}
             ORDER BY s.period_start DESC, s.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    /**
     * All matching rows for reporting (bounded to protect memory).
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = [], int $limit = 5000): array
    {
        [$where, $params] = $this->filterClause($filters);
        $limit = max(1, min($limit, 20000));
        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM consumption_snapshots s {$where}
             ORDER BY s.period_start ASC, s.id ASC LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM consumption_snapshots WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $filters */
    public function totals(array $filters = []): array
    {
        [$where, $params] = $this->filterClause($filters);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS records,
                    COALESCE(SUM(s.total_kwh), 0) AS total_kwh,
                    COALESCE(SUM(s.electricity_cost), 0) AS total_cost,
                    COALESCE(SUM(s.co2_kg), 0) AS total_co2
             FROM consumption_snapshots s {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'records' => (int) ($row['records'] ?? 0),
            'total_kwh' => (float) ($row['total_kwh'] ?? 0),
            'total_cost' => (float) ($row['total_cost'] ?? 0),
            'total_co2' => (float) ($row['total_co2'] ?? 0),
        ];
    }

    /**
     * Totals for one calendar month. When no month key is given the most
     * recent month that has recorded consumption is used, so the dashboard
     * always defaults to a monthly view instead of an all-time total.
     *
     * @param array<string, mixed> $filters
     * @return array{month: string, total_kwh: float, total_cost: float, total_co2: float}
     */
    public function monthlyTotals(array $filters = [], ?string $monthKey = null): array
    {
        if ($monthKey === null) {
            [$where, $params] = $this->filterClause($filters);
            $latest = $this->pdo->prepare(
                "SELECT DATE_FORMAT(s.period_start, '%Y-%m') AS month
                 FROM consumption_snapshots s {$where}
                 ORDER BY s.period_start DESC, s.id DESC LIMIT 1"
            );
            $latest->execute($params);
            $monthKey = (string) ($latest->fetchColumn() ?: date('Y-m'));
        }

        $start = $monthKey . '-01';
        $end = date('Y-m-t', (int) strtotime($start));

        $totals = $this->totals(array_merge($filters, ['date_from' => $start, 'date_to' => $end]));

        return [
            'month' => $monthKey,
            'total_kwh' => $totals['total_kwh'],
            'total_cost' => $totals['total_cost'],
            'total_co2' => $totals['total_co2'],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function monthlyTrend(array $filters = []): array
    {
        [$where, $params] = $this->filterClause($filters);
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(s.period_start, '%Y-%m') AS month,
                    COALESCE(SUM(s.total_kwh), 0) AS total_kwh,
                    COALESCE(SUM(s.electricity_cost), 0) AS total_cost,
                    COALESCE(SUM(s.co2_kg), 0) AS total_co2
             FROM consumption_snapshots s {$where}
             GROUP BY month ORDER BY month ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function summaryBy(string $level, array $filters = [], int $limit = 0): array
    {
        // Campus, office, category and appliance names are resolved from the
        // live records so the charts always match the Organization, Categories
        // and Appliances modules, even after a record is renamed. The name
        // frozen into the snapshot is only a fallback for records that no
        // longer exist.
        $groups = [
            'campus' => [
                'join' => 'LEFT JOIN campuses cp ON cp.id = s.campus_id',
                'id' => 'COALESCE(cp.id, s.campus_id)',
                'name' => 'COALESCE(cp.name, s.campus_name)',
                'extra' => '',
                'extra_group' => '',
            ],
            'office' => [
                'join' => 'LEFT JOIN offices o ON o.id = s.office_id LEFT JOIN campuses cp ON cp.id = o.campus_id',
                'id' => 'COALESCE(o.id, s.office_id)',
                'name' => 'COALESCE(o.name, s.office_name)',
                'extra' => ', COALESCE(cp.name, s.campus_name) AS campus_name',
                'extra_group' => ', COALESCE(cp.name, s.campus_name)',
            ],
            // Snapshots store no category id, so the live category is reached
            // through the appliance record; the frozen name is the fallback.
            'category' => [
                'join' => 'LEFT JOIN appliances a ON a.id = s.appliance_id LEFT JOIN appliance_categories ac ON ac.id = a.category_id',
                'id' => 'COALESCE(ac.id, 0)',
                'name' => 'COALESCE(ac.name, s.category_name)',
                'extra' => '',
                'extra_group' => '',
            ],
            'appliance' => [
                'join' => 'LEFT JOIN appliances a ON a.id = s.appliance_id',
                'id' => 'COALESCE(a.id, s.appliance_id)',
                'name' => 'COALESCE(a.name, s.appliance_name)',
                'extra' => '',
                'extra_group' => '',
            ],
        ];

        if (!isset($groups[$level])) {
            return [];
        }

        $group = $groups[$level];
        $idExpr = $group['id'];
        $nameExpr = $group['name'];
        [$where, $params] = $this->filterClause($filters);
        $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
        $groupSql = "GROUP BY {$idExpr}, {$nameExpr}{$group['extra_group']}";

        $stmt = $this->pdo->prepare(
            "SELECT {$idExpr} AS id, {$nameExpr} AS name{$group['extra']},
                    COALESCE(SUM(s.total_kwh), 0) AS total_kwh,
                    COALESCE(SUM(s.electricity_cost), 0) AS total_cost,
                    COALESCE(SUM(s.co2_kg), 0) AS total_co2
             FROM consumption_snapshots s {$group['join']} {$where}
             {$groupSql} ORDER BY total_kwh DESC{$limitSql}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($level === 'campus' || $level === 'office') {
            $rows = $this->withEmptyOrganizationRows($level, $rows, $filters);
        } elseif ($level === 'category') {
            $categories = $this->pdo->query('SELECT id, name FROM appliance_categories ORDER BY name ASC')->fetchAll();
            $rows = $this->mergeWithEntities($rows, $categories);
        }

        return $rows;
    }

    /**
     * Campuses and offices come from the live organization records, so a campus
     * or office without consumption is still listed (with zero totals) and a
     * newly added one shows up immediately. Entities that no longer exist keep
     * their frozen snapshot name so no consumption disappears from the totals.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function withEmptyOrganizationRows(string $level, array $rows, array $filters): array
    {
        $sql = $level === 'campus'
            ? 'SELECT cp.id, cp.name FROM campuses cp'
            : 'SELECT o.id, o.name, cp.name AS campus_name FROM offices o JOIN campuses cp ON cp.id = o.campus_id';
        $order = $level === 'campus' ? 'cp.name ASC' : 'cp.name ASC, o.name ASC';

        $where = [];
        $params = [];
        if ($level === 'campus') {
            if (!empty($filters['campus_id'])) {
                $where[] = 'cp.id = ?';
                $params[] = (int) $filters['campus_id'];
            }
            if (!empty($filters['office_id'])) {
                $where[] = 'cp.id = (SELECT campus_id FROM offices WHERE id = ?)';
                $params[] = (int) $filters['office_id'];
            }
        } else {
            if (!empty($filters['campus_id'])) {
                $where[] = 'o.campus_id = ?';
                $params[] = (int) $filters['campus_id'];
            }
            if (!empty($filters['office_id'])) {
                $where[] = 'o.id = ?';
                $params[] = (int) $filters['office_id'];
            }
        }

        $stmt = $this->pdo->prepare(
            $sql . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY ' . $order
        );
        $stmt->execute($params);

        return $this->mergeWithEntities($rows, $stmt->fetchAll());
    }

    /**
     * Adds a zero-totals row for every entity that has no consumption yet, then
     * orders the result by consumption (highest first) and name.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, array<string, mixed>>
     */
    private function mergeWithEntities(array $rows, array $entities): array
    {
        $totals = [];
        $orphans = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $totals[$id] = $row;
            } else {
                $orphans[] = $row;
            }
        }

        $result = [];
        foreach ($entities as $entity) {
            $id = (int) $entity['id'];
            $usage = $totals[$id] ?? null;
            unset($totals[$id]);
            $result[] = [
                'id' => $id,
                'name' => (string) $entity['name'],
                'campus_name' => (string) ($entity['campus_name'] ?? ''),
                'total_kwh' => (float) ($usage['total_kwh'] ?? 0),
                'total_cost' => (float) ($usage['total_cost'] ?? 0),
                'total_co2' => (float) ($usage['total_co2'] ?? 0),
            ];
        }

        foreach (array_merge(array_values($totals), $orphans) as $leftover) {
            $result[] = [
                'id' => (int) ($leftover['id'] ?? 0),
                'name' => (string) ($leftover['name'] ?? ''),
                'campus_name' => (string) ($leftover['campus_name'] ?? ''),
                'total_kwh' => (float) ($leftover['total_kwh'] ?? 0),
                'total_cost' => (float) ($leftover['total_cost'] ?? 0),
                'total_co2' => (float) ($leftover['total_co2'] ?? 0),
            ];
        }

        usort($result, static function (array $a, array $b): int {
            $byUsage = $b['total_kwh'] <=> $a['total_kwh'];

            return $byUsage !== 0 ? $byUsage : strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Aggregate consumption for a threshold scope within a reporting period.
     */
    public function scopeTotalKwh(string $scopeType, int $scopeId, string $periodStart, string $periodEnd): float
    {
        $column = match ($scopeType) {
            'campus' => 's.campus_id',
            'office' => 's.office_id',
            'appliance' => 's.appliance_id',
            default => null,
        };

        if ($column === null) {
            return 0.0;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(s.total_kwh), 0) FROM consumption_snapshots s
             WHERE {$column} = ? AND s.period_start >= ? AND s.period_end <= ?"
        );
        $stmt->execute([$scopeId, $periodStart, $periodEnd]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function filterClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 's.period_start >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.period_end <= ?';
            $params[] = $filters['date_to'];
        }
        foreach (['campus_id', 'office_id', 'appliance_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = 's.' . $key . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }
        if (!empty($filters['period_type']) && in_array($filters['period_type'], ['daily', 'weekly', 'monthly'], true)) {
            $where[] = 's.period_type = ?';
            $params[] = $filters['period_type'];
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(s.appliance_name LIKE ? OR s.office_name LIKE ? OR s.category_name LIKE ?)';
            array_push($params, $term, $term, $term);
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }
}
