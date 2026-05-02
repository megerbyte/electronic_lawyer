<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/researchrepository.info/includes/config.sys';
require_once $_SERVER['DOCUMENT_ROOT'] . '/researchrepository.info/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$prospect_id = $_SESSION['prospect_id'] ?? null;
$report_name = $_SESSION['report_name'] ?? null;
$report_path = $_SESSION['report_path'] ?? null;

if (!$prospect_id || !$report_name || !$report_path || !file_exists($report_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing report or session info']);
    exit;
}

// Load DB config
$pdo = db_connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get lawyers
$lawyers = [];
try {
    $stmt = $pdo->prepare("SELECT email, name FROM lawyers WHERE active = 1");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lawyers[] = $row;
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
    exit;
}

if (empty($lawyers)) {
    echo json_encode(['status' => 'error', 'message' => 'No active lawyers found']);
    exit;
}

// Randomly select up to 5 lawyers
shuffle($lawyers);
$selected_lawyers = array_slice($lawyers, 0, 5);
// Add your own email so you get a copy
$selected_lawyers[] = ['email' => 'randy@randykelton.com', 'name' => 'Randy Kelton'];

// Prepare email
$subject = "New Prospect Report Submission";
$message = "A new user has submitted their case. See attached report.\n\n";
$boundary = md5(time());
$headers = "From: noreply@researchrepository.info\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"".$boundary."\"\r\n";

$attachment = chunk_split(base64_encode(file_get_contents($report_path)));
$body = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n\r\n";
$body .= $message . "\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/plain; name=\"".$report_name."\"\r\n";
$body .= "Content-Disposition: attachment; filename=\"".$report_name."\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body .= $attachment . "\r\n";
$body .= "--$boundary--";

$success = true;
foreach ($selected_lawyers as $lawyer) {
    if (!@mail($lawyer['email'], $subject, $body, $headers)) {
        $success = false;
        error_log("Failed to send email to {$lawyer['email']}");
    }
}

// Clean up session and file
unlink($report_path); // Delete temporary report file
$_SESSION['report_ready'] = null;
$_SESSION['report_name'] = null;
$_SESSION['report_path'] = null;

if ($success) {
    echo json_encode(['status' => 'success', 'redirect' => '/thank_you.php']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send emails to some lawyers']);
}
?>