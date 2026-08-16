<?php
/**
 * Serves a candidate photo by Candidate_ID.
 * Returns the raw image with appropriate Content-Type headers.
 * No authentication required — photos are public during elections.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// candidate_photo.Candidate_ID stores the Student_ID string (e.g. "2023-00001")
$studentId = trim($_GET['id'] ?? '');
if ($studentId === '') {
    http_response_code(404);
    exit;
}

try {
    $cfg  = \Configuration\Application::$SSG_Candidate_DBase;
    $opts = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo  = new PDO(
        "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
        $cfg['Username'], $cfg['Password'], $opts
    );
    $stmt = $pdo->prepare('SELECT Photo FROM candidate_photo WHERE Candidate_ID = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $row  = $stmt->fetch();
} catch (\Throwable $e) {
    http_response_code(500);
    exit;
}

if (!$row || empty($row['Photo'])) {
    http_response_code(404);
    exit;
}

$raw = $row['Photo'];

// Photo may be stored as a base64 string or as a data URI
if (strpos($raw, 'data:') === 0) {
    // data URI — strip the prefix and decode
    if (preg_match('/^data:(image\/\w+);base64,(.+)$/s', $raw, $m)) {
        $mime   = $m[1];
        $binary = base64_decode($m[2], true);
    } else {
        http_response_code(500);
        exit;
    }
} else {
    // Raw base64 string
    $binary = base64_decode($raw, true);
    // Detect MIME from magic bytes
    $magic = substr($binary, 0, 4);
    if ($magic === "\x89PNG") {
        $mime = 'image/png';
    } elseif (substr($binary, 0, 3) === "\xff\xd8\xff") {
        $mime = 'image/jpeg';
    } elseif (substr($binary, 0, 6) === 'GIF87a' || substr($binary, 0, 6) === 'GIF89a') {
        $mime = 'image/gif';
    } else {
        $mime = 'image/jpeg';
    }
}

if ($binary === false || strlen($binary) < 4) {
    http_response_code(404);
    exit;
}

// Cache for 1 hour — photos rarely change during an election
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . strlen($binary));
echo $binary;
