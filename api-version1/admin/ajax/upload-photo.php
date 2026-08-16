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

$candidateId = trim($_POST['candidate_id'] ?? '');
$photob64    = trim($_POST['photo_b64']    ?? '');

if ($candidateId === '') {
    echo json_encode(['success' => false, 'error' => 'Candidate ID is required.']);
    exit;
}
if ($photob64 === '') {
    echo json_encode(['success' => false, 'error' => 'Photo data is required.']);
    exit;
}

// Validate the base64 payload is a real image
$decoded = base64_decode($photob64, true);
if ($decoded === false || strlen($decoded) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid image data.']);
    exit;
}
// Reject payloads larger than ~5 MB decoded
if (strlen($decoded) > 5_000_000) {
    echo json_encode(['success' => false, 'error' => 'Photo file is too large (max ~5 MB).']);
    exit;
}

// Write directly to the candidate_photo table — the stored procedure route has a
// silent catch block that swallows failures, so we use the same direct PDO
// approach that candidate-photo.php uses to read the photo.
try {
    $cfg  = \Configuration\Application::$SSG_Candidate_DBase;
    $opts = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO(
        "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
        $cfg['Username'], $cfg['Password'], $opts
    );

    // INSERT or UPDATE — if a row already exists for this Candidate_ID, replace the photo
    $stmt = $pdo->prepare(
        'INSERT INTO candidate_photo (Candidate_ID, Photo)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE Photo = VALUES(Photo)'
    );
    $stmt->execute([$candidateId, $photob64]);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    error_log('upload-photo PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: could not save photo.']);
}
