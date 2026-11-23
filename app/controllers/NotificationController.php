<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Notification;

class NotificationController extends BaseController
{
    private Notification $notifications;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->notifications = new Notification();
    }

    public function index(): void
    {
        $userId = (int) Session::get('user_id');
        $unreadOnly = !empty($_GET['unread']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->notifications->paginateForUser($userId, $unreadOnly, $page);

        View::render('notifications/index', [
            'pageTitle' => 'Notifications',
            'active' => 'notifications',
            'notifications' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->notifications->unreadCount($userId),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function markRead(int $id): void
    {
        $this->guardCsrf('/notifications');
        $userId = (int) Session::get('user_id');

        if ($this->notifications->markRead($id, $userId)) {
            Session::flash('success', 'Notification marked as read.');
        } else {
            Session::flash('error', 'Notification not found.');
        }
        $this->redirect('/notifications');
    }

    public function markAllRead(): void
    {
        $this->guardCsrf('/notifications');
        $userId = (int) Session::get('user_id');
        $count = $this->notifications->markAllRead($userId);
        Audit::record('UPDATE', 'notifications', null, 'Marked ' . $count . ' notification(s) as read');
        Session::flash('success', $count . ' notification(s) marked as read.');
        $this->redirect('/notifications');
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
