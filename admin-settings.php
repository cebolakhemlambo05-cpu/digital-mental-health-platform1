<?php
session_start();
$sessionTimeout = 1800;

function redirectToLogin() {
    session_unset();
    session_destroy();
    header('Location: admin-login.html?error=expired');
    exit;
}

if (empty($_SESSION['is_admin']) || !$_SESSION['is_admin'] || time() - ($_SESSION['last_activity'] ?? 0) > $sessionTimeout) {
    redirectToLogin();
}

$_SESSION['last_activity'] = time();

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function load_settings() {
    $file = __DIR__ . '/admin-settings.json';
    if (!file_exists($file)) {
        return [
            'siteTitle' => 'Digitala Mental Health Platform',
            'adminEmail' => 'admin@digitalamentalhealthplatform.com',
            'sessionTimeout' => 30,
        ];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? array_merge([
        'siteTitle' => 'Digitala Mental Health Platform',
        'adminEmail' => 'admin@digitalamentalhealthplatform.com',
        'sessionTimeout' => 30,
    ], $data) : [];
}

function save_settings(array $settings) {
    file_put_contents(__DIR__ . '/admin-settings.json', json_encode($settings, JSON_PRETTY_PRINT), LOCK_EX);
}

function sanitize($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$settings = load_settings();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $message = 'CSRF validation failed. Please reload and try again.';
    } else {
        $siteTitle = trim($_POST['siteTitle'] ?? '');
        $adminEmail = trim($_POST['adminEmail'] ?? '');
        $sessionTimeoutValue = intval($_POST['sessionTimeout'] ?? 30);

        if ($siteTitle !== '') {
            $settings['siteTitle'] = $siteTitle;
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $settings['adminEmail'] = $adminEmail;
        }
        $settings['sessionTimeout'] = max(10, min(120, $sessionTimeoutValue));
        save_settings($settings);
        $message = 'Settings updated successfully.';
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitala Mental Health Platform - Admin Settings</title>
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>Digitala Mental Health Platform</h2>
                <p>Settings</p>
            </div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-users.php">Users</a>
                <a href="admin-analytics.php">Mood Analytics</a>
                <a class="active" href="admin-settings.php">Settings</a>
                <a href="admin-logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>Admin Settings</h1>
                    <p class="page-meta">Adjust branding, contact details, and session behavior.</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="card">
                    <p style="color: #f7b7b7; margin: 0;"><?= sanitize($message) ?></p>
                </div>
            <?php endif; ?>

            <section class="card">
                <form method="post" class="form-inline">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

                    <div class="form-group">
                        <label for="siteTitle">Site Title</label>
                        <input id="siteTitle" name="siteTitle" type="text" value="<?= sanitize($settings['siteTitle']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="adminEmail">Admin Contact Email</label>
                        <input id="adminEmail" name="adminEmail" type="email" value="<?= sanitize($settings['adminEmail']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="sessionTimeout">Session Timeout (minutes)</label>
                        <input id="sessionTimeout" name="sessionTimeout" type="number" min="10" max="120" value="<?= sanitize($settings['sessionTimeout']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </section>

            <section class="card">
                <h2>Current Configuration</h2>
                <p><strong>Site Title:</strong> <?= sanitize($settings['siteTitle']) ?></p>
                <p><strong>Admin Contact:</strong> <?= sanitize($settings['adminEmail']) ?></p>
                <p><strong>Session Timeout:</strong> <?= sanitize($settings['sessionTimeout']) ?> minutes</p>
            </section>
        </main>
    </div>
</body>
</html>
