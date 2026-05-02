<?php
session_start();
$report_name = $_GET['file'] ?? '';
if (!$report_name) die('No file.');
$temp_file_path = sys_get_temp_dir() . '/' . $report_name;
if (!file_exists($temp_file_path)) die('File not found.');
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . basename($report_name) . '"');
readfile($temp_file_path);
exit;