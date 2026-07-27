<?php
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'EduLearn LMS') ?></title>
<link rel="stylesheet" href="../assets/css/Lms.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="lms-app">

<aside class="sidebar" id="sidebar">
    <div class="logo">
        <div class="logo-mark"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="logo-text">
            <span>EduLearn</span>
            <small>LMS PORTAL</small>
        </div>
    </div>

    <nav class="sidebar-navigation">
        <div class="nav-label">MAIN MENU</div>
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i><span>Dashboard</span>
                </a>
            </li>

            <?php if ($role === 'admin'): ?>
                <li class="menu-item">
                    <a href="manage_users.php" class="<?= $currentPage === 'manage_users.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i><span>Users</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="manage_courses.php" class="<?= $currentPage === 'manage_courses.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-book"></i><span>Courses</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="progress.php" class="<?= $currentPage === 'progress.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i><span>Progress</span>
                    </a>
                </li>
            <?php elseif ($role === 'instructor'): ?>
                <li class="menu-item">
                    <a href="manage_quiz.php" class="<?= $currentPage === 'manage_quiz.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-question"></i><span>Quizzes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="view_results.php" class="<?= $currentPage === 'view_results.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-column"></i><span>Quiz Results</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="view_students.php" class="<?= $currentPage === 'view_students.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i><span>Students</span>
                    </a>
                </li>
            <?php elseif ($role === 'learner'): ?>
                <li class="menu-item">
                    <a href="view_courses.php" class="<?= $currentPage === 'view_courses.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-book-open"></i><span>My Courses</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="quiz_results.php" class="<?= $currentPage === 'quiz_results.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-square-poll-vertical"></i><span>Quiz Results</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="../profile.php" class="sidebar-user">
            <div class="sidebar-user-avatar"><?= e(strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1))) ?></div>
            <div class="sidebar-user-details">
                <strong><?= e($_SESSION['name'] ?? 'User') ?></strong>
                <small><?= e(ucfirst($role ?: 'User')) ?></small>
            </div>
        </a>
        <a href="../logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="top-header">
        <div class="header-left">
            <button id="menuButton" class="menu-button" type="button" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="page-title-block">
                <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
                <p>Learning Management System</p>
            </div>
        </div>

        <div class="header-right">
            <button type="button" class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>
            <a href="../profile.php" class="profile profile-link">
                <div class="profile-image"><?= e(strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1))) ?></div>
                <div class="profile-details">
                    <strong><?= e($_SESSION['name'] ?? 'User') ?></strong>
                    <small><?= e(ucfirst($role ?: 'User')) ?></small>
                </div>
            </a>
        </div>
    </header>

    <section class="content">
