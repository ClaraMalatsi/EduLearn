<?php

/**
 * Course progress.
 *
 * A course is made up of lectures (learning material) and quizzes, so the
 * completion percentage counts both:
 *
 *     percentage = (lectures completed + quizzes attempted) /
 *                  (total lectures  + total quizzes) x 100
 *
 * The result is stored in the progress table, which the learner dashboard,
 * instructor student list and admin progress report all read from.
 */

/**
 * Recalculate and save one learner's progress for one course.
 * Returns the saved percentage.
 */
function recalculateProgress(PDO $pdo, $userId, $courseId)
{
    $userId = (int)$userId;
    $courseId = (int)$courseId;

    // Lectures in the course, and how many this learner has completed.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lectures WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $totalLectures = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM learner_lecture_progress lp
         JOIN lectures l ON l.lecture_id = lp.lecture_id
         WHERE lp.user_id = ? AND l.course_id = ?"
    );
    $stmt->execute([$userId, $courseId]);
    $doneLectures = (int)$stmt->fetchColumn();

    // Quizzes in the course, and how many this learner has attempted.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $totalQuizzes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT a.quiz_id)
         FROM quiz_attempts a
         JOIN quizzes q ON q.quiz_id = a.quiz_id
         WHERE a.user_id = ? AND q.course_id = ?"
    );
    $stmt->execute([$userId, $courseId]);
    $doneQuizzes = (int)$stmt->fetchColumn();

    $totalItems = $totalLectures + $totalQuizzes;
    $doneItems = $doneLectures + $doneQuizzes;

    $percentage = $totalItems > 0 ? round(($doneItems / $totalItems) * 100, 2) : 0;

    $stmt = $pdo->prepare(
        "INSERT INTO progress (user_id, course_id, completed_lectures, percentage)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE completed_lectures = VALUES(completed_lectures),
                                 percentage = VALUES(percentage)"
    );
    $stmt->execute([$userId, $courseId, $doneLectures, $percentage]);

    return $percentage;
}

/**
 * Refresh every enrolled learner's progress for a course.
 * Used after an instructor adds or removes a lecture or quiz, because the
 * totals those percentages were based on have changed.
 */
function refreshCourseProgress(PDO $pdo, $courseId)
{
    $stmt = $pdo->prepare("SELECT user_id FROM enrollments WHERE course_id = ?");
    $stmt->execute([(int)$courseId]);

    foreach ($stmt->fetchAll() as $row) {
        recalculateProgress($pdo, $row['user_id'], $courseId);
    }
}
