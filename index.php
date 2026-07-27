<?php

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirectByRole(currentUserRole());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {

        $error = 'Please enter your login details.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT *
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

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'] . ' ' . $user['surname'];
                $_SESSION['email'] = $user['email'];

                redirectByRole($user['role']);

            } else {

                $error = 'Invalid login details.';
            }

        } catch (PDOException $e) {

            $error = 'A system error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EduLearn LMS - Login</title>

    <link rel="stylesheet" href="assets/css/Lms.css">

</head>

<body>

<div class="login-page">

    <div class="login-container">

        <div class="login-logo">
            <div class="logo-icon">🎓</div>
            <h1>EduLearn</h1>
        </div>

        <p class="login-subtitle">
            Learning Management System
        </p>

        <h2>Welcome Back</h2>

        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="input-group">

                <label for="login">
                    Email, Admin Number or Invigilator Number
                </label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    placeholder="Enter your login"
                    required
                >

            </div>

            <div class="input-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button type="submit" class="login-button">
                Login
            </button>

        </form>

        <div class="login-footer">

            <p>
                New learner?
            </p>

            <a href="register.php">
                Create Learner Account
            </a>

        </div>

    </div>

</div>

<script src="assets/js/Lms.js"></script>

</body>

</html>