<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$pageTitle = 'Admin Dashboard';

$courses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$learners = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='learner'")->fetchColumn();
$instructors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='instructor'")->fetchColumn();
$quizzes = (int)$pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();

require '../includes/header.php';
?>
<div class="page-heading"><div><p>Manage the EduLearn platform.</p></div></div>
<div class="statistics-grid">
<div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-book"></i></div><div><h3><?= $courses ?></h3><p>Total Courses</p></div></div>
<div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div><div><h3><?= $learners ?></h3><p>Learners</p></div></div>
<div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-chalkboard-user"></i></div><div><h3><?= $instructors ?></h3><p>Instructors</p></div></div>
<div class="stat-card"><div class="stat-icon purple"><i class="fa-solid fa-circle-question"></i></div><div><h3><?= $quizzes ?></h3><p>Quizzes</p></div></div>
</div>
<div class="dashboard-grid">
<div class="dashboard-card"><h2>Administration</h2><p>Manage users, classes, courses and assignments.</p><br><a class="primary-button" href="manage_users.php">Manage Users</a></div>
<div class="dashboard-card"><h2>Course Management</h2><p>Create courses and assign them to classes and instructors.</p><br><a class="primary-button" href="manage_courses.php">Manage Courses</a></div>
<div class="dashboard-card"><h2>Class Management</h2><p>Create classes and group learners into them.</p><br><a class="primary-button" href="manage_classes.php">Manage Classes</a></div>
</div>
<section class="dashboard-card" id="quoteCard" data-api="../api_proxy/quote_api.php">
<div class="card-header"><div><h2>Daily Motivation</h2><p class="text-muted">Read live from a free public quotes API.</p></div><i class="fa-solid fa-quote-left"></i></div>
<p id="quoteText">Loading today's quote...</p>
<p class="text-muted" id="quoteAuthor"></p>
</section>
<?php require '../includes/footer.php'; ?>
