<?php

require_once 'includes/db_connect.php';

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