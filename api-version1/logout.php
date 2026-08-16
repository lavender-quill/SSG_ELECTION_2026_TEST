<?php
session_start();
if (isset($_COOKIE[session_name()])) {
    $__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
session_unset();
session_destroy();
header('Location: /');
exit;
