<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $service = new NotificationService();
        $service->markRead((string) $request->query('read', ''));
        $this->view('notifications/index', ['title' => 'Notifications', 'notifications' => $service->all(100)]);
    }

    public function read(Request $request): void
    {
        $service = new NotificationService();
        $service->markRead((string) $request->input('id', ''));
        $target = trim((string) $request->input('target', ''), '/');
        $this->redirect($target !== '' ? $target : 'notifications');
    }

    public function readAll(Request $request): void
    {
        (new NotificationService())->markAllRead();
        $this->redirect('notifications');
    }
}
