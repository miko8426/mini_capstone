<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class EmissionFactor
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM emission_factors ORDER BY effective_date DESC, id DESC')->fetchAll();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM emission_factors')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->query(
            "SELECT * FROM emission_factors ORDER BY effective_date DESC, id DESC LIMIT {$perPage} OFFSET {$offset}"
        );

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM emission_factors WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function applicableOn(string $date): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM emission_factors
             WHERE is_active = 1 AND effective_date <= ?
             ORDER BY effective_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch();

        if ($row === false) {
            $stmt = $this->pdo->query(
                'SELECT * FROM emission_factors WHERE is_active = 1 ORDER BY effective_date ASC, id ASC LIMIT 1'
            );
            $row = $stmt->fetch();
        }

        return $row === false ? null : $row;
    }

    public function effectiveDateExists(string $date, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM emission_factors WHERE effective_date = ?';
        $params = [$date];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name, float $kgPerKwh, ?string $source, string $effectiveDate, bool $active): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO emission_factors (factor_name, kg_co2_per_kwh, source, effective_date, is_active)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $kgPerKwh, $source, $effectiveDate, $active ? 1 : 0]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, float $kgPerKwh, ?string $source, string $effectiveDate, bool $active): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE emission_factors SET factor_name = ?, kg_co2_per_kwh = ?, source = ?, effective_date = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $kgPerKwh, $source, $effectiveDate, $active ? 1 : 0, $id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM emission_factors WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
