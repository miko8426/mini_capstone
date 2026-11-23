<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Campus
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM campuses ORDER BY name ASC')->fetchAll();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(string $search = '', int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = 'WHERE c.name LIKE ? OR c.code LIKE ? OR c.address LIKE ?';
            $term = '%' . $search . '%';
            $params = [$term, $term, $term];
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM campuses c {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                (SELECT COUNT(*) FROM offices o WHERE o.campus_id = c.id) AS office_count
             FROM campuses c {$where}
             ORDER BY c.name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM campuses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function exists(string $column, string $value, ?int $exceptId = null): bool
    {
        $column = $column === 'code' ? 'code' : 'name';
        $sql = "SELECT COUNT(*) FROM campuses WHERE {$column} = ?";
        $params = [$value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name, string $code, ?string $address): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO campuses (name, code, address) VALUES (?, ?, ?)');
        $stmt->execute([$name, $code, $address]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $code, ?string $address): bool
    {
        $stmt = $this->pdo->prepare('UPDATE campuses SET name = ?, code = ?, address = ? WHERE id = ?');
        $stmt->execute([$name, $code, $address, $id]);

        return $stmt->rowCount() > 0;
    }

    public function dependencyCounts(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM offices WHERE campus_id = ?) AS offices,
                    (SELECT COUNT(*) FROM users WHERE campus_id = ?) AS users'
        );
        $stmt->execute([$id, $id]);

        return $stmt->fetch() ?: ['offices' => 0, 'users' => 0];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM campuses WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
