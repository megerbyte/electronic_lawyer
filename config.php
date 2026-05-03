<?php
// Only enable error reporting in local development — never in production
error_reporting(0);
ini_set('display_errors', 0);

// Environment variables must be loaded before this file is included.
// Use a library such as vlucas/phpdotenv or set variables via your web server
// (e.g. Apache SetEnv / Nginx fastcgi_param) or hosting control panel.
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);
?>