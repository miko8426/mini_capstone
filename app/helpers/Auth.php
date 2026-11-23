<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;
use PDO;

class Auth
{
    /** Idle session lifetime in seconds (1 hour). */
    public const SESSION_TIMEOUT = 3600;

    public static function attemptLogin(string $email, string $password): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.email = :email AND u.status = 'active' AND u.deleted_at IS NULL"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, (string) $user['password_hash'])) {
            Session::start();
            session_regenerate_id(true);

            Session::set('user_id', (int) $user['id']);
            Session::set('user_role', $user['role_name']);
            Session::set('user_name', trim((string) $user['first_name'] . ' ' . (string) $user['last_name']));
            Session::set('user_campus_id', $user['campus_id'] !== null ? (int) $user['campus_id'] : null);
            Session::set('user_office_id', $user['office_id'] !== null ? (int) $user['office_id'] : null);
            Session::set('last_activity', time());

            return true;
        }

        return false;
    }

    public static function isLoggedIn(): bool
    {
        Session::start();

        if (Session::get('user_id') === null) {
            return false;
        }

        $lastActivity = Session::get('last_activity');
        if ($lastActivity !== null && time() - (int) $lastActivity > self::SESSION_TIMEOUT) {
            self::logout();

            return false;
        }

        Session::set('last_activity', time());

        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function user(): array
    {
        return [
            'id' => (int) Session::get('user_id', 0),
            'name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('user_role', ''),
            'campus_id' => Session::get('user_campus_id'),
            'office_id' => Session::get('user_office_id'),
        ];
    }

    public static function hasRole(string $role): bool
    {
        return Session::get('user_role') === $role;
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('Super Admin');
    }

    /**
     * Administrators, campus managers and department auditors may manage data.
     * Viewers are read-only.
     */
    public static function canManage(): bool
    {
        $role = Session::get('user_role');

        return in_array($role, ['Super Admin', 'Campus Manager', 'Department Auditor'], true);
    }

    /**
     * Only administrators may manage organization records, users, rates,
     * emission factors and thresholds.
     */
    public static function canAdminister(): bool
    {
        return self::isAdmin();
    }

    /**
     * Organizational scope enforced in PHP for non-admin accounts.
     *
     * A campus assignment grants access to everything inside that campus;
     * an office assignment is only used when no campus has been assigned.
     * An account with neither matches nothing (campus_id -1).
     * An empty array means unrestricted (administrator).
     *
     * @return array<string, int>
     */
    public static function scope(): array
    {
        if (self::isAdmin()) {
            return [];
        }

        $campusId = (int) (Session::get('user_campus_id') ?? 0);
        if ($campusId > 0) {
            return ['campus_id' => $campusId];
        }

        $officeId = (int) (Session::get('user_office_id') ?? 0);
        if ($officeId > 0) {
            return ['office_id' => $officeId];
        }

        return ['campus_id' => -1];
    }

    public static function officeId(): ?int
    {
        $value = Session::get('user_office_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Merge the logged-in user's scope into a filter array. Admin filters win
     * only when no scope restriction applies.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function applyScope(array $filters): array
    {
        return array_merge($filters, self::scope());
    }

    /**
     * Whether the current user may access a record with the given scope keys.
     * Unlimited for administrators; every configured scope key must match.
     *
     * @param array<string, mixed> $row
     */
    public static function allows(array $row): bool
    {
        foreach (self::scope() as $key => $value) {
            if ((int) ($row[$key] ?? 0) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the current user may see the given office.
     */
    public static function canAccessOffice(int $officeId, ?int $campusId = null): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $scope = self::scope();
        if (isset($scope['campus_id'])) {
            return $campusId !== null && $scope['campus_id'] === $campusId;
        }
        if (isset($scope['office_id'])) {
            return $scope['office_id'] === $officeId;
        }

        return false;
    }
}
