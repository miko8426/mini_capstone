<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Threshold
{
    public const SCOPE_TYPES = ['campus', 'office', 'appliance'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM consumption_thresholds ORDER BY scope_type, id')->fetchAll();

        return array_map(function (array $row): array {
            $row['scope_name'] = $this->scopeName((string) $row['scope_type'], (int) $row['scope_id']);
            return $row;
        }, $rows);
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM consumption_thresholds')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->query(
            "SELECT * FROM consumption_thresholds ORDER BY scope_type ASC, id ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $items = array_map(function (array $row): array {
            $row['scope_name'] = $this->scopeName((string) $row['scope_type'], (int) $row['scope_id']);
            return $row;
        }, $stmt->fetchAll());

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM consumption_thresholds WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function exists(string $scopeType, int $scopeId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM consumption_thresholds WHERE scope_type = ? AND scope_id = ?';
        $params = [$scopeType, $scopeId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $scopeType, int $scopeId, float $limit, float $alertPercentage): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO consumption_thresholds (scope_type, scope_id, monthly_kwh_limit, alert_percentage)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$scopeType, $scopeId, $limit, $alertPercentage]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $scopeType, int $scopeId, float $limit, float $alertPercentage): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE consumption_thresholds SET scope_type = ?, scope_id = ?, monthly_kwh_limit = ?, alert_percentage = ?
             WHERE id = ?'
        );
        $stmt->execute([$scopeType, $scopeId, $limit, $alertPercentage, $id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM consumption_thresholds WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function scopeName(string $type, int $id): string
    {
        $sql = match ($type) {
            'campus' => 'SELECT name FROM campuses WHERE id = ?',
            'office' => 'SELECT CONCAT(o.name, " (", cp.name, ")") FROM offices o JOIN campuses cp ON cp.id = o.campus_id WHERE o.id = ?',
            'appliance' => 'SELECT CONCAT(a.name, " (", o.name, " - ", cp.name, ")") FROM appliances a JOIN offices o ON o.id = a.office_id JOIN campuses cp ON cp.id = o.campus_id WHERE a.id = ?',
            default => null,
        };

        if ($sql === null) {
            return 'Unknown';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        return $name === false ? 'Deleted #' . $id : (string) $name;
    }
}
