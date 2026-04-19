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

function load_users() {
    $file = __DIR__ . '/users.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function sanitize($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function pretty_date($date) {
    $timestamp = strtotime($date);
    return $timestamp ? date('M j', $timestamp) : '-';
}

$users = load_users();
$moodDistribution = ['Very Low' => 0, 'Low' => 0, 'Neutral' => 0, 'Good' => 0, 'Great' => 0];
$dailyCounts = [];
$periodStart = strtotime('-29 days');
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("{$i} days", $periodStart));
    $dailyCounts[$date] = 0;
}
$userDates = [];
$mostActive = [];

foreach ($users as $user) {
    $entryCount = 0;
    foreach ($user['moodEntries'] as $entry) {
        $entryCount++;
        $moodKey = normalize_mood_label($entry['mood']);
        $moodDistribution[$moodKey]++;
        $timestamp = strtotime($entry['timestamp']);
        if ($timestamp && isset($dailyCounts[date('Y-m-d', $timestamp)])) {
            $dailyCounts[date('Y-m-d', $timestamp)]++;
        }
        if ($timestamp) {
            $userDates[$user['id']][] = $timestamp;
        }
    }
    $mostActive[] = ['username' => $user['username'], 'count' => $entryCount];
}

usort($mostActive, fn ($a, $b) => $b['count'] <=> $a['count']);
$activeToday = 0;
$activeWeek = 0;
$activeMonth = 0;
$todayStart = strtotime('today');
$weekStart = strtotime('-6 days', $todayStart);
$monthStart = strtotime('-29 days', $todayStart);

foreach ($users as $user) {
    $latest = 0;
    foreach ($user['moodEntries'] as $entry) {
        $timestamp = strtotime($entry['timestamp']);
        if ($timestamp > $latest) {
            $latest = $timestamp;
        }
        if ($timestamp >= $todayStart) {
            $activeToday++;
            break;
        }
    }
    foreach ($user['moodEntries'] as $entry) {
        $timestamp = strtotime($entry['timestamp']);
        if ($timestamp >= $weekStart) {
            $activeWeek++;
            break;
        }
    }
    foreach ($user['moodEntries'] as $entry) {
        $timestamp = strtotime($entry['timestamp']);
        if ($timestamp >= $monthStart) {
            $activeMonth++;
            break;
        }
    }
}

$chartLabels = array_map('pretty_date', array_keys($dailyCounts));
$chartValues = array_values($dailyCounts);
$moodLabels = array_keys($moodDistribution);
$moodValues = array_values($moodDistribution);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=analytics-report.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Active users today', $activeToday]);
    fputcsv($output, ['Active users last 7 days', $activeWeek]);
    fputcsv($output, ['Active users last 30 days', $activeMonth]);
    fputcsv($output, []);
    fputcsv($output, ['Mood Category', 'Count']);
    foreach ($moodDistribution as $label => $count) {
        fputcsv($output, [$label, $count]);
    }
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitala Mental Health Platform - Mood Analytics</title>
    <link rel="stylesheet" href="admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>Digitala Mental Health Platform</h2>
                <p>Analytics</p>
            </div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-users.php">Users</a>
                <a class="active" href="admin-analytics.php">Mood Analytics</a>
                <a href="admin-settings.php">Settings</a>
                <a href="admin-logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>Mood Analytics</h1>
                    <p class="page-meta">Track mood behavior, active participation, and export measurement reports.</p>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <a class="btn btn-primary" href="admin-analytics.php?export=csv">Export CSV</a>
                    <button id="printReport" class="btn btn-secondary" style="background: rgba(255,255,255,0.08);">Print PDF</button>
                </div>
            </header>

            <section class="stats-grid">
                <div class="card">
                    <h2>Active Today</h2>
                    <p class="stat-value"><?= sanitize($activeToday) ?></p>
                    <p>Unique users who submitted mood data within the last 24 hours.</p>
                </div>
                <div class="card">
                    <h2>Active Last 7 Days</h2>
                    <p class="stat-value"><?= sanitize($activeWeek) ?></p>
                    <p>Users with recent engagement over one week.</p>
                </div>
                <div class="card">
                    <h2>Active Last 30 Days</h2>
                    <p class="stat-value"><?= sanitize($activeMonth) ?></p>
                    <p>Tracked activity for the past month.</p>
                </div>
            </section>

            <section class="dashboard-grid">
                <div class="card">
                    <h2>Daily Mood Log</h2>
                    <canvas id="lineChart" height="220"></canvas>
                </div>
                <div class="card">
                    <h2>Mood Distribution</h2>
                    <canvas id="barChart" height="220"></canvas>
                </div>
            </section>

            <section class="card">
                <h2>Most Active Users</h2>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Entries</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($mostActive, 0, 5) as $activeUser): ?>
                                <tr>
                                    <td><?= sanitize($activeUser['username']) ?></td>
                                    <td><?= sanitize($activeUser['count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        const lineLabels = <?= json_encode($chartLabels, JSON_HEX_TAG) ?>;
        const lineData = <?= json_encode($chartValues, JSON_HEX_TAG) ?>;
        const moodLabels = <?= json_encode($moodLabels, JSON_HEX_TAG) ?>;
        const moodValues = <?= json_encode($moodValues, JSON_HEX_TAG) ?>;

        new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: {
                labels: lineLabels,
                datasets: [{
                    label: 'Mood entries',
                    data: lineData,
                    fill: true,
                    backgroundColor: 'rgba(214, 68, 68, 0.16)',
                    borderColor: '#d64444',
                    tension: 0.35,
                    pointRadius: 3,
                }],
            },
            options: {
                scales: {
                    x: { ticks: { color: '#f5f5f7' } },
                    y: { ticks: { color: '#f5f5f7' }, beginAtZero: true },
                },
                plugins: { legend: { display: false } },
            },
        });

        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: moodLabels,
                datasets: [{
                    label: 'Mood distribution',
                    data: moodValues,
                    backgroundColor: ['#b33939', '#d46b6b', '#e6a643', '#7fbd8a', '#8bdc93'],
                }],
            },
            options: {
                scales: {
                    x: { ticks: { color: '#f5f5f7' } },
                    y: { ticks: { color: '#f5f5f7' }, beginAtZero: true },
                },
                plugins: { legend: { display: false } },
            },
        });

        document.getElementById('printReport').addEventListener('click', function () {
            window.print();
        });
    </script>
</body>
</html>
