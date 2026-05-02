<?php
// Ensure this file is not web-accessible except via POST!
require_once $_SERVER['DOCUMENT_ROOT'] . '/researchrepository.info/includes/config.sys';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['send_now'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$prospect_id = $_SESSION['prospect_id'] ?? null;
$report_name = $_SESSION['report_name'] ?? null;
$report_path = $_SESSION['report_path'] ?? null;

if (!$prospect_id || !$report_name || !$report_path || !file_exists($report_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing report or session info']);
    exit;
}

// Load DB config
$config = include($_SERVER['DOCUMENT_ROOT'] . '/researchrepository.info/includes/config.sys');
$db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
if ($db->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}

// Get lawyers
$lawyers = [];
$res = $db->query("SELECT email, name FROM lawyers WHERE active=1");
while ($row = $res->fetch_assoc()) $lawyers[] = $row;

// Prepare email
$subject = "New Prospect Report Submission";
$message = "A new user has submitted their case. See attached report.\n\n";
$message .= "See attached report file.";
$boundary = md5(time());
$headers = "From: noreply@yourdomain.com\r\n";
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

foreach ($lawyers as $lawyer) {
    @mail($lawyer['email'], $subject, $body, $headers);
}
echo json_encode(['status' => 'success']);