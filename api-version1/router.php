<?php
// Simple router for PHP built-in server
$file = __DIR__ . $_SERVER["REQUEST_URI"];
$file = preg_replace('#(\?.*)$#', '', $file);

if (is_file($file) && is_readable($file)) {
    return false;
}

// Route everything else to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
include __DIR__ . '/index.php';
?>
