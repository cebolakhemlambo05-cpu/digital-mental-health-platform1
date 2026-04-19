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

function load_users() {
    $file = __DIR__ . '/users.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_users(array $users) {
    file_put_contents(__DIR__ . '/users.json', json_encode(array_values($users), JSON_PRETTY_PRINT), LOCK_EX);
}

function sanitize($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date($value) {
    $date = strtotime($value);
    return $date ? date('M j, Y', $date) : '-';
}

function find_user(array $users, $id) {
    foreach ($users as $user) {
        if ($user['id'] === $id) {
            return $user;
        }
    }
    return null;
}

function mood_history_html(array $entries) {
    if (empty($entries)) {
        return '<p style="color: var(--muted);">No mood history available.</p>';
    }
    $html = '<table><thead><tr><th>Date</th><th>Mood</th><th>Notes</th></tr></thead><tbody>';
    foreach ($entries as $entry) {
        $html .= '<tr><td>' . sanitize($entry['timestamp']) . '</td><td>' . sanitize($entry['mood']) . '</td><td>' . sanitize($entry['notes']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

$users = load_users();
$search = trim($_GET['search'] ?? '');
$viewId = $_GET['view_id'] ?? null;
$editId = $_GET['edit_id'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $message = 'CSRF validation failed. Please refresh and try again.';
    } elseif ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? '';
        $filtered = array_filter($users, fn($user) => $user['id'] !== $userId);
        if (count($filtered) < count($users)) {
            save_users($filtered);
            $users = array_values($filtered);
            $message = 'User deleted successfully.';
        } else {
            $message = 'User not found.';
        }
    } elseif ($action === 'update_user') {
        $userId = $_POST['user_id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $updated = false;
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                if ($username !== '') {
                    $user['username'] = $username;
                }
                if ($email !== '') {
                    $user['email'] = $email;
                }
                $updated = true;
                break;
            }
        }
        unset($user);
        if ($updated) {
            save_users($users);
            $message = 'User profile updated.';
        } else {
            $message = 'User not found.';
        }
    }
}

$filteredUsers = $users;
if ($search !== '') {
    $filteredUsers = array_filter($filteredUsers, function ($user) use ($search) {
        return str_contains(strtolower($user['username']), strtolower($search)) || str_contains(strtolower($user['email']), strtolower($search));
    });
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users-export.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username', 'Email', 'Join Date', 'Mood Entries']);
    foreach ($filteredUsers as $user) {
        fputcsv($output, [$user['id'], $user['username'], $user['email'], $user['joinDate'], count($user['moodEntries'] ?? [])]);
    }
    fclose($output);
    exit;
}

$viewUser = $viewId ? find_user($users, $viewId) : null;
$editUser = $editId ? find_user($users, $editId) : null;
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitala Mental Health Platform - User Management</title>
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>Digitala Mental Health Platform</h2>
                <p>User Management</p>
            </div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a class="active" href="admin-users.php">Users</a>
                <a href="admin-analytics.php">Mood Analytics</a>
                <a href="admin-settings.php">Settings</a>
                <a href="admin-logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>User Management</h1>
                    <p class="page-meta">Search, review mood history, update accounts, and export user data.</p>
                </div>
                <a class="btn btn-primary" href="admin-users.php?export=csv">Export CSV</a>
            </header>

            <?php if ($message): ?>
                <div class="card">
                    <p style="color: #f7b7b7; margin: 0;"><?= sanitize($message) ?></p>
                </div>
            <?php endif; ?>

            <section class="card form-inline">
                <form method="get" style="width:100%; display:grid; gap:12px;">
                    <input type="search" name="search" placeholder="Search by username or email" value="<?= sanitize($search) ?>" style="padding:14px 16px; border-radius:14px; border:1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: var(--text);">
                    <button type="submit" class="btn btn-primary" style="width:fit-content;">Search</button>
                </form>
            </section>

            <section class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Join Date</th>
                                <th>Mood Entries</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredUsers as $user): ?>
                                <tr>
                                    <td><?= sanitize($user['username']) ?></td>
                                    <td><?= sanitize($user['email']) ?></td>
                                    <td><?= sanitize(format_date($user['joinDate'])) ?></td>
                                    <td><?= count($user['moodEntries'] ?? []) ?></td>
                                    <td>
                                        <a class="tag" href="admin-users.php?view_id=<?= urlencode($user['id']) ?>">View</a>
                                        <a class="tag" href="admin-users.php?edit_id=<?= urlencode($user['id']) ?>">Edit</a>
                                        <form style="display:inline-block; margin:0;" method="post" onsubmit="return confirm('Delete this user permanently?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= sanitize($user['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                            <button class="tag" style="background: rgba(214,68,68,0.16); border:none; color:#ffb8b8;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($viewUser): ?>
                <section class="card">
                    <h2>Detail View: <?= sanitize($viewUser['username']) ?></h2>
                    <p><strong>Email:</strong> <?= sanitize($viewUser['email']) ?></p>
                    <p><strong>Join Date:</strong> <?= sanitize(format_date($viewUser['joinDate'])) ?></p>
                    <p><strong>Total Mood Entries:</strong> <?= count($viewUser['moodEntries'] ?? []) ?></p>
                    <h3 style="margin-top: 20px;">Mood History</h3>
                    <?= mood_history_html($viewUser['moodEntries'] ?? []) ?>
                </section>
            <?php endif; ?>

            <?php if ($editUser): ?>
                <section class="card">
                    <h2>Edit Account: <?= sanitize($editUser['username']) ?></h2>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= sanitize($editUser['id']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input id="username" name="username" value="<?= sanitize($editUser['username']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?= sanitize($editUser['email']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
