<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

$oldSid  = trim($_POST['old_student_id'] ?? '');
$newSid  = trim($_POST['new_student_id'] ?? '');
$yr      = trim($_POST['election_year']  ?? '');

if (!$oldSid || !$newSid || !$yr) {
    echo json_encode(['success' => false, 'error' => 'Old ID, New ID, and Election Year are required.']);
    exit;
}

if ($oldSid === $newSid) {
    echo json_encode(['success' => false, 'error' => 'New ID is the same as the current ID.']);
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

    // Check the old ID actually exists for this year
    $chk = $pdo->prepare("SELECT COUNT(*) FROM candidate_position WHERE Student_ID = ? AND Election_Year = ?");
    $chk->execute([$oldSid, $yr]);
    if ((int)$chk->fetchColumn() === 0) {
        echo json_encode(['success' => false, 'error' => 'Candidate not found.']);
        exit;
    }

    // Check new ID not already in use for this year
    $dup = $pdo->prepare("SELECT COUNT(*) FROM candidate_position WHERE Student_ID = ? AND Election_Year = ?");
    $dup->execute([$newSid, $yr]);
    if ((int)$dup->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Student ID ' . htmlspecialchars($newSid) . ' already has a registration for this election year.']);
        exit;
    }

    $upd = $pdo->prepare("UPDATE candidate_position SET Student_ID = ? WHERE Student_ID = ? AND Election_Year = ?");
    $upd->execute([$newSid, $oldSid, $yr]);

    // Try to fetch student name from voter DB to return to UI
    $studentName = null;
    try {
        $vDb = \Configuration\Application::$SSG_Voter_DBase;
        $vPdo = new PDO(
            "mysql:host={$vDb['Host']};port={$vDb['Port']};dbname={$vDb['DBName']};charset=utf8mb4",
            $vDb['Username'], $vDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $ns = $vPdo->prepare("SELECT Student_Name FROM student WHERE Student_ID = ? LIMIT 1");
        $ns->execute([$newSid]);
        $nr = $ns->fetch();
        if ($nr) $studentName = $nr['Student_Name'];
    } catch (\Throwable $e) {}

    echo json_encode([
        'success'      => true,
        'new_id'       => $newSid,
        'student_name' => $studentName,
    ]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error — could not update ID.']);
}
