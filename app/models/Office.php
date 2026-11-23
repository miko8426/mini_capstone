<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Office
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(string $search = '', int $campusId = 0, int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $where = [];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(o.name LIKE ? OR o.room_number LIKE ? OR cp.name LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        if ($campusId > 0) {
            $where[] = 'o.campus_id = ?';
            $params[] = $campusId;
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $from = 'FROM offices o
                 JOIN campuses cp ON cp.id = o.campus_id';

        $count = $this->pdo->prepare("SELECT COUNT(*) {$from} {$clause}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT o.*, cp.name AS campus_name,
                (SELECT COUNT(*) FROM appliances a WHERE a.office_id = o.id AND a.archived_at IS NULL) AS appliance_count
             {$from} {$clause}
             ORDER BY cp.name ASC, o.name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, cp.name AS campus_name
             FROM offices o
             JOIN campuses cp ON cp.id = o.campus_id
             WHERE o.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $campusId, string $roomNumber, string $name, int $floor): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO offices (campus_id, room_number, name, floor_level) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$campusId, $roomNumber, $name, $floor]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $campusId, string $roomNumber, string $name, int $floor): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE offices SET campus_id = ?, room_number = ?, name = ?, floor_level = ? WHERE id = ?'
        );
        $stmt->execute([$campusId, $roomNumber, $name, $floor, $id]);

        return $stmt->rowCount() > 0;
    }

    public function dependencyCounts(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM appliances WHERE office_id = ?) AS appliances,
                    (SELECT COUNT(*) FROM users WHERE office_id = ?) AS users,
                    (SELECT COUNT(*) FROM consumption_snapshots WHERE office_id = ?) AS snapshots'
        );
        $stmt->execute([$id, $id, $id]);

        return $stmt->fetch() ?: ['appliances' => 0, 'users' => 0, 'snapshots' => 0];
    }

    public function applianceCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appliances WHERE office_id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM offices WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
