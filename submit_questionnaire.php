<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Must be logged in (prospect_id set)
if (!isset($_SESSION['prospect_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $fields = ['legal_issue', 'date_of_incident', 'description'];
    $errors = [];
    $data = [];
    foreach ($fields as $field) {
        $value = trim($_POST[$field] ?? '');
        if ($value === '') $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        $data[$field] = $value;
    }
    if ($errors) {
        $_SESSION['form_errors'] = $errors;
        header('Location: First_Contact.php?form=error');
        exit;
    }
    // Save to DB -- Example table: questionnaire_responses
    try {
        $pdo = db_connect();
        $stmt = $pdo->prepare(
            "INSERT INTO questionnaire_responses (prospect_id, legal_issue, date_of_incident, description)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['prospect_id'], $data['legal_issue'], $data['date_of_incident'], $data['description']
        ]);
        unset($_SESSION['csrf_token']);
        // Redirect to a thank you or dashboard page
        header('Location: thankyou.php');
        exit;
    } catch (Exception $e) {
        error_log('Questionnaire insert error: ' . $e->getMessage());
        $_SESSION['form_errors'] = ['An internal error occurred. Please try again later.'];
        header('Location: First_Contact.php?form=error');
        exit;
    }
} else {
    // User tried to visit this file directly
    http_response_code(405);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>405 Method Not Allowed</title></head>
    <body>
    <div style="margin:2em auto;max-width:600px;text-align:center">
        <h2>405 Method Not Allowed</h2>
        <p>This page can only be accessed by submitting the questionnaire form.</p>
        <a href="First_Contact.php">Return to questionnaire</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>