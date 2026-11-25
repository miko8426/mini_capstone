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

    /**
     * Re-read the account's role and organizational assignment from the
     * database on every request, so a campus/office change (or a role change,
     * deactivation or deletion) made by an administrator applies immediately
     * instead of waiting for the user to log out and back in.
     *
     * Logs the session out when the account is no longer allowed to sign in.
     */
    public static function syncSession(): void
    {
        $userId = (int) Session::get('user_id', 0);
        if ($userId <= 0) {
            return;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT u.first_name, u.last_name, u.campus_id, u.office_id, u.status, u.deleted_at,
                    r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false || $user['status'] !== 'active' || $user['deleted_at'] !== null) {
            self::logout();

            return;
        }

        Session::set('user_role', (string) $user['role_name']);
        Session::set('user_name', trim((string) $user['first_name'] . ' ' . (string) $user['last_name']));
        Session::set('user_campus_id', $user['campus_id'] !== null ? (int) $user['campus_id'] : null);
        Session::set('user_office_id', $user['office_id'] !== null ? (int) $user['office_id'] : null);
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
     * The most specific assignment limits visibility: an office assignment
     * restricts the account to that office, otherwise a campus assignment
     * restricts it to everything inside that campus. An account with neither
     * matches nothing (campus_id -1). An empty array means unrestricted
     * (administrator).
     *
     * @return array<string, int>
     */
    public static function scope(): array
    {
        if (self::isAdmin()) {
            return [];
        }

        $officeId = (int) (Session::get('user_office_id') ?? 0);
        if ($officeId > 0) {
            return ['office_id' => $officeId];
        }

        $campusId = (int) (Session::get('user_campus_id') ?? 0);
        if ($campusId > 0) {
            return ['campus_id' => $campusId];
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
     * only when no scope restriction applies. A conflicting request filter on
     * the other hierarchy level (a campus id while scoped to an office, or an
     * office id while scoped to a campus) is dropped so URL parameters can
     * never widen or empty the result set.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function applyScope(array $filters): array
    {
        $scope = self::scope();

        if (isset($scope['office_id'])) {
            unset($filters['campus_id']);
        }
        if (isset($scope['campus_id'])) {
            unset($filters['office_id']);
        }

        return array_merge($filters, $scope);
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
        if (isset($scope['office_id'])) {
            return $scope['office_id'] === $officeId;
        }
        if (isset($scope['campus_id'])) {
            return $campusId !== null && $scope['campus_id'] === $campusId;
        }

        return false;
    }
}
