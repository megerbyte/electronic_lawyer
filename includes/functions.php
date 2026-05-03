<?php
// includes/functions.php

// Only enable error reporting in local development — never in production
ini_set('display_errors', 0);
error_reporting(0);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function db_connect() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
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
