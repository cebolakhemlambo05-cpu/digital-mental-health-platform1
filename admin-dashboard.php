<?php
session_start();
$sessionTimeout = 1800;

function redirectToLogin($reason = 'expired') {
    session_unset();
    session_destroy();
    header('Location: admin-login.html?error=' . urlencode($reason));
    exit;
}

if (empty($_SESSION['is_admin']) || !$_SESSION['is_admin'] || time() - ($_SESSION['last_activity'] ?? 0) > $sessionTimeout) {
    redirectToLogin('expired');
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

function mood_score($mood) {
    return match (strtolower(trim($mood))) {
        'very low' => 1,
        'low' => 2,
        'neutral' => 3,
        'good' => 4,
        'great', 'excellent' => 5,
        default => 3,
    };
}

function normalize_mood_label($mood) {
    return match (strtolower(trim($mood))) {
        'very low' => 'Very Low',
        'low' => 'Low',
        'neutral' => 'Neutral',
        'good' => 'Good',
        'great', 'excellent' => 'Great',
        default => 'Neutral',
    };
}

function format_date($value) {
    $date = strtotime($value);
    return $date ? date('M j, Y', $date) : '-';
}

$users = load_users();
$deleteMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $userId = $_POST['user_id'] ?? null;
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $deleteMessage = 'Unable to verify request. Please reload and try again.';
    } else {
        $filtered = array_filter($users, fn($user) => $user['id'] !== $userId);
        if (count($filtered) < count($users)) {
            save_users($filtered);
            $users = array_values($filtered);
            $deleteMessage = 'User deleted successfully.';
        } else {
            $deleteMessage = 'User not found.';
        }
    }
}

$totalUsers = count($users);
$totalMoodEntries = array_sum(array_map(fn($user) => count($user['moodEntries'] ?? []), $users));
$activeToday = 0;
$sumMoodScores = 0;
$subjectMoodCount = 0;
$moodDistribution = [
    'Very Low' => 0,
    'Low' => 0,
    'Neutral' => 0,
    'Good' => 0,
    'Great' => 0,
];
$today = date('Y-m-d');

foreach ($users as $user) {
    foreach ($user['moodEntries'] as $entry) {
        $score = mood_score($entry['mood']);
        $sumMoodScores += $score;
        $subjectMoodCount++;
        $key = normalize_mood_label($entry['mood']);
        $moodDistribution[$key]++;

        if (strpos($entry['timestamp'], $today) === 0) {
            $activeToday++;
        }
    }
}

$averageMoodScore = $subjectMoodCount > 0 ? round($sumMoodScores / $subjectMoodCount, 2) : 0;

$recentUsers = $users;
usort($recentUsers, fn($a, $b) => strtotime($b['joinDate']) <=> strtotime($a['joinDate']));
$recentUsers = array_slice($recentUsers, 0, 5);

function user_mood_count($user) {
    return count($user['moodEntries'] ?? []);
}

$chartLabels = array_keys($moodDistribution);
$chartData = array_values($moodDistribution);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitala Mental Health Platform - Admin Dashboard</title>
    <link rel="stylesheet" href="admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>Digitala Mental Health Platform</h2>
                <p>Admin Panel</p>
            </div>
            <nav>
                <a class="active" href="admin-dashboard.php">Dashboard</a>
                <a href="admin-users.php">Users</a>
                <a href="admin-analytics.php">Mood Analytics</a>
                <a href="admin-settings.php">Settings</a>
                <a href="admin-logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p class="page-meta">Overview of platform activity and live mood distribution.</p>
                </div>
                <div class="tag">Secure administration</div>
            </header>

            <?php if ($deleteMessage): ?>
                <div class="card">
                    <p style="color: #f7b7b7; margin: 0;"><?= htmlspecialchars($deleteMessage) ?></p>
                </div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="card">
                    <h2>Total Users</h2>
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <p>Registered accounts available in the system.</p>
                </div>
                <div class="card">
                    <h2>Total Mood Entries</h2>
                    <div class="stat-value"><?= $totalMoodEntries ?></div>
                    <p>Tracked mood records from all users.</p>
                </div>
                <div class="card">
                    <h2>Active Today</h2>
                    <div class="stat-value"><?= $activeToday ?></div>
                    <p>Users who submitted mood data today.</p>
                </div>
                <div class="card">
                    <h2>Average Mood Score</h2>
                    <div class="stat-value"><?= $averageMoodScore ?></div>
                    <p>Average score across all entries.</p>
                </div>
            </section>

            <section class="card">
                <h2>Recent Users</h2>
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
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars(format_date($user['joinDate'])) ?></td>
                                    <td><?= user_mood_count($user) ?></td>
                                    <td>
                                        <a class="tag" href="admin-users.php?view_id=<?= urlencode($user['id']) ?>">View</a>
                                        <form style="display:inline-block; margin:0;" method="post" onsubmit="return confirm('Delete this user? This action is permanent.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button class="tag" style="background: rgba(214,68,68,0.16); border:none; color:#ffb8b8;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-grid">
                <div class="card">
                    <h2>Mood Distribution</h2>
                    <canvas id="moodChart" height="180"></canvas>
                </div>
                <div class="card">
                    <h2>Quick Actions</h2>
                    <p>Use these shortcuts to manage users and inspect mood trends quickly.</p>
                    <div style="display:grid; gap: 12px; margin-top: 20px;">
                        <a class="btn btn-primary" href="admin-users.php">Manage Users</a>
                        <a class="btn btn-primary" href="admin-analytics.php">Open Analytics</a>
                        <a class="btn btn-secondary" href="admin-settings.php" style="background: rgba(255,255,255,0.08);">Admin Settings</a>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const chartLabels = <?= json_encode($chartLabels, JSON_HEX_TAG) ?>;
        const chartData = <?= json_encode($chartData, JSON_HEX_TAG) ?>;
        const ctx = document.getElementById('moodChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: ['#a32323', '#c33d3d', '#e6a643', '#65a07a', '#8bdc93'],
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                }],
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            color: '#f5f5f7',
                        },
                    },
                },
            },
        });
    </script>
</body>
</html>
