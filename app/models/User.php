<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class User
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function roles(): array
    {
        return $this->pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(string $search = '', int $roleId = 0, string $status = '', int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        if ($roleId > 0) {
            $where[] = 'u.role_id = ?';
            $params[] = $roleId;
        }
        if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM users u {$clause}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.role_id, u.first_name, u.last_name, u.email, u.status,
                    u.campus_id, u.office_id, u.created_at,
                    r.name AS role_name, COALESCE(c.name, oc.name) AS campus_name, o.name AS office_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN campuses c ON c.id = u.campus_id
             LEFT JOIN offices o ON o.id = u.office_id
             LEFT JOIN campuses oc ON oc.id = o.campus_id
             {$clause}
             ORDER BY u.last_name ASC, u.first_name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (role_id, first_name, last_name, email, password_hash, status,
                                campus_id, office_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['role_id'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            password_hash((string) $data['password'], PASSWORD_BCRYPT),
            $data['status'],
            $data['campus_id'] ?: null,
            $data['office_id'] ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE users SET role_id = ?, first_name = ?, last_name = ?, email = ?, status = ?,
                campus_id = ?, office_id = ?';
        $params = [
            (int) $data['role_id'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['status'],
            $data['campus_id'] ?: null,
            $data['office_id'] ?: null,
        ];

        if (!empty($data['password'])) {
            $sql .= ', password_hash = ?';
            $params[] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Move an account to the recycle bin (soft delete).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /** Permanently remove an account that is already in the recycle bin. */
    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Permanently remove several accounts that are already in the recycle bin.
     *
     * @param array<int, int> $ids
     * @return int number of accounts removed
     */
    public function forceDeleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "DELETE FROM users WHERE deleted_at IS NOT NULL AND id IN ({$placeholders})"
        );
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * Accounts currently held in the recycle bin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deleted(int $limit = 100): array
    {
        $limit = max(1, $limit);

        return $this->pdo->query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.deleted_at,
                    r.name AS role_name, COALESCE(c.name, oc.name) AS campus_name, o.name AS office_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN campuses c ON c.id = u.campus_id
             LEFT JOIN offices o ON o.id = u.office_id
             LEFT JOIN campuses oc ON oc.id = o.campus_id
             WHERE u.deleted_at IS NOT NULL
             ORDER BY u.deleted_at DESC, u.id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    public function activeAdminCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'Super Admin' AND u.status = 'active' AND u.deleted_at IS NULL"
        )->fetchColumn();
    }
}
