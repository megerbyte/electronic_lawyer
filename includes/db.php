<?php
require_once __DIR__ . '/config.php';

// Electronic Lawyer DB constants (used by the legal forms system)
// Falls back to the existing DB_* constants defined in config.php if not overridden
if (!defined('EL_DB_HOST')) {
    define('EL_DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
}
if (!defined('EL_DB_NAME')) {
    define('EL_DB_NAME', 'electronic_lawyer');
}
if (!defined('EL_DB_USER')) {
    define('EL_DB_USER', defined('DB_USER') ? DB_USER : 'root');
}
if (!defined('EL_DB_PASS')) {
    define('EL_DB_PASS', defined('DB_PASS') ? DB_PASS : '');
}

/**
 * Returns a PDO connection to the application database.
 * The connection is created once per PHP process (static singleton).
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . EL_DB_HOST . ';dbname=' . EL_DB_NAME . ';charset=utf8mb4',
                EL_DB_USER,
                EL_DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // Log and show a safe error — never expose credentials
            error_log('DB Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection error. Please try again later.');
        }
    }
    return $pdo;
}
