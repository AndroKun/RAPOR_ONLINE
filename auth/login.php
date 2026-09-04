<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        redirect('/staff/dashboard.php');
    }

    $error = 'Username atau password tidak valid.';
}

$pageTitle = 'Login Staff';
require_once __DIR__ . '/../includes/header.php';
?>
<main>
    <h1>Login Staff</h1>
    <?php if ($error !== ''): ?><p><?= e($error) ?></p><?php endif; ?>
    <form method="post">
        <label>Username <input type="text" name="username" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Masuk</button>
    </form>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
