<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$panel = $_GET['panel'] ?? '';
$editingCourse = null;
$error = '';
$success = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_course') {
            $courseName = trim($_POST['course_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($courseName === '') {
                throw new RuntimeException('Course name is required.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO courses (course_name, description, created_by_admin_id)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$courseName, $description, $_SESSION['user_id']]);

            header('Location: manage_courses.php?panel=view&success=course_created');
            exit;
        }

        if ($action === 'update_course') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $courseName = trim($_POST['course_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($courseId <= 0 || $courseName === '') {
                throw new RuntimeException('Course name is required.');
            }

            $stmt = $pdo->prepare(
                "UPDATE courses
                 SET course_name = ?, description = ?
                 WHERE course_id = ?"
            );
            $stmt->execute([$courseName, $description, $courseId]);

            header('Location: manage_courses.php?panel=view&success=course_updated');
            exit;
        }

        if ($action === 'delete_course') {
            $courseId = (int)($_POST['course_id'] ?? 0);

            if ($courseId > 0) {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->execute([$courseId]);
            }

            header('Location: manage_courses.php?panel=view&success=course_deleted');
            exit;
        }

        if ($action === 'assign_instructor') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $classId = (int)($_POST['class_id'] ?? 0);
            $instructorId = (int)($_POST['instructor_id'] ?? 0);

            if ($courseId <= 0 || $classId <= 0 || $instructorId <= 0) {
                throw new RuntimeException('Please select a course, class and instructor.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO course_assignments (course_id, class_id, instructor_id)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$courseId, $classId, $instructorId]);

            header('Location: manage_courses.php?panel=assign-instructor&success=instructor_assigned');
            exit;
        }

        if ($action === 'enroll_learner') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $learnerId = (int)($_POST['learner_id'] ?? 0);
            $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;

            if ($courseId <= 0 || $learnerId <= 0) {
                throw new RuntimeException('Please select a course and learner.');
            }

            if ($classId === null) {
                $stmt = $pdo->prepare("SELECT class_id FROM users WHERE user_id = ? AND role = 'learner'");
                $stmt->execute([$learnerId]);
                $classId = $stmt->fetchColumn() ?: null;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO enrollments (user_id, course_id, class_id)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$learnerId, $courseId, $classId]);

            $stmt = $pdo->prepare(
                "INSERT INTO progress (user_id, course_id, completed_lectures, percentage)
                 VALUES (?, ?, 0, 0)
                 ON DUPLICATE KEY UPDATE user_id = user_id"
            );
            $stmt->execute([$learnerId, $courseId]);

            header('Location: manage_courses.php?panel=assign-learner&success=learner_enrolled');
            exit;
        }

        if ($action === 'remove_assignment') {
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            if ($assignmentId > 0) {
                $stmt = $pdo->prepare("DELETE FROM course_assignments WHERE assignment_id = ?");
                $stmt->execute([$assignmentId]);
            }
            header('Location: manage_courses.php?panel=assign-instructor&success=assignment_removed');
            exit;
        }

        if ($action === 'remove_enrollment') {
            $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
            if ($enrollmentId > 0) {
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
                $stmt->execute([$enrollmentId]);
            }
            header('Location: manage_courses.php?panel=assign-learner&success=enrollment_removed');
            exit;
        }
    }

    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editingCourse = $stmt->fetch();
        $panel = 'add';
    }

    $courses = $pdo->query(
        "SELECT c.course_id, c.course_name, c.description, c.created_at,
                CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.surname, '')) AS created_by
         FROM courses c
         LEFT JOIN users u ON u.user_id = c.created_by_admin_id
         ORDER BY c.created_at DESC"
    )->fetchAll();

    $classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_name")->fetchAll();

    $instructors = $pdo->query(
        "SELECT user_id, name, surname, email, invigilator_number
         FROM users
         WHERE role = 'instructor'
         ORDER BY name, surname"
    )->fetchAll();

    $learners = $pdo->query(
        "SELECT u.user_id, u.name, u.surname, u.email, u.class_id, c.class_name
         FROM users u
         LEFT JOIN classes c ON c.class_id = u.class_id
         WHERE u.role = 'learner'
         ORDER BY u.name, u.surname"
    )->fetchAll();

    $assignments = $pdo->query(
        "SELECT ca.assignment_id, c.course_name, cl.class_name,
                u.name, u.surname, u.email
         FROM course_assignments ca
         JOIN courses c ON c.course_id = ca.course_id
         JOIN classes cl ON cl.class_id = ca.class_id
         JOIN users u ON u.user_id = ca.instructor_id
         ORDER BY c.course_name, cl.class_name, u.name"
    )->fetchAll();

    $enrollments = $pdo->query(
        "SELECT e.enrollment_id, c.course_name, u.name, u.surname, u.email,
                COALESCE(cl.class_name, 'Unassigned') AS class_name,
                COALESCE(p.percentage, 0) AS percentage
         FROM enrollments e
         JOIN courses c ON c.course_id = e.course_id
         JOIN users u ON u.user_id = e.user_id
         LEFT JOIN classes cl ON cl.class_id = e.class_id
         LEFT JOIN progress p ON p.user_id = e.user_id AND p.course_id = e.course_id
         ORDER BY c.course_name, u.name"
    )->fetchAll();

    $successMessages = [
        'course_created' => 'Course created successfully.',
        'course_updated' => 'Course updated successfully.',
        'course_deleted' => 'Course deleted successfully.',
        'instructor_assigned' => 'Instructor assigned successfully.',
        'assignment_removed' => 'Instructor assignment removed.',
        'learner_enrolled' => 'Existing learner enrolled successfully.',
        'enrollment_removed' => 'Learner enrollment removed.'
    ];

    if (!empty($_GET['success']) && isset($successMessages[$_GET['success']])) {
        $success = $successMessages[$_GET['success']];
    }
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        $error = 'That assignment or enrollment already exists.';
    } else {
        $error = 'The requested action could not be completed.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Manage Courses';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1>Manage Courses</h1>
        <p>Create courses, assign instructors and enroll existing learners.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-primary" href="manage_courses.php?panel=add">
        <i class="fa-solid fa-plus"></i> Add Course
    </a>
    <a class="btn btn-outline" href="manage_courses.php?panel=assign-instructor">
        <i class="fa-solid fa-chalkboard-user"></i> Assign Instructor
    </a>
    <a class="btn btn-outline" href="manage_courses.php?panel=assign-learner">
        <i class="fa-solid fa-user-plus"></i> Assign Learner
    </a>
    <a class="btn btn-outline" href="manage_courses.php?panel=view">
        <i class="fa-solid fa-table-list"></i> View Courses
    </a>
</div>

<?php if ($panel === 'add'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2><?= $editingCourse ? 'Edit Course' : 'Add Course' ?></h2>
            <p class="text-muted">Course details are saved in the central course catalogue.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="<?= $editingCourse ? 'update_course' : 'create_course' ?>">
        <?php if ($editingCourse): ?>
            <input type="hidden" name="course_id" value="<?= (int)$editingCourse['course_id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="input-group">
                <label for="course_name">Course Name</label>
                <input id="course_name" name="course_name" required
                       value="<?= e($editingCourse['course_name'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required><?= e($editingCourse['description'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-save"></i>
                <?= $editingCourse ? 'Update Course' : 'Create Course' ?>
            </button>
            <a class="btn btn-secondary" href="manage_courses.php">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($panel === 'assign-instructor'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Assign Existing Instructor</h2>
            <p class="text-muted">No new user is created. Select an instructor who already exists.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="assign_instructor">

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
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">Select a class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int)$class['class_id'] ?>"><?= e($class['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label for="instructor_id">Existing Instructor</label>
                <select id="instructor_id" name="instructor_id" required>
                    <option value="">Select an instructor</option>
                    <?php foreach ($instructors as $instructor): ?>
                        <option value="<?= (int)$instructor['user_id'] ?>">
                            <?= e($instructor['name'] . ' ' . $instructor['surname'] . ' — ' . $instructor['email']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-link"></i> Assign Instructor
        </button>
    </form>
</section>

<section class="dashboard-card">
    <div class="card-header">
        <h2>Current Instructor Assignments</h2>
    </div>

    <?php if (!$assignments): ?>
        <div class="empty-state">No instructor assignments yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Course</th><th>Class</th><th>Instructor</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?= e($assignment['course_name']) ?></td>
                        <td><?= e($assignment['class_name']) ?></td>
                        <td><?= e($assignment['name'] . ' ' . $assignment['surname']) ?></td>
                        <td>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this course? All related assignments, enrollments, quizzes and progress may be removed.');">
                                <input type="hidden" name="action" value="remove_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['assignment_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Remove</button>
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

<?php if ($panel === 'assign-learner'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Enroll Existing Learner</h2>
            <p class="text-muted">Select a learner who has already registered. This does not create another account.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="enroll_learner">

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
                <label for="learner_id">Existing Learner</label>
                <select id="learner_id" name="learner_id" required>
                    <option value="">Select a learner</option>
                    <?php foreach ($learners as $learner): ?>
                        <option value="<?= (int)$learner['user_id'] ?>">
                            <?= e($learner['name'] . ' ' . $learner['surname'] . ' — ' . $learner['email']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label for="class_id">Class (optional)</label>
                <select id="class_id" name="class_id">
                    <option value="">Use learner's current class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int)$class['class_id'] ?>"><?= e($class['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-user-check"></i> Enroll Learner
        </button>
    </form>
</section>

<section class="dashboard-card">
    <div class="card-header">
        <h2>Current Enrollments</h2>
    </div>

    <?php if (!$enrollments): ?>
        <div class="empty-state">No learner enrollments yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Learner</th><th>Course</th><th>Class</th><th>Progress</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td>
                            <strong><?= e($enrollment['name'] . ' ' . $enrollment['surname']) ?></strong><br>
                            <small class="text-muted"><?= e($enrollment['email']) ?></small>
                        </td>
                        <td><?= e($enrollment['course_name']) ?></td>
                        <td><?= e($enrollment['class_name']) ?></td>
                        <td><?= e($enrollment['percentage']) ?>%</td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="remove_enrollment">
                                <input type="hidden" name="enrollment_id" value="<?= (int)$enrollment['enrollment_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Remove</button>
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

<?php if ($panel === 'view'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Course Catalogue</h2>
            <p class="text-muted"><?= count($courses) ?> course(s) currently available.</p>
        </div>
    </div>

    <?php if (!$courses): ?>
        <div class="empty-state">No courses have been created yet.</div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Course</th><th>Description</th><th>Created By</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?= e($course['course_name']) ?></strong></td>
                        <td><?= e($course['description']) ?></td>
                        <td><?= e(trim($course['created_by'])) ?></td>
                        <td><?= e($course['created_at']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-outline btn-small" href="manage_courses.php?edit=<?= (int)$course['course_id'] ?>">
                                    Edit
                                </a>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= (int)$course['course_id'] ?>">
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
        <p class="text-muted">Courses in catalogue</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon green"><i class="fa-solid fa-user-graduate"></i></div>
        <h2><?= count($learners) ?></h2>
        <p class="text-muted">Registered learners available for enrollment</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon purple"><i class="fa-solid fa-chalkboard-user"></i></div>
        <h2><?= count($instructors) ?></h2>
        <p class="text-muted">Existing instructors available for assignment</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
