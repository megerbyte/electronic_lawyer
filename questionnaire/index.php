<?php
/**
 * questionnaire/index.php
 *
 * Entry point for the legal form questionnaire system.
 * Starts or resumes a user session, creates a user_cases record,
 * and redirects to the intake decision-tree questionnaire.
 */

session_start();

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// If the user already has an active case in progress, resume it
if (!empty($_SESSION['case_id'])) {
    $stmt = $pdo->prepare("SELECT id, status FROM user_cases WHERE id = ? AND session_token = ?");
    $stmt->execute([$_SESSION['case_id'], $_SESSION['session_token'] ?? '']);
    $existingCase = $stmt->fetch();

    if ($existingCase) {
        if ($existingCase['status'] === 'variables') {
            header('Location: variables.php');
            exit;
        }
        if ($existingCase['status'] === 'complete') {
            header('Location: output.php');
            exit;
        }
        // status = 'intake' — continue the tree
        header('Location: intake.php');
        exit;
    }
}

// Create a new case session
$token = bin2hex(random_bytes(32));

$stmt = $pdo->prepare(
    "INSERT INTO user_cases (session_token, status) VALUES (?, 'intake')"
);
$stmt->execute([$token]);
$caseId = (int)$pdo->lastInsertId();

$_SESSION['case_id']       = $caseId;
$_SESSION['session_token'] = $token;
$_SESSION['breadcrumb']    = [];  // track question/answer history

header('Location: intake.php');
exit;
