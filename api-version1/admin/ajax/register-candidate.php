<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

$sid         = trim($_POST['student_id']          ?? '');
$posId       = (int)($_POST['position_numeric_id'] ?? 0);
$slate       = trim($_POST['candidate_slate_id']  ?? '') ?: '1';
$yr          = trim($_POST['election_year']       ?? '');
$photo       = trim($_POST['photo_b64']           ?? '');
$collegeCode = strtoupper(trim($_POST['college_code'] ?? ''));
$postedName  = trim($_POST['student_name']        ?? '');

// Auto-generate a temporary Student ID if none was provided
$tempIdGenerated = false;
if (!$sid) {
    $sid = 'TEMP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $tempIdGenerated = true;
}

if (!$posId || !$yr) {
    echo json_encode(['success' => false, 'error' => 'Position and Election Year are required.']);
    exit;
}

$res = callModel(function() use ($sid, $posId, $slate, $yr) {
    Candidate::Register_Position([
        'Student_ID'         => $sid,
        'Position_ID'        => $posId,
        'Candidate_Slate_ID' => (int)$slate,
        'Election_Year'      => $yr,
    ]);
});

if (isError($res)) {
    echo json_encode(['success' => false, 'error' => $res['Status'] ?? 'Failed to register candidate.']);
    exit;
}

// Auto-approve immediately — admin-registered candidates don't need a separate approval step
callModel(function() use ($sid, $yr) {
    Candidate::Profile_Status_Update([
        'Student_ID'         => $sid,
        'Election_Year'      => $yr,
        'Application_Status' => 'APPROVED',
    ]);
});

