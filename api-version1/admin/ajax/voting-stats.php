<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;

use Configuration\Application as Application;

/**
 * Open a PDO connection to a given DB config.
 */
function openPdo(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
        $cfg['Username'],
        $cfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

$collegeVotes = [];
$totalVoters  = 0;
$alreadyVoted = 0;
$recentVoters = [];

// ── 1. Count distinct voters & fetch their IDs from election DB ──────────────
try {
    $ePdo = openPdo(Application::$SSG_Election_DBase);

    // Total unique voters who have cast ballots this school year
    $stmt = $ePdo->prepare(
        'SELECT COUNT(DISTINCT Voter_ID) FROM votes WHERE School_Year = ?'
    );
    $stmt->execute([$schoolYear]);
    $alreadyVoted = (int) $stmt->fetchColumn();

    // Fetch the distinct voter IDs so we can look up their college
    $stmt = $ePdo->prepare(
        'SELECT DISTINCT Voter_ID FROM votes WHERE School_Year = ?'
    );
    $stmt->execute([$schoolYear]);
    $voterIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Recent 10 voters (most recent ballot row per voter)
    $stmt = $ePdo->prepare(
        'SELECT Voter_ID, MAX(Record_ID) AS last_id
         FROM votes
         WHERE School_Year = ?
         GROUP BY Voter_ID
         ORDER BY last_id DESC
         LIMIT 10'
    );
    $stmt->execute([$schoolYear]);
    $recentRows = $stmt->fetchAll();

    // Read cast timestamps — DB primary, JSON fallback
    $castTimes = [];
    try {
        $_eCfg2 = \Configuration\Application::$SSG_Election_DBase;
        $_ePdo2 = new PDO("mysql:host={$_eCfg2['Host']};port={$_eCfg2['Port']};dbname={$_eCfg2['DBName']};charset=utf8mb4",
            $_eCfg2['Username'], $_eCfg2['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $_ctStmt = $_ePdo2->prepare('SELECT Student_ID, Cast_At FROM cast_votes WHERE School_Year = ?');
        $_ctStmt->execute([$schoolYear]);
        foreach ($_ctStmt->fetchAll(PDO::FETCH_ASSOC) as $_ct) {
            $castTimes[$_ct['Student_ID']] = $_ct['Cast_At'];
        }
    } catch (\Throwable $_cte) {
        // DB unavailable — fall back to JSON cache
        $castVotesFile = dirname(dirname(dirname(__DIR__))) . '/data/cast_votes.json';
        if (is_readable($castVotesFile)) {
            $decoded = json_decode(file_get_contents($castVotesFile), true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $ts) {
                    $parts = explode('::', $key, 2);
                    if (count($parts) === 2) $castTimes[$parts[1]] = $ts;
                }
            }
        }
    }

    foreach ($recentRows as $r) {
        $recentVoters[] = [
            'Voter_ID'  => $r['Voter_ID'],
            'Voted_At'  => $castTimes[$r['Voter_ID']] ?? null,
        ];
    }
} catch (Throwable $e) {
    error_log('voting-stats election DB error: ' . $e->getMessage());
}

// ── 1b. Enrich recent voters with name + college from voter DB ───────────────
// Done after voter DB section so we can reuse the same $vPdo connection.

// ── 2. Total registered voters & per-college breakdown from voter DB ─────────
// The ARMS enrollment data available is 2024-2025. We use that as the voter
// base regardless of the current election year, since it is the most recent
// complete enrollment snapshot.
try {
    $vPdo = openPdo(Application::$SSG_Voter_DBase);

    // Auto-detect the most recent school year that has student data
    $baseYearRow = $vPdo->query(
        'SELECT School_Year FROM student GROUP BY School_Year ORDER BY School_Year DESC LIMIT 1'
    )->fetch();
    $baseYear = $baseYearRow ? $baseYearRow['School_Year'] : null;

    if ($baseYear) {
        // Total enrolled students for the base year (all semesters combined)
        $stmt = $vPdo->prepare(
            'SELECT COUNT(DISTINCT Student_ID) FROM student WHERE School_Year = ?'
        );
        $stmt->execute([$baseYear]);
        $totalVoters = (int) $stmt->fetchColumn();
    }

    // Always load all colleges with their enrolled totals from the base year.
    // This ensures every college appears in the chart even with 0 votes.
    $collegeTotals = []; // College_Description => total students
    if ($baseYear) {
        $stmt = $vPdo->prepare(
            'SELECT c.College_Description,
                    COUNT(DISTINCT s.Student_ID) AS Total
             FROM student s
             JOIN program p ON s.Program_Code = p.Program_Code
             JOIN college c ON p.College_Code = c.College_Code
             WHERE s.School_Year = ?
             GROUP BY c.College_Description
             ORDER BY Total DESC'
        );
        $stmt->execute([$baseYear]);
        foreach ($stmt->fetchAll() as $row) {
            $collegeTotals[$row['College_Description']] = (int) $row['Total'];
        }
    }

    // Per-college vote counts — match voted IDs against student table
    $votedByCollege = []; // College_Description => voted count
    $matchedCount   = 0;
    if (!empty($voterIds)) {
        $placeholders = implode(',', array_fill(0, count($voterIds), '?'));
        $stmt = $vPdo->prepare(
            "SELECT c.College_Description, COUNT(DISTINCT s.Student_ID) AS VoterCount
             FROM student s
             JOIN program p ON s.Program_Code = p.Program_Code
             JOIN college c ON p.College_Code = c.College_Code
             WHERE s.Student_ID IN ($placeholders)
             GROUP BY c.College_Description"
        );
        $stmt->execute(array_values($voterIds));
        foreach ($stmt->fetchAll() as $row) {
            $votedByCollege[$row['College_Description']] = (int) $row['VoterCount'];
            $matchedCount += (int) $row['VoterCount'];
        }
    }

    // Build final college list: every enrolled college, sorted by total students desc
    foreach ($collegeTotals as $collegeName => $total) {
        $collegeVotes[] = [
            'College'       => $collegeName,
            'Already_Voted' => $votedByCollege[$collegeName] ?? 0,
            'Total_Voters'  => $total,
        ];
    }

    // Append unmatched voters (authenticated via ARMS but not in local enrollment DB)
    $unmatched = $alreadyVoted - $matchedCount;
    if ($unmatched > 0) {
        $collegeVotes[] = [
            'College'       => 'Other / Not in Enrollment DB',
            'Already_Voted' => $unmatched,
            'Total_Voters'  => 0,
        ];
    }

    // Enrich recent voters with name + college from student table
    if (!empty($recentVoters)) {
        $rIds = array_column($recentVoters, 'Voter_ID');
        $ph   = implode(',', array_fill(0, count($rIds), '?'));
        $rStmt = $vPdo->prepare(
            "SELECT s.Student_ID,
                    MAX(s.Student_Name) AS Student_Name,
                    MAX(COALESCE(c.College_Code, '')) AS College_Code,
                    MAX(COALESCE(c.College_Description, '')) AS College
             FROM student s
             LEFT JOIN program p ON s.Program_Code = p.Program_Code
             LEFT JOIN college c ON p.College_Code = c.College_Code
             WHERE s.Student_ID IN ($ph)
             GROUP BY s.Student_ID"
        );
        $rStmt->execute(array_values($rIds));
        $profileMap = [];
        foreach ($rStmt->fetchAll() as $row) {
            $profileMap[$row['Student_ID']] = $row;
        }
        foreach ($recentVoters as &$rv) {
            $p = $profileMap[$rv['Voter_ID']] ?? [];
            $rv['Student_Name'] = $p['Student_Name'] ?? '—';
            $rv['College_Code'] = $p['College_Code'] ?? '—';
            $rv['College']      = $p['College']      ?? 'Unknown';
        }
        unset($rv);
    }

} catch (Throwable $e) {
    error_log('voting-stats voter DB error: ' . $e->getMessage());
}

// ── 3. Derived stats ─────────────────────────────────────────────────────────
$notYet = max(0, $totalVoters - $alreadyVoted);
$pct    = $totalVoters > 0 ? round(($alreadyVoted / $totalVoters) * 100, 1) : 0;

echo json_encode([
    'school_year'   => $schoolYear,
    'total_voters'  => $totalVoters,
    'already_voted' => $alreadyVoted,
    'pending'       => 0,
    'not_yet'       => $notYet,
    'percent'       => $pct,
    'colleges'      => $collegeVotes,
    'recent'        => $recentVoters,
]);
