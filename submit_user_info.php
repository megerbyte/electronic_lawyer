<?php
/**
 * submit_user_info.php
 * 
 * Handles both the initial user info submission (from index.php) and the later report upload (after questionnaire).
 * - If only user info is submitted, save it and redirect to the questionnaire.
 * - If a report file is uploaded, save it to the database for the existing user.
 *
 * This version will NOT require a report file for the initial submission,
 * and will only expect one after the questionnaire.
 */

ob_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<h2>405 Method Not Allowed</h2>";
    ob_end_flush();
    exit;
}

// CSRF protection
if (
    empty($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['form_errors'] = ['Invalid CSRF token. Please try again.'];
    header('Location: /index.php?form=error');
    ob_end_flush();
    exit;
}

$pdo = db_connect();
$fields = ['name', 'address', 'city', 'state', 'zip', 'phone', 'email'];
$errors = [];
$data = [];
foreach ($fields as $field) {
    $value = trim($_POST[$field] ?? '');
    if ($value === '') $errors[] = ucfirst($field) . ' is required.';
    $data[$field] = $value;
}
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if (!empty($data['zip']) && !preg_match('/^\d{5}(-\d{4})?$/', $data['zip'])) {
    $errors[] = 'Invalid ZIP code format.';
}
if (!empty($data['phone']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $data['phone'])) {
    $errors[] = 'Invalid phone number.';
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    header('Location: /index.php?form=error');
    ob_end_flush();
    exit;
}

// If a report file is uploaded, process it (this should only happen after the questionnaire)
if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
    // Only allow this if the user is already registered/logged in (prospect_id is set)
    if (!isset($_SESSION['prospect_id'])) {
        $_SESSION['form_errors'] = ['You must complete your contact information first.'];
        header('Location: /index.php?form=error');
        ob_end_flush();
        exit;
    }
    $fileContent = file_get_contents($_FILES['report_file']['tmp_name']);
    $allowedTypes = ['text/plain'];
    if (!in_array($_FILES['report_file']['type'], $allowedTypes)) {
        $_SESSION['form_errors'] = ['Invalid file type. Only text files are allowed.'];
        header('Location: /First_Contact.html?form=error');
        ob_end_flush();
        exit;
    }
    // Update the prospect's report
    $stmt = $pdo->prepare(
        "UPDATE prospects SET report = ? WHERE id = ?"
    );
    $stmt->execute([$fileContent, $_SESSION['prospect_id']]);
    $_SESSION['report_name'] = $_FILES['report_file']['name'];
    $_SESSION['report_content'] = $fileContent;
    unset($_SESSION['csrf_token']);
    // Proceed to the next step (e.g., send to lawyers)
    header('Location: /send_to_lawyers.php');
    ob_end_flush();
    exit;
} else {
    // No file uploaded: this is the initial info submission
    // Insert user info into the prospects table, report is NULL
    $stmt = $pdo->prepare(
        "INSERT INTO prospects (name, address, city, state, zip, phone, email, report)
         VALUES (?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    $stmt->execute([
        $data['name'], $data['address'], $data['city'], $data['state'],
        $data['zip'], $data['phone'], $data['email']
    ]);
    $prospectId = $pdo->lastInsertId();
    $_SESSION['user_info'] = $data;
    $_SESSION['prospect_id'] = $prospectId;
    unset($_SESSION['csrf_token']);
    // Redirect to the questionnaire
    header('Location: /Master_Folders/Criminal/First_Contact/First_Contact.html');
    ob_end_flush();
    exit;
}