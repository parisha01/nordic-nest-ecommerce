<?php
require_once __DIR__ . '/includes/functions.php';

// Fully destroy the session: clear data, expire the cookie, then wipe.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

// Start a fresh session only so the flash message can be shown once on the login page.
session_start();
flash_set('success', 'You have been logged out.');
redirect('login.php');
