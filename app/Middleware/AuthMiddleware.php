<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

/** Require an authenticated user. */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in to continue.');
            redirect('login');
        }
    }
}
