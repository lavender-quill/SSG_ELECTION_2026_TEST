<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

$sid = trim($_POST['student_id']    ?? '');
$yr  = trim($_POST['election_year'] ?? '');

if (!$sid) {
    echo json_encode(['success' => false, 'error' => 'Student ID is required.']);
    exit;
}

try {
    $db  = \Configuration\Application::$SSG_Candidate_DBase;
    $pdo = new PDO(
        "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4",
        $db['Username'],
        $db['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare("DELETE FROM candidate_position WHERE Student_ID = ? AND Election_Year = ?");
    $stmt->execute([$sid, $yr]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('remove-candidate AJAX PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
}
