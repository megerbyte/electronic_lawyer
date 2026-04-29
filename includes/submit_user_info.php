<?php
// submit_user_info.php - Updated to remove hCAPTCHA verification
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdo = db_connect();
    $stmt = $pdo->prepare("INSERT INTO prospects (name, address, city, state, zip, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['phone'], $_POST['email']]);
    $prospectId = $pdo->lastInsertId();

    $_SESSION['prospect_id'] = $prospectId;

    header('Location: /Criminal/first%20contact/First%20Contact/First%20Contact.html');
    exit;
}
?>