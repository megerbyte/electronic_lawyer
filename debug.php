<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Script started<br>";

require_once 'includes/config.php';
echo "Loaded config<br>";

require_once 'includes/functions.php';
echo "Loaded functions<br>";

echo "End of script<br>";
?>
?>