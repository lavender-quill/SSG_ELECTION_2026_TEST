<?php
// Router for PHP built-in server
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Remove query string from file path
$file = preg_replace('#(\?.*)$#', '', $file);

// If it's a real file or directory, serve it
if (is_file($file) || is_dir($file)) {
    return false;
}

// Check if admin directory exists and route to admin/index.php
if (strpos($uri, '/admin') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    $_SERVER['REQUEST_URI'] = $uri;
    include __DIR__ . '/admin/index.php';
    return true;
}

// Route everything else to main index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = $uri;
include __DIR__ . '/index.php';
?>
