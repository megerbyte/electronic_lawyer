<?php
// includes/functions.php

// Enable error reporting during development (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function db_connect() {
    $host = 'localhost';
    $db   = 'resemzqt_researchrepository';
    $user = 'resemzqt_randy';
    $pass = 'Corky@4332661';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Input sanitization function
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>
