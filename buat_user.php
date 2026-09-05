<?php
declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';

$username = 'admin_baru';
$password_asli = 'rahasia123';
$role = 'staff'; 
$is_active = 1; 

$password_hash = password_hash($password_asli, PASSWORD_DEFAULT);

try {
    $statement = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (:username, :password_hash, :role, :is_active)');
    $statement->execute([
        'username' => $username,
        'password_hash' => $password_hash,
        'role' => $role,
        'is_active' => $is_active
    ]);
    
    echo "<h3>Sukses! Akun baru berhasil ditambahkan ke database.</h3>";
    echo "Silakan coba login dengan data berikut:<br>";
    echo "Username : <b>{$username}</b><br>";
    echo "Password : <b>{$password_asli}</b><br><br>";
    echo "<a href='auth/login.php'>Klik di sini untuk menuju halaman Login</a>";

} catch (PDOException $e) {
    echo "Gagal menambahkan user. Pastikan tabel 'users' sudah terbuat di database. Error: " . $e->getMessage();
}
?>