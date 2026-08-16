<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

$sid = trim($_GET['student_id'] ?? '');
$yr  = trim($_GET['election_year'] ?? '');

if (!$sid || !$yr) {
    echo json_encode(['success' => true, 'duplicate' => false]);
    exit;
}

try {
    $cDb = \Configuration\Application::$SSG_Candidate_DBase;
    $pdo = new PDO(
        "mysql:host={$cDb['Host']};port={$cDb['Port']};dbname={$cDb['DBName']};charset=utf8mb4",
        $cDb['Username'],
        $cDb['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->prepare(
        "SELECT cp.Application_Status, cp.Election_Year,
                pp.Position_Name
         FROM candidate_position cp
         LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID
         WHERE cp.Student_ID = ? AND cp.Election_Year = ?
         ORDER BY cp.Record_ID DESC
         LIMIT 1"
    );
    $stmt->execute([$sid, $yr]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'success'   => true,
            'duplicate' => true,
            'status'    => $row['Application_Status'] ?? 'UNKNOWN',
            'position'  => $row['Position_Name']      ?? 'a position',
            'year'      => $row['Election_Year']       ?? $yr,
        ]);
    } else {
        echo json_encode(['success' => true, 'duplicate' => false]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'duplicate' => false]);
}
