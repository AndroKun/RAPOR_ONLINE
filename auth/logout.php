<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/fungsi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_start();

set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect('/auth/login.php');
