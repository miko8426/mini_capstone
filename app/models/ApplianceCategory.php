<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Database;
use PDO;

/**
 * Appliance category data access.
 */
class ApplianceCategory
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->pdo
            ->query('SELECT * FROM appliance_categories ORDER BY name ASC')
            ->fetchAll();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public function paginate(string $search = '', int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE c.name LIKE ? OR c.description LIKE ?';
            $term = '%' . $search . '%';
            $params = [$term, $term];
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM appliance_categories c {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM appliances a WHERE a.category_id = c.id AND a.archived_at IS NULL) AS appliance_count
                FROM appliance_categories c
                {$where}
                ORDER BY c.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
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
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM appliance_categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        return $category === false ? null : $category;
    }

    public function existsByName(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM appliance_categories WHERE name = ?';
        $params = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name, ?string $description): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO appliance_categories (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        $stmt = $this->pdo->prepare('UPDATE appliance_categories SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Number of active (non-archived) appliances referencing this category.
     */
    public function applianceCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appliances WHERE category_id = ? AND archived_at IS NULL');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Number of appliances referencing this category, archived included.
     * Used to block deletion before the FK restriction triggers.
     */
    public function referenceCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appliances WHERE category_id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM appliance_categories WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
