<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

class Notification
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Create a notification once per user/threshold/reporting period.
     */
    public function createOnce(
        int $userId,
        ?int $thresholdId,
        ?string $periodKey,
        string $title,
        string $message,
        string $type = 'warning'
    ): bool {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO notifications (user_id, threshold_id, period_key, title, message, type)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $thresholdId, $periodKey, $title, $message, $type]);

            return true;
        } catch (PDOException $e) {
            // Unique key blocks duplicate alerts for the same threshold/period.
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId, bool $unreadOnly = false, int $limit = 10): array
    {
        $limit = max(1, $limit);
        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function paginateForUser(int $userId, bool $unreadOnly = false, int $page = 1, int $perPage = 15): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $where = 'WHERE user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '');

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM notifications {$where}");
        $count->execute([$userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT * FROM notifications {$where} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$userId]);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }

    /** @return array<int, int> */
    public function adminUserIds(): array
    {
        $rows = $this->pdo->query(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'Super Admin' AND u.status = 'active' AND u.deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }
}
