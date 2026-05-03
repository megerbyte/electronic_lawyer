<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
session_start();

$prospect_id = $_SESSION['prospect_id'] ?? null;
$prospect_info = [];

$pdo = db_connect();

if ($prospect_id) {
    $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ?");
    $stmt->execute([$prospect_id]);
    $prospect_info = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Start Questionnaire</title>
    <link rel="stylesheet" href="styles.css"> 
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($prospect_info['name'] ?? 'User'); ?>!</h1>
    <p>Your information has been saved. Click below to begin the questionnaire.</p>
    <a href="/Master_Folders/Criminal/First_Contact/First Contact.html?prospect_id=<?php echo $prospect_id; ?>">Start Questionnaire</a>
</body>
</html>