<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Database;
use PDO;

/**
 * Read-only lookups for the organizational hierarchy used when assigning
 * appliances: campus -> office.
 */
class Organization
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Campuses visible to the given organizational scope.
     * An empty scope returns every campus (administrator).
     *
     * @param array<string, int> $scope
     * @return array<int, array<string, mixed>>
     */
    public function campuses(array $scope = []): array
    {
        $sql = 'SELECT id, name, code FROM campuses';
        $params = [];

        if (isset($scope['campus_id'])) {
            $sql .= ' WHERE id = ?';
            $params[] = (int) $scope['campus_id'];
        } elseif (isset($scope['office_id'])) {
            $sql .= ' WHERE id = (SELECT campus_id FROM offices WHERE id = ?)';
            $params[] = (int) $scope['office_id'];
        }

        $stmt = $this->pdo->prepare($sql . ' ORDER BY name ASC');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Offices with their campus, limited to the given organizational scope.
     * An empty scope returns every office (administrator).
     *
     * @param array<string, int> $scope
     * @return array<int, array<string, mixed>>
     */
    public function offices(array $scope = []): array
    {
        $sql = 'SELECT o.id, o.name, o.room_number, o.floor_level, o.campus_id,
                    cp.name AS campus_name
             FROM offices o
             JOIN campuses cp ON cp.id = o.campus_id';
        $params = [];

        if (isset($scope['campus_id'])) {
            $sql .= ' WHERE o.campus_id = ?';
            $params[] = (int) $scope['campus_id'];
        } elseif (isset($scope['office_id'])) {
            $sql .= ' WHERE o.id = ?';
            $params[] = (int) $scope['office_id'];
        }

        $stmt = $this->pdo->prepare($sql . ' ORDER BY cp.name ASC, o.name ASC');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Find a single office (assignment target) with its parent campus.
     *
     * @return array<string, mixed>|null
     */
    public function findOffice(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.name, o.room_number, o.campus_id, cp.name AS campus_name
             FROM offices o
             JOIN campuses cp ON cp.id = o.campus_id
             WHERE o.id = ?'
        );
        $stmt->execute([$id]);
        $office = $stmt->fetch();

        return $office === false ? null : $office;
    }
}
