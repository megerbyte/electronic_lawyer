<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/researchrepository.info/includes/config.sys';

file_put_contents('/tmp/bridge_debug.txt', "Bridge loaded at ".date('c')."\n", FILE_APPEND);

$report_name = $_SESSION['report_name'] ?? null;
$report_path = $_SESSION['report_path'] ?? (isset($_SESSION['report_name']) ? sys_get_temp_dir() . '/' . $_SESSION['report_name'] : null);

file_put_contents('/tmp/bridge_debug.txt', "Session: ".print_r($_SESSION,true)."\n", FILE_APPEND);

if (!$report_name || !$report_path || !file_exists($report_path)) {
    file_put_contents('/tmp/bridge_debug.txt', "Missing report_name or report_path or file.\n", FILE_APPEND);
    $_SESSION['form_errors'] = ["No report file found in session. Please regenerate your report."];
    header('Location: /First_Contact.php?form=error');
    exit;
}

$postData = [
    'send_now' => 1,
    'report_file' => new CURLFile($report_path, 'text/plain', $report_name)
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://researchrepository.info/send_report_to_lawyers.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // ADD THIS LINE

file_put_contents('/tmp/bridge_debug.txt', "About to start cURL\n", FILE_APPEND);

$response = curl_exec($ch);

$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

file_put_contents('/tmp/bridge_debug.txt', "Finished cURL - error: $curlError, code: $httpCode, response: $response\n", FILE_APPEND);

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    file_put_contents('/tmp/bridge_debug.txt', "Decoded result: ".print_r($result, true)."\n", FILE_APPEND);
    if ($result && isset($result['status']) && $result['status'] === 'success') {
        header('Location: /thank_you.php');
        exit;
    } else {
        $_SESSION['form_errors'] = ['Error sending report to lawyers: ' . ($result['message'] ?? 'Unknown error')];
        header('Location: /First_Contact.php?form=error');
        exit;
    }
} else {
    $_SESSION['form_errors'] = ['Could not send report to lawyers. Please try again.'];
    header('Location: /First_Contact.php?form=error');
    exit;
}
?>