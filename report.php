<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['prospect_id']) || !isset($_SESSION['report_content'])) {
    header('Location: /case_eval/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Evaluation Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Your Case Evaluation Report</h1>
    <p><strong>Report Name:</strong> <?php echo htmlspecialchars($_SESSION['report_name']); ?></p>
    <p><?php echo nl2br(htmlspecialchars($_SESSION['report_content'])); ?></p>
    <a href="/logout.php" class="btn btn-secondary">Logout</a>
</div>
</body>
</html>