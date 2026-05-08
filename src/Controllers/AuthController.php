<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\View;

class AuthController
{
    /** Root: redirect to dashboard if logged in, else to login. */
    public function index(): void
    {
        if (Auth::check()) View::redirect('/dashboard');
        View::redirect('/login');
    }

    public function showLogin(): void
    {
        if (Auth::check()) { View::redirect('/dashboard'); return; }
        View::render('auth/login', [
            'error' => $_GET['error'] ?? null,
            'email' => $_GET['email'] ?? '',
            'csrf'  => Auth::csrfToken(),
        ], null);
    }

    public function doLogin(): void
    {
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            View::redirect('/login?error=session');
            return;
        }
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) { View::redirect('/login?error=missing'); return; }

        if (Auth::attempt($email, $password)) {
            View::redirect('/dashboard');
        } else {
            View::redirect('/login?error=invalid&email=' . urlencode($email));
        }
    }

    public function doLogout(): void
    {
        Auth::logout();
        View::redirect('/login');
    }
}
