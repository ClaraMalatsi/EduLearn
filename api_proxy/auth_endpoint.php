<?php
/**
 * REST authentication endpoint.
 *
 * Receives JSON credentials, validates them against the users table and
 * returns a JSON verdict. index.php calls this over HTTP through
 * callExternalAuthApi() in auth_api.php, so authentication runs through a
 * real REST round-trip as the integration spec requires. Point index.php
 * at a different URL later to swap in a real third-party provider.
 */

require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$login = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(['status' => 'fail', 'message' => 'Missing credentials.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT user_id, role, name, surname, email, password_hash
    FROM users
    WHERE email = :email
       OR admin_number = :admin_number
       OR invigilator_number = :invigilator_number
    LIMIT 1
");
$stmt->execute([
    ':email' => $login,
    ':admin_number' => $login,
    ':invigilator_number' => $login
]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    echo json_encode([
        'status' => 'success',
        'token' => bin2hex(random_bytes(16)),
        'user_id' => (int)$user['user_id'],
        'role' => $user['role'],
        'name' => $user['name'] . ' ' . $user['surname'],
        'email' => $user['email']
    ]);
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Invalid login details.']);
}
