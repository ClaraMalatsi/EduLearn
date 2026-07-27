<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output safely.
 */
function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in.
 */
function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get logged-in user ID.
 */
function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get logged-in user role.
 */
function currentUserRole()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Redirect based on role.
 */
function redirectByRole($role)
{
    switch ($role) {

        case 'admin':
            header('Location: admin/dashboard.php');
            exit;

        case 'instructor':
            header('Location: instructor/dashboard.php');
            exit;

        case 'learner':
            header('Location: learner/dashboard.php');
            exit;

        default:
            header('Location: index.php');
            exit;
    }
}

/**
 * Require login.
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Require a specific role.
 */
function requireRole($requiredRole)
{
    requireLogin();

    if ($_SESSION['role'] !== $requiredRole) {
        http_response_code(403);

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f8f8f8;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                }

                .box {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0,0,0,.1);
                }

                h1 {
                    color: #b00020;
                }

                a {
                    display: inline-block;
                    margin-top: 20px;
                    background: #b00020;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 6px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>Access Denied</h1>
                <p>You do not have permission to access this page.</p>
                <a href="../index.php">Return to Login</a>
            </div>
        </body>
        </html>';

        exit;
    }
}

/**
 * Log out user.
 */
function logoutUser()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header('Location: index.php');
    exit;
}