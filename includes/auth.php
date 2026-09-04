<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if (($_SESSION['user']['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}
