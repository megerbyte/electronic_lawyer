<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Collect GET parameters
$fields = ['name', 'address', 'city', 'state', 'zip', 'phone', 'email', 'csrf_token'];
$postData = [];
foreach ($fields as $field) {
    if (isset($_GET[$field]) && trim($_GET[$field]) !== '') {
        $postData[$field] = trim($_GET[$field]);
    }
}

// Validate required fields
$missing = [];
foreach ($fields as $field) {
    if (empty($postData[$field])) {
        $missing[] = $field;
    }
}
if ($missing) {
    $_SESSION['form_errors'] = ["Missing fields: " . implode(', ', $missing)];
    header('Location: /Master_Folders/Criminal/First_Contact/First_Contact.html?form=error');
    exit;
}

// Add the report file as a real file upload
if (isset($_SESSION['report_name'])) {
    $reportFile = sys_get_temp_dir() . '/' . $_SESSION['report_name'];
    if (file_exists($reportFile)) {
        $postData['report_file'] = new CURLFile($reportFile, 'text/plain', $_SESSION['report_name']);
    } else {
        $_SESSION['form_errors'] = ["Report file not found. Please regenerate your report."];
        header('Location: /Master_Folders/Criminal/First_Contact/First_Contact.html?form=error');
        exit;
    }
} else {
    $_SESSION['form_errors'] = ["No report file found in session. Please regenerate your report."];
    header('Location: /Master_Folders/Criminal/First_Contact/First_Contact.html?form=error');
    exit;
}

// Send POST request with file
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://researchrepository.info/submit_user_info.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 302 || $httpCode === 200) {
    header('Location: /send_to_lawyers.php');
    exit;
} else {
    $_SESSION['form_errors'] = ['Error processing form data. Please try again.'];
    header('Location: /Master_Folders/Criminal/First_Contact/First_Contact.html?form=error');
    exit;
}
?>