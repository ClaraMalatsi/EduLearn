<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

requireRole('instructor');
requireCsrf();

$panel = $_GET['panel'] ?? '';
$error = '';
$success = '';

// Defaults so the page still renders if a query below cannot run.
$courses = [];
$lectures = [];

// Accepted material types and the largest file the server will store.
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'png', 'jpg', 'jpeg'];
$maxFileSize = 10 * 1024 * 1024;
$uploadDir = __DIR__ . '/../uploads';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_lecture') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $lectureName = trim($_POST['lecture_name'] ?? '');

            if ($courseId <= 0 || $lectureName === '') {
                throw new RuntimeException('Please select a course and enter a lecture name.');
            }

            // The course must be one that is assigned to this instructor.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM course_assignments
                 WHERE course_id = ? AND instructor_id = ?"
            );
            $stmt->execute([$courseId, $_SESSION['user_id']]);

            if (!(int)$stmt->fetchColumn()) {
                throw new RuntimeException('You can only upload material for courses assigned to you.');
            }

            $file = $_FILES['material'] ?? null;

            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Please choose a file to upload.');
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('The file could not be uploaded. Please try again.');
            }

            if ($file['size'] > $maxFileSize) {
                throw new RuntimeException('The file is too large. The maximum size is 10 MB.');
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('That file type is not allowed. Accepted types: ' . implode(', ', $allowedExtensions) . '.');
            }

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            // Stored under a random name so nobody can guess a file address,
            // and so two uploads with the same name cannot overwrite each other.
            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;

            if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $storedName)) {
                throw new RuntimeException('The file could not be saved on the server.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO lectures (course_id, lecture_name, file_path)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$courseId, $lectureName, $storedName]);

            // A new lecture changes the course total, so percentages must be redone.
            refreshCourseProgress($pdo, $courseId);

            header('Location: manage_lectures.php?panel=view&success=uploaded');
            exit;
        }

        if ($action === 'delete_lecture') {
            $lectureId = (int)($_POST['lecture_id'] ?? 0);

            $stmt = $pdo->prepare(
                "SELECT l.lecture_id, l.course_id, l.file_path
                 FROM lectures l
                 JOIN course_assignments ca ON ca.course_id = l.course_id
                 WHERE l.lecture_id = ? AND ca.instructor_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$lectureId, $_SESSION['user_id']]);
            $lecture = $stmt->fetch();

            if (!$lecture) {
                throw new RuntimeException('That lecture does not belong to one of your courses.');
            }

            $stmt = $pdo->prepare("DELETE FROM lectures WHERE lecture_id = ?");
            $stmt->execute([$lecture['lecture_id']]);

            $storedFile = $uploadDir . '/' . basename($lecture['file_path']);
            if (is_file($storedFile)) {
                unlink($storedFile);
            }

            refreshCourseProgress($pdo, $lecture['course_id']);

            header('Location: manage_lectures.php?panel=view&success=deleted');
            exit;
        }
    }
} catch (PDOException $e) {
    $error = 'The lecture could not be saved.';
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

// Loaded separately so a failed upload above still leaves the page usable.
try {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT c.course_id, c.course_name
         FROM courses c
         JOIN course_assignments ca ON c.course_id = ca.course_id
         WHERE ca.instructor_id = ?
         ORDER BY c.course_name"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT DISTINCT l.lecture_id, l.lecture_name, l.file_path, l.created_at,
                c.course_name,
                (SELECT COUNT(*) FROM learner_lecture_progress lp WHERE lp.lecture_id = l.lecture_id) AS completed_by
         FROM lectures l
         JOIN courses c ON c.course_id = l.course_id
         JOIN course_assignments ca ON ca.course_id = l.course_id
         WHERE ca.instructor_id = ?
         ORDER BY l.created_at DESC"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $lectures = $stmt->fetchAll();

    $successMessages = [
        'uploaded' => 'Learning material uploaded successfully.',
        'deleted' => 'Lecture deleted successfully.'
    ];

    if (!empty($_GET['success']) && isset($successMessages[$_GET['success']])) {
        $success = $successMessages[$_GET['success']];
    }
} catch (PDOException $e) {
    $error = $error ?: 'Your lectures could not be loaded.';
}

$pageTitle = 'Learning Material';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">INSTRUCTOR PORTAL</div>
        <h1>Learning Material</h1>
        <p>Upload lecture notes and resources for your courses. Learners download them and mark each lecture as complete.</p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-primary" href="manage_lectures.php?panel=add">
        <i class="fa-solid fa-upload"></i> Upload Material
    </a>
    <a class="btn btn-outline" href="manage_lectures.php?panel=view">
        <i class="fa-solid fa-table-list"></i> View Material
    </a>
</div>

<?php if ($panel === 'add'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Upload Learning Material</h2>
            <p class="text-muted">Maximum 10 MB. Accepted types: <?= e(implode(', ', $allowedExtensions)) ?>.</p>
        </div>
    </div>

    <?php if (!$courses): ?>
        <div class="alert alert-warning">No courses are currently assigned to you.</div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_lecture">

            <div class="form-grid">
                <div class="input-group">
                    <label for="course_id">Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int)$course['course_id'] ?>"><?= e($course['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="lecture_name">Lecture Name</label>
                    <input id="lecture_name" name="lecture_name" required placeholder="e.g. Week 1 - Introduction">
                </div>
            </div>

            <div class="input-group">
                <label for="material">File</label>
                <input id="material" type="file" name="material" required>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
                <a class="btn btn-secondary" href="manage_lectures.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($panel === 'view'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Uploaded Material</h2>
            <p class="text-muted"><?= count($lectures) ?> lecture(s) across your courses.</p>
        </div>
    </div>

    <?php if (!$lectures): ?>
        <div class="empty-state">No learning material has been uploaded yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Lecture</th><th>Course</th><th>Completed By</th><th>Uploaded</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lectures as $lecture): ?>
                    <tr>
                        <td><strong><?= e($lecture['lecture_name']) ?></strong></td>
                        <td><?= e($lecture['course_name']) ?></td>
                        <td><?= (int)$lecture['completed_by'] ?> learner(s)</td>
                        <td><?= e($lecture['created_at']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-outline btn-small" href="../download.php?lecture_id=<?= (int)$lecture['lecture_id'] ?>">Download</a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this lecture and its file? Learner progress for the course is recalculated.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_lecture">
                                    <input type="hidden" name="lecture_id" value="<?= (int)$lecture['lecture_id'] ?>">
                                    <button class="btn btn-danger btn-small" type="submit">Delete</button>
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
<?php endif; ?>

<?php if ($panel === ''): ?>
<section class="dashboard-grid">
    <div class="dashboard-card">
        <div class="stat-icon blue"><i class="fa-solid fa-book"></i></div>
        <h2><?= count($courses) ?></h2>
        <p class="text-muted">Assigned courses</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon green"><i class="fa-solid fa-file-lines"></i></div>
        <h2><?= count($lectures) ?></h2>
        <p class="text-muted">Lectures uploaded</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
