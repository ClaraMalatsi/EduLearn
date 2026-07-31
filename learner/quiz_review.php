<?php
/**
 * Answer review for one quiz attempt.
 *
 * Reads the quiz_answers table, which records the option each learner chose
 * and whether it was correct, so an attempt can be looked back over question
 * by question.
 *
 * A learner may review their own attempts. An instructor may review attempts
 * on quizzes they created, which is how they see where a class went wrong.
 */

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$role = $_SESSION['role'] ?? '';
$userId = (int)$_SESSION['user_id'];
$attemptId = (int)($_GET['attempt_id'] ?? 0);

if (!in_array($role, ['learner', 'instructor'], true)) {
    http_response_code(403);
    exit('This page is for learners and instructors.');
}

$stmt = $pdo->prepare(
    "SELECT a.attempt_id, a.user_id, a.score, a.attempt_number, a.submitted_at, a.is_final,
            q.quiz_id, q.title, q.created_by_instructor_id,
            c.course_name,
            u.name, u.surname
     FROM quiz_attempts a
     JOIN quizzes q ON q.quiz_id = a.quiz_id
     JOIN courses c ON c.course_id = q.course_id
     JOIN users u ON u.user_id = a.user_id
     WHERE a.attempt_id = ?"
);
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    http_response_code(404);
    exit('That attempt could not be found.');
}

// A learner sees only their own attempt; an instructor only their own quiz.
$isOwnAttempt = (int)$attempt['user_id'] === $userId;
$isOwnQuiz = (int)$attempt['created_by_instructor_id'] === $userId;

if (!(($role === 'learner' && $isOwnAttempt) || ($role === 'instructor' && $isOwnQuiz))) {
    http_response_code(403);
    exit('You do not have permission to review this attempt.');
}

$stmt = $pdo->prepare(
    "SELECT q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
            q.correct_answer, ans.selected_answer, ans.is_correct
     FROM questions q
     LEFT JOIN quiz_answers ans ON ans.question_id = q.question_id AND ans.attempt_id = ?
     WHERE q.quiz_id = ?
     ORDER BY q.question_id"
);
$stmt->execute([$attemptId, $attempt['quiz_id']]);
$questions = $stmt->fetchAll();

$correctCount = count(array_filter($questions, function ($q) { return (int)$q['is_correct'] === 1; }));

$pageTitle = 'Answer Review';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">ANSWER REVIEW</div>
        <h1><?= e($attempt['title']) ?></h1>
        <p>
            <?= e($attempt['course_name']) ?>
            &middot; Attempt <?= (int)$attempt['attempt_number'] ?>
            <?= (int)$attempt['is_final'] === 1 ? '(final result)' : '' ?>
            <?php if ($role === 'instructor'): ?>
                &middot; <?= e($attempt['name'] . ' ' . $attempt['surname']) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="action-toolbar">
    <?php if ($role === 'instructor'): ?>
        <a class="btn btn-outline" href="../instructor/view_results.php">
            <i class="fa-solid fa-arrow-left"></i> Back to Quiz Results
        </a>
    <?php else: ?>
        <a class="btn btn-outline" href="quiz_results.php">
            <i class="fa-solid fa-arrow-left"></i> Back to My Results
        </a>
        <a class="btn btn-primary" href="take_quiz.php?quiz_id=<?= (int)$attempt['quiz_id'] ?>">
            <i class="fa-solid fa-rotate-right"></i> Retake Quiz
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Result</h2>
            <p class="text-muted"><?= $correctCount ?> of <?= count($questions) ?> answered correctly on this attempt.</p>
        </div>
    </div>
    <div class="result-score"><?= e($attempt['score']) ?>%</div>
    <p class="text-muted">Submitted <?= e($attempt['submitted_at']) ?></p>
</section>

<?php foreach ($questions as $index => $question): ?>
    <?php
    $options = [
        'A' => $question['option_a'],
        'B' => $question['option_b'],
        'C' => $question['option_c'],
        'D' => $question['option_d']
    ];
    $selected = $question['selected_answer'];
    $wasCorrect = (int)$question['is_correct'] === 1;
    ?>
    <section class="dashboard-card quiz-question">
        <div class="card-header">
            <div>
                <h3><?= ($index + 1) ?>. <?= e($question['question_text']) ?></h3>
            </div>
            <?php if ($wasCorrect): ?>
                <span class="badge badge-learner">Correct</span>
            <?php else: ?>
                <span class="badge badge-admin">Incorrect</span>
            <?php endif; ?>
        </div>

        <?php foreach ($options as $letter => $text): ?>
            <?php
            $isAnswer = $letter === $question['correct_answer'];
            $isChoice = $letter === $selected;
            ?>
            <div class="quiz-option">
                <span>
                    <strong><?= $letter ?>.</strong> <?= e($text) ?>
                    <?php if ($isAnswer): ?>
                        <span class="badge badge-learner">Correct answer</span>
                    <?php endif; ?>
                    <?php if ($isChoice && !$isAnswer): ?>
                        <span class="badge badge-admin">Your answer</span>
                    <?php elseif ($isChoice): ?>
                        <span class="badge badge-instructor">Your answer</span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>

        <?php if ($selected === null): ?>
            <p class="text-muted">This question was left unanswered.</p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
