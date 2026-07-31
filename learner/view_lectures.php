<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

requireRole('learner');
requireCsrf();

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// The learner must be enrolled in the course before seeing its material.
$stmt = $pdo->prepare(
    "SELECT c.course_id, c.course_name, c.description
     FROM courses c
     JOIN enrollments e ON e.course_id = c.course_id AND e.user_id = ?
     WHERE c.course_id = ?"
);
$stmt->execute([$userId, $courseId]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(403);
    exit('This course is not available to you.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $lectureId = (int)($_POST['lecture_id'] ?? 0);

        // Confirm the lecture really belongs to this course.
        $stmt = $pdo->prepare("SELECT lecture_id FROM lectures WHERE lecture_id = ? AND course_id = ?");
        $stmt->execute([$lectureId, $courseId]);

        if ($stmt->fetch()) {
            if ($action === 'complete_lecture') {
                $stmt = $pdo->prepare(
                    "INSERT INTO learner_lecture_progress (user_id, lecture_id)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE completed_at = completed_at"
                );
                $stmt->execute([$userId, $lectureId]);
                $success = 'Lecture marked as complete.';
            }

            if ($action === 'uncomplete_lecture') {
                $stmt = $pdo->prepare(
                    "DELETE FROM learner_lecture_progress WHERE user_id = ? AND lecture_id = ?"
                );
                $stmt->execute([$userId, $lectureId]);
                $success = 'Lecture marked as not complete.';
            }

            recalculateProgress($pdo, $userId, $courseId);
        }
    }
} catch (PDOException $e) {
    $error = 'Your progress could not be saved.';
}

$lectures = [];
$percentage = 0;

try {
    $stmt = $pdo->prepare(
        "SELECT l.lecture_id, l.lecture_name, l.file_path, l.created_at,
                (lp.progress_id IS NOT NULL) AS completed
         FROM lectures l
         LEFT JOIN learner_lecture_progress lp
                ON lp.lecture_id = l.lecture_id AND lp.user_id = ?
         WHERE l.course_id = ?
         ORDER BY l.created_at"
    );
    $stmt->execute([$userId, $courseId]);
    $lectures = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COALESCE(percentage, 0) FROM progress WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$userId, $courseId]);
    $percentage = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $error = $error ?: 'The learning material could not be loaded.';
}

$completedCount = count(array_filter($lectures, function ($l) { return (int)$l['completed'] === 1; }));

$pageTitle = 'Learning Material';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">LEARNING MATERIAL</div>
        <h1><?= e($course['course_name']) ?></h1>
        <p>Download each lecture and mark it complete as you work through the course.</p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-outline" href="view_courses.php">
        <i class="fa-solid fa-arrow-left"></i> Back to My Courses
    </a>
</div>

<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Course Progress</h2>
            <p class="text-muted"><?= $completedCount ?> of <?= count($lectures) ?> lecture(s) completed. Quizzes also count towards your progress.</p>
        </div>
    </div>

    <div class="progress-header">
        <span>Overall Progress</span>
        <strong><?= e(round($percentage, 2)) ?>%</strong>
    </div>
    <div class="large-progress">
        <div style="width: <?= min(100, max(0, $percentage)) ?>%;"></div>
    </div>
</section>

<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Lectures</h2>
            <p class="text-muted"><?= count($lectures) ?> lecture(s) uploaded by your instructor.</p>
        </div>
    </div>

    <?php if (!$lectures): ?>
        <div class="empty-state">No learning material has been uploaded for this course yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Lecture</th><th>Status</th><th>Uploaded</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lectures as $lecture): ?>
                    <?php $done = (int)$lecture['completed'] === 1; ?>
                    <tr>
                        <td><strong><?= e($lecture['lecture_name']) ?></strong></td>
                        <td>
                            <?php if ($done): ?>
                                <span class="badge badge-learner">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-admin">Not started</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($lecture['created_at']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-outline btn-small" href="../download.php?lecture_id=<?= (int)$lecture['lecture_id'] ?>">Download</a>
                                <form method="POST" class="inline-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
                                    <input type="hidden" name="lecture_id" value="<?= (int)$lecture['lecture_id'] ?>">
                                    <?php if ($done): ?>
                                        <input type="hidden" name="action" value="uncomplete_lecture">
                                        <button class="btn btn-secondary btn-small" type="submit">Undo</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="complete_lecture">
                                        <button class="btn btn-primary btn-small" type="submit">Mark Complete</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
