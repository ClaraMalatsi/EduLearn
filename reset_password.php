<?php

require_once 'includes/db_connect.php';

// Emergency utility. It is restricted to the command line: a browser request
// can never run it, so nobody can reset the administrator password over the web.
// Run it with:  php reset_password.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This utility can only be run from the command line: php reset_password.php');
}

$newPassword = 'password';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password_hash = ?
    WHERE admin_number = ?
");

$stmt->execute([$newHash, 'ADMIN001']);

echo "Password reset completed. Rows changed: " . $stmt->rowCount();

?>