<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('instructor');

$stmt = $pdo->prepare("SELECT DISTINCT u.name,u.surname,u.email,c.course_name,p.percentage FROM users u JOIN enrollments e ON u.user_id=e.user_id JOIN courses c ON e.course_id=c.course_id JOIN course_assignments ca ON c.course_id=ca.course_id LEFT JOIN progress p ON p.user_id=u.user_id AND p.course_id=c.course_id WHERE ca.instructor_id=?");
$stmt->execute([$_SESSION['user_id']]);
$students = $stmt->fetchAll();
$pageTitle = 'Student Progress';
require '../includes/header.php';
?>
<div class="page-heading"><div><p>View learners enrolled in your courses.</p></div></div>
<div class="table-container"><table><thead><tr><th>Student</th><th>Email</th><th>Course</th><th>Progress</th></tr></thead><tbody><?php foreach($students as $student): ?><tr><td><?= e($student['name'].' '.$student['surname']) ?></td><td><?= e($student['email']) ?></td><td><?= e($student['course_name']) ?></td><td><?= e($student['percentage'] ?? 0) ?>%</td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php'; ?>
