<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Appliance;
use App\Models\User;

/**
 * Recycle bin: review, restore or permanently delete records removed from
 * User Management and the appliance inventory.
 */
class RecycleBinController extends BaseController
{
    private const LIST_LIMIT = 100;

    private User $users;
    private Appliance $appliances;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->users = new User();
        $this->appliances = new Appliance();
    }

    public function index(): void
    {
        $archived = $this->appliances->paginate(Auth::applyScope(['status' => 'archived']), 1, self::LIST_LIMIT);

        View::render('recycle_bin/index', [
            'pageTitle' => 'Recycle Bin',
            'active' => 'recycle-bin',
            // Deleted accounts are only listed for administrators; every other
            // role gets the appliance bin inside its own scope.
            'showAccounts' => Auth::canAdminister(),
            'canManage' => Auth::canManage(),
            'users' => Auth::canAdminister() ? $this->users->deleted(self::LIST_LIMIT) : [],
            'appliances' => $archived['items'],
            'applianceTotal' => $archived['total'],
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function restoreUser(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/recycle-bin');

        $user = $this->users->find($id);
        if ($user === null || $user['deleted_at'] === null) {
            Session::flash('error', 'That account is not in the Recycle Bin.');
            $this->redirect('/recycle-bin');
        }

        $this->users->restore($id);
        Audit::record('RESTORE', 'users', $id, 'Restored user ' . $user['email'] . ' from the Recycle Bin');
        Session::flash('success', 'User account restored. The account can log in again.');
        $this->redirect('/recycle-bin');
    }

    public function purgeUser(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/recycle-bin');

        $user = $this->users->find($id);
        if ($user === null || $user['deleted_at'] === null) {
            Session::flash('error', 'That account is not in the Recycle Bin.');
            $this->redirect('/recycle-bin');
        }

        if ($id === (int) Session::get('user_id')) {
            Session::flash('error', 'You cannot permanently delete your own account.');
            $this->redirect('/recycle-bin');
        }

        $this->users->forceDelete($id);
        Audit::record('DELETE', 'users', $id, 'Permanently deleted user ' . $user['email'] . ' from the Recycle Bin');
        Session::flash('success', 'User account permanently deleted.');
        $this->redirect('/recycle-bin');
    }

    public function restoreAppliance(int $id): void
    {
        $appliance = $this->guardScopedAppliance($id);
        $this->guardCsrf('/recycle-bin');

        $this->appliances->restore($id);
        Audit::record('RESTORE', 'appliances', $id, 'Restored appliance ' . $appliance['name'] . ' from the Recycle Bin');
        Session::flash('success', 'Appliance restored to the active inventory.');
        $this->redirect('/recycle-bin');
    }

    public function purgeAppliance(int $id): void
    {
        $appliance = $this->guardScopedAppliance($id);
        $this->guardCsrf('/recycle-bin');

        $this->appliances->forceDelete($id);
        Audit::record('DELETE', 'appliances', $id, 'Permanently deleted appliance ' . $appliance['name'] . ' from the Recycle Bin');
        Session::flash('success', 'Appliance permanently deleted along with its recorded usage.');
        $this->redirect('/recycle-bin');
    }

    /**
     * Permanently delete several selected accounts at once.
     */
    public function purgeUsers(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/recycle-bin');

        $ids = $this->postedIds();
        if ($ids === []) {
            Session::flash('error', 'Select at least one account to delete permanently.');
            $this->redirect('/recycle-bin');
        }

        $selfId = (int) Session::get('user_id');
        $removable = [];
        foreach ($ids as $id) {
            $user = $this->users->find($id);
            if ($user === null || $user['deleted_at'] === null || $id === $selfId) {
                continue;
            }
            $removable[] = $id;
        }

        if ($removable === []) {
            Session::flash('error', 'None of the selected accounts can be deleted permanently.');
            $this->redirect('/recycle-bin');
        }

        $deleted = $this->users->forceDeleteMany($removable);
        Audit::record(
            'DELETE',
            'users',
            null,
            'Permanently deleted ' . $deleted . ' user account(s) from the Recycle Bin (ids: ' . implode(', ', $removable) . ')'
        );

        $message = $deleted . ' account' . ($deleted === 1 ? '' : 's') . ' permanently deleted.';
        $skipped = count($ids) - $deleted;
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' selected record' . ($skipped === 1 ? ' was' : 's were')
                . ' skipped (no longer in the Recycle Bin, or your own account).';
        }

        Session::flash('success', $message);
        $this->redirect('/recycle-bin');
    }

    /**
     * Permanently delete several selected appliances at once.
     */
    public function purgeAppliances(): void
    {
        $this->guardManage();
        $this->guardCsrf('/recycle-bin');

        $ids = $this->postedIds();
        if ($ids === []) {
            Session::flash('error', 'Select at least one appliance to delete permanently.');
            $this->redirect('/recycle-bin');
        }

        $removable = [];
        foreach ($ids as $id) {
            $appliance = $this->appliances->find($id);
            if ($appliance === null || $appliance['archived_at'] === null || !Auth::allows($appliance)) {
                continue;
            }
            $removable[] = $id;
        }

        if ($removable === []) {
            Session::flash('error', 'None of the selected appliances can be deleted permanently.');
            $this->redirect('/recycle-bin');
        }

        $deleted = $this->appliances->forceDeleteMany($removable);
        Audit::record(
            'DELETE',
            'appliances',
            null,
            'Permanently deleted ' . $deleted . ' appliance(s) from the Recycle Bin (ids: ' . implode(', ', $removable) . ')'
        );

        $message = $deleted . ' appliance' . ($deleted === 1 ? '' : 's') . ' permanently deleted with their recorded usage.';
        $skipped = count($ids) - $deleted;
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' selected record' . ($skipped === 1 ? ' was' : 's were')
                . ' skipped (no longer in the Recycle Bin).';
        }

        Session::flash('success', $message);
        $this->redirect('/recycle-bin');
    }

    /** @return array<int, int> */
    private function postedIds(): array
    {
        $raw = $_POST['ids'] ?? [];

        return is_array($raw)
            ? array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)))
            : [];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can manage deleted accounts.');
        }
    }

    /**
     * Appliance bin actions are open to every account that may manage the
     * inventory, but only for records inside that account's own scope.
     */
    private function guardManage(): void
    {
        if (!Auth::canManage()) {
            http_response_code(403);
            exit('Access Denied: your role has read-only access to the Recycle Bin.');
        }
    }

    /**
     * Load an archived appliance the current user is allowed to act on.
     *
     * @return array<string, mixed>
     */
    private function guardScopedAppliance(int $id): array
    {
        $this->guardManage();

        $appliance = $this->appliances->find($id);
        if ($appliance === null || $appliance['archived_at'] === null || !Auth::allows($appliance)) {
            Session::flash('error', 'That appliance is not in your Recycle Bin.');
            $this->redirect('/recycle-bin');
        }

        return $appliance;
    }

    private function guardCsrf(string $redirectTo): void
    {
        if (!$this->csrfValid()) {
            Session::flash('error', 'Security token expired. Please try again.');
            $this->redirect($redirectTo);
        }
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
