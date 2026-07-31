<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('learner');

$userId = (int)$_SESSION['user_id'];
$pageTitle = 'Learner Dashboard';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
$stmt->execute([$userId]);
$courseCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT q.quiz_id)
    FROM quizzes q
    JOIN enrollments e ON e.course_id = q.course_id
    WHERE e.user_id = ?");
$stmt->execute([$userId]);
$quizCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?");
$stmt->execute([$userId]);
$attemptCount = (int)$stmt->fetchColumn();

// Averaged over enrolled courses only, so progress from a course the learner
// was removed from cannot skew the figure.
$stmt = $pdo->prepare("SELECT COALESCE(AVG(p.percentage),0)
    FROM progress p
    JOIN enrollments e ON e.user_id = p.user_id AND e.course_id = p.course_id
    WHERE p.user_id = ?");
$stmt->execute([$userId]);
$averageProgress = round((float)$stmt->fetchColumn(), 1);

$stmt = $pdo->prepare("
    SELECT
        c.course_id,
        c.course_name,
        c.description,
        COALESCE(p.percentage, 0) AS percentage,
        COUNT(DISTINCT q.quiz_id) AS quiz_count,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                iu.name, ' ', iu.surname, '||',
                COALESCE(iu.email, ''), '||',
                COALESCE(iu.contact, ''), '||',
                COALESCE(iu.qualification, ''), '||',
                COALESCE(iu.department, ''), '||',
                COALESCE(iu.bio, '')
            )
            SEPARATOR '##'
        ) AS instructor_data
    FROM enrollments e
    JOIN courses c ON c.course_id = e.course_id
    LEFT JOIN progress p ON p.user_id = e.user_id AND p.course_id = e.course_id
    LEFT JOIN quizzes q ON q.course_id = c.course_id
    LEFT JOIN course_assignments ca ON ca.course_id = c.course_id
    LEFT JOIN users iu ON iu.user_id = ca.instructor_id AND iu.role = 'instructor'
    WHERE e.user_id = ?
    GROUP BY c.course_id, c.course_name, c.description, p.percentage
    ORDER BY c.course_name
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">LEARNER PORTAL</div>
        <h1>Welcome, <?= e($_SESSION['name'] ?? 'Learner') ?></h1>
        <p>Continue your learning, take quizzes and track your progress.</p>
    </div>
</div>

<div class="statistics-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-book-open"></i></div>
        <div><h3><?= $courseCount ?></h3><p>My Courses</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-circle-question"></i></div>
        <div><h3><?= $quizCount ?></h3><p>Available Quizzes</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
        <div><h3><?= $attemptCount ?></h3><p>Quiz Attempts</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-chart-line"></i></div>
        <div><h3><?= e($averageProgress) ?>%</h3><p>Average Progress</p></div>
    </div>
</div>

<div class="action-toolbar">
    <a class="btn btn-primary" href="view_courses.php">
        <i class="fa-solid fa-book-open"></i> View My Courses
    </a>
    <a class="btn btn-outline" href="quiz_results.php">
        <i class="fa-solid fa-square-poll-vertical"></i> View Quiz Results
    </a>
</div>

<?php if (!$courses): ?>
    <section class="dashboard-card">
        <div class="empty-state">
            <i class="fa-solid fa-book-open" style="font-size:32px;margin-bottom:10px;"></i>
            <h2>No courses assigned yet</h2>
            <p>Your courses will appear here once an administrator enrolls you.</p>
        </div>
    </section>
<?php else: ?>
    <div class="courses-grid">
        <?php foreach ($courses as $course): ?>
            <?php
            $quizStmt = $pdo->prepare("
                SELECT q.quiz_id, q.title,
                       (SELECT COUNT(*) FROM quiz_attempts a
                        WHERE a.quiz_id = q.quiz_id AND a.user_id = ?) AS attempts
                FROM quizzes q
                WHERE q.course_id = ?
                ORDER BY q.created_at DESC
            ");
            $quizStmt->execute([$userId, $course['course_id']]);
            $quizzes = $quizStmt->fetchAll();

            $instructors = [];
            if (!empty($course['instructor_data'])) {
                foreach (explode('##', $course['instructor_data']) as $record) {
                    $parts = explode('||', $record);
                    if (!empty($parts[0])) {
                        $instructors[] = [
                            'name' => $parts[0],
                            'email' => $parts[1] ?? '',
                            'contact' => $parts[2] ?? '',
                            'qualification' => $parts[3] ?? '',
                            'department' => $parts[4] ?? '',
                            'bio' => $parts[5] ?? ''
                        ];
                    }
                }
            }
            ?>
            <section class="course-card">
                <div class="course-image"><i class="fa-solid fa-book-open"></i></div>
                <div class="course-body">
                    <h3><?= e($course['course_name']) ?></h3>
                    <p><?= e($course['description'] ?: 'No course description available.') ?></p>

                    <div class="progress-header">
                        <span>Course Progress</span>
                        <strong><?= e($course['percentage']) ?>%</strong>
                    </div>
                    <div class="large-progress">
                        <div style="width: <?= min(100, max(0, (float)$course['percentage'])) ?>%;"></div>
                    </div>

                    <?php if ($instructors): ?>
                        <div class="course-actions">
                            <strong><i class="fa-solid fa-chalkboard-user"></i> Instructor</strong>
                            <?php foreach ($instructors as $instructor): ?>
                                <div class="instructor-mini-card">
                                    <div class="instructor-avatar">
                                        <?= e(strtoupper(substr($instructor['name'], 0, 1))) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($instructor['name']) ?></strong>
                                        <?php if ($instructor['department']): ?>
                                            <small><?= e($instructor['department']) ?></small>
                                        <?php elseif ($instructor['qualification']): ?>
                                            <small><?= e($instructor['qualification']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($instructor['email']): ?>
                                            <small><?= e($instructor['email']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="course-actions">
                        <strong><i class="fa-solid fa-file-lines"></i> Learning Material</strong>
                        <a class="btn btn-primary btn-small" href="view_lectures.php?course_id=<?= (int)$course['course_id'] ?>">
                            Open Lectures
                        </a>
                    </div>

                    <div class="course-actions">
                        <strong><i class="fa-solid fa-circle-question"></i> Quizzes</strong>
                        <?php if (!$quizzes): ?>
                            <span class="text-muted">No quizzes available yet.</span>
                        <?php else: ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <a class="btn btn-outline btn-small" href="take_quiz.php?quiz_id=<?= (int)$quiz['quiz_id'] ?>">
                                    <?= e($quiz['title']) ?>
                                    <?php if ((int)$quiz['attempts'] > 0): ?>
                                        <span class="quiz-attempt-badge"><?= (int)$quiz['attempts'] ?> attempt<?= (int)$quiz['attempts'] === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="dashboard-card" id="quoteCard" data-api="../api_proxy/quote_api.php">
    <div class="card-header">
        <div>
            <h2>Daily Motivation</h2>
            <p class="text-muted">Read live from a free public quotes API.</p>
        </div>
        <i class="fa-solid fa-quote-left"></i>
    </div>
    <p id="quoteText">Loading today's quote...</p>
    <p class="text-muted" id="quoteAuthor"></p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
