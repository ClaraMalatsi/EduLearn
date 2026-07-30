<?php
/**
 * Serves an uploaded lecture file.
 *
 * Files are stored under uploads/ with random names and that folder blocks
 * direct web access, so every download comes through here and is checked first:
 *
 *   admin      - may download any lecture
 *   instructor - only lectures in courses assigned to them
 *   learner    - only lectures in courses they are enrolled in
 */

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$lectureId = (int)($_GET['lecture_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$stmt = $pdo->prepare(
    "SELECT lecture_id, course_id, lecture_name, file_path
     FROM lectures WHERE lecture_id = ?"
);
$stmt->execute([$lectureId]);
$lecture = $stmt->fetch();

if (!$lecture) {
    http_response_code(404);
    exit('That lecture could not be found.');
}

$allowed = false;

if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'instructor') {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM course_assignments
         WHERE course_id = ? AND instructor_id = ?"
    );
    $stmt->execute([$lecture['course_id'], $userId]);
    $allowed = (int)$stmt->fetchColumn() > 0;
} elseif ($role === 'learner') {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM enrollments
         WHERE course_id = ? AND user_id = ?"
    );
    $stmt->execute([$lecture['course_id'], $userId]);
    $allowed = (int)$stmt->fetchColumn() > 0;
}

if (!$allowed) {
    http_response_code(403);
    exit('You do not have permission to download this file.');
}

// basename() keeps the stored value from stepping outside the uploads folder.
$path = __DIR__ . '/uploads/' . basename($lecture['file_path']);

if (!is_file($path)) {
    http_response_code(404);
    exit('The file is missing from the server.');
}

// Offer the download under the lecture name, keeping the real file extension.
$extension = pathinfo($lecture['file_path'], PATHINFO_EXTENSION);
$downloadName = preg_replace('/[^A-Za-z0-9 ._-]/', '', $lecture['lecture_name']);
$downloadName = trim($downloadName) !== '' ? $downloadName : 'lecture';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '.' . $extension . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
