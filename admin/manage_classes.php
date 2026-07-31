<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
requireCsrf();

$panel = $_GET['panel'] ?? '';
$editingClass = null;
$error = '';
$success = '';

// Defaults so the page still renders if a query below cannot run.
$classes = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_class') {
            $className = trim($_POST['class_name'] ?? '');

            if ($className === '') {
                throw new RuntimeException('Class name is required.');
            }

            $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
            $stmt->execute([$className]);

            header('Location: manage_classes.php?panel=view&success=class_created');
            exit;
        }

        if ($action === 'update_class') {
            $classId = (int)($_POST['class_id'] ?? 0);
            $className = trim($_POST['class_name'] ?? '');

            if ($classId <= 0 || $className === '') {
                throw new RuntimeException('Class name is required.');
            }

            $stmt = $pdo->prepare("UPDATE classes SET class_name = ? WHERE class_id = ?");
            $stmt->execute([$className, $classId]);

            header('Location: manage_classes.php?panel=view&success=class_updated');
            exit;
        }

        if ($action === 'delete_class') {
            $classId = (int)($_POST['class_id'] ?? 0);

            if ($classId > 0) {
                // Learners keep their accounts and courses keep their data.
                // The foreign keys simply unassign them from this class.
                $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
                $stmt->execute([$classId]);
            }

            header('Location: manage_classes.php?panel=view&success=class_deleted');
            exit;
        }
    }
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        $error = 'A class with that name already exists.';
    } else {
        $error = 'The class could not be saved.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

// Loaded separately so a failed action above still leaves the page usable.
try {
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editingClass = $stmt->fetch();
        $panel = 'add';
    }

    $classes = $pdo->query(
        "SELECT c.class_id, c.class_name,
                (SELECT COUNT(*) FROM users u WHERE u.class_id = c.class_id AND u.role = 'learner') AS learner_count,
                (SELECT COUNT(DISTINCT ca.course_id) FROM course_assignments ca WHERE ca.class_id = c.class_id) AS course_count,
                (SELECT COUNT(DISTINCT ca.instructor_id) FROM course_assignments ca WHERE ca.class_id = c.class_id) AS instructor_count
         FROM classes c
         ORDER BY c.class_name"
    )->fetchAll();

    $successMessages = [
        'class_created' => 'Class created successfully.',
        'class_updated' => 'Class updated successfully.',
        'class_deleted' => 'Class deleted successfully.'
    ];

    if (!empty($_GET['success']) && isset($successMessages[$_GET['success']])) {
        $success = $successMessages[$_GET['success']];
    }
} catch (PDOException $e) {
    $error = $error ?: 'The class list could not be loaded.';
}

$pageTitle = 'Manage Classes';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1>Manage Classes</h1>
        <p>Group learners into classes such as Class A and Class B, then assign courses to those classes.</p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-primary" href="manage_classes.php?panel=add">
        <i class="fa-solid fa-plus"></i> Add Class
    </a>
    <a class="btn btn-outline" href="manage_classes.php?panel=view">
        <i class="fa-solid fa-table-list"></i> View Classes
    </a>
</div>

<?php if ($panel === 'add'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2><?= $editingClass ? 'Edit Class' : 'Add Class' ?></h2>
            <p class="text-muted">Class names must be unique.</p>
        </div>
    </div>

    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editingClass ? 'update_class' : 'create_class' ?>">
        <?php if ($editingClass): ?>
            <input type="hidden" name="class_id" value="<?= (int)$editingClass['class_id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="input-group">
                <label for="class_name">Class Name</label>
                <input id="class_name" name="class_name" required
                       placeholder="e.g. Class C"
                       value="<?= e($editingClass['class_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-save"></i>
                <?= $editingClass ? 'Update Class' : 'Create Class' ?>
            </button>
            <a class="btn btn-secondary" href="manage_classes.php">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($panel === 'view'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Classes</h2>
            <p class="text-muted"><?= count($classes) ?> class(es) in the system.</p>
        </div>
    </div>

    <?php if (!$classes): ?>
        <div class="empty-state">No classes have been created yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Class</th><th>Learners</th><th>Courses</th><th>Instructors</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><strong><?= e($class['class_name']) ?></strong></td>
                        <td><?= (int)$class['learner_count'] ?></td>
                        <td><?= (int)$class['course_count'] ?></td>
                        <td><?= (int)$class['instructor_count'] ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-outline btn-small" href="manage_classes.php?edit=<?= (int)$class['class_id'] ?>">Edit</a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this class? Its learners and course assignments become unassigned, but no accounts or courses are deleted.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_class">
                                    <input type="hidden" name="class_id" value="<?= (int)$class['class_id'] ?>">
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
        <div class="stat-icon blue"><i class="fa-solid fa-layer-group"></i></div>
        <h2><?= count($classes) ?></h2>
        <p class="text-muted">Classes</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon green"><i class="fa-solid fa-user-graduate"></i></div>
        <h2><?= array_sum(array_column($classes, 'learner_count')) ?></h2>
        <p class="text-muted">Learners grouped into classes</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon purple"><i class="fa-solid fa-book"></i></div>
        <h2><?= array_sum(array_column($classes, 'course_count')) ?></h2>
        <p class="text-muted">Course assignments across classes</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
