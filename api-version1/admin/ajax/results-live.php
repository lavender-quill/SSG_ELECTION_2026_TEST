<?php
/**
 * Live candidate vote tally for the Results page.
 * Returns JSON grouped by position, ordered by Position_Rank then vote count desc.
 */
require_once dirname(dirname(dirname(__DIR__))) . '/api-version1/includes/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api-version1/includes/admin-guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$schoolYear = ELECTION_SCHOOL_YEAR;

try {
    // ── Election DB: vote tally ───────────────────────────────────────────────
    $eCfg = \Configuration\Application::$SSG_Election_DBase;
    $ePdo = new PDO(
        "mysql:host={$eCfg['Host']};port={$eCfg['Port']};dbname={$eCfg['DBName']};charset=utf8mb4",
        $eCfg['Username'], $eCfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $ePdo->prepare(
        'SELECT Candidate_ID, Position, COUNT(*) AS vote_count
         FROM votes
         WHERE School_Year = ?
         GROUP BY Candidate_ID, Position'
    );
    $stmt->execute([$schoolYear]);
    $tallyRows = $stmt->fetchAll();

    // Build quick lookup: Candidate_ID => vote_count
    $tallyMap = [];
    foreach ($tallyRows as $t) {
        $tallyMap[$t['Candidate_ID']] = (int)$t['vote_count'];
    }

    $totalVotes = array_sum($tallyMap);

    // ── Candidate DB: approved candidates + position info ─────────────────────
    $cCfg = \Configuration\Application::$SSG_Candidate_DBase;
    $cPdo = new PDO(
        "mysql:host={$cCfg['Host']};port={$cCfg['Port']};dbname={$cCfg['DBName']};charset=utf8mb4",
        $cCfg['Username'], $cCfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $cStmt = $cPdo->prepare(
        "SELECT cp.Candidate_ID, cp.Student_ID, cp.Position_ID, cp.Candidate_Slate_ID,
                p.Position, p.Position_Rank, p.Num_Elected_Officer,
                cs.Candidate_Slate
         FROM candidate_position cp
         LEFT JOIN position p ON cp.Position_ID = p.Position_ID
         LEFT JOIN candidate_slate cs ON cp.Candidate_Slate_ID = cs.Candidate_Slate_ID
         WHERE cp.Election_Year = ? AND cp.Application_Status = 'APPROVED'
         ORDER BY p.Position_Rank ASC"
    );
    $cStmt->execute([$schoolYear]);
    $candidates = $cStmt->fetchAll();

    // ── Voter DB: student names ───────────────────────────────────────────────
    $vCfg = \Configuration\Application::$SSG_Voter_DBase;
    $vPdo = new PDO(
        "mysql:host={$vCfg['Host']};port={$vCfg['Port']};dbname={$vCfg['DBName']};charset=utf8mb4",
        $vCfg['Username'], $vCfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    if (!empty($candidates)) {
        $sIds = array_unique(array_column($candidates, 'Student_ID'));
        $ph   = implode(',', array_fill(0, count($sIds), '?'));
        $sStmt = $vPdo->prepare(
            "SELECT Student_ID, MAX(Student_Name) AS Student_Name
             FROM student
             WHERE Student_ID IN ($ph)
             GROUP BY Student_ID"
        );
        $sStmt->execute(array_values($sIds));
        $nameMap = [];
        foreach ($sStmt->fetchAll() as $row) {
            $nameMap[$row['Student_ID']] = $row['Student_Name'];
        }
    } else {
        $nameMap = [];
    }

    // ── Assemble per-position groups ──────────────────────────────────────────
    $byPosition = [];
    foreach ($candidates as $c) {
        $posId   = (int)$c['Position_ID'];
        $posName = $c['Position'] ?? 'Position ' . $posId;
        $rank    = (int)($c['Position_Rank'] ?? 99);
        $numElect = (int)($c['Num_Elected_Officer'] ?? 1);

        if (!isset($byPosition[$posId])) {
            $byPosition[$posId] = [
                'position_id'        => $posId,
                'position_name'      => $posName,
                'position_rank'      => $rank,
                'num_elected'        => $numElect,
                'candidates'         => [],
            ];
        }

        $votes = $tallyMap[$c['Candidate_ID']] ?? 0;

        $byPosition[$posId]['candidates'][] = [
            'candidate_id' => $c['Candidate_ID'],
            'student_id'   => $c['Student_ID'],
            'name'         => $nameMap[$c['Student_ID']] ?? '—',
            'slate'        => $c['Candidate_Slate'] ?? '—',
            'votes'        => $votes,
        ];
    }

    // Sort candidates within each position by votes desc
    foreach ($byPosition as &$pos) {
        usort($pos['candidates'], fn($a, $b) => $b['votes'] - $a['votes']);
    }
    unset($pos);

    // Sort positions by rank
    usort($byPosition, fn($a, $b) => $a['position_rank'] - $b['position_rank']);

    echo json_encode([
        'ok'          => true,
        'school_year' => $schoolYear,
        'total_votes' => $totalVotes,
        'positions'   => array_values($byPosition),
        'generated'   => date('Y-m-d H:i:s'),
    ]);

} catch (Throwable $e) {
    error_log('results-live error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
}
