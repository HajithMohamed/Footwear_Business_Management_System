<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

/** Require the administrator role (owner-only areas). */
class AdminMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Session::flash('error', 'You do not have permission to access that area.');
            redirect('');
        }
    }
}
