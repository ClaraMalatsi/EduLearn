<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('instructor');
requireCsrf();

$panel = $_GET['panel'] ?? '';
$error = '';
$success = '';

// Defaults so the page still renders if a query below cannot run.
$courses = [];
$quizzes = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_quiz') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');

            if ($courseId <= 0 || $title === '') {
                throw new RuntimeException('Please select a course and enter a quiz title.');
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM course_assignments
                 WHERE course_id = ? AND instructor_id = ?"
            );
            $stmt->execute([$courseId, $_SESSION['user_id']]);

            if (!(int)$stmt->fetchColumn()) {
                throw new RuntimeException('You can only create quizzes for courses assigned to you.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO quizzes (course_id, title, created_by_instructor_id)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$courseId, $title, $_SESSION['user_id']]);

            header('Location: manage_quiz.php?panel=view&success=created');
            exit;
        }

        if ($action === 'delete_quiz') {
            $quizId = (int)($_POST['quiz_id'] ?? 0);

            $stmt = $pdo->prepare(
                "DELETE FROM quizzes
                 WHERE quiz_id = ? AND created_by_instructor_id = ?"
            );
            $stmt->execute([$quizId, $_SESSION['user_id']]);

            header('Location: manage_quiz.php?panel=view&success=deleted');
            exit;
        }
    }
} catch (PDOException $e) {
    $error = 'The quiz could not be saved.';
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

// The lists below load in their own try block so a failed POST above still
// leaves the course dropdown and quiz table populated.
try {
    // DISTINCT: a course assigned to more than one class would otherwise appear
    // once per assignment in the dropdown and in the counts.
    $stmt = $pdo->prepare(
        "SELECT DISTINCT c.*
         FROM courses c
         JOIN course_assignments ca ON c.course_id = ca.course_id
         WHERE ca.instructor_id = ?
         ORDER BY c.course_name"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT q.*, c.course_name
         FROM quizzes q
         JOIN courses c ON q.course_id = c.course_id
         WHERE q.created_by_instructor_id = ?
         ORDER BY q.created_at DESC"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $quizzes = $stmt->fetchAll();

    $successMessages = [
        'created' => 'Quiz created successfully.',
        'deleted' => 'Quiz deleted successfully.'
    ];

    if (!empty($_GET['success']) && isset($successMessages[$_GET['success']])) {
        $success = $successMessages[$_GET['success']];
    }
} catch (PDOException $e) {
    $error = $error ?: 'Your quizzes could not be loaded.';
}

$pageTitle = 'Manage Quizzes';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">INSTRUCTOR PORTAL</div>
        <h1>Manage Quizzes</h1>
        <p>Create quizzes for courses assigned to you.</p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-primary" href="manage_quiz.php?panel=add">
        <i class="fa-solid fa-plus"></i> Create Quiz
    </a>
    <a class="btn btn-outline" href="manage_quiz.php?panel=view">
        <i class="fa-solid fa-table-list"></i> View My Quizzes
    </a>
</div>

<?php if ($panel === 'add'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Create New Quiz</h2>
            <p class="text-muted">Create a quiz for one of your assigned courses.</p>
        </div>
    </div>

    <?php if (!$courses): ?>
        <div class="alert alert-warning">No courses are currently assigned to you.</div>
    <?php else: ?>
        <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="action" value="create_quiz">

            <div class="form-grid">
                <div class="input-group">
                    <label for="course_id">Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int)$course['course_id'] ?>">
                                <?= e($course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="title">Quiz Title</label>
                    <input id="title" name="title" placeholder="e.g. Module 1 Assessment" required>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-save"></i> Create Quiz
            </button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($panel === 'view'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Your Quizzes</h2>
            <p class="text-muted"><?= count($quizzes) ?> quiz(es) created by you.</p>
        </div>
    </div>

    <?php if (!$quizzes): ?>
        <div class="empty-state">No quizzes have been created yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Quiz</th><th>Course</th><th>Created</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><strong><?= e($quiz['title']) ?></strong></td>
                        <td><?= e($quiz['course_name']) ?></td>
                        <td><?= e($quiz['created_at']) ?></td>
                        <td><a class="btn btn-outline btn-small" href="manage_questions.php?quiz_id=<?= (int)$quiz['quiz_id'] ?>">Questions</a> 
                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this quiz and its questions?');"><?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_quiz">
                                <input type="hidden" name="quiz_id" value="<?= (int)$quiz['quiz_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($panel === ''): ?>
<section class="dashboard-grid">
    <div class="dashboard-card">
        <div class="stat-icon blue"><i class="fa-solid fa-book"></i></div>
        <h2><?= count($courses) ?></h2>
        <p class="text-muted">Assigned courses</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon purple"><i class="fa-solid fa-circle-question"></i></div>
        <h2><?= count($quizzes) ?></h2>
        <p class="text-muted">Quizzes created</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
