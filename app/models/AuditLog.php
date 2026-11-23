<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditLog
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(a.action LIKE ? OR a.entity LIKE ? OR a.details LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity'])) {
            $where[] = 'a.entity = ?';
            $params[] = $filters['entity'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $from = 'FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id';

        $count = $this->pdo->prepare("SELECT COUNT(*) {$from} {$clause}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.first_name, u.last_name, u.email {$from} {$clause}
             ORDER BY a.created_at DESC, a.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    /** @return array<int, string> */
    public function distinctActions(): array
    {
        return array_map('strval', $this->pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, string> */
    public function distinctEntities(): array
    {
        return array_map('strval', $this->pdo->query('SELECT DISTINCT entity FROM audit_logs ORDER BY entity')->fetchAll(PDO::FETCH_COLUMN));
    }
}
