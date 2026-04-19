<?php
session_start();

$adminUser = 'admin';
$adminPassword = 'Admin@123';
$sessionTimeout = 1800;

if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] && time() - ($_SESSION['last_activity'] ?? 0) < $sessionTimeout) {
    header('Location: admin-dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);

    if ($username === $adminUser && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = $adminUser;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: admin-dashboard.php');
        exit;
    }

    header('Location: admin-login.html?error=1');
    exit;
}

header('Location: admin-login.html');
exit;
