<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth/login', ['title' => 'Sign in'], 'auth');
    }

    public function login(Request $request): void
    {
        $data = $request->all(['username', 'password']);

        $v = new Validator($data, [
            'username' => 'required|string|max:60',
            'password' => 'required|string',
        ]);
        if ($v->fails()) {
            $this->withErrors($v->errors(), ['username' => $data['username'] ?? '']);
        }

        $user = Auth::attempt($data['username'], $data['password']);
        if (!$user) {
            $this->log('login_failed', 'user', null, ['username' => $data['username'] ?? '']);
            $this->withErrors(
                ['username' => ['Invalid username or password.']],
                ['username' => $data['username'] ?? '']
            );
        }

        $this->log('login', 'user', (int) $user['id']);
        Session::flash('success', 'Welcome back, ' . $user['name'] . '!');
        $this->redirect('');
    }

    public function logout(Request $request): void
    {
        $this->log('logout', 'user', Auth::id());
        Auth::logout();
        Session::start(); // fresh session for the flash below
        Session::flash('success', 'You have been signed out.');
        $this->redirect('login');
    }
}
