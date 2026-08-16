<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

$candidateId = trim($_POST['candidate_id'] ?? '');

if (!$candidateId) {
    echo json_encode(['success' => false, 'error' => 'Candidate ID is required']);
    exit;
}

use Configuration\Application as Application;

try {
    $db = Application::$SSG_Candidate_DBase;
    $pdo = new PDO(
        "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
        $db['Username'],
        $db['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("DELETE FROM candidate_position WHERE Candidate_ID = ?");
    $stmt->execute([$candidateId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No matching record found. The candidate may have already been removed.']);
    }
} catch (PDOException $e) {
    error_log('candidate-delete PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
}
