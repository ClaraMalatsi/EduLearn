<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('learner');
$stmt=$pdo->prepare("SELECT c.course_id,c.course_name,c.description,COALESCE(p.percentage,0) percentage FROM enrollments e JOIN courses c ON e.course_id=c.course_id LEFT JOIN progress p ON p.user_id=e.user_id AND p.course_id=e.course_id WHERE e.user_id=? ORDER BY c.course_name");$stmt->execute([$_SESSION['user_id']]);$courses=$stmt->fetchAll();
$pageTitle='My Courses';require '../includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">LEARNING</div><h1>My Courses</h1><p>Continue learning and complete your assigned course work.</p></div></div>
<div class="courses-grid">
<?php foreach($courses as $course): $q=$pdo->prepare("SELECT q.quiz_id,q.title FROM quizzes q WHERE q.course_id=? ORDER BY q.created_at DESC");$q->execute([$course['course_id']]);$quizzes=$q->fetchAll(); ?>
<div class="course-card"><div class="course-image"><i class="fa-solid fa-book-open"></i></div><div class="course-body"><h3><?=e($course['course_name'])?></h3><p><?=e($course['description'])?></p><div class="progress-header"><span>Progress</span><strong><?=e($course['percentage'])?>%</strong></div><div class="large-progress"><div style="width:<?=e($course['percentage'])?>%"></div></div><?php if($quizzes):?><div class="course-actions"><strong>Quizzes</strong><?php foreach($quizzes as $quiz):?><a class="btn btn-outline btn-small" href="take_quiz.php?quiz_id=<?=$quiz['quiz_id']?>"><?=e($quiz['title'])?></a><?php endforeach;?></div><?php endif;?></div></div>
<?php endforeach;?>
</div>
<?php require '../includes/footer.php'; ?>
