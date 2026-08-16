<?php
/**
 * JRMSU SSG Election Portal — Health Check
 *
 * Verifies all four MySQL database connections using the environment-supplied
 * credentials.  Safe to run at any time; it only opens and closes connections,
 * never writes data.
 *
 * Usage (CLI):
 *   php api-version1/healthcheck.php
 *
 * Usage (HTTP — restricted to localhost):
 *   curl http://127.0.0.1:5000/healthcheck.php
 */

require_once __DIR__ . '/Configuration/Application.Config.php';
Configuration\Application::init();

$databases = [
    'Manage'    => \Configuration\Application::$SSG_API_Manage_DBase,
    'Voter'     => \Configuration\Application::$SSG_Voter_DBase,
    'Candidate' => \Configuration\Application::$SSG_Candidate_DBase,
    'Election'  => \Configuration\Application::$SSG_Election_DBase,
];

// HTTP: only allow localhost access
if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain');
}

$allOk = true;
foreach ($databases as $name => $cfg) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $cfg['Host'], $cfg['Port'], $cfg['DBName']);
        $pdo = new PDO($dsn, $cfg['Username'], $cfg['Password'], [
            PDO::ATTR_TIMEOUT    => 8,
            PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[OK]   DB_{$name}: connected to {$cfg['DBName']} on {$cfg['Host']}\n";
    } catch (\Exception $e) {
        echo "[FAIL] DB_{$name}: " . $e->getMessage() . "\n";
        $allOk = false;
    }
}

echo $allOk ? "\nAll databases OK.\n" : "\nOne or more database connections FAILED.\n";
if (php_sapi_name() !== 'cli') {
    http_response_code($allOk ? 200 : 503);
}
