<?php
header('Content-Type: application/json');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$userId = $data['id'] ?? bin2hex(random_bytes(8));

$users = load_users();

// Check if email already exists
foreach ($users as $user) {
    if ($user['email'] === $email) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already registered']);
        exit;
    }
}

// Add new user
$newUser = [
    'id' => $userId,
    'username' => $name,
    'name' => $name,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'joinDate' => date('Y-m-d'),
    'registered_at' => date('Y-m-d H:i:s'),
    'moodEntries' => []
];

$users[] = $newUser;
save_users($users);

echo json_encode([
    'success' => true,
    'message' => 'User registered successfully',
    'user' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email
    ]
]);
