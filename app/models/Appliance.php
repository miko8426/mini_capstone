<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Database;
use PDO;

/**
 * Appliance inventory data access, including search/filtering,
 * pagination, aggregation and archival.
 */
class Appliance
{
    public const STATUSES = ['active', 'under_maintenance', 'decommissioned'];
    public const USAGE_FREQUENCIES = ['daily', 'weekly', 'monthly', 'occasional'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Shared joined SELECT used by list/detail queries.
     */
    private const BASE_SELECT = "SELECT a.*,
            c.name AS category_name,
            o.name AS office_name, o.room_number,
            cp.id AS campus_id, cp.name AS campus_name
        FROM appliances a
        JOIN appliance_categories c ON c.id = a.category_id
        JOIN offices o ON o.id = a.office_id
        JOIN campuses cp ON cp.id = o.campus_id";

    private const BASE_FROM = " FROM appliances a
        JOIN appliance_categories c ON c.id = a.category_id
        JOIN offices o ON o.id = a.office_id
        JOIN campuses cp ON cp.id = o.campus_id";

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        [$where, $params] = $this->buildFilterClause($filters);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*)' . self::BASE_FROM . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = self::BASE_SELECT . $where . " ORDER BY a.name ASC, a.id ASC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{count: int, total_quantity: int, kwh_per_day: float}
     */
    public function stats(array $filters = []): array
    {
        [$where, $params] = $this->buildFilterClause($filters);

        $sql = 'SELECT COUNT(*) AS appliance_count,
                       COALESCE(SUM(a.quantity), 0) AS total_quantity,
                       COALESCE(SUM(a.quantity * a.wattage * a.daily_operating_hours / 1000), 0) AS kwh_per_day'
            . self::BASE_FROM . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'count' => (int) ($row['appliance_count'] ?? 0),
            'total_quantity' => (int) ($row['total_quantity'] ?? 0),
            'kwh_per_day' => (float) ($row['kwh_per_day'] ?? 0),
        ];
    }

    /**
     * Active appliances for selection lists, honoring the same filters.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function allActive(array $filters = []): array
    {
        $filters['status'] = 'active';
        [$where, $params] = $this->buildFilterClause($filters);
        $stmt = $this->pdo->prepare(self::BASE_SELECT . $where . ' ORDER BY a.name ASC');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::BASE_SELECT . ' WHERE a.id = ?');
        $stmt->execute([$id]);
        $appliance = $stmt->fetch();

        return $appliance === false ? null : $appliance;
    }

    /**
     * Assign an appliance to an office and persist its inventory details.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO appliances
                (category_id, office_id, name, brand_model, voltage, wattage, quantity,
                 daily_operating_hours, usage_frequency, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['category_id'],
            (int) $data['office_id'],
            $data['name'],
            $data['brand_model'] !== '' ? $data['brand_model'] : null,
            $data['voltage'],
            $data['wattage'],
            (int) $data['quantity'],
            $data['daily_operating_hours'],
            $data['usage_frequency'],
            $data['status'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE appliances
             SET category_id = ?, office_id = ?, name = ?, brand_model = ?, voltage = ?,
                 wattage = ?, quantity = ?, daily_operating_hours = ?, usage_frequency = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([
            (int) $data['category_id'],
            (int) $data['office_id'],
            $data['name'],
            $data['brand_model'] !== '' ? $data['brand_model'] : null,
            $data['voltage'],
            $data['wattage'],
            (int) $data['quantity'],
            $data['daily_operating_hours'],
            $data['usage_frequency'],
            $data['status'],
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE appliances SET status = ? WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$status, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Whether an active appliance with this name already exists in the office.
     * Used by bulk import to reject duplicate rows.
     */
    public function existsInOffice(string $name, int $officeId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM appliances
             WHERE office_id = ? AND archived_at IS NULL AND LOWER(name) = LOWER(?)'
        );
        $stmt->execute([$officeId, $name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Archive several appliances at once (bulk delete).
     *
     * @param array<int, int> $ids
     * @return int number of appliances archived
     */
    public function archiveMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE appliances SET archived_at = CURRENT_TIMESTAMP
             WHERE archived_at IS NULL AND id IN ({$placeholders})"
        );
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * Archive instead of hard-deleting so historical usage records stay valid.
     */
    public function archive(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE appliances SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Permanently remove an archived appliance (its usage records cascade).
     */
    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM appliances WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Permanently remove several archived appliances at once.
     *
     * @param array<int, int> $ids
     * @return int number of appliances removed
     */
    public function forceDeleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "DELETE FROM appliances WHERE archived_at IS NOT NULL AND id IN ({$placeholders})"
        );
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE appliances SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Appliance totals grouped by campus or office, honoring the user's scope.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function summaryBy(string $level, array $filters = []): array
    {
        $groups = [
            'campus' => ['cp.id', 'cp.name'],
            'office' => ['o.id', 'o.name'],
        ];

        if (!isset($groups[$level])) {
            return [];
        }

        [$idExpr, $nameExpr] = $groups[$level];

        $where = ['a.archived_at IS NULL'];
        $params = [];
        foreach (['campus_id' => 'cp.id', 'office_id' => 'o.id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }

        $sql = "SELECT {$idExpr} AS id, {$nameExpr} AS name,
                       COUNT(*) AS appliance_count,
                       COALESCE(SUM(a.quantity), 0) AS total_quantity
                FROM appliances a
                JOIN appliance_categories c ON c.id = a.category_id
                JOIN offices o ON o.id = a.office_id
                JOIN campuses cp ON cp.id = o.campus_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY {$idExpr}, {$nameExpr}
                ORDER BY name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildFilterClause(array $filters): array
    {
        $where = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'archived') {
            $where[] = 'a.archived_at IS NOT NULL';
        } else {
            $where[] = 'a.archived_at IS NULL';
            if (in_array($status, self::STATUSES, true)) {
                $where[] = 'a.status = ?';
                $params[] = $status;
            }
        }

        foreach ([
            'category_id' => 'a.category_id',
            'campus_id' => 'cp.id',
            'office_id' => 'o.id',
        ] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(a.name LIKE ? OR a.brand_model LIKE ? OR c.name LIKE ? OR o.name LIKE ? OR cp.name LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term);
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
