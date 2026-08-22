<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

/** Only for guests — logged-in users are sent to the dashboard. */
class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            redirect('');
        }
    }
}
