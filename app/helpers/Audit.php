<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;
use Throwable;

/**
 * Records important administrative and security events.
 */
class Audit
{
    public static function record(string $action, string $entity, ?int $entityId = null, string $details = ''): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                'INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $userId = Session::get('user_id');
            $stmt->execute([
                $userId !== null ? (int) $userId : null,
                $action,
                $entity,
                $entityId,
                $details !== '' ? $details : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Audit failures must never break the main request.
            error_log('Audit log failure: ' . $e->getMessage());
        }
    }
}