// Persist manually-entered name so pages that look up names from the voter DB
// can still display the correct name for temp/unknown student IDs.
if ($postedName !== '') {
    $cnFile = DATA_DIR . '/candidate_names.json';
    $cnMap  = file_exists($cnFile) ? (json_decode(file_get_contents($cnFile), true) ?: []) : [];
    $cnMap[$sid] = $postedName;
    file_put_contents($cnFile, json_encode($cnMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Any college-level position (Governor, Vice-Governor, Representative) — persist college for accordion grouping
if ($collegeCode !== '') {
    $ccFile = DATA_DIR . '/candidate_college.json';
    $ccMap  = [];
    if (file_exists($ccFile)) {
        $ccMap = json_decode(file_get_contents($ccFile), true) ?: [];
    }
    $ccMap[$sid] = $collegeCode;
    file_put_contents($ccFile, json_encode($ccMap, JSON_PRETTY_PRINT), LOCK_EX);
}

$newRecord = [
    'Student_ID'         => $sid,
    'Position_ID'        => $posId,
    'Candidate_Slate_ID' => (int)$slate,
    'Election_Year'      => $yr,
    'Application_Status' => 'APPROVED',
];

$raw = callModel(function() use ($sid, $yr) {
    Candidate::Get_All_Candidates(['Election_Year' => $yr, 'Application_Status' => 'APPROVED']);
});

$pendingList = [];
if (isset($raw['Record']) && is_array($raw['Record'])) {
    $pendingList = $raw['Record'];
} elseif (is_array($raw) && !empty($raw) && !isset($raw['Status'])) {
    $pendingList = $raw;
}

foreach ($pendingList as $c) {
    $cSid = $c['Student_ID'] ?? $c['student_id'] ?? '';
    if ($cSid === $sid) {
        $newRecord = $c;
        break;
    }
}

// Fallback: query candidate DB directly for the Candidate_ID if still missing
if (empty($newRecord['Candidate_ID'])) {
    try {
        $cDb  = \Configuration\Application::$SSG_Candidate_DBase;
        $cPdo = new PDO(
            "mysql:host={$cDb['Host']};port={$cDb['Port']};dbname={$cDb['DBName']};charset=utf8mb4",
            $cDb['Username'], $cDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $cStmt = $cPdo->prepare(
            "SELECT Candidate_ID FROM candidate_position WHERE Student_ID = ? AND Election_Year = ? ORDER BY Record_ID DESC LIMIT 1"
        );
        $cStmt->execute([$sid, $yr]);
        $cRow = $cStmt->fetch();
        if ($cRow && !empty($cRow['Candidate_ID'])) {
            $newRecord['Candidate_ID'] = $cRow['Candidate_ID'];
        }
    } catch (\Throwable $e) {}
}

// Fetch student name from voter DB; fall back to posted name for new/temp students
$studentName = $postedName ?: '—';
if (!$tempIdGenerated && $studentName === '—') {
    try {
        $vDb = \Configuration\Application::$SSG_Voter_DBase;
        $vPdo = new PDO(
            "mysql:host={$vDb['Host']};port={$vDb['Port']};dbname={$vDb['DBName']};charset=utf8mb4",
            $vDb['Username'], $vDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $nStmt = $vPdo->prepare("SELECT Student_Name FROM student WHERE Student_ID = ? LIMIT 1");
        $nStmt->execute([$sid]);
        $nRow = $nStmt->fetch();
        if ($nRow) $studentName = $nRow['Student_Name'];
    } catch (\Throwable $e) {}
} elseif (!$tempIdGenerated && $postedName === '') {
    // Known student ID but no name posted — look up from voter DB
    try {
        $vDb = \Configuration\Application::$SSG_Voter_DBase;
        $vPdo = new PDO(
            "mysql:host={$vDb['Host']};port={$vDb['Port']};dbname={$vDb['DBName']};charset=utf8mb4",
            $vDb['Username'], $vDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $nStmt = $vPdo->prepare("SELECT Student_Name FROM student WHERE Student_ID = ? LIMIT 1");
        $nStmt->execute([$sid]);
        $nRow = $nStmt->fetch();
        if ($nRow) $studentName = $nRow['Student_Name'];
    } catch (\Throwable $e) {}
}

// Now upload photo with the real Candidate_ID (fetched above)
$hasPhoto = false;
$realCandidateId = $newRecord['Candidate_ID'] ?? '';
if ($photo && $realCandidateId !== '') {
    $pRes = callModel(function() use ($realCandidateId, $photo) {
        Candidate::Upload_Photo(['Candidate_ID' => $realCandidateId, 'Photo' => $photo]);
    });
    $hasPhoto = !isError($pRes);
}

// Position name map (matches candidates.php)
$positionNameMap = [
    1=>'President', 2=>'Vice President', 3=>'Governor', 4=>'Vice Governor',
    5=>'Representative (CCS)', 6=>'Representative (CBA)', 7=>'Representative (CTED)',
    8=>'Representative (CAS)', 9=>'Representative (CCJE)', 10=>'Representative (CIT)',
    11=>'Representative (CTED-HS)', 12=>'Representative (CME)', 13=>'Representative (COE)',
    14=>'Representative (COL)', 15=>'Representative (HS)', 16=>'Representative (GRAD)',
    17=>'Representative (SOM)', 18=>'Representative (CNAHS)',
];
$positionName = $positionNameMap[$posId] ?? 'Position #' . $posId;

// Slate name lookup
$slateName = '—';
try {
    $cDb2 = \Configuration\Application::$SSG_Candidate_DBase;
    $cPdo2 = new PDO(
        "mysql:host={$cDb2['Host']};port={$cDb2['Port']};dbname={$cDb2['DBName']};charset=utf8mb4",
        $cDb2['Username'], $cDb2['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $slStmt = $cPdo2->prepare("SELECT Candidate_Slate FROM candidate_slate WHERE Candidate_Slate_ID = ? LIMIT 1");
    $slStmt->execute([(int)$slate]);
    $slRow = $slStmt->fetch();
    if ($slRow) $slateName = $slRow['Candidate_Slate'];
} catch (\Throwable $e) {}

// Rebuild record with Student_Name inserted right after Student_ID
$enriched = [];
foreach ($newRecord as $k => $v) {
    $enriched[$k] = $v;
    if (strtolower($k) === 'student_id') {
        $enriched['Student_Name'] = $studentName;
    }
}

// Add display-ready fields for the JS row renderer
$enriched['Position_Name'] = $positionName;
$enriched['Slate_Name']    = $slateName;

// Include College_Code in response for governor/vice-gov
if ($collegeCode !== '') {
    $enriched['College_Code'] = $collegeCode;
}

echo json_encode(['success' => true, 'record' => $enriched, 'has_photo' => $hasPhoto]);
