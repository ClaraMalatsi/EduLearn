<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('instructor');
$pageTitle = 'Instructor Dashboard';
// COUNT(DISTINCT course_id): one course assigned to two classes is still one course.
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT course_id) FROM course_assignments WHERE instructor_id=?");
$stmt->execute([$_SESSION['user_id']]);
$courseCount = $stmt->fetchColumn();
require '../includes/header.php';
?>
<div class="page-heading"><div><p>Manage your courses, learning materials and quizzes.</p></div></div>
<div class="statistics-grid"><div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-book"></i></div><div><h3><?= $courseCount ?></h3><p>Assigned Courses</p></div></div></div>
<div class="dashboard-grid"><div class="dashboard-card"><h2>Quiz Management</h2><p>Create MCQ quizzes and review attempts.</p><br><a class="primary-button" href="manage_quiz.php">Manage Quizzes</a></div><div class="dashboard-card"><h2>Learning Material</h2><p>Upload lecture notes and resources for your courses.</p><br><a class="primary-button" href="manage_lectures.php">Manage Material</a></div><div class="dashboard-card"><h2>Students</h2><p>View students enrolled in your assigned courses.</p><br><a class="primary-button" href="view_students.php">View Students</a></div></div>
<section class="dashboard-card" id="quoteCard" data-api="../api_proxy/quote_api.php">
<div class="card-header"><div><h2>Daily Motivation</h2><p class="text-muted">Read live from a free public quotes API.</p></div><i class="fa-solid fa-quote-left"></i></div>
<p id="quoteText">Loading today's quote...</p>
<p class="text-muted" id="quoteAuthor"></p>
</section>
<?php require '../includes/footer.php'; ?>
