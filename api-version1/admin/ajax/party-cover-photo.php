<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$pid   = (int)($_POST['party_id']        ?? 0);
$photo = trim($_POST['cover_photo_b64']  ?? '');

if (!$pid || !$photo) {
    echo json_encode(['success' => false, 'error' => 'Party ID and cover photo are required.']);
    exit;
}

// Validate image
$decoded = base64_decode($photo, true);
if ($decoded === false || strlen($decoded) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid image data.']);
    exit;
}
if (strlen($decoded) > 5_000_000) {
    echo json_encode(['success' => false, 'error' => 'Photo is too large (max ~5 MB).']);
    exit;
}

$partiesFile = DATA_DIR . '/parties.json';
$parties = file_exists($partiesFile)
    ? (json_decode(file_get_contents($partiesFile), true) ?: [])
    : [];

$found = false;
foreach ($parties as &$p) {
    if ((int)($p['id'] ?? 0) === $pid) {
        $p['cover_photo'] = $photo;
        $found = true;
    }
}
unset($p);

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Party not found.']);
    exit;
}

$ok = file_put_contents(
    $partiesFile,
    json_encode(array_values($parties), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

echo json_encode($ok !== false
    ? ['success' => true]
    : ['success' => false, 'error' => 'Could not save cover photo. Please try again.']
);
