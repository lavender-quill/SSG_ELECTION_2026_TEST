<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;
$success = '';
$error   = '';

// ── Party List helpers ─────────────────────────────────────────────────────────
$partiesFile = DATA_DIR . '/parties.json';
function loadParties(string $file): array {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}
function saveParties(string $file, array $parties): void {
    file_put_contents($file, json_encode(array_values($parties), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // CSRF check for all POST actions on this page
    $submittedCsrf = trim($_POST['_csrf'] ?? '');
    if (!$submittedCsrf || !hash_equals(adminCsrfToken(), $submittedCsrf)) {
        $error = 'Invalid request. Please reload the page and try again.';
        goto render_page;
    }

    if ($_POST['action'] === 'register_with_photo') {
        $sid   = trim($_POST['student_id']         ?? '');
        $posId = (int)($_POST['position_numeric_id'] ?? 0);
        $slate = trim($_POST['candidate_slate_id'] ?? '');
        $yr    = trim($_POST['election_year']      ?? $schoolYear);
        $photo = trim($_POST['photo_b64']          ?? '');
        if (!$sid || !$posId || !$slate || !$yr) {
            $error = 'Student ID, Position ID (numeric), Slate ID, and Election Year are required.';
        } else {
            $res = callModel(function() use ($sid, $posId, $slate, $yr) {
                Candidate::Register_Position([
                    'Student_ID'         => $sid,
                    'Position_ID'        => $posId,
                    'Candidate_Slate_ID' => (int)$slate,
                    'Election_Year'      => $yr,
                ]);
            });
            if (isError($res)) {
                $error = $res['Status'] ?? 'Failed to register candidate.';
            } else {
                // Auto-approve immediately — admin-registered candidates don't need a separate approval step
                callModel(function() use ($sid, $yr) {
                    Candidate::Profile_Status_Update([
                        'Student_ID'         => $sid,
                        'Election_Year'      => $yr,
                        'Application_Status' => 'APPROVED',
                    ]);
                });
                $success = 'Candidate registered and approved.';
                if ($photo) {
                    $pRes = callModel(function() use ($sid, $photo) {
                        Candidate::Upload_Photo(['Candidate_ID' => $sid, 'Photo' => $photo]);
                    });
                    if (isError($pRes)) { $success .= ' (Photo upload failed: ' . ($pRes['Status'] ?? 'unknown') . ')'; }
                    else                { $success .= ' Photo uploaded.'; }
                }
            }
        }
    }

    if ($_POST['action'] === 'upload_photo') {
        $cid   = trim($_POST['candidate_id'] ?? '');
        $photo = trim($_POST['photo_b64']    ?? '');
        if (!$cid || !$photo) {
            $error = 'Candidate ID and photo are required.';
        } else {
            $res = callModel(function() use ($cid, $photo) {
                Candidate::Upload_Photo(['Candidate_ID' => $cid, 'Photo' => $photo]);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to upload photo.'; }
            else                { $success = 'Photo uploaded for ' . htmlspecialchars($cid) . '.'; }
        }
    }

    if ($_POST['action'] === 'update_status') {
        $sid    = trim($_POST['student_id']        ?? '');
        $yr     = trim($_POST['election_year']     ?? $schoolYear);
        $status = trim($_POST['application_status'] ?? '');
        if (!$sid || !$status) {
            $error = 'Student ID and status are required.';
        } else {
            $res = callModel(function() use ($sid, $yr, $status) {
                Candidate::Profile_Status_Update([
                    'Student_ID'         => $sid,
                    'Election_Year'      => $yr,
                    'Application_Status' => $status,
                ]);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to update status.'; }
            else                { $success = 'Candidate status updated to "' . htmlspecialchars($status) . '".'; }
        }
    }

    if ($_POST['action'] === 'assign_college') {
        $sid  = trim($_POST['student_id']  ?? '');
        $code = strtoupper(trim($_POST['college_code'] ?? ''));
        if (!$sid || !$code) {
            $error = 'Student ID and college code are required.';
        } else {
            $ccFile = DATA_DIR . '/candidate_college.json';
            $ccMap  = file_exists($ccFile) ? (json_decode(file_get_contents($ccFile), true) ?: []) : [];
            $ccMap[$sid] = $code;
            file_put_contents($ccFile, json_encode($ccMap, JSON_PRETTY_PRINT), LOCK_EX);
            $success = 'College assigned (' . htmlspecialchars($code) . ') for candidate ' . htmlspecialchars($sid) . '.';
        }
    }

    if ($_POST['action'] === 'remove_candidate') {
        $sid = trim($_POST['student_id']    ?? '');
        $yr  = trim($_POST['election_year'] ?? $schoolYear);
        if (!$sid) {
            $error = 'Student ID is required.';
        } else {
            try {
                $db  = \Configuration\Application::$SSG_Candidate_DBase;
                $pdo = new PDO(
                    "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
                    $db['Username'],
                    $db['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $stmt = $pdo->prepare("DELETE FROM candidate_position WHERE Student_ID = ? AND Election_Year = ?");
                $stmt->execute([$sid, $yr]);
                $success = 'Candidate ' . htmlspecialchars($sid) . ' permanently deleted.';
            } catch (PDOException $e) {
                error_log('remove_candidate PDOException: ' . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }

    // ── Sync parties.json → candidate_slate DB table ───────────────────────
    function syncPartyListsToDB(string $partiesFile): void {
        $parties = file_exists($partiesFile)
            ? (json_decode(file_get_contents($partiesFile), true) ?: [])
            : [];
        if (empty($parties)) return;
        try {
            $db  = \Configuration\Application::$SSG_Candidate_DBase;
            $pdo = new PDO(
                "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
                $db['Username'], $db['Password'],
                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $upsert = $pdo->prepare(
                "INSERT INTO candidate_slate (Candidate_Slate_ID, Candidate_Slate)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE Candidate_Slate = VALUES(Candidate_Slate)"
            );
            $ids = [];
            foreach ($parties as $p) {
                $id   = (int)($p['id']   ?? 0);
                $name = trim($p['name']  ?? '');
                if (!$id || !$name) continue;
                $upsert->execute([$id, $name]);
                $ids[] = $id;
            }
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM candidate_slate WHERE Candidate_Slate_ID NOT IN ({$placeholders})")
                    ->execute($ids);
            }
        } catch (\Throwable $e) {
            error_log('syncPartyListsToDB error: ' . $e->getMessage());
        }
    }

    // ── Party List actions ──────────────────────────────────────────────────
    if ($_POST['action'] === 'party_add') {
        $pname = trim($_POST['party_name'] ?? '');
        $pdesc = trim($_POST['party_desc'] ?? '');
        $ptheme= trim($_POST['party_theme'] ?? 'theme-blue');
        $ptag  = trim($_POST['party_tag']  ?? 'Party List');
        if (!$pname) {
            $error = 'Party name is required.';
        } else {
            $parties = loadParties($partiesFile);
            $maxId   = 0;
            foreach ($parties as $p) { if (($p['id'] ?? 0) > $maxId) $maxId = $p['id']; }
            $parties[] = ['id' => $maxId + 1, 'name' => $pname, 'description' => $pdesc, 'theme' => $ptheme, 'tag' => $ptag];
            saveParties($partiesFile, $parties);
            syncPartyListsToDB($partiesFile);
            $success = 'Party "' . htmlspecialchars($pname) . '" added successfully.';
        }
    }

    if ($_POST['action'] === 'party_cover_photo') {
        $pid   = (int)($_POST['party_id']        ?? 0);
        $photo = trim($_POST['cover_photo_b64']  ?? '');
        if (!$pid || !$photo) {
            $error = 'Party ID and cover photo are required.';
        } else {
            $parties = loadParties($partiesFile);
            foreach ($parties as &$p) {
                if (($p['id'] ?? 0) === $pid) {
                    $p['cover_photo'] = $photo;
                }
            }
            unset($p);
            saveParties($partiesFile, $parties);
            $success = 'Cover photo saved successfully.';
        }
    }

    if ($_POST['action'] === 'party_delete') {
        $pid = (int)($_POST['party_id'] ?? 0);
        if (!$pid) {
            $error = 'Invalid party ID.';
        } else {
            $parties = loadParties($partiesFile);
            $parties = array_filter($parties, fn($p) => ($p['id'] ?? 0) !== $pid);
            saveParties($partiesFile, $parties);
            syncPartyListsToDB($partiesFile);
            $success = 'Party removed.';
        }
    }

    if ($_POST['action'] === 'party_edit') {
        $pid   = (int)($_POST['party_id']   ?? 0);
        $pname = trim($_POST['party_name']  ?? '');
        $pdesc = trim($_POST['party_desc']  ?? '');
        $ptheme= trim($_POST['party_theme'] ?? 'theme-blue');
        $ptag  = trim($_POST['party_tag']   ?? 'Party List');
        if (!$pid || !$pname) {
            $error = 'Party ID and name are required.';
        } else {
            $parties = loadParties($partiesFile);
            foreach ($parties as &$p) {
                if (($p['id'] ?? 0) === $pid) {
                    $p['name'] = $pname; $p['description'] = $pdesc;
                    $p['theme'] = $ptheme; $p['tag'] = $ptag;
                }
            }
            unset($p);
            saveParties($partiesFile, $parties);
            syncPartyListsToDB($partiesFile);
            $success = 'Party updated.';
        }
    }
}

render_page:
// ── Load data ─────────────────────────────────────────────────────────────────
// NOTE: DB enum is PENDING / APPROVED / DENIED / DISQUALIFIED (all uppercase)

// Always load pending candidates for the Pending section
$pendingList = [];
$rawPending = callModel(function() use ($schoolYear) {
    Candidate::Get_All_Candidates(['Election_Year' => $schoolYear, 'Application_Status' => 'PENDING']);
});
if (isset($rawPending['Record']) && is_array($rawPending['Record'])) { $pendingList = $rawPending['Record']; }
elseif (is_array($rawPending) && !empty($rawPending) && !isset($rawPending['Status'])) { $pendingList = $rawPending; }

// Approved candidates for the Candidates List
$candidateList = [];
$raw = callModel(function() use ($schoolYear) {
    Candidate::Get_All_Candidates(['Election_Year' => $schoolYear, 'Application_Status' => 'APPROVED']);
});
if (isset($raw['Record']) && is_array($raw['Record'])) { $candidateList = $raw['Record']; }
elseif (is_array($raw) && !empty($raw) && !isset($raw['Status'])) { $candidateList = $raw; }

// Denied/Disapproved candidates (DB enum uses DENIED, not REJECTED)
$rejectedList = [];
$rawRejected = callModel(function() use ($schoolYear) {
    Candidate::Get_All_Candidates(['Election_Year' => $schoolYear, 'Application_Status' => 'DENIED']);
});
if (isset($rawRejected['Record']) && is_array($rawRejected['Record'])) { $rejectedList = $rawRejected['Record']; }
elseif (is_array($rawRejected) && !empty($rawRejected) && !isset($rawRejected['Status'])) { $rejectedList = $rawRejected; }

// ── Enrich all lists with Student_Name from voter DB ──────────────────────
function enrichWithNames(array &$list, array $nameMap): void {
    foreach ($list as &$c) {
        $sid  = $c['Student_ID'] ?? $c['student_id'] ?? '';
        $raw  = $nameMap[$sid] ?? '—';
        // Normalise to proper case so voter-DB (ALL CAPS) and manually-entered names look the same
        $name = ($raw === '—') ? '—' : ucwords(strtolower($raw));
        // Insert Student_Name right after Student_ID, preserving other keys
        $new = [];
        foreach ($c as $k => $v) {
            $new[$k] = $v;
            if (strtolower($k) === 'student_id') {
                $new['Student_Name'] = $name;
            }
        }
        $c = $new;
    }
    unset($c);
}

$allLists = array_merge($pendingList, $candidateList, $rejectedList);
$allSids  = array_unique(array_filter(array_map(fn($c) => $c['Student_ID'] ?? $c['student_id'] ?? '', $allLists)));
$nameMap  = [];

if (!empty($allSids)) {
    try {
        $vDb = \Configuration\Application::$SSG_Voter_DBase;
        $vPdo = new PDO(
            "mysql:host={$vDb['Host']};port={$vDb['Port']};dbname={$vDb['DBName']};charset=utf8mb4",
            $vDb['Username'], $vDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $placeholders = implode(',', array_fill(0, count($allSids), '?'));
        $stmt = $vPdo->prepare("SELECT Student_ID, Student_Name FROM student WHERE Student_ID IN ($placeholders)");
        $stmt->execute(array_values($allSids));
        foreach ($stmt->fetchAll() as $row) {
            $nameMap[$row['Student_ID']] = $row['Student_Name'];
        }
    } catch (Exception $e) {
        // Name lookup failed — tables will show '—' for names
    }
}

// Merge manually-entered names (for temp/unknown IDs) as fallback for voter-DB misses
$_cnFile = DATA_DIR . '/candidate_names.json';
if (file_exists($_cnFile)) {
    $_cnMap = json_decode(file_get_contents($_cnFile), true) ?: [];
    foreach ($_cnMap as $_cnSid => $_cnName) {
        if (!isset($nameMap[$_cnSid])) {
            $nameMap[$_cnSid] = $_cnName;
        }
    }
}

enrichWithNames($pendingList, $nameMap);
enrichWithNames($candidateList, $nameMap);
enrichWithNames($rejectedList, $nameMap);

// ── College → Representative Position_ID map ──────────────────────────────
$collegePositionMap = [
    'CAS'     => 8,
    'CBA'     => 6,
    'CCJE'    => 9,
    'CCS'     => 5,
    'CIT'     => 10,
    'CME'     => 12,
    'CNAHS'   => 18,
    'COE'     => 13,
    'COL'     => 14,
    'CTED'    => 7,
    'CTED_HS' => 11,
    'GRAD'    => 16,
    'HS'      => 15,
    'SOM'     => 17,
];

// Build deterministic Position_ID → College_Code map from the existing college→posId map
// Representatives (positions 5-18) each have a unique Position_ID per college, so no
// sidecar JSON lookup is needed for them.
$_posCollegeMap = array_flip($collegePositionMap);
// [5=>'CCS', 6=>'CBA', 7=>'CTED', 8=>'CAS', 9=>'CCJE', 10=>'CIT',
//  11=>'CTED_HS', 12=>'CME', 13=>'COE', 14=>'COL', 15=>'HS', 16=>'GRAD', 17=>'SOM', 18=>'CNAHS']

// JSON sidecar is still used for Governors/Vice Governors (pos 3/4) where the same
// Position_ID is shared across colleges.
$_ccFile = DATA_DIR . '/candidate_college.json';
$_ccMap  = file_exists($_ccFile) ? (json_decode(file_get_contents($_ccFile), true) ?: []) : [];

function injectCollegeCode(array &$list, array $ccMap, array $posCollegeMap): void {
    foreach ($list as &$c) {
        $sid   = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
        $posId = (int)($c['Position_ID'] ?? 0);
        // Deterministic for Representatives (unique posId per college);
        // fall back to JSON sidecar for Governors / Vice Governors.
        $college = $posCollegeMap[$posId] ?? $ccMap[$sid] ?? '';
        $new = [];
        foreach ($c as $k => $v) {
            $new[$k] = $v;
        }
        $new['College_Code'] = $college;
        $c = $new;
    }
    unset($c);
}

injectCollegeCode($pendingList,    $_ccMap, $_posCollegeMap);
injectCollegeCode($candidateList,  $_ccMap, $_posCollegeMap);
injectCollegeCode($rejectedList,   $_ccMap, $_posCollegeMap);

// ── Detect approved Gov/Vice-Gov candidates missing a college assignment ───
// These candidates will be INVISIBLE on every student's ballot until fixed.
$_missingCollegeAssignment = [];
foreach ($candidateList as $_mc) {
    $_mcPosId = (int)($_mc['Position_ID'] ?? 0);
    if ($_mcPosId === 3 || $_mcPosId === 4) {
        $_mcSid = trim($_mc['Student_ID'] ?? $_mc['student_id'] ?? '');
        if ($_mcSid !== '' && empty($_ccMap[$_mcSid])) {
            $_missingCollegeAssignment[] = $_mc;
        }
    }
}

$rejectedColumns = [];
if (!empty($rejectedList)) {
    $firstR = reset($rejectedList);
    foreach ($firstR as $k => $v) { if (is_scalar($v)) $rejectedColumns[] = $k; }
}

// Group approved by position
$byPosition = [];
foreach ($candidateList as $c) {
    $pos = $c['Position_ID'] ?? $c['Position'] ?? $c['Position_Name'] ?? 'Unknown';
    $byPosition[$pos][] = $c;
}
ksort($byPosition, SORT_NATURAL);

$totalApproved  = count($candidateList);
$totalPending   = count($pendingList);
$totalRejected  = count($rejectedList);
$totalPositions = count($byPosition);

// ── Group ALL candidates by college for accordion view ────────────────────
$collegeColors = [
    'CAS'     => '#f472b6',
    'CBA'     => '#fb923c',
    'CCJE'    => '#facc15',
    'CCS'     => '#818cf8',
    'CIT'     => '#38bdf8',
    'CME'     => '#34d399',
    'CNAHS'   => '#a78bfa',
    'COE'     => '#f59e0b',
    'COL'     => '#6ee7b7',
    'CTED'    => '#86efac',
    'CTED_HS' => '#fdba74',
    'GRAD'    => '#c4b5fd',
    'HS'      => '#93c5fd',
    'SOM'     => '#fda4af',
    'SSG'     => '#1a3a8f',
];
$collegeNames = [
    'CAS'     => 'College of Arts and Sciences (CAS)',
    'CBA'     => 'College of Business and Accountancy (CBA)',
    'CCJE'    => 'College of Criminal Justice Education (CCJE)',
    'CCS'     => 'College of Computer Studies (CCS)',
    'CIT'     => 'College of Industrial Technology (CIT)',
    'CME'     => 'College of Maritime Education (CME)',
    'CNAHS'   => 'College of Nursing & Allied Health Sciences (CNAHS)',
    'COE'     => 'College of Engineering (COE)',
    'COL'     => 'College of Law (COL)',
    'CTED'    => 'College of Teacher Education (CTED)',
    'CTED_HS' => 'College of Teacher Education – High School (CTED-HS)',
    'GRAD'    => 'Graduate School (GRAD)',
    'HS'      => 'High School (HS)',
    'SOM'     => 'School of Midwifery (SOM)',
    'SSG'     => 'Presidential & Vice-Presidential Candidates (SSG)',
];
$byCollege = [];
foreach (array_merge($pendingList, $candidateList, $rejectedList) as $c) {
    $col = $c['College_Code'] ?? '';
    $key = ($col === '' || $col === null) ? 'SSG' : $col;
    $byCollege[$key][] = $c;
}
ksort($byCollege);
// Move SSG (Presidential/VP) group to top — university-wide positions listed first
if (isset($byCollege['SSG']) && count($byCollege) > 1) {
    $tmp = $byCollege['SSG'];
    unset($byCollege['SSG']);
    $byCollege = array_merge(['SSG' => $tmp], $byCollege);
}

// Sort candidates within each college card: Governor (3) → Vice Governor (4) → Representatives (5+)
$_posSortRank = [1 => 0, 2 => 1, 3 => 2, 4 => 3];
foreach ($byCollege as &$_colGroup) {
    usort($_colGroup, function($a, $b) use ($_posSortRank) {
        $aPid  = (int)($a['Position_ID'] ?? 0);
        $bPid  = (int)($b['Position_ID'] ?? 0);
        $aRank = $_posSortRank[$aPid] ?? 4;
        $bRank = $_posSortRank[$bPid] ?? 4;
        return $aRank !== $bRank ? $aRank - $bRank : $aPid - $bPid;
    });
}
unset($_colGroup);

// Collect columns from ALL candidates (union), not just the first row,
// so fields that only some candidates have (e.g. Candidate_ID) still appear.
function collectColumns(array $list): array {
    $seen = [];
    $cols = [];
    foreach ($list as $c) {
        foreach ($c as $k => $v) {
            if (is_scalar($v) && !isset($seen[$k])) {
                $seen[$k] = true;
                $cols[]   = $k;
            }
        }
    }
    return $cols;
}

$columns        = collectColumns($candidateList);
$pendingColumns = collectColumns($pendingList);
$rejectedColumns = collectColumns($rejectedList);

// Shared column list — same schema for all statuses; used when a table is initially empty
$allCols = $pendingColumns ?: $columns ?: $rejectedColumns ?: ['Student_ID','Application_Status'];

// ── Collect DB load errors for display ────────────────────────────────────
$dbLoadErrors = [];
if (isError($rawPending) && strpos($rawPending['Status'] ?? '', 'Could not parse') === false) {
    $dbLoadErrors[] = 'Pending: ' . ($rawPending['Status'] ?? 'unknown error');
}
if (isError($raw) && strpos($raw['Status'] ?? '', 'Could not parse') === false) {
    $dbLoadErrors[] = 'Approved: ' . ($raw['Status'] ?? 'unknown error');
}
if (isError($rawRejected) && strpos($rawRejected['Status'] ?? '', 'Could not parse') === false) {
    $dbLoadErrors[] = 'Rejected: ' . ($rawRejected['Status'] ?? 'unknown error');
}

// ── Load parties from JSON ─────────────────────────────────────────────────
$partiesList = loadParties($partiesFile);

// ── Column label map ──────────────────────────────────────────────────────
function colLabel(string $col): string {
    static $map = [
        'Candidate_ID'       => 'Candidate ID',
        'Student_ID'         => 'Student ID',
        'Student_Name'       => 'Name',
        'Position_ID'        => 'Position',
        'Candidate_Slate_ID' => 'Slate',
        'Election_Year'      => 'Election Year',
        'Application_Status' => 'Status',
        'College_Code'       => 'College',
    ];
    return $map[$col] ?? ucwords(str_replace('_', ' ', $col));
}

// ── Position ID → name map ────────────────────────────────────────────────
$positionNameMap = [
    1  => 'President',
    2  => 'Vice President',
    3  => 'Governor',
    4  => 'Vice Governor',
    5  => 'Representative (CCS)',
    6  => 'Representative (CBA)',
    7  => 'Representative (CTED)',
    8  => 'Representative (CAS)',
    9  => 'Representative (CCJE)',
    10 => 'Representative (CIT)',
    11 => 'Representative (CTED-HS)',
    12 => 'Representative (CME)',
    13 => 'Representative (COE)',
    14 => 'Representative (COL)',
    15 => 'Representative (HS)',
    16 => 'Representative (GRAD)',
    17 => 'Representative (SOM)',
    18 => 'Representative (CNAHS)',
];

// ── Load slates from candidate DB ─────────────────────────────────────────
$slatesList = [];
$photoExistsMap = [];
try {
    $cDb = \Configuration\Application::$SSG_Candidate_DBase;
    $cPdo = new PDO(
        "mysql:host={$cDb['Host']};port={$cDb['Port']};dbname={$cDb['DBName']};charset=utf8mb4",
        $cDb['Username'], $cDb['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $slatesList = $cPdo->query("SELECT Candidate_Slate_ID, Candidate_Slate FROM candidate_slate ORDER BY Candidate_Slate_ID")->fetchAll();
    // Load which candidates already have a photo (boolean map, no raw image data)
    $photoRows = $cPdo->query("SELECT Candidate_ID FROM candidate_photo WHERE Photo IS NOT NULL AND Photo != ''")->fetchAll();
    foreach ($photoRows as $pr) {
        $photoExistsMap[$pr['Candidate_ID']] = true;
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Candidates &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .stats-grid { grid-template-columns: repeat(4,1fr); }
        /* ── Confirm Modal ── */
        .cm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
        .cm-overlay.open { display:flex; animation:cmFadeIn .18s ease; }
        @keyframes cmFadeIn { from{opacity:0} to{opacity:1} }
        .cm-card { background:#fff; border-radius:16px; overflow:hidden; max-width:400px; width:90%; box-shadow:0 24px 64px rgba(0,0,0,.22); text-align:center; animation:cmSlideUp .2s ease; }
        @keyframes cmSlideUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
        .cm-bar { height:5px; width:100%; }
        .cm-bar.bar-green { background:#16a34a; }
        .cm-bar.bar-red   { background:#dc2626; }
        .cm-bar.bar-gray  { background:#6b7280; }
        .cm-body { padding:28px 28px 24px; }
        .cm-title { font-size:17px; font-weight:900; color:#111827; margin-bottom:8px; }
        .cm-msg { font-size:13px; color:#6b7280; font-weight:500; line-height:1.65; margin-bottom:24px; }
        .cm-actions { display:flex; gap:10px; justify-content:center; }
        .cm-actions .btn { min-width:110px; font-size:13px; }
        /* ── Toast notice ── */
        .notice-toast { display:none; position:fixed; bottom:28px; right:28px; z-index:10000; padding:14px 22px; border-radius:12px; font-size:13px; font-weight:700; color:#fff; box-shadow:0 8px 28px rgba(0,0,0,.22); max-width:340px; }
        .notice-toast.show { display:flex; align-items:center; gap:10px; animation:toastIn .3s ease; }
        @keyframes toastIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .pos-label  { font-size:13px; font-weight:800; color:#1a3a8f; padding:10px 20px; background:#fafafa; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; }
        .pos-count  { font-size:12px; color:#9ca3af; font-weight:600; }
        .btn-sm-photo { background:#1a3a8f; color:#fff; padding:6px 13px; font-size:12px; border-radius:7px; border:none; cursor:pointer; font-family:'Poppins',sans-serif; font-weight:700; }
        .btn-sm-photo:hover { background:#162f78; }
        .has-photo-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#16a34a; margin-right:4px; flex-shrink:0; }
        .no-photo-dot  { display:inline-block; width:8px; height:8px; border-radius:50%; background:#d1d5db; margin-right:4px; flex-shrink:0; }
        .cand-thumb-wrap { display:flex; align-items:center; gap:9px; }
        .cand-thumb { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid #e5e7eb; cursor:pointer; flex-shrink:0; display:block; }
        .cand-thumb-ph { width:42px; height:42px; border-radius:50%; background:#e5e7eb; display:flex; align-items:center; justify-content:center; font-size:20px; color:#9ca3af; cursor:pointer; flex-shrink:0; border:2px dashed #d1d5db; }
        /* ── Photo Upload Modal ── */
        .pm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9990; align-items:center; justify-content:center; }
        .pm-overlay.open { display:flex; animation:cmFadeIn .18s ease; }
        .pm-card { background:#fff; border-radius:18px; overflow:hidden; max-width:420px; width:92%; box-shadow:0 28px 72px rgba(0,0,0,.25); animation:cmSlideUp .2s ease; }
        .pm-header { background:#1a3a8f; color:#fff; padding:18px 24px 14px; }
        .pm-header h4 { margin:0 0 2px; font-size:16px; font-weight:800; }
        .pm-header p  { margin:0; font-size:12px; opacity:.75; font-weight:500; }
        .pm-body { padding:22px 24px 20px; }
        .pm-preview-wrap { display:flex; align-items:center; justify-content:center; margin-bottom:18px; }
        .pm-preview { width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #e5e7eb; background:#f3f4f6; display:block; }
        .pm-preview-ph { width:100px; height:100px; border-radius:50%; background:#e5e7eb; display:flex; align-items:center; justify-content:center; font-size:34px; color:#9ca3af; flex-shrink:0; }
        .pm-file-label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:6px; }
        .pm-file-input { width:100%; font-size:12px; margin-bottom:14px; }
        .pm-actions { display:flex; gap:10px; justify-content:flex-end; }
        .pm-cancel { background:#f3f4f6; color:#374151; border:none; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:700; cursor:pointer; font-family:'Poppins',sans-serif; }
        .pm-submit { background:#1a3a8f; color:#fff; border:none; border-radius:8px; padding:9px 20px; font-size:13px; font-weight:700; cursor:pointer; font-family:'Poppins',sans-serif; }
        .pm-submit:disabled { opacity:.55; cursor:not-allowed; }
        /* ── Registration photo preview ── */
        .reg-photo-preview { width:64px; height:64px; border-radius:50%; object-fit:cover; border:2.5px solid #e5e7eb; background:#f3f4f6; display:none; margin-top:8px; }
        .party-admin-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-bottom:8px; }
        .party-admin-card { background:#fff; border-radius:16px; box-shadow:0 2px 16px rgba(0,0,0,.07); overflow:hidden; border:2px solid transparent; transition:border-color .2s, box-shadow .2s; }
        .party-admin-card:hover { border-color:#1a3a8f; box-shadow:0 4px 20px rgba(26,58,143,.12); }
        /* ── Party cover upload zone ── */
        .party-cover-zone { position:relative; width:100%; height:120px; cursor:pointer; overflow:hidden; background:linear-gradient(135deg,#dde4f0,#f0f4ff); flex-shrink:0; }
        .party-cover-zone img { width:100%; height:100%; object-fit:cover; display:block; }
        .party-cover-placeholder-inner { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; pointer-events:none; }
        .party-cover-placeholder-inner span { font-size:11px; color:#9ca3af; font-weight:700; letter-spacing:.5px; }
        .party-cover-overlay { position:absolute; inset:0; background:rgba(26,58,143,.6); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; opacity:0; transition:opacity .18s; pointer-events:none; }
        .party-cover-zone:hover .party-cover-overlay { opacity:1; }
        .party-cover-overlay span { font-size:11px; color:#fff; font-weight:700; letter-spacing:.5px; }
        .party-cover-uploading { position:absolute; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; }
        .cover-spinner { width:28px; height:28px; border:3px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:coverSpin .7s linear infinite; }
        @keyframes coverSpin { to { transform:rotate(360deg); } }
        .party-admin-card-header { padding:14px 18px 10px; display:flex; align-items:center; justify-content:space-between; }
        .party-admin-card-name { font-size:14px; font-weight:800; color:#1a3a8f; }
        .party-admin-card-tag  { font-size:11px; font-weight:700; padding:2px 10px; border-radius:20px; background:#dde4f0; color:#1a3a8f; }
        .party-admin-card-desc { font-size:12.5px; color:#6b7280; padding:0 18px 14px; line-height:1.6; }
        .party-admin-card-footer { display:flex; align-items:center; justify-content:space-between; padding:10px 18px; border-top:1px solid #f0f0f0; background:#fafafa; }
        /* .btn-cover-upload and .cover-upload-row removed — replaced by .party-cover-zone */
        .party-theme-dot { width:14px; height:14px; border-radius:50%; display:inline-block; }
        .dot-theme-blue   { background:#1a3a8f; }
        .dot-theme-purple { background:#7c3aed; }
        .dot-theme-navy   { background:#0d2a6e; }
        .dot-theme-green  { background:#16a34a; }
        .dot-theme-red    { background:#dc2626; }
        .dot-theme-gold   { background:#f5c400; }
        .party-card-actions { display:flex; gap:6px; }
        .btn-edit-party { background:#1a3a8f; color:#fff; padding:5px 12px; font-size:12px; border-radius:7px; border:none; cursor:pointer; font-weight:700; }
        .btn-del-party  { background:#dc2626; color:#fff; padding:5px 12px; font-size:12px; border-radius:7px; border:none; cursor:pointer; font-weight:700; }
        .party-empty-state { padding:32px; text-align:center; color:#9ca3af; font-size:13px; background:#fff; border-radius:16px; box-shadow:0 2px 16px rgba(0,0,0,.07); }
        @media(max-width:900px){
            .stats-grid { grid-template-columns:repeat(2,1fr) !important; }
            .party-admin-grid { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:600px){
            .party-admin-grid { grid-template-columns:1fr; }
            .cm-actions { flex-direction:column; align-items:stretch; }
            .cm-actions .btn { min-width:0; width:100%; }
            .notice-toast { right:12px; bottom:12px; left:12px; max-width:none; }
            .pos-label { flex-direction:column; align-items:flex-start; gap:4px; }

        }
        @media(max-width:480px){
            .stats-grid { grid-template-columns:1fr !important; }
        }
        /* ── College Accordion ── */
        .coll-card { background:#fff; border:1.5px solid #e5e7eb; border-radius:14px; overflow:hidden; transition:box-shadow .2s; margin-bottom:0; }
        .coll-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.09); }
        .coll-header { display:flex; align-items:center; gap:12px; padding:16px 20px; cursor:pointer; user-select:none; }
        .coll-header:hover { background:#f9fafb; }
        .coll-dot { width:13px; height:13px; border-radius:50%; flex-shrink:0; }
        .coll-name { font-size:14px; font-weight:700; color:#111827; flex:1; }
        .coll-badges { display:flex; gap:6px; flex-shrink:0; }
        .coll-badge-approved { font-size:11px; font-weight:700; background:#dcfce7; color:#15803d; padding:2px 9px; border-radius:99px; }
        .coll-badge-pending  { font-size:11px; font-weight:700; background:#fef3c7; color:#b45309; padding:2px 9px; border-radius:99px; }
        .coll-badge-denied   { font-size:11px; font-weight:700; background:#fee2e2; color:#dc2626; padding:2px 9px; border-radius:99px; }
        .coll-count { font-size:12px; color:#9ca3af; white-space:nowrap; flex-shrink:0; }
        .coll-chevron { color:#9ca3af; font-size:11px; transition:transform .25s; flex-shrink:0; margin-left:4px; }
        .coll-card.open .coll-chevron { transform:rotate(180deg); }
        .coll-body { border-top:1.5px solid #f0f0f0; overflow-x:auto; }
        .coll-table { width:100%; border-collapse:collapse; min-width:700px; }
        .coll-table th { padding:10px 14px; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; text-align:left; background:#fafafa; border-bottom:1px solid #f0f0f0; white-space:nowrap; }
        .coll-table td { padding:11px 14px; font-size:13px; color:#374151; border-bottom:1px solid #f9fafb; vertical-align:middle; }
        .coll-table tr:last-child td { border-bottom:none; }
        .coll-table tr:hover td { background:#f9fafb; }
        .badge-sm { display:inline-block; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700; }
        .badge-approved { background:#dcfce7; color:#15803d; }
        .badge-pending  { background:#fef3c7; color:#b45309; }
        .badge-rejected { background:#fee2e2; color:#dc2626; }
        .btn-green { background:#16a34a; color:#fff; border:none; }
        .btn-green:hover { background:#15803d; }
        .accordion-search-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
        .accordion-search { flex:1; padding:9px 14px; border:1.5px solid #e5e7eb; border-radius:9px; font-size:13px; font-family:inherit; color:#374151; outline:none; }
        .accordion-search:focus { border-color:#1a3a8f; }
        @media(max-width:700px){
            .coll-badges { display:none; }
            .coll-header { flex-wrap:wrap; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="/Presets/jrmsu-logo.png" alt="Logo"/>
            <div>
                <div class="logo-text">SSG Election</div>
                <div class="logo-sub">Admin Panel</div>
            </div>
        </div>
        <span class="sidebar-badge">Administrator</span>
        <nav class="sidebar-nav">
            <a href="/admin/dashboard.php" class="nav-item">Dashboard</a>
            <a href="/admin/candidates.php" class="nav-item active">Candidates</a>
            <a href="/admin/voters.php" class="nav-item">Voters</a>
            <a href="/admin/results.php" class="nav-item">Results</a>
            <a href="/admin/users.php" class="nav-item">Users</a>
            <a href="/admin/settings.php" class="nav-item">Settings</a>
            <a href="/admin/api-accounts.php" class="nav-item">API Accounts</a>
        </nav>
        <div class="sidebar-footer">
            <a href="#" onclick="openTeamModal();return false;" class="sidebar-powered">Powered by CCS-Creatives Society</a>
            <a href="/admin/logout.php" class="btn-logout-side">Sign Out</a>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-title">Candidates</div>
            <div class="topbar-right">
                <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
            </div>
        </div>

        <div class="content">

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1a3a8f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    </div>
                    <div class="stat-value" id="statApproved"><?= $totalApproved ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="stat-value" id="statRejected"><?= $totalRejected ?></div>
                    <div class="stat-label">Disapproved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="stat-value">A.Y. <?= htmlspecialchars($schoolYear) ?></div>
                    <div class="stat-label">Academic Year</div>
                </div>
            </div>

            <?php if (!empty($dbLoadErrors)): ?>
            <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;">
                <span style="font-size:18px;line-height:1.2;">⚠️</span>
                <div>
                    <div style="font-weight:700;color:#dc2626;font-size:13px;margin-bottom:4px;">Database load error — some tables may be empty</div>
                    <?php foreach ($dbLoadErrors as $dbe): ?>
                    <div style="font-size:12px;color:#7f1d1d;font-family:monospace;"><?= htmlspecialchars($dbe) ?></div>
                    <?php endforeach; ?>
                    <div style="font-size:12px;color:#b91c1c;margin-top:4px;">Try refreshing the page. If this persists, check the database connection in Settings.</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($_missingCollegeAssignment)): ?>
            <!-- ── Missing College Assignment Warning ── -->
            <div id="missingCollegePanel" style="background:#fffbeb;border:2px solid #f59e0b;border-radius:12px;padding:0;margin-bottom:22px;overflow:hidden;">
                <div style="background:#f59e0b;padding:12px 18px;display:flex;align-items:center;gap:10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <span style="font-weight:800;color:#fff;font-size:14px;">
                        <?= count($_missingCollegeAssignment) ?> Governor / Vice-Governor candidate<?= count($_missingCollegeAssignment) !== 1 ? 's are' : ' is' ?> missing a college assignment — invisible on the ballot
                    </span>
                </div>
                <div style="padding:14px 18px;">
                    <p style="font-size:12.5px;color:#78350f;margin:0 0 14px;">
                        These approved candidates will <strong>not appear on any student's ballot</strong> until you assign their college. Governors and Vice-Governors are only shown to students from their own college.
                    </p>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($_missingCollegeAssignment as $_mca):
                        $_mcaSid   = htmlspecialchars($_mca['Student_ID'] ?? $_mca['student_id'] ?? '');
                        $_mcaName  = htmlspecialchars($_mca['Student_Name'] ?? '—');
                        $_mcaPosId = (int)($_mca['Position_ID'] ?? 0);
                        $_mcaPos   = $_mcaPosId === 3 ? 'Governor' : 'Vice-Governor';
                    ?>
                    <form method="POST" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;background:#fff;border:1.5px solid #fcd34d;border-radius:9px;padding:10px 14px;">
                        <input type="hidden" name="_csrf"       value="<?= htmlspecialchars(adminCsrfToken()) ?>">
                        <input type="hidden" name="action"      value="assign_college">
                        <input type="hidden" name="student_id"  value="<?= $_mcaSid ?>">
                        <div style="flex:1;min-width:200px;">
                            <div style="font-weight:700;font-size:13px;color:#1a3a8f;"><?= $_mcaName ?></div>
                            <div style="font-size:11.5px;color:#6b7280;"><?= $_mcaPos ?> &middot; ID: <?= $_mcaSid ?></div>
                        </div>
                        <select name="college_code" required style="padding:7px 10px;border:1.5px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;color:#374151;background:#fff;min-width:220px;">
                            <option value="">— Select college —</option>
                            <option value="CAS">College of Arts and Sciences (CAS)</option>
                            <option value="CBA">College of Business Administration (CBA)</option>
                            <option value="CCJE">College of Criminal Justice Education (CCJE)</option>
                            <option value="CCS">College of Computer Studies (CCS)</option>
                            <option value="CIT">College of Industrial Technology (CIT)</option>
                            <option value="CME">College of Marine Engineering (CME)</option>
                            <option value="CNAHS">College of Nursing &amp; Allied Health Sciences (CNAHS)</option>
                            <option value="COE">College of Engineering (COE)</option>
                            <option value="COL">College of Law (COL)</option>
                            <option value="CTED">College of Teacher Education (CTED)</option>
                            <option value="GRAD">Graduate School (GRAD)</option>
                            <option value="HS">High School (HS)</option>
                            <option value="SOM">School of Medicine (SOM)</option>
                        </select>
                        <button type="submit" style="padding:7px 18px;background:#1a3a8f;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;">Assign &amp; Save</button>
                    </form>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Register Candidate (merged form) ── -->
            <div class="section-title">Register New Candidate</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3>Register Candidate</h3>
                    <span>Registers a student for a position &mdash; approved immediately</span>
                </div>
                <div class="card-body">
                    <form id="registerForm" onsubmit="submitRegisterForm(event)">
                        <input type="hidden" name="photo_b64"           id="regPhotob64"/>
                        <input type="hidden" name="student_id"          id="regStudentId"/>
                        <input type="hidden" name="position_numeric_id" id="regPositionNumericId"/>

                        <?php
                        $collegeNames = [
                            'CAS'     => 'College of Arts and Sciences (CAS)',
                            'CBA'     => 'College of Business and Accountancy (CBA)',
                            'CCJE'    => 'College of Criminal Justice Education (CCJE)',
                            'CCS'     => 'College of Computer Studies (CCS)',
                            'CIT'     => 'College of Industrial Technology (CIT)',
                            'CME'     => 'College of Maritime Education (CME)',
                            'CNAHS'   => 'College of Nursing & Allied Health Sciences (CNAHS)',
                            'COE'     => 'College of Engineering (COE)',
                            'COL'     => 'College of Law (COL)',
                            'CTED'    => 'College of Teacher Education (CTED)',
                            'CTED_HS' => 'College of Teacher Education – High School (CTED-HS)',
                            'GRAD'    => 'Graduate School (GRAD)',
                            'HS'      => 'High School (HS)',
                            'SOM'     => 'School of Midwifery (SOM)',
                        ];
                        ?>

                        <!-- Row 1: Student search — full width -->
                        <div class="form-group" style="position:relative;margin-bottom:6px;">
                            <label>Student Name <span style="color:#ef4444">*</span></label>
                            <input type="text" id="regStudentNameInput"
                                placeholder="Type name or student ID to search…"
                                autocomplete="off" oninput="searchVoterByName(this.value)"/>
                            <div id="voterDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid #e5e7eb;border-radius:0 0 8px 8px;z-index:999;max-height:200px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.1);"></div>
                            <div id="regNameLabel" style="display:none;margin-top:6px;padding:7px 12px;background:#f0fdf4;border-radius:7px;border:1.5px solid #bbf7d0;font-size:13px;font-weight:700;color:#15803d;"></div>
                            <div id="dupWarning" style="display:none;margin-top:6px;padding:9px 13px;background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;font-size:12.5px;color:#991b1b;font-weight:700;"></div>
                            <div style="margin-top:8px;">
                                <button type="button" id="manualEntryToggle" onclick="toggleManualEntry()"
                                    style="background:none;border:none;color:#1a3a8f;font-size:12px;font-weight:700;cursor:pointer;padding:0;text-decoration:underline;">
                                    &#9998; Student not in list? Enter manually
                                </button>
                            </div>
                        </div>

                        <!-- Manual Entry Fields (hidden by default) -->
                        <div id="manualEntryFields" style="display:none;margin-bottom:16px;">
                            <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;padding:12px 16px;margin-bottom:10px;font-size:12.5px;color:#92400e;">
                                &#9888; Manual mode — enter the student details below. Student ID is optional; a temporary one will be assigned if left blank.
                            </div>
                            <div class="form-row-2" style="align-items:start;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Student ID <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                                    <input type="text" id="manualStudentId" placeholder="Leave blank to auto-assign"
                                        autocomplete="off" oninput="onManualIdInput(this.value)"
                                        style="font-family:monospace;border-color:#fcd34d;"/>
                                    <div class="hint" style="color:#92400e;">A temporary ID will be assigned if blank.</div>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Full Name <span style="color:#ef4444">*</span></label>
                                    <input type="text" id="manualStudentName" placeholder="Last, First Middle"
                                        autocomplete="off" oninput="onManualNameInput(this.value)"
                                        style="border-color:#fcd34d;"/>
                                    <div class="hint">&nbsp;</div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Position + Party List — side by side -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Position <span style="color:#ef4444">*</span></label>
                                <select id="regPositionType" onchange="onPositionTypeChange(this.value)">
                                    <option value="">— Choose a position —</option>
                                    <option value="president">President</option>
                                    <option value="vicepresident">Vice-President</option>
                                    <option value="governor">Governor</option>
                                    <option value="vicegovernor">Vice Governor</option>
                                    <option value="representative">Representative</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Party List <span style="color:#ef4444">*</span></label>
                                <select id="regPartyList" name="candidate_slate_id" required>
                                    <option value="">— Select Party List —</option>
                                    <?php if (!empty($partiesList)): ?>
                                        <?php foreach ($partiesList as $pl): ?>
                                        <option value="<?= (int)($pl['id'] ?? 0) ?>">
                                            <?= htmlspecialchars($pl['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="1">Independent</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Row 3: College — full width, shown only for Governor / Vice Governor / Representative -->
                        <div id="regCollegeRow" style="display:none;margin-bottom:16px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>College <span style="color:#ef4444">*</span></label>
                                <select id="regCollegeSelect" name="college_code" onchange="onCollegeChange(this.value)">
                                    <option value="">— Choose College —</option>
                                    <?php foreach ($collegeNames as $code => $label): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Row 4: Election Year + Photo — side by side -->
                        <div class="form-row-2" style="align-items:start;">
                            <div class="form-group">
                                <label>Election Year <span style="color:#ef4444">*</span></label>
                                <input type="text" name="election_year" id="regElectionYear"
                                    value="<?= htmlspecialchars($schoolYear) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label>Candidate Photo <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                                <input type="file" id="regPhotoFile" accept="image/*" onchange="encodeRegPhoto(this)"/>
                                <img id="regPhotoPreview" class="reg-photo-preview" alt="Photo preview"/>
                                <div class="hint">Uploaded automatically with registration.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="regSubmitBtn"
                            style="width:100%;margin-top:4px;">
                            Register Candidate
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Candidates by College (Accordion) ── -->
            <div class="section-title" style="margin-top:32px;">Candidates by College</div>
            <!-- ── Search bar ── -->
            <div class="accordion-search-row">
                <input id="accordionSearch" class="accordion-search" oninput="filterAccordion()"
                       placeholder="&#128269; Search by name, ID, position, or slate..."/>
                <span style="font-size:12px;color:#9ca3af;white-space:nowrap;" id="accordionTotal">
                    <?= $totalApproved + $totalPending + $totalRejected ?> total candidates
                </span>
            </div>

            <!-- ── College Accordion ── -->
            <?php if (empty($byCollege)): ?>
            <div style="background:#f9fafb;border-radius:12px;padding:40px;text-align:center;color:#6b7280;font-size:13px;margin-bottom:32px;">
                No candidates registered for A.Y. <?= htmlspecialchars($schoolYear) ?> yet. Use the Register form above to add one.
            </div>
            <?php else: ?>
            <div id="collegeAccordion" style="display:flex;flex-direction:column;gap:10px;margin-bottom:32px;">
            <?php foreach ($byCollege as $collCode => $collCandidates):
                $collColor    = $collegeColors[$collCode] ?? '#94a3b8';
                $collFullName = $collegeNames[$collCode]  ?? ($collCode ?: 'No College Assigned');
                $collTotal    = count($collCandidates);
                $collApproved = 0; $collDenied = 0;
                foreach ($collCandidates as $cc) {
                    $s = $cc['Application_Status'] ?? '';
                    if ($s === 'APPROVED') $collApproved++;
                    else $collDenied++;
                }
                $safeCode = htmlspecialchars($collCode, ENT_QUOTES);
            ?>
            <div class="coll-card" id="coll-<?= $safeCode ?>">
                <div class="coll-header" onclick="toggleCollege('<?= $safeCode ?>')">
                    <span class="coll-dot" style="background:<?= $collColor ?>"></span>
                    <span class="coll-name"><?= htmlspecialchars($collFullName) ?></span>
                    <span class="coll-badges">
                        <?php if ($collApproved > 0): ?><span class="coll-badge-approved"><?= $collApproved ?> approved</span><?php endif; ?>
                        <?php if ($collDenied   > 0): ?><span class="coll-badge-denied"><?= $collDenied ?> denied</span><?php endif; ?>
                    </span>
                    <span class="coll-count"><?= $collTotal ?> candidate<?= $collTotal !== 1 ? 's' : '' ?></span>
                    <span class="coll-chevron" id="chevron-<?= $safeCode ?>">&#9660;</span>
                </div>
                <div class="coll-body" id="body-<?= $safeCode ?>" style="display:none;">
                    <table class="coll-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Slate</th>
                                <th>Status</th>
                                <th>Photo</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $rn = 0; foreach ($collCandidates as $c): $rn++;
                            $cSid         = $c['Student_ID']         ?? $c['student_id']         ?? '';
                            $cStatus      = $c['Application_Status'] ?? 'PENDING';
                            $cPosId       = (int)($c['Position_ID']  ?? 0);
                            $cPosName     = $positionNameMap[$cPosId] ?? ($cPosId ? "Position #$cPosId" : '—');
                            $cSlateId     = (int)($c['Candidate_Slate_ID'] ?? 0);
                            $cSlateName   = '—';
                            foreach ($slatesList as $sl) {
                                if ((int)($sl['Candidate_Slate_ID'] ?? 0) === $cSlateId) {
                                    $cSlateName = $sl['Candidate_Slate'] ?? '—';
                                    break;
                                }
                            }
                            $cName        = $c['Student_Name'] ?? '—';
                            $cCandidateId = $c['Candidate_ID'] ?? $c['candidate_id'] ?? '';
                            $cHasPhoto    = !empty($cCandidateId) && isset($photoExistsMap[$cCandidateId]);
                            $cYr          = $c['Election_Year'] ?? $schoolYear;
                            $badgeCls     = ($cStatus === 'APPROVED') ? 'badge-approved' : 'badge-rejected';
                        ?>
                        <tr data-sid="<?= htmlspecialchars($cSid, ENT_QUOTES) ?>"
                            data-row="<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>">
                            <td><?= $rn ?></td>
                            <td><span style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($cSid) ?></span></td>
                            <td><?= htmlspecialchars($cName) ?></td>
                            <td><?= htmlspecialchars($cPosName) ?></td>
                            <td><?= htmlspecialchars($cSlateName) ?></td>
                            <td><span class="badge-sm status-badge <?= $badgeCls ?>"><?= htmlspecialchars($cStatus) ?></span></td>
                            <td>
                                <div class="cand-thumb-wrap">
                                <?php if ($cHasPhoto): ?>
                                    <img class="cand-thumb"
                                         id="photoThumb_<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>"
                                         src="/ajax/candidate-photo.php?id=<?= urlencode($cCandidateId) ?>&t=<?= time() ?>"
                                         alt="<?= htmlspecialchars($cName,ENT_QUOTES) ?>"
                                         onclick="openPhotoModal('<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>','<?= htmlspecialchars($cName,ENT_QUOTES) ?>',true)"
                                         title="Click to replace photo">
                                <?php else: ?>
                                    <div class="cand-thumb-ph"
                                         id="photoThumb_<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>"
                                         onclick="openPhotoModal('<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>','<?= htmlspecialchars($cName,ENT_QUOTES) ?>',false)"
                                         title="Click to upload photo">📷</div>
                                <?php endif; ?>
                                    <button type="button" class="btn-sm-photo"
                                        onclick="openPhotoModal('<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>','<?= htmlspecialchars($cName,ENT_QUOTES) ?>',<?= $cHasPhoto?'true':'false' ?>)"
                                        id="photoBtn_<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>">
                                        <span class="<?= $cHasPhoto?'has-photo-dot':'no-photo-dot' ?>"
                                              id="photoDot_<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>"></span><?= $cHasPhoto ? 'Replace' : 'Upload' ?>
                                    </button>
                                    <button type="button" class="btn-sm-photo" style="background:#9333ea;border-color:#9333ea;color:#fff;"
                                        onclick="openGalleryModal('<?= htmlspecialchars($cCandidateId,ENT_QUOTES) ?>','<?= htmlspecialchars($cName,ENT_QUOTES) ?>')"
                                        title="Upload multiple images for gallery">
                                        🖼️ Gallery
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="actions row-actions" id="actions-<?= htmlspecialchars($cSid,ENT_QUOTES) ?>">
                                <?php
                                    $isTempId = (strncmp($cSid, 'TEMP-', 5) === 0 || $cSid === '');
                                    $editIdBtn = '<button type="button" class="btn" '
                                        . 'style="background:#0ea5e9;color:#fff;border-color:#0ea5e9;" '
                                        . 'onclick="openEditIdModal('
                                        . '\'' . htmlspecialchars($cSid, ENT_QUOTES) . '\','
                                        . '\'' . htmlspecialchars($cName, ENT_QUOTES) . '\','
                                        . '\'' . htmlspecialchars($cYr, ENT_QUOTES) . '\''
                                        . ')" title="Assign real Student ID">&#9998; Set ID</button>';
                                ?>
                                <?php if ($cStatus === 'APPROVED'): ?>
                                    <?php if ($isTempId): echo $editIdBtn; endif; ?>
                                    <button type="button" class="btn btn-red"
                                        onclick="removeCandidate('<?= htmlspecialchars($cSid,ENT_QUOTES) ?>','<?= htmlspecialchars($cYr,ENT_QUOTES) ?>','approved')">&#128465; Remove</button>
                                <?php else: ?>
                                    <?php if ($isTempId): echo $editIdBtn; endif; ?>
                                    <button type="button" class="btn btn-green"
                                        onclick="setStatus('<?= htmlspecialchars($cSid,ENT_QUOTES) ?>','<?= htmlspecialchars($cYr,ENT_QUOTES) ?>','APPROVED')">&#10003; Approve</button>
                                    <button type="button" class="btn btn-red"
                                        onclick="removeCandidate('<?= htmlspecialchars($cSid,ENT_QUOTES) ?>','<?= htmlspecialchars($cYr,ENT_QUOTES) ?>','denied')">&#128465; Remove</button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- Party List Management                                         -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="section-title" style="margin-top:36px;"> Party List &mdash; Public Landing Page</div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header-bar">
                    <h3> Manage Party Lists</h3>
                    <span><?= count($partiesList) ?> part<?= count($partiesList)!==1?'ies':'y' ?> registered &bull; Shown on index.php candidate cards</span>
                </div>
                <div class="card-body">
                    <form method="POST" style="margin-bottom:24px;">
                        <input type="hidden" name="action" value="party_add"/>
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:12px;">Add New Party</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Party Name <span style="color:#ef4444">*</span></label>
                                <input type="text" name="party_name" placeholder="e.g. The Lakas Agila Coalition" required/>
                            </div>
                            <div class="form-group">
                                <label>Tag / Type</label>
                                <select name="party_tag">
                                    <option value="Party List">Party List</option>
                                    <option value="Independent">Independent</option>
                                    <option value="Coalition">Coalition</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Theme Color</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span id="addColorDot" style="width:16px;height:16px;border-radius:50%;background:#1a3a8f;display:inline-block;flex-shrink:0;border:1px solid #ddd;"></span>
                                    <select name="party_theme" onchange="updateColorDot(this,'addColorDot')" style="flex:1;">
                                        <option value="theme-blue">Blue</option>
                                        <option value="theme-purple">Purple</option>
                                        <option value="theme-navy">Navy</option>
                                        <option value="theme-green">Green</option>
                                        <option value="theme-red">Red</option>
                                        <option value="theme-gold">Gold</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Description / Tagline</label>
                            <textarea name="party_desc" rows="2" placeholder="Short party description shown on candidate cards..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"> Add Party</button>
                    </form>

                    <!-- Party Cards Grid -->
                    <?php if (empty($partiesList)): ?>
                    <div class="party-empty-state"> No parties added yet. Use the form above to add your first party.</div>
                    <?php else: ?>
                    <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:12px;">Current Parties</div>
                    <div class="party-admin-grid">
                        <?php foreach ($partiesList as $party): $pid = (int)$party['id']; $hasCover = !empty($party['cover_photo']); ?>
                        <div class="party-admin-card">
                            <!-- Cover upload zone — click anywhere on the cover to upload/replace -->
                            <div class="party-cover-zone" onclick="document.getElementById('coverInput_<?= $pid ?>').click()" title="<?= $hasCover ? 'Click to replace cover photo' : 'Click to upload cover photo' ?>">
                                <?php if ($hasCover): ?>
                                <img src="data:image/jpeg;base64,<?= htmlspecialchars($party['cover_photo']) ?>" id="coverImg_<?= $pid ?>" alt="Cover"/>
                                <?php else: ?>
                                <div class="party-cover-placeholder-inner" id="coverPlaceholder_<?= $pid ?>">
                                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="#b0bac8" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.576 2.25 8.382 2.25 9.348v9.404c0 1.035.84 1.875 1.875 1.875h15.75c1.035 0 1.875-.84 1.875-1.875V9.348c0-.966-.75-1.772-1.802-1.943a48.111 48.111 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                    <span>NO COVER PHOTO</span>
                                </div>
                                <?php endif; ?>
                                <div class="party-cover-overlay" id="coverOverlay_<?= $pid ?>">
                                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.576 2.25 8.382 2.25 9.348v9.404c0 1.035.84 1.875 1.875 1.875h15.75c1.035 0 1.875-.84 1.875-1.875V9.348c0-.966-.75-1.772-1.802-1.943a48.111 48.111 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                    <span><?= $hasCover ? 'CHANGE PHOTO' : 'UPLOAD PHOTO' ?></span>
                                </div>
                                <div class="party-cover-uploading" id="coverUploading_<?= $pid ?>">
                                    <div class="cover-spinner"></div>
                                </div>
                                <input type="file" id="coverInput_<?= $pid ?>" accept="image/*" style="display:none" onchange="uploadCoverPhoto(this,<?= $pid ?>)"/>
                            </div>
                            <div class="party-admin-card-header">
                                <span class="party-admin-card-name"><?= htmlspecialchars($party['name']) ?></span>
                                <span class="party-admin-card-tag"><?= htmlspecialchars($party['tag'] ?? 'Party List') ?></span>
                            </div>
                            <div class="party-admin-card-desc"><?= htmlspecialchars($party['description'] ?? '') ?></div>
                            <div class="party-admin-card-footer">
                                <span class="party-theme-dot dot-<?= htmlspecialchars($party['theme'] ?? 'theme-blue') ?>" title="<?= htmlspecialchars($party['theme'] ?? '') ?>"></span>
                                <div class="party-card-actions">
                                    <button class="btn-edit-party" onclick="openEditParty(<?= $pid ?>, <?= htmlspecialchars(json_encode($party['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($party['description'] ?? ''), ENT_QUOTES) ?>, '<?= htmlspecialchars($party['theme'] ?? 'theme-blue', ENT_QUOTES) ?>', '<?= htmlspecialchars($party['tag'] ?? 'Party List', ENT_QUOTES) ?>')">Edit</button>
                                    <button class="btn-del-party" onclick="deleteParty(<?= $pid ?>, <?= htmlspecialchars(json_encode($party['name']), ENT_QUOTES) ?>)">&#10005; Remove</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- By Position -->
            <?php if (!empty($byPosition)): ?>
            <div class="section-title">By Position</div>
            <?php foreach ($byPosition as $pos => $cands): ?>
            <div class="card" style="margin-bottom:16px;">
                <div class="pos-label">
                    <span><?= htmlspecialchars($positionNameMap[$pos] ?? 'Position ' . $pos) ?></span>
                    <span class="pos-count"><?= count($cands) ?> candidate<?= count($cands)!==1?'s':'' ?></span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><?php foreach ($columns as $col): ?><th><?= htmlspecialchars(colLabel($col)) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($cands as $c): ?>
                        <tr>
                            <?php foreach ($columns as $col): $val = (isset($c[$col]) && $c[$col] !== '') ? $c[$col] : '—'; $lower = strtolower($col); ?>
                            <td>
                                <?php if (stripos($lower,'status')!==false):
                                    $sc = strtolower($val ?? '');
                                    $cls = stripos($sc,'approv')!==false ? 'badge-approved' : (stripos($sc,'pend')!==false ? 'badge-pending' : (stripos($sc,'reject')!==false ? 'badge-rejected' : 'badge-other'));
                                ?><span class="badge-sm <?= $cls ?>"><?= htmlspecialchars($val) ?></span>
                                <?php elseif (stripos($lower,'_id')!==false): ?><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($val) ?></span>
                                <?php else: ?><?= htmlspecialchars(is_scalar($val) ? $val : '—') ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>

<!-- Photo Upload Modal -->
<div class="pm-overlay" id="photoModal" onclick="if(event.target===this)closePhotoModal()">
    <div class="pm-card">
        <div class="pm-header">
            <h4>&#128247; Upload Candidate Photo</h4>
            <p id="pmCandidateName"></p>
        </div>
        <div class="pm-body">
            <div class="pm-preview-wrap">
                <img id="pmPreviewImg" class="pm-preview" alt="" style="display:none;"/>
                <div class="pm-preview-ph" id="pmPreviewPh">&#128100;</div>
            </div>
            <label class="pm-file-label" for="pmFileInput">Select Photo (JPG / PNG / WEBP)</label>
            <input type="file" class="pm-file-input" id="pmFileInput" accept="image/*" onchange="onPmFileChange(this)"/>
            <input type="hidden" id="pmCandidateId"/>
            <input type="hidden" id="pmPhotob64"/>
            <div class="pm-actions">
                <button type="button" class="pm-cancel" onclick="closePhotoModal()">Cancel</button>
                <button type="button" class="pm-submit" id="pmSubmitBtn" onclick="submitPhotoUpload()" disabled>Upload Photo</button>
            </div>
        </div>
    </div>
</div>

<!-- Status update hidden form -->
<form method="POST" id="statusForm" style="display:none;">
    <input type="hidden" name="action" value="update_status"/>
    <input type="hidden" name="student_id" id="sf_sid"/>
    <input type="hidden" name="election_year" id="sf_year"/>
    <input type="hidden" name="application_status" id="sf_status"/>
</form>

<!-- Gallery Upload Modal -->
<div class="pm-overlay" id="galleryModal" onclick="if(event.target===this)closeGalleryModal()">
    <div class="pm-card">
        <div class="pm-header">
            <h4>🖼️ Upload Candidate Gallery</h4>
            <p id="gmCandidateName"></p>
            <p style="font-size:12px;color:#9ca3af;margin:0;">Select multiple images (JPG/PNG/WEBP, max 5 MB each)</p>
        </div>
        <div class="pm-body">
            <div class="pm-preview-wrap" id="gmPreviewWrap" style="display:none;max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#f9fafb;">
                <div id="gmPreviewList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(60px,1fr));gap:8px;"></div>
            </div>
            <label class="pm-file-label" for="gmFileInput">Select Multiple Photos</label>
            <input type="file" class="pm-file-input" id="gmFileInput" accept="image/*" multiple onchange="onGmFileChange(this)"/>
            <input type="hidden" id="gmCandidateId"/>
            <p id="gmStatusText" style="font-size:12px;color:#6b7280;margin:8px 0 0;"></p>
            <div class="pm-actions">
                <button type="button" class="pm-cancel" onclick="closeGalleryModal()">Cancel</button>
                <button type="button" class="pm-submit" id="gmSubmitBtn" onclick="submitGalleryUpload()" disabled>Upload Gallery</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Student ID modal -->
<div class="modal-backdrop" id="editIdModal">
    <div class="modal" style="max-width:480px;">
        <h4>&#9998; Assign Student ID</h4>
        <p id="editIdModalDesc" style="font-size:13px;color:#6b7280;margin:4px 0 18px;"></p>
        <div class="form-group" style="margin-bottom:6px;">
            <label>Current ID</label>
            <input type="text" id="editIdOldVal" readonly
                style="width:100%;padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:monospace;font-size:13px;color:#9ca3af;background:#f9fafb;box-sizing:border-box;"/>
        </div>
        <div class="form-group" style="margin-bottom:18px;">
            <label>New Student ID <span style="color:#ef4444">*</span></label>
            <input type="text" id="editIdNewVal" placeholder="e.g. 25-A-00123" autocomplete="off"
                style="width:100%;padding:9px 12px;border:1.5px solid #0ea5e9;border-radius:8px;font-family:monospace;font-size:14px;font-weight:700;color:#1a1a1a;background:#fff;box-sizing:border-box;"
                onkeydown="if(event.key==='Enter'){submitEditId();}"/>
            <div class="hint">You can also type a new TEMP ID here if the real ID is still unavailable.</div>
        </div>
        <div id="editIdError" style="display:none;color:#dc2626;font-size:12.5px;font-weight:700;margin-bottom:10px;padding:8px 12px;background:#fef2f2;border-radius:7px;border:1px solid #fca5a5;"></div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeEditIdModal()">Cancel</button>
            <button type="button" id="editIdSaveBtn" class="btn btn-primary" onclick="submitEditId()"
                style="background:#0ea5e9;border-color:#0ea5e9;">Save ID</button>
        </div>
    </div>
</div>

<!-- Party delete hidden form -->
<form method="POST" id="partyDeleteForm" style="display:none;">
    <input type="hidden" name="action" value="party_delete"/>
    <input type="hidden" name="party_id" id="pd_id"/>
</form>

<!-- Party edit modal -->
<div class="modal-backdrop" id="editPartyModal">
    <div class="modal" style="max-width:500px;">
        <h4>Edit Party</h4>
        <form method="POST">
            <input type="hidden" name="action" value="party_edit"/>
            <input type="hidden" name="party_id" id="ep_id"/>
            <div class="form-group" style="margin-bottom:12px;">
                <label>Party Name <span style="color:#ef4444">*</span></label>
                <input type="text" name="party_name" id="ep_name" required/>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="form-group">
                    <label>Tag / Type</label>
                    <select name="party_tag" id="ep_tag">
                        <option value="Party List">Party List</option>
                        <option value="Independent">Independent</option>
                        <option value="Coalition">Coalition</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Theme Color</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span id="editColorDot" style="width:16px;height:16px;border-radius:50%;background:#1a3a8f;display:inline-block;flex-shrink:0;border:1px solid #ddd;"></span>
                        <select name="party_theme" id="ep_theme" onchange="updateColorDot(this,'editColorDot')" style="flex:1;">
                            <option value="theme-blue">Blue</option>
                            <option value="theme-purple">Purple</option>
                            <option value="theme-navy">Navy</option>
                            <option value="theme-green">Green</option>
                            <option value="theme-red">Red</option>
                            <option value="theme-gold">Gold</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label>Description / Tagline</label>
                <textarea name="party_desc" id="ep_desc" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditParty()">Cancel</button>
                <button type="submit" class="btn btn-primary"> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
var _adminCsrf = '<?= htmlspecialchars(adminCsrfToken(), ENT_QUOTES) ?>';

// ── Live counter helpers ───────────────────────────────────────────────────
function getApprovedCount() {
    return parseInt(document.getElementById('statApproved').textContent) || 0;
}
function getRejectedCount() {
    return parseInt(document.getElementById('statRejected').textContent) || 0;
}
function setApprovedCount(n) {
    document.getElementById('statApproved').textContent = n;
    var hdr = document.getElementById('approvedHeaderCount');
    if (hdr) hdr.textContent = n + ' approved';
    var fc = document.getElementById('filterCount');
    if (fc) fc.textContent = n + ' candidate' + (n !== 1 ? 's' : '');
}
function setRejectedCount(n) {
    document.getElementById('statRejected').textContent = n;
    var hdr = document.getElementById('rejectedHeaderCount');
    if (hdr) hdr.textContent = n + ' disapproved';
}

// ── Add row to disapproved table ───────────────────────────────────────────
function addDisapprovedRow(rowData) {
    var tbody = document.getElementById('rejectedTableBody');
    if (!tbody) return;

    var wrap = document.getElementById('rejectedTableWrap');
    var es   = document.getElementById('rejectedEmptyState');
    if (es)   es.style.display   = 'none';
    if (wrap) wrap.style.display = '';

    var sid = rowData['Student_ID'] || rowData['student_id'] || '';
    var yr  = rowData['Election_Year'] || rowData['election_year'] || '';
    var keys = Object.keys(rowData).filter(function(k) { var v = rowData[k]; return v === null || v === undefined || typeof v !== 'object'; });
    var rowCount = tbody.querySelectorAll('tr').length + 1;
    var tr = document.createElement('tr');
    tr.setAttribute('data-sid', sid);
    tr.setAttribute('data-row', JSON.stringify(rowData));
    var html = '<td>' + rowCount + '</td>';
    keys.forEach(function(k) {
        var val = rowData[k] !== null && rowData[k] !== undefined ? String(rowData[k]) : '—';
        var lower = k.toLowerCase();
        if (lower.indexOf('status') !== -1) {
            var cls = val.toLowerCase().indexOf('approv') !== -1 ? 'badge-approved' :
                      val.toLowerCase().indexOf('pend')   !== -1 ? 'badge-pending'  :
                      val.toLowerCase().indexOf('reject') !== -1 ? 'badge-rejected' : 'badge-other';
            html += '<td><span class="badge-sm ' + cls + '">' + escHtml(val) + '</span></td>';
        } else if (lower.indexOf('_id') !== -1) {
            html += '<td><span style="font-family:monospace;font-size:12px">' + escHtml(val) + '</span></td>';
        } else {
            html += '<td>' + escHtml(val) + '</td>';
        }
    });
    html += '<td><div class="actions">'
          + '<button type="button" class="btn btn-green" onclick="setStatus(\'' + escJs(sid) + '\',\'' + escJs(yr) + '\',\'APPROVED\')">&#10003; Approve</button>'
          + '<button type="button" class="btn btn-red" onclick="removeCandidate(\'' + escJs(sid) + '\',\'' + escJs(yr) + '\')">&#128465; Remove</button>'
          + '</div></td>';
    tr.innerHTML = html;
    tr.style.opacity = '0';
    tbody.appendChild(tr);
    requestAnimationFrame(function() { tr.style.transition = 'opacity .35s'; tr.style.opacity = '1'; });
}

// ── Remove row from disapproved table ─────────────────────────────────────
function removeDisapprovedRow(sid) {
    var body = document.getElementById('rejectedTableBody');
    if (!body) return;
    var row = body.querySelector('tr[data-sid="' + sid + '"]');
    if (!row) return;
    row.style.transition = 'opacity .3s';
    row.style.opacity = '0';
    setTimeout(function() {
        row.remove();
        var rows = body.querySelectorAll('tr');
        rows.forEach(function(r, i) { var td = r.querySelector('td'); if (td) td.textContent = i + 1; });
        if (rows.length === 0) {
            var wrap = document.getElementById('rejectedTableWrap');
            var es   = document.getElementById('rejectedEmptyState');
            if (wrap) wrap.style.display = 'none';
            if (es)   es.style.display   = '';
        }
    }, 320);
}

// ── Remove candidate permanently ───────────────────────────────────────────
// ── Edit / Assign Student ID ───────────────────────────────────────────────
var _editId_oldSid = '';
var _editId_year   = '';

function openEditIdModal(sid, name, year) {
    _editId_oldSid = sid;
    _editId_year   = year;
    document.getElementById('editIdOldVal').value   = sid || '(none)';
    document.getElementById('editIdNewVal').value   = '';
    document.getElementById('editIdModalDesc').textContent = 'Candidate: ' + name;
    document.getElementById('editIdError').style.display  = 'none';
    document.getElementById('editIdError').textContent    = '';
    document.getElementById('editIdModal').classList.add('open');
    setTimeout(function() { document.getElementById('editIdNewVal').focus(); }, 120);
}

function closeEditIdModal() {
    document.getElementById('editIdModal').classList.remove('open');
    _editId_oldSid = '';
    _editId_year   = '';
}

document.getElementById('editIdModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditIdModal();
});

function submitEditId() {
    var newSid = document.getElementById('editIdNewVal').value.trim();
    var errEl  = document.getElementById('editIdError');
    errEl.style.display = 'none';

    if (!newSid) {
        errEl.textContent    = 'Please enter a Student ID.';
        errEl.style.display  = 'block';
        document.getElementById('editIdNewVal').focus();
        return;
    }

    var btn = document.getElementById('editIdSaveBtn');
    btn.disabled    = true;
    btn.textContent = 'Saving...';

    var fd = new FormData();
    fd.append('old_student_id', _editId_oldSid);
    fd.append('new_student_id', newSid);
    fd.append('election_year',  _editId_year);
    fd.append('_csrf',          _adminCsrf);

    fetch('/admin/ajax/update-candidate-id.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled    = false;
            btn.textContent = 'Save ID';
            if (!data.success) {
                errEl.textContent   = data.error || 'Update failed.';
                errEl.style.display = 'block';
                return;
            }
            // Update every row in the DOM that has the old SID
            document.querySelectorAll('tr[data-sid="' + _editId_oldSid + '"]').forEach(function(row) {
                row.setAttribute('data-sid', newSid);
                // Update monospace Student ID cell (2nd td)
                var idCell = row.querySelector('td:nth-child(2) span');
                if (idCell) idCell.textContent = newSid;
                // If voter DB returned a real name, update name cell too
                if (data.student_name) {
                    var nameCell = row.querySelector('td:nth-child(3)');
                    if (nameCell) nameCell.textContent = data.student_name;
                }
                // Remove the "Set ID" button since ID is now assigned
                var setIdBtn = row.querySelector('button[title="Assign real Student ID"]');
                if (setIdBtn) setIdBtn.remove();
                // Update onclick attrs on remaining action buttons
                row.querySelectorAll('button[onclick]').forEach(function(b) {
                    b.setAttribute('onclick', b.getAttribute('onclick').replace(
                        new RegExp(_editId_oldSid.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'g'), newSid
                    ));
                });
            });
            closeEditIdModal();
            showNotice('Student ID updated to ' + newSid + '.', 'success');
        })
        .catch(function() {
            btn.disabled    = false;
            btn.textContent = 'Save ID';
            errEl.textContent   = 'Network error. Please try again.';
            errEl.style.display = 'block';
        });
}

// ── Remove candidate permanently ───────────────────────────────────────────
function removeCandidate(sid, year, source) {
    var row = document.querySelector('tr[data-sid="' + sid + '"]');
    var name = row ? (row.querySelector('td:nth-child(3)') || {}).textContent || sid : sid;
    if (!confirm('Remove "' + name + '" from the candidate list?\n\nThis only removes their candidacy — their voter account is not affected.\nThis cannot be undone.')) return;

    var fd = new FormData();
    fd.append('student_id', sid);
    fd.append('election_year', year);
    fd.append('_csrf', _adminCsrf);
    fetch('/admin/ajax/remove-candidate.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                alert('Error: ' + (data.error || 'Could not remove candidate.'));
                return;
            }
            if (row) {
                var rowData = JSON.parse(row.getAttribute('data-row') || '{}');
                var oldStatus = rowData['Application_Status'] || '';
                adjustStatCount(oldStatus, -1);
                var card = row.closest ? row.closest('.coll-card') : null;
                row.style.transition = 'opacity .3s';
                row.style.opacity = '0';
                setTimeout(function() {
                    row.remove();
                    if (card) updateCollegeBadges({ closest: function() { return card; } });
                }, 320);
            }
            showNotice('Candidate removed.', 'success');
        })
        .catch(function() { alert('Network error. Please try again.'); });
}

// ── Remove row from approved table ────────────────────────────────────────
function removeApprovedRow(sid) {
    var body = document.getElementById('approvedTableBody');
    if (!body) return;
    var row = body.querySelector('tr[data-sid="' + sid + '"]');
    if (!row) return;
    row.style.transition = 'opacity .3s';
    row.style.opacity = '0';
    setTimeout(function() {
        row.remove();
        var remaining = body.querySelectorAll('tr').length;
        var countEl = document.getElementById('filterCount');
        if (countEl) countEl.textContent = remaining + ' candidate' + (remaining !== 1 ? 's' : '');
        if (remaining === 0) {
            var wrap = document.getElementById('approvedTableWrap');
            var es   = document.getElementById('approvedEmptyState');
            if (wrap) wrap.style.display = 'none';
            if (es)   es.style.display   = '';
        }
    }, 320);
}

// ── Add row to approved table ──────────────────────────────────────────────
function addApprovedRow(rowData) {
    var tbody = document.getElementById('approvedTableBody');
    if (!tbody) return;

    var wrap = document.getElementById('approvedTableWrap');
    var fb   = document.getElementById('approvedFilterBar');
    var es   = document.getElementById('approvedEmptyState');
    if (es)   es.style.display   = 'none';
    if (wrap) wrap.style.display = '';
    if (fb)   fb.style.display   = '';

    var sid      = rowData['Student_ID']    || rowData['student_id']    || '';
    var yr       = rowData['Election_Year'] || rowData['election_year'] || '';
    var cid      = rowData['Candidate_ID']  || rowData['candidate_id']  || '';
    var cname    = rowData['Student_Name']  || sid;
    var posName  = rowData['Position_Name'] || ('Position #' + (rowData['Position_ID'] || '?'));
    var slateName= rowData['Slate_Name']    || '—';
    var hasPhoto = !!rowData['_hasPhoto'];
    var rowCount = tbody.querySelectorAll('tr').length + 1;
    var tr = document.createElement('tr');
    tr.setAttribute('data-sid', sid);
    tr.setAttribute('data-row', JSON.stringify(rowData));

    var photoDotCls  = hasPhoto ? 'has-photo-dot' : 'no-photo-dot';
    var photoLabel   = hasPhoto ? 'Replace' : 'Upload';
    var photoId      = cid ? ' id="photoBtn_' + escHtml(cid) + '"' : '';
    var dotId        = cid ? ' id="photoDot_' + escHtml(cid) + '"' : '';
    var thumbId      = cid ? 'id="photoThumb_' + escHtml(cid) + '"' : '';
    var thumbHtml    = hasPhoto
        ? '<img class="cand-thumb" ' + thumbId + ' src="/ajax/candidate-photo.php?id=' + encodeURIComponent(cid) + '&t=' + Date.now() + '" alt="' + escHtml(cname) + '" onclick="openPhotoModal(\'' + escJs(cid) + '\',\'' + escJs(cname) + '\',true)" title="Click to replace photo">'
        : '<div class="cand-thumb-ph" ' + thumbId + ' onclick="openPhotoModal(\'' + escJs(cid) + '\',\'' + escJs(cname) + '\',false)" title="Click to upload photo">\uD83D\uDCF7</div>';

    var html = '<td>' + rowCount + '</td>'
             + '<td><span style="font-family:monospace;font-size:12px">' + escHtml(sid) + '</span></td>'
             + '<td>' + escHtml(cname) + '</td>'
             + '<td>' + escHtml(posName) + '</td>'
             + '<td>' + escHtml(slateName) + '</td>'
             + '<td><span class="badge-sm badge-approved">APPROVED</span></td>'
             + '<td><div class="cand-thumb-wrap">' + thumbHtml
             +     '<button type="button" class="btn-sm-photo"' + photoId
             +     ' onclick="openPhotoModal(\'' + escJs(cid) + '\',\'' + escJs(cname) + '\',' + (hasPhoto ? 'true' : 'false') + ')">'
             +     '<span class="' + photoDotCls + '"' + dotId + '></span>' + photoLabel
             +     '</button></div></td>';
    html += '<td><div class="actions">'
          + '<button type="button" class="btn btn-red" onclick="removeCandidate(\'' + escJs(sid) + '\',\'' + escJs(yr) + '\',\'approved\')">&#128465; Remove</button>'
          + '</div></td>';
    tr.innerHTML = html;
    tr.style.opacity = '0';
    tbody.appendChild(tr);
    requestAnimationFrame(function() { tr.style.transition = 'opacity .35s'; tr.style.opacity = '1'; });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) { return String(s).replace(/'/g,"\\'"); }

// ── Modal helpers ─────────────────────────────────────────────────────────
var _modalCb = null;
function showConfirmModal(title, msg, barCls, confirmText, confirmCls, cb) {
    var bar = document.getElementById('cmBar');
    bar.className = 'cm-bar ' + barCls;
    document.getElementById('cmTitle').textContent = title;
    document.getElementById('cmMsg').innerHTML = msg;
    var btn = document.getElementById('cmConfirmBtn');
    btn.textContent = confirmText;
    btn.className   = 'btn ' + confirmCls;
    _modalCb = cb;
    document.getElementById('confirmModal').classList.add('open');
}
function closeModal() {
    document.getElementById('confirmModal').classList.remove('open');
    _modalCb = null;
}
function doModalConfirm() {
    closeModal();
    if (_modalCb) _modalCb();
}
function showNotice(msg, type) {
    var t = document.getElementById('noticeToast');
    t.querySelector('.nt-msg').textContent = msg;
    t.style.background = (type === 'error') ? '#dc2626' : '#16a34a';
    t.querySelector('.nt-icon').textContent = (type === 'error') ? '✕' : '✓';
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.classList.remove('show'); }, 3500);
}

// ── Main setStatus — AJAX, updates badge in-place inside college accordion ─
function setStatus(sid, year, status) {
    var isApprove = status === 'APPROVED';
    var isReject  = status === 'DENIED';
    var msg = isApprove ? 'Approve candidate ' + sid + '?'
            : 'Reject candidate ' + sid + '?';
    if (!confirm(msg)) return;

    var fd = new FormData();
    fd.append('student_id',         sid);
    fd.append('election_year',      year);
    fd.append('application_status', status);
    fd.append('_csrf', _adminCsrf);

    fetch('/admin/ajax/candidate-status.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotice(
                    isApprove ? 'Candidate approved!' : 'Candidate rejected.',
                    isReject ? 'error' : 'success'
                );
                var row = document.querySelector('tr[data-sid="' + sid + '"]');
                if (row) {
                    var rowData = JSON.parse(row.getAttribute('data-row') || '{}');
                    var oldStatus = rowData['Application_Status'] || '';
                    rowData['Application_Status'] = status;
                    row.setAttribute('data-row', JSON.stringify(rowData));

                    var badge = row.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'badge-sm status-badge ' +
                            (status === 'APPROVED' ? 'badge-approved' : 'badge-rejected');
                        badge.textContent = status;
                    }

                    var actDiv = row.querySelector('.row-actions');
                    if (actDiv) { actDiv.innerHTML = buildRowActions(sid, year, status, rowData['Candidate_ID'] || rowData['candidate_id'] || '', rowData['Student_Name'] || sid); }

                    if (oldStatus !== status) {
                        adjustStatCount(oldStatus, -1);
                        adjustStatCount(status,    +1);
                    }
                    updateCollegeBadges(row);
                }
            } else {
                showNotice('Error: ' + (data.error || 'Could not update status.'), 'error');
            }
        })
        .catch(function() { showNotice('Network error. Please try again.', 'error'); });
}

function buildRowActions(sid, year, status, candidateId, candidateName) {
    candidateId   = candidateId   || '';
    candidateName = candidateName || sid;
    var hasPhoto = !!document.getElementById('photoDot_' + candidateId) &&
                   document.getElementById('photoDot_' + candidateId).classList.contains('has-photo-dot');
    if (status === 'APPROVED') {
        return '<button class="btn btn-red"    onclick="removeCandidate(\'' + escJs(sid) + '\',\'' + escJs(year) + '\',\'approved\')">&#128465; Remove</button>';
    } else {
        return '<button class="btn btn-green"  onclick="setStatus(\'' + escJs(sid) + '\',\'' + escJs(year) + '\',\'APPROVED\')">&#10003; Approve</button>'
             + '<button class="btn btn-red"    onclick="removeCandidate(\'' + escJs(sid) + '\',\'' + escJs(year) + '\',\'denied\')">&#128465; Remove</button>';
    }
}

function adjustStatCount(status, delta) {
    if (status === 'APPROVED') setApprovedCount(Math.max(0, getApprovedCount() + delta));
    else if (status === 'DENIED' || status === 'DISQUALIFIED' || status === 'PENDING') setRejectedCount(Math.max(0, getRejectedCount() + delta));
}

function updateCollegeBadges(row) {
    var card = row.closest ? row.closest('.coll-card') : null;
    if (!card) return;
    var tbody = card.querySelector('tbody');
    if (!tbody) return;
    var approvedN = tbody.querySelectorAll('.badge-approved').length;
    var deniedN   = tbody.querySelectorAll('.badge-rejected').length;
    var ab = card.querySelector('.coll-badge-approved');
    var db = card.querySelector('.coll-badge-denied');
    if (ab) { ab.textContent = approvedN + ' approved'; ab.style.display = approvedN ? '' : 'none'; }
    if (db) { db.textContent = deniedN   + ' denied';   db.style.display = deniedN   ? '' : 'none'; }
}

function toggleCollege(code) {
    var body    = document.getElementById('body-'    + code);
    var card    = document.getElementById('coll-'    + code);
    var chevron = document.getElementById('chevron-' + code);
    if (!body) return;
    var isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : '';
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    if (card)    card.classList.toggle('open', !isOpen);
}

function filterAccordion() {
    var s = document.getElementById('accordionSearch').value.toLowerCase().trim();
    var totalVisible = 0;
    document.querySelectorAll('#collegeAccordion tbody tr').forEach(function(r) {
        var show = !s || r.textContent.toLowerCase().includes(s);
        r.style.display = show ? '' : 'none';
        if (show) totalVisible++;
    });
    if (s) {
        document.querySelectorAll('#collegeAccordion .coll-card').forEach(function(card) {
            var visibleRows = card.querySelectorAll('tbody tr:not([style*="display: none"]):not([style*="display:none"])').length;
            var body = card.querySelector('.coll-body');
            if (visibleRows > 0) { if (body) body.style.display = ''; card.classList.add('open'); }
        });
    }
    var el = document.getElementById('accordionTotal');
    if (el) el.textContent = totalVisible + ' candidate' + (totalVisible !== 1 ? 's' : '');
}
function filterTable() { filterAccordion(); }
// ── Voter name search (autocomplete) ──────────────────────────────────────
var _searchTimer = null;
var _selectedStudentId = '';

function searchVoterByName(val) {
    _selectedStudentId = '';
    document.getElementById('regStudentId').value = '';
    var label = document.getElementById('regNameLabel');
    label.style.display = 'none';
    label.textContent = '';
    var dw = document.getElementById('dupWarning');
    if (dw) { dw.style.display = 'none'; dw.textContent = ''; }

    clearTimeout(_searchTimer);
    if (val.length < 2) { closeVoterDropdown(); return; }
    _searchTimer = setTimeout(function() {
        fetch('/admin/ajax/search-voter.php?name=' + encodeURIComponent(val))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var dd = document.getElementById('voterDropdown');
                if (!data.success || !data.results || data.results.length === 0) {
                    dd.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#9ca3af;font-weight:600;">No students found &mdash; try a partial last name, or use &ldquo;Enter manually&rdquo; below.</div>';
                    dd.style.display = 'block';
                    return;
                }
                dd.innerHTML = '';
                data.results.forEach(function(s) {
                    var item = document.createElement('div');
                    item.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#1a1a1a;border-bottom:1px solid #f3f4f6;';
                    item.innerHTML = escHtml(s.Student_Name) + ' <span style="font-family:monospace;font-size:11px;color:#6b7280;font-weight:500;">(' + escHtml(s.Student_ID) + ')</span>';
                    item.addEventListener('mouseenter', function() { this.style.background = '#f0f9ff'; });
                    item.addEventListener('mouseleave', function() { this.style.background = ''; });
                    item.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        selectVoter(s.Student_ID, s.Student_Name);
                    });
                    item.addEventListener('click', function(e) {
                        selectVoter(s.Student_ID, s.Student_Name);
                    });
                    dd.appendChild(item);
                });
                dd.style.display = 'block';
            })
            .catch(function() { closeVoterDropdown(); });
    }, 300);
}

function selectVoter(sid, name) {
    _selectedStudentId = sid;
    document.getElementById('regStudentId').value = sid;
    document.getElementById('regStudentNameInput').value = name;
    var label = document.getElementById('regNameLabel');
    label.textContent = '\u2713 Selected: ' + name + ' (ID: ' + sid + ')';
    label.style.display = 'block';
    closeVoterDropdown();
    checkDuplicate(sid);
}

// ── Duplicate-candidate check ──────────────────────────────────────────────
var _dupCheckTimer = null;
function checkDuplicate(sid) {
    var warn = document.getElementById('dupWarning');
    warn.style.display = 'none';
    warn.textContent = '';
    if (!sid) return;
    var yr = (document.getElementById('regElectionYear') || {}).value || '';
    if (!yr) return;
    fetch('/admin/ajax/check-candidate-duplicate.php?student_id=' + encodeURIComponent(sid) + '&election_year=' + encodeURIComponent(yr))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.duplicate) {
                var status = data.status || 'registered';
                var pos    = data.position || 'a position';
                warn.innerHTML = '&#9888;&#xFE0E; Duplicate — this student already has a <strong>' + escHtml(status) + '</strong> application for <strong>' + escHtml(pos) + '</strong> in A.Y. ' + escHtml(String(data.year)) + '. Registering again will create a second entry.';
                warn.style.display = 'block';
            }
        })
        .catch(function() {});
}

function closeVoterDropdown() {
    var dd = document.getElementById('voterDropdown');
    if (dd) dd.style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#regStudentNameInput') && !e.target.closest('#voterDropdown')) {
        closeVoterDropdown();
    }
});

// ── Position / College dropdown logic ────────────────────────────────────
var _collegePositionMap = <?= json_encode($collegePositionMap) ?>;

function onPositionTypeChange(val) {
    var collegeRow    = document.getElementById('regCollegeRow');
    var collegeSelect = document.getElementById('regCollegeSelect');
    var collegeLabel  = collegeRow ? collegeRow.querySelector('label') : null;
    var posInput      = document.getElementById('regPositionNumericId');

    if (collegeSelect) collegeSelect.value = '';
    posInput.value = '';
    collegeRow.style.display = 'none';

    if (!val) return;

    // Show college for every position — it records the candidate's home college.
    // For Governor / Vice-Governor / Representative the selected college also
    // controls which college section they appear under on the ballot.
    collegeRow.style.display = 'block';

    if (val === 'president')      { posInput.value = '1'; }
    else if (val === 'vicepresident')  { posInput.value = '2'; }
    else if (val === 'governor')       { posInput.value = '3'; }
    else if (val === 'vicegovernor')   { posInput.value = '4'; }
    // representative: posInput.value set later via onCollegeChange

    // Update the label to clarify intent depending on position
    if (collegeLabel) {
        var isCollegePos = (val === 'governor' || val === 'vicegovernor' || val === 'representative');
        collegeLabel.innerHTML = isCollegePos
            ? 'College <span style="color:#ef4444">*</span>'
            : 'College <span style="color:#ef4444">*</span> <span style="font-size:11px;font-weight:400;color:#6b7280;">(candidate\'s home college)</span>';
    }

    collegeRow.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function onCollegeChange(collegeCode) {
    var posType = document.getElementById('regPositionType').value;
    if (posType === 'representative') {
        document.getElementById('regPositionNumericId').value = _collegePositionMap[collegeCode] || '';
    }
}

// ── Manual entry toggle ────────────────────────────────────────────────────
var _manualMode = false;
function toggleManualEntry() {
    _manualMode = !_manualMode;
    var fields = document.getElementById('manualEntryFields');
    var toggle = document.getElementById('manualEntryToggle');
    var searchInput = document.getElementById('regStudentNameInput');
    if (_manualMode) {
        fields.style.display = '';
        toggle.textContent = '\u2716 Cancel manual entry';
        searchInput.value = '';
        document.getElementById('regStudentId').value = '';
        document.getElementById('regNameLabel').style.display = 'none';
        closeVoterDropdown();
        document.getElementById('manualStudentId').focus();
    } else {
        fields.style.display = 'none';
        toggle.textContent = '\u270e Student not in list? Enter manually';
        document.getElementById('manualStudentId').value = '';
        document.getElementById('manualStudentName').value = '';
        document.getElementById('regStudentId').value = '';
        document.getElementById('regNameLabel').style.display = 'none';
    }
}
function onManualIdInput(val) {
    var sid = val.trim();
    document.getElementById('regStudentId').value = sid;
    _updateManualLabel();
    clearTimeout(_dupCheckTimer);
    document.getElementById('dupWarning').style.display = 'none';
    if (sid.length >= 3) {
        _dupCheckTimer = setTimeout(function() { checkDuplicate(sid); }, 500);
    }
}
function onManualNameInput(val) {
    _updateManualLabel();
}
function _updateManualLabel() {
    var sid  = document.getElementById('manualStudentId').value.trim();
    var name = document.getElementById('manualStudentName').value.trim();
    var label = document.getElementById('regNameLabel');
    if (sid && name) {
        label.textContent = '\u2713 Manual: ' + name + ' (ID: ' + sid + ')';
        label.style.display = 'block';
    } else {
        label.style.display = 'none';
    }
}

// ── AJAX register form submission ─────────────────────────────────────────
function submitRegisterForm(e) {
    e.preventDefault();
    var sid = document.getElementById('regStudentId').value.trim() || _selectedStudentId || '';

    // In manual mode, pull from the manual fields
    if (_manualMode) {
        sid = document.getElementById('manualStudentId').value.trim();
        var manualName = document.getElementById('manualStudentName').value.trim();
        if (!manualName) {
            showNotice('Please enter the student\'s Full Name.', 'error');
            document.getElementById('manualStudentName').focus();
            return;
        }
        document.getElementById('regStudentId').value = sid;
    }

    if (!_manualMode && !sid) {
        showNotice('Please search for and select a student first.', 'error');
        document.getElementById('regStudentNameInput').focus();
        return;
    }
    var posId    = document.getElementById('regPositionNumericId').value.trim();
    var yr       = document.getElementById('regElectionYear').value.trim();
    var slate    = document.getElementById('regPartyList').value.trim();
    var posType  = document.getElementById('regPositionType').value;
    var colSel   = document.getElementById('regCollegeSelect');
    if (!posId || !yr) {
        showNotice('Please select a position and fill in all required fields.', 'error');
        return;
    }
    if (['governor','vicegovernor','representative'].indexOf(posType) !== -1 && (!colSel || !colSel.value)) {
        showNotice('Please select a College for this position.', 'error');
        document.getElementById('regCollegeRow').style.display = 'block';
        colSel && colSel.focus();
        return;
    }
    if (!slate) {
        showNotice('Please select a Party List.', 'error');
        document.getElementById('regPartyList').focus();
        return;
    }

    var btn = document.getElementById('regSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Registering...';

    var fd = new FormData();
    fd.append('student_id',           sid);
    fd.append('position_numeric_id',  posId);
    fd.append('election_year',        yr);
    fd.append('candidate_slate_id',   slate);
    // Send manually entered name (or search-selected name) so backend can use it
    if (_manualMode) {
        var mName = document.getElementById('manualStudentName').value.trim();
        if (mName) fd.append('student_name', mName);
    } else if (_selectedStudentId) {
        var sName = document.getElementById('regStudentNameInput').value.trim();
        if (sName) fd.append('student_name', sName);
    }
    var photo = document.getElementById('regPhotob64').value;
    if (photo) fd.append('photo_b64', photo);
    var colSel = document.getElementById('regCollegeSelect');
    if (colSel && colSel.value) fd.append('college_code', colSel.value);
    fd.append('_csrf', _adminCsrf);

    fetch('/admin/ajax/register-candidate.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Register Candidate';
            if (data.success) {
                showNotice('Candidate registered! Refreshing...', 'success');
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                showNotice('Error: ' + (data.error || 'Registration failed.'), 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Register Candidate';
            showNotice('Network error. Please try again.', 'error');
        });
}

function resizeAndEncode(file, callback) {
    var MAX_PX = 800;
    var QUALITY = 0.85;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = new Image();
        img.onload = function() {
            var w = img.width, h = img.height;
            if (w > MAX_PX || h > MAX_PX) {
                if (w > h) { h = Math.round(h * MAX_PX / w); w = MAX_PX; }
                else       { w = Math.round(w * MAX_PX / h); h = MAX_PX; }
            }
            var canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            var dataUrl = canvas.toDataURL('image/jpeg', QUALITY);
            callback(dataUrl);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function encodeRegPhoto(input) {
    var file = input.files[0];
    if (!file) return;
    resizeAndEncode(file, function(dataUrl) {
        document.getElementById('regPhotob64').value = dataUrl.split(',')[1];
        var prev = document.getElementById('regPhotoPreview');
        if (prev) { prev.src = dataUrl; prev.style.display = 'block'; }
    });
}

// ── Photo Upload Modal ─────────────────────────────────────────────────────
function openPhotoModal(candidateId, candidateName, hasPhoto) {
    document.getElementById('pmCandidateId').value   = candidateId;
    document.getElementById('pmCandidateName').textContent = candidateName + ' — ' + candidateId;
    document.getElementById('pmPhotob64').value      = '';
    document.getElementById('pmFileInput').value     = '';
    document.getElementById('pmPreviewImg').src      = '';
    document.getElementById('pmPreviewImg').style.display = 'none';
    document.getElementById('pmPreviewPh').style.display  = '';
    document.getElementById('pmSubmitBtn').disabled  = true;
    document.getElementById('pmSubmitBtn').textContent = hasPhoto ? 'Replace Photo' : 'Upload Photo';
    document.getElementById('photoModal').classList.add('open');
}
function closePhotoModal() {
    document.getElementById('photoModal').classList.remove('open');
}
function onPmFileChange(input) {
    var file = input.files[0];
    if (!file) return;
    resizeAndEncode(file, function(dataUrl) {
        document.getElementById('pmPhotob64').value = dataUrl.split(',')[1];
        var img = document.getElementById('pmPreviewImg');
        var ph  = document.getElementById('pmPreviewPh');
        img.src = dataUrl;
        img.style.display = 'block';
        ph.style.display  = 'none';
        document.getElementById('pmSubmitBtn').disabled = false;
    });
}
function submitPhotoUpload() {
    var cid   = document.getElementById('pmCandidateId').value;
    var photo = document.getElementById('pmPhotob64').value;
    if (!cid || !photo) { showNotice('Please select a photo first.', 'error'); return; }
    var btn = document.getElementById('pmSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';
    var fd = new FormData();
    fd.append('candidate_id', cid);
    fd.append('photo_b64',    photo);
    fd.append('_csrf',        _adminCsrf);
    fetch('/admin/ajax/upload-photo.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotice('Photo uploaded successfully!', 'success');
                closePhotoModal();
                // Update the dot and button label in the approved table
                var dot   = document.getElementById('photoDot_' + cid);
                var pbtn  = document.getElementById('photoBtn_' + cid);
                var thumb = document.getElementById('photoThumb_' + cid);
                if (dot)  { dot.className = 'has-photo-dot'; }
                if (pbtn) { pbtn.innerHTML = '<span class="has-photo-dot"></span>Replace'; pbtn.title = 'Replace photo'; }
                if (thumb) {
                    var newSrc = '/ajax/candidate-photo.php?id=' + encodeURIComponent(cid) + '&t=' + Date.now();
                    if (thumb.tagName === 'IMG') {
                        thumb.src = newSrc;
                    } else {
                        var img = document.createElement('img');
                        img.className = 'cand-thumb';
                        img.id = 'photoThumb_' + cid;
                        img.src = newSrc;
                        img.alt = cid;
                        img.title = 'Click to replace photo';
                        img.onclick = function() { openPhotoModal(cid, document.getElementById('pmCandidateName').textContent.split(' — ')[0], true); };
                        thumb.parentNode.replaceChild(img, thumb);
                    }
                }
            } else {
                btn.disabled = false;
                btn.textContent = 'Upload Photo';
                showNotice('Error: ' + (data.error || 'Upload failed.'), 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Upload Photo';
            showNotice('Network error. Please try again.', 'error');
        });
}

// ── Gallery Upload Modal ───────────────────────────────────────────────────
function openGalleryModal(candidateId, candidateName) {
    document.getElementById('gmCandidateId').value   = candidateId;
    document.getElementById('gmCandidateName').textContent = candidateName + ' — ' + candidateId;
    document.getElementById('gmFileInput').value     = '';
    document.getElementById('gmStatusText').textContent = '';
    document.getElementById('gmPreviewList').innerHTML = '';
    document.getElementById('gmPreviewWrap').style.display = 'none';
    document.getElementById('gmSubmitBtn').disabled  = true;
    document.getElementById('gmSubmitBtn').textContent = 'Upload Gallery';
    document.getElementById('galleryModal').classList.add('open');
}
function closeGalleryModal() {
    document.getElementById('galleryModal').classList.remove('open');
}
function onGmFileChange(input) {
    var files = input.files;
    if (!files || files.length === 0) return;
    
    var statusText = document.getElementById('gmStatusText');
    statusText.textContent = files.length + ' file(s) selected';
    
    // Show preview thumbnails
    var previewList = document.getElementById('gmPreviewList');
    previewList.innerHTML = '';
    
    for (var i = 0; i < Math.min(files.length, 10); i++) {
        var file = files[i];
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:4px;';
            previewList.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
    
    document.getElementById('gmPreviewWrap').style.display = 'block';
    document.getElementById('gmSubmitBtn').disabled  = false;
}
function submitGalleryUpload() {
    var cid   = document.getElementById('gmCandidateId').value;
    var files = document.getElementById('gmFileInput').files;
    if (!cid || !files || files.length === 0) { 
        showNotice('Please select photos first.', 'error'); 
        return; 
    }
    
    var btn = document.getElementById('gmSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Uploading ' + files.length + ' image(s)...';
    
    var fd = new FormData();
    fd.append('candidate_id', cid);
    for (var i = 0; i < files.length; i++) {
        fd.append('gallery_images[]', files[i]);
    }
    fd.append('_csrf', _adminCsrf);
    
    fetch('/admin/ajax/upload-gallery.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotice(data.message || 'Gallery uploaded successfully!', data.errors && data.errors.length > 0 ? 'warning' : 'success');
                closeGalleryModal();
            } else {
                btn.disabled = false;
                btn.textContent = 'Upload Gallery';
                showNotice('Error: ' + (data.error || 'Upload failed.'), 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Upload Gallery';
            showNotice('Network error. Please try again.', 'error');
        });
}


    var file = input.files[0];
    if (!file) return;
    // Reset so the same file can be re-selected after an error
    input.value = '';

    var reader = new FileReader();
    reader.onload = function(e) {
        var b64 = e.target.result.split(',')[1];

        // Show preview immediately in the zone
        var zone        = document.getElementById('coverInput_' + pid).closest('.party-cover-zone');
        var placeholder = document.getElementById('coverPlaceholder_' + pid);
        var uploading   = document.getElementById('coverUploading_' + pid);
        var overlay     = document.getElementById('coverOverlay_' + pid);
        var imgEl       = document.getElementById('coverImg_' + pid);

        if (!imgEl) {
            imgEl    = document.createElement('img');
            imgEl.id = 'coverImg_' + pid;
            imgEl.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
            zone.insertBefore(imgEl, zone.firstChild);
        }
        imgEl.src = e.target.result;           // full data-URL for instant preview
        if (placeholder) placeholder.style.display = 'none';

        // Show spinner, hide hover overlay
        uploading.style.display = 'flex';
        overlay.style.opacity   = '0';
        overlay.style.pointerEvents = 'none';

        var fd = new FormData();
        fd.append('party_id',        pid);
        fd.append('cover_photo_b64', b64);

        fetch('/admin/ajax/party-cover-photo.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': _adminCsrf },
            body:    fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            uploading.style.display     = 'none';
            overlay.style.opacity       = '';
            overlay.style.pointerEvents = '';
            if (data.success) {
                var overlaySpan = overlay.querySelector('span');
                if (overlaySpan) overlaySpan.textContent = 'CHANGE PHOTO';
                zone.title = 'Click to replace cover photo';
                showNotice('Cover photo saved!', 'success');
            } else {
                // Revert preview on failure
                if (!imgEl.dataset.confirmed) {
                    imgEl.remove();
                    if (placeholder) placeholder.style.display = '';
                }
                showNotice(data.error || 'Upload failed. Please try again.', 'error');
            }
        })
        .catch(function() {
            uploading.style.display     = 'none';
            overlay.style.opacity       = '';
            overlay.style.pointerEvents = '';
            showNotice('Network error. Please try again.', 'error');
        });
    };
    reader.readAsDataURL(file);
}
function deleteParty(id, name) {
    if (!confirm('Remove "' + name + '" from the landing page? This cannot be undone.')) return;
    document.getElementById('pd_id').value = id;
    document.getElementById('partyDeleteForm').submit();
}
var _themeHexMap = {
    'theme-blue':   '#1a3a8f',
    'theme-purple': '#7c3aed',
    'theme-navy':   '#0d2a6e',
    'theme-green':  '#16a34a',
    'theme-red':    '#dc2626',
    'theme-gold':   '#f5c400',
};
function updateColorDot(sel, dotId) {
    var dot = document.getElementById(dotId);
    if (dot) dot.style.background = _themeHexMap[sel.value] || '#1a3a8f';
}
function openEditParty(id, name, desc, theme, tag) {
    document.getElementById('ep_id').value   = id;
    document.getElementById('ep_name').value = name;
    document.getElementById('ep_desc').value = desc;
    var thSel  = document.getElementById('ep_theme');
    var tagSel = document.getElementById('ep_tag');
    for (var i = 0; i < thSel.options.length;  i++) { thSel.options[i].selected  = (thSel.options[i].value  === theme); }
    for (var i = 0; i < tagSel.options.length; i++) { tagSel.options[i].selected = (tagSel.options[i].value === tag);   }
    // Sync the live color dot to the party's saved theme
    var dot = document.getElementById('editColorDot');
    if (dot) dot.style.background = _themeHexMap[theme] || '#1a3a8f';
    document.getElementById('editPartyModal').classList.add('open');
}
function closeEditParty() {
    document.getElementById('editPartyModal').classList.remove('open');
}
document.getElementById('editPartyModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditParty();
});
</script>

<!-- Confirm Modal -->
<div class="cm-overlay" id="confirmModal" onclick="if(event.target===this)closeModal()">
    <div class="cm-card">
        <div class="cm-bar bar-gray" id="cmBar"></div>
        <div class="cm-body">
            <div class="cm-title" id="cmTitle"></div>
            <div class="cm-msg" id="cmMsg"></div>
            <div class="cm-actions">
                <button class="btn" style="background:#f3f4f6;color:#374151;border-color:#f3f4f6;" onclick="closeModal()">Cancel</button>
                <button class="btn" id="cmConfirmBtn" onclick="doModalConfirm()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="notice-toast" id="noticeToast">
    <span class="nt-icon" style="font-size:16px;font-weight:900;flex-shrink:0;"></span>
    <span class="nt-msg"></span>
</div>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
</body>
</html>
