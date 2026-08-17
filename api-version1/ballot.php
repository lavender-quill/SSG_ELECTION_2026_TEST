<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$studentId        = $_SESSION['student_id']    ?? '';
$name             = $_SESSION['student_name']  ?? 'Student';
$schoolYear       = ELECTION_SCHOOL_YEAR;
$voterCollege     = trim($_SESSION['college']       ?? '');
$voterCollegeCode = normalizeCollegeCode($_SESSION['college_code'] ?? $voterCollege);
if ($voterCollegeCode === '' && $voterCollege !== '') {
    $voterCollegeCode = normalizeCollegeCode($voterCollege);
}
// Derive college code from program if session doesn't provide it
if ($voterCollegeCode === '') {
    $voterProgram = strtoupper(trim($_SESSION['program'] ?? ''));
    $programToCollegeMap = [
        'BSCS' => 'CCS', 'BSIS' => 'CCS', 'BSIA' => 'CCS', 'BS-AIS' => 'CCS',
        'BS ACCOUNTANCY' => 'CBA', 'BSBA-FM' => 'CBA', 'BSBA-MARKETING' => 'CBA',
        'BS-ENTREP' => 'CBA', 'BSHM' => 'CBA', 'BSTM' => 'CBA',
        'BEED' => 'CTED', 'BPE' => 'CTED', 'BSE-ENGLISH' => 'CTED',
        'BSE-FILIPINO' => 'CTED', 'BSE-MATH' => 'CTED',
        'BSE-SOC-STUD' => 'CTED', 'BSED-SCIENCE' => 'CTED',
        'BS-CRIM' => 'CCJE',
        'AB-ELS' => 'CAS', 'AB-POL-SCI' => 'CAS', 'BCAE' => 'CAS',
        'BECE' => 'CAS', 'BSMA' => 'CAS', 'BSMB' => 'CAS',
        'BSCE' => 'COE', 'BSCOE' => 'COE', 'BSECE' => 'COE',
        'BSEE' => 'COE', 'BSME' => 'COE',
        'BS-NURSING' => 'CNAHS',
        'BSMT' => 'CME', 'BSMID' => 'CME',
        'DPA' => 'GRAD',
        'GR-7' => 'HS', 'GR-8' => 'HS', 'GR-9' => 'HS', 'GR-10' => 'HS',
        'SH-STEM' => 'HS',
    ];
    if (isset($programToCollegeMap[$voterProgram])) {
        $voterCollegeCode = $programToCollegeMap[$voterProgram];
    }
}

// Load governor/vice-governor college map (set by admin at registration time)
$candidateCollegeMap = [];
$_ccFile = DATA_DIR . '/candidate_college.json';
if (file_exists($_ccFile)) {
    $candidateCollegeMap = json_decode(file_get_contents($_ccFile), true) ?: [];
}

// ── GLOBAL ELECTION SCHEDULE CHECK ─────────────────────────────────────────
// Default CLOSED — voting only opens when a valid schedule window is active
$votingOpen      = false;
$votingClosedMsg = 'No election schedule has been set. Please contact the SSG Election Committee.';

// Read schedule — JSON first, DB fallback (self-healing on redeploy)
$_localSched = loadElectionSchedule($schoolYear);
$globalStart = $_localSched ? (int)($_localSched['Time_Start'] ?? 0) : 0;
$globalEnd   = $_localSched ? (int)($_localSched['Time_End']   ?? 0) : 0;

if ($globalStart && $globalEnd) {
    $now = time();
    if ($now < $globalStart) {
        $votingClosedMsg = 'The election has not started yet. Voting opens on <strong>' . date('F j, Y \a\t g:i A', $globalStart) . '</strong>.';
    } elseif ($now > $globalEnd) {
        $votingClosedMsg = 'The election period has ended. Voting closed on <strong>' . date('F j, Y \a\t g:i A', $globalEnd) . '</strong>.';
    } else {
        // Within the global window — open
        $votingOpen      = true;
        $votingClosedMsg = '';
    }
}

// ── COLLEGE VOTING SCHEDULE CHECK ──────────────────────────────────────────
// JSON first, DB fallback (self-healing on redeploy)
$collegeSchedules = loadCollegeSchedules();

$voterSched = null;
foreach ($collegeSchedules as $cs) {
    if (normalizeCollegeCode($cs['College'] ?? '') === $voterCollegeCode) {
        $voterSched = $cs;
        break;
    }
}

// College schedule check (only applies when global is open and college is known)
// If global is open but college has NO schedule defined, voting is CLOSED for that college
if ($votingOpen && $voterCollegeCode !== '') {
    if (!$voterSched) {
        // Global is open but this college has no schedule — they cannot vote
        $votingOpen      = false;
        $votingClosedMsg = 'Voting schedule for your college has not been set. Please contact the SSG Election Committee.';
    } else {
        // College has a schedule — check if we're in the college's voting window
        $now     = time();
        $tsStart = is_numeric($voterSched['Time_Start']) ? (int)$voterSched['Time_Start'] : strtotime((string)$voterSched['Time_Start']);
        $tsEnd   = is_numeric($voterSched['Time_End'])   ? (int)$voterSched['Time_End']   : strtotime((string)$voterSched['Time_End']);
        if ($now < $tsStart) {
            $votingOpen      = false;
            $votingClosedMsg = 'Voting for your college has not started yet. Your scheduled window opens on <strong>' . date('F j, Y \a\t g:i A', $tsStart) . '</strong>.';
        } elseif ($now > $tsEnd) {
            $votingOpen      = false;
            $votingClosedMsg = 'Voting for your college has ended. The window closed on <strong>' . date('F j, Y \a\t g:i A', $tsEnd) . '</strong>.';
        }
    }
}

// ── Enrollment gate ────────────────────────────────────────────────────────
// enrollment_verified is set to true by login.php only when ARMS confirms
// the student is enrolled. false means they were explicitly blocked at login
// but somehow reached this page (e.g. session tampering). Absent = old session
// created before this check existed → allow to avoid disrupting existing users.
if (isset($_SESSION['enrollment_verified']) && $_SESSION['enrollment_verified'] === false) {
    session_destroy();
    header('Location: /login.php?reason=not_enrolled');
    exit;
}

// Must have confirmed profile first
if (empty($_SESSION['profile_confirmed'])) {
    header('Location: /dashboard.php');
    exit;
}

// Clear the voted flag if an admin reset wiped the votes after this session voted
clearStaleVoteSession($schoolYear);

// Already voted?
if (!empty($_SESSION['voted'])) {
    header('Location: /success.php');
    exit;
}

$voteError = '';

// Generate CSRF token for the ballot form (rotate once per session)
if (empty($_SESSION['ballot_csrf'])) {
    $_SESSION['ballot_csrf'] = bin2hex(random_bytes(32));
}
$ballotCsrfToken = $_SESSION['ballot_csrf'];

// ── HANDLE BALLOT SUBMISSION ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vote']) && $votingOpen) {

    // CSRF check
    if (!hash_equals($ballotCsrfToken, $_POST['ballot_csrf'] ?? '')) {
        $voteError = 'Security token mismatch. Please reload the page and try again.';
    } else {

    $selections = $_POST['vote'] ?? [];
    $votesList  = [];

    // Server-side: load all APPROVED candidate IDs for this election year.
    // If the DB is unreachable the ballot is refused — never skip validation.
    $_validCandidateIds = [];
    $_whitelistLoaded   = false;
    try {
        $_vcDb  = \Configuration\Application::$SSG_Candidate_DBase;
        $_vcPdo = new PDO(
            "mysql:host={$_vcDb['Host']};port={$_vcDb['Port']};dbname={$_vcDb['DBName']};charset=utf8mb4",
            $_vcDb['Username'], $_vcDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $_vcStmt = $_vcPdo->prepare(
            "SELECT Candidate_ID FROM candidate_position WHERE Application_Status = 'APPROVED' AND Election_Year = ?"
        );
        $_vcStmt->execute([$schoolYear]);
        foreach ($_vcStmt->fetchAll() as $_vcRow) {
            $_validCandidateIds[] = (string)$_vcRow['Candidate_ID'];
        }
        $_whitelistLoaded = true;
    } catch (\Throwable $_ve) {
        error_log('ballot: candidate whitelist load failed: ' . $_ve->getMessage());
        $voteError = 'The election system is temporarily unavailable. Please try again in a moment.';
    }

    if ($voteError === '') {
    $_invalidVote = false;
    foreach ($selections as $position => $candidateId) {
        // Sanitise position key — must be alphanumeric / dash / underscore only
        $position = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $position);
        if ($position === '') continue;

        // Handle both single candidate and multiple candidates (array)
        $candidates = is_array($candidateId) ? $candidateId : ((!empty($candidateId) && $candidateId !== 'ABSTAIN') ? [$candidateId] : []);
        
        if (!empty($candidates)) {
            $candidateList = [];
            foreach ($candidates as $cid) {
                if (!empty($cid) && $cid !== 'ABSTAIN') {
                    // Whitelist check — always enforced now that whitelist is required
                    if (!in_array((string)$cid, $_validCandidateIds, true)) {
                        $_invalidVote = true;
                        break 2; // Break both loops
                    }
                    $candidateList[] = ['candidate_id' => $cid];
                }
            }
            if (!empty($candidateList)) {
                $votesList[] = [
                    'position_name' => $position,
                    'candidates'    => $candidateList,
                ];
            }
        }
    }

    if ($_invalidVote) {
        $voteError = 'Invalid ballot detected. Please reload the page and try again.';
    } else {
        // Allow submission even with zero votes (abstention is allowed)
        $castRecord = [
            'Voter_ID'    => $studentId,
            'School_Year' => $schoolYear,
            'votes_list'  => $votesList,
        ];
        $castResponse = callModel(function() use ($castRecord) {
            Election_ExtModel::vote_cast($castRecord);
        });
        $castData = unwrap($castResponse);
        if (isError($castData)) {
            $voteError = $castData['Status'] ?? 'Failed to cast vote. Please try again.';
        } else {
            writeVoteAuditLog($studentId, $schoolYear, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            markVoteCast($studentId, $schoolYear);
            $_SESSION['voted']    = true;
            $_SESSION['voted_at'] = time(); // used to detect post-reset stale sessions
            header('Location: /success.php');
            exit;
        }
    }
    } // end whitelist-loaded guard
    } // end CSRF check
}

// ── LOAD CANDIDATES ────────────────────────────────────────────────────────
$candidatesRaw = callModel(function() use ($schoolYear) {
    Candidate::Get_All_Candidates([
        'Election_Year'      => $schoolYear,
        'Application_Status' => 'APPROVED',
    ]);
});
$candidatesData = unwrap($candidatesRaw);

$candidateList = [];
if (!isError($candidatesData)) {
    if (isset($candidatesData['Candidates']) && is_array($candidatesData['Candidates'])) {
        $candidateList = $candidatesData['Candidates'];
    } elseif (isset($candidatesData['Record']) && is_array($candidatesData['Record'])) {
        $candidateList = $candidatesData['Record'];
    } elseif (is_array($candidatesData) && isset($candidatesData[0])) {
        $candidateList = $candidatesData;
    }
}
$candidateList = applyCandidateJsonNameOverrides($candidateList);

// ── ENRICH CANDIDATES WITH NAMES & PARTY FROM DB ──────────────────────────
// The candidate DB only stores Student_ID — names come from the voter DB,
// party/slate names come from the candidate slate table.

// 1. Build slate map (Candidate_Slate_ID → name) from candidate DB; keep connection for photo query
$_slateMap = [];
$_cPdo     = null;
try {
    $_cDb  = \Configuration\Application::$SSG_Candidate_DBase;
    $_cPdo = new PDO(
        "mysql:host={$_cDb['Host']};port={$_cDb['Port']};dbname={$_cDb['DBName']};charset=utf8mb4",
        $_cDb['Username'], $_cDb['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    foreach ($_cPdo->query("SELECT Candidate_Slate_ID, Candidate_Slate FROM candidate_slate ORDER BY Candidate_Slate_ID")->fetchAll() as $_row) {
        $_slateMap[(int)$_row['Candidate_Slate_ID']] = $_row['Candidate_Slate'];
    }
} catch (\Throwable $_e) { $_cPdo = null; }

// 2. Build name map (Student_ID → Student_Name) from voter DB
$_nameMap = [];
$_photoMap = [];
$_sids    = array_unique(array_filter(array_map(fn($c) => $c['Student_ID'] ?? $c['student_id'] ?? '', $candidateList)));
if (!empty($_sids)) {
    $_ph = implode(',', array_fill(0, count($_sids), '?'));
    try {
        $_vDb  = \Configuration\Application::$SSG_Voter_DBase;
        $_vPdo = new PDO(
            "mysql:host={$_vDb['Host']};port={$_vDb['Port']};dbname={$_vDb['DBName']};charset=utf8mb4",
            $_vDb['Username'], $_vDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $_stmt = $_vPdo->prepare("SELECT Student_ID, Student_Name FROM student WHERE Student_ID IN ($_ph)");
        $_stmt->execute(array_values($_sids));
        foreach ($_stmt->fetchAll() as $_row) {
            $_nameMap[$_row['Student_ID']] = $_row;
        }
    } catch (\Throwable $_e) {}

    // Also fetch photos from candidate DB (candidate_photo table)
    if ($_cPdo !== null) {
        try {
            $_stmt = $_cPdo->prepare("SELECT Candidate_ID, Photo FROM candidate_photo WHERE Candidate_ID IN ($_ph)");
            $_stmt->execute(array_values($_sids));
            foreach ($_stmt->fetchAll() as $_row) {
                if (!empty($_row['Photo'])) {
                    $_photoMap[$_row['Candidate_ID']] = $_row['Photo'];
                }
            }
        } catch (\Throwable $_e) {}
    }
}

// Merge manually-entered names as fallback for voter-DB misses (temp/unknown IDs)
$_cnFile = DATA_DIR . '/candidate_names.json';
if (file_exists($_cnFile)) {
    $_cnMap = json_decode(file_get_contents($_cnFile), true) ?: [];
    foreach ($_cnMap as $_cnSid => $_cnName) {
        if (!isset($_nameMap[$_cnSid])) {
            $_nameMap[$_cnSid] = ['Student_ID' => $_cnSid, 'Student_Name' => $_cnName];
        }
    }
}

// 3. Inject enriched fields into each candidate record
foreach ($candidateList as &$_c) {
    $_sid        = $_c['Student_ID'] ?? $_c['student_id'] ?? '';
    $_studentRow = $_nameMap[$_sid] ?? [];
    if (!empty($_studentRow['Student_Name'])) {
        $_c['Student_Name'] = $_studentRow['Student_Name'];
    }
    if (empty($_c['College']) && !empty($_studentRow['College'])) {
        $_c['College'] = $_studentRow['College'];
    }
    if (empty($_c['Program']) && !empty($_studentRow['Program_Enrolled'])) {
        $_c['Program'] = $_studentRow['Program_Enrolled'];
    }
    if (empty($_c['Year_Level']) && !empty($_studentRow['Year_Level'])) {
        $_c['Year_Level'] = $_studentRow['Year_Level'];
    }
    // Always resolve party from slate map (DB may return a default like "Officium")
    $_slateId = (int)($_c['Candidate_Slate_ID'] ?? $_c['candidate_slate_id'] ?? 0);
    if ($_slateId && isset($_slateMap[$_slateId])) {
        $_c['Party_Name'] = $_slateMap[$_slateId];
    }
    // Inject photo from candidate DB if not already present in record
    if (empty($_c['Photo']) && empty($_c['Photo_URL']) && isset($_photoMap[$_sid])) {
        $_c['Photo'] = $_photoMap[$_sid];
    }
}
unset($_c);

// ── FILTER CANDIDATES BY COLLEGE VOTING RULES ─────────────────────────────
// President & Vice-President : all colleges vote
// Governor & Vice-Governor   : only the candidate's own college votes
// Representatives             : only the college the position is assigned to
//
// Safe-by-default: when the voter's or candidate's college cannot be
// determined, college-specific positions are hidden (not shown to all).

// Maps Position_ID → college code for representative slots.
$positionToCollegeCode = [
    5  => 'CCS',  6  => 'CBA',   7  => 'CTED',  8  => 'CAS',
    9  => 'CCJE', 10 => 'CIT',   11 => 'CTED',  12 => 'CME',
    13 => 'COE',  14 => 'COL',   15 => 'HS',    16 => 'GRAD',
    17 => 'SOM',  18 => 'CNAHS',
];

// College-specific representative vote limits
$collegeRepresentativeLimits = [
    'CBA'    => 15,
    'COE'    => 5,
    'CTED'   => 5,
    'CCS'    => 4,
    'CAS'    => 4,
    'CLAMS'  => 4,
    'CNAHS'  => 2,
    'CCJE'   => 2,
    'CME'    => 2,
    'SOM'    => 1,
];

/**
 * Resolve a candidate position to one of five canonical categories
 * using the numeric Position_ID first, then falling back to the name string.
 * Returns: PRESIDENT | VICE_PRESIDENT | GOVERNOR | VICE_GOVERNOR | REPRESENTATIVE | UNKNOWN
 */
function _positionCategory(int $id, string $name): string {
    if ($id === 1)                    return 'PRESIDENT';
    if ($id === 2)                    return 'VICE_PRESIDENT';
    if ($id === 3)                    return 'GOVERNOR';
    if ($id === 4)                    return 'VICE_GOVERNOR';
    if ($id >= 5 && $id <= 18)        return 'REPRESENTATIVE';
    // ID missing or unrecognised — fall back to name
    $n = strtoupper($name);
    if (str_contains($n, 'PRESIDENT') && !str_contains($n, 'VICE')) return 'PRESIDENT';
    if (str_contains($n, 'VICE') && str_contains($n, 'PRESIDENT'))  return 'VICE_PRESIDENT';
    if (str_contains($n, 'GOVERNOR') && !str_contains($n, 'VICE'))  return 'GOVERNOR';
    if (str_contains($n, 'VICE') && str_contains($n, 'GOVERNOR'))   return 'VICE_GOVERNOR';
    if (str_contains($n, 'REPRESENTATIVE') || str_contains($n, 'SENATOR')) return 'REPRESENTATIVE';
    return 'UNKNOWN';
}

$filteredList = [];
foreach ($candidateList as $c) {
    $posId   = (int)($c['Position_ID'] ?? $c['position_id'] ?? 0);
    $posName = strtoupper(trim($c['Position'] ?? $c['Position_Name'] ?? $c['position_name'] ?? ''));
    $category = _positionCategory($posId, $posName);

    switch ($category) {

        case 'PRESIDENT':
        case 'VICE_PRESIDENT':
            // University-wide — every student votes regardless of college
            $filteredList[] = $c;
            break;

        case 'GOVERNOR':
        case 'VICE_GOVERNOR':
            // Only students from the candidate's own college vote.
            // Source priority: admin-mapped JSON → College_Code field from DB.
            // The raw College field (full name) is intentionally NOT used here
            // because it can contain the full string (e.g. "CCS COLLEGE OF COMPUTER STUDIES")
            // which would never equal the short code stored in $voterCollegeCode.
            $_sid  = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
            $cCode = strtoupper($candidateCollegeMap[$_sid] ?? $c['College_Code'] ?? '');
            // Safe default: if either side is unknown, do NOT show the candidate
            if ($voterCollegeCode !== '' && $cCode !== '' && $cCode === $voterCollegeCode) {
                $filteredList[] = $c;
            }
            break;

        case 'REPRESENTATIVE':
            // For numbered position slots, use the hardcoded position→college map.
            if ($posId >= 5 && isset($positionToCollegeCode[$posId])) {
                if ($voterCollegeCode !== ''
                    && strcasecmp($positionToCollegeCode[$posId], $voterCollegeCode) === 0) {
                    $filteredList[] = $c;
                }
                // If voter college unknown → hide (safe default, no else branch)
            } else {
                // No numeric slot — fall back to admin-mapped college code
                $_sid  = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
                $cCode = strtoupper($candidateCollegeMap[$_sid] ?? $c['College_Code'] ?? '');
                if ($voterCollegeCode !== '' && $cCode !== '' && $cCode === $voterCollegeCode) {
                    $filteredList[] = $c;
                }
            }
            break;

        case 'UNKNOWN':
        default:
            // Unrecognised position — hide by default.
            // Admin should assign a proper Position_ID to make this candidate visible.
            break;
    }
}
$candidateList = $filteredList;

// Position ID → name map (for resolving when DB doesn't return Position_Name)
$ballotPositionNames = [
    1  => 'PRESIDENT',
    2  => 'VICE-PRESIDENT',
    3  => 'GOVERNOR',
    4  => 'VICE-GOVERNOR',
    5  => 'REPRESENTATIVE',
    6  => 'REPRESENTATIVE',
    7  => 'REPRESENTATIVE',
    8  => 'REPRESENTATIVE',
    9  => 'REPRESENTATIVE',
    10 => 'REPRESENTATIVE',
    11 => 'REPRESENTATIVE',
    12 => 'REPRESENTATIVE',
    13 => 'REPRESENTATIVE',
    14 => 'REPRESENTATIVE',
    15 => 'REPRESENTATIVE',
    16 => 'REPRESENTATIVE',
    17 => 'REPRESENTATIVE',
    18 => 'REPRESENTATIVE',
];

// Group & order by position
$byPosition = [];
$positionOrder = ['PRESIDENT','VICE-PRESIDENT','GOVERNOR','VICE-GOVERNOR','REPRESENTATIVE'];

foreach ($candidateList as $c) {
    $pos = strtoupper(trim($c['Position'] ?? $c['Position_Name'] ?? $c['position_name'] ?? ''));
    if ($pos === '' || $pos === 'UNKNOWN' || $pos === 'GENERAL') {
        $posId = (int)($c['Position_ID'] ?? $c['position_id'] ?? 0);
        $pos   = $posId > 0 ? ($ballotPositionNames[$posId] ?? 'GENERAL') : 'GENERAL';
    }
    
    // For representatives, append college code to create college-specific positions
    if ($pos === 'REPRESENTATIVE') {
        $posId = (int)($c['Position_ID'] ?? $c['position_id'] ?? 0);
        $collegeCode = '';
        if ($posId >= 5 && isset($positionToCollegeCode[$posId])) {
            $collegeCode = $positionToCollegeCode[$posId];
        } else {
            $_sid = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
            $collegeCode = strtoupper($candidateCollegeMap[$_sid] ?? $c['College_Code'] ?? '');
        }
        if ($collegeCode !== '') {
            $pos = 'REPRESENTATIVE-' . $collegeCode;
        }
    }
    
    $byPosition[$pos][] = $c;
}

// Sort by preset order, then alphabetically for any extra
uksort($byPosition, function($a, $b) use ($positionOrder) {
    $ai = array_search($a, $positionOrder);
    $bi = array_search($b, $positionOrder);
    
    // For representative positions, sort by college code alphabetically
    if (strpos($a, 'REPRESENTATIVE-') === 0 && strpos($b, 'REPRESENTATIVE-') === 0) {
        return strcmp($a, $b);
    }
    
    // Standard positions come before representative positions
    if (strpos($a, 'REPRESENTATIVE-') === 0) return 1;
    if (strpos($b, 'REPRESENTATIVE-') === 0) return -1;
    
    $ai = $ai === false ? 99 : $ai;
    $bi = $bi === false ? 99 : $bi;
    return $ai !== $bi ? $ai - $bi : strcmp($a, $b);
});

// Build positions JSON for JS
$positionsForJs = [];
foreach ($byPosition as $posName => $candidates) {
    $cList = [];
    foreach ($candidates as $idx => $c) {
        $cId      = $c['Candidate_ID'] ?? $c['Student_ID'] ?? '';
        $cName    = ucwords(strtolower($c['Candidate_Name'] ?? $c['Student_Name'] ?? $c['Name'] ?? 'Unknown'));
        $nickname = $c['Nickname'] ?? $c['Alias'] ?? '';
        if (!$nickname) {
            // Generate nickname from first name
            $parts    = explode(' ', $cName);
            $nickname = '"' . ($parts[0] ?? $cName) . '"';
        } else {
            $nickname = '"' . $nickname . '"';
        }
        $college  = $c['College'] ?? $c['College_Name'] ?? $c['College_Code'] ?? '';
        $program  = $c['Program'] ?? $c['Program_Enrolled'] ?? $c['Course_Description'] ?? '';
        $party    = $c['Party_Name'] ?? $c['Candidate_Slate'] ?? $c['Slate'] ?? $c['Party'] ?? '';
        $color    = $c['Color'] ?? $c['Party_Color'] ?? '';
        $year     = $c['Year_Level'] ?? '';
        $photo    = $c['Photo_URL'] ?? $c['Photo'] ?? $c['Image_URL'] ?? '';

        $cList[] = [
            'id'       => $cId,
            'name'     => $cName,
            'nickname' => $nickname,
            'college'  => $college,
            'program'  => $program,
            'party'    => $party,
            'color'    => $color,
            'year'     => $year,
            'photo'    => $photo,
            'num'      => $idx + 1,
        ];
    }
    
    // Extract college code and vote limit for representative positions
    $isRepresentative = false;
    $voteLimit = 1;
    if (strpos($posName, 'REPRESENTATIVE-') === 0) {
        $isRepresentative = true;
        $collegeCode = substr($posName, strlen('REPRESENTATIVE-'));
        $voteLimit = $collegeRepresentativeLimits[$collegeCode] ?? 1;
    }
    
    $positionsForJs[] = [
        'name'       => ucwords(strtolower($posName)),
        'key'        => $posName,
        'candidates' => $cList,
        'isRepresentative' => $isRepresentative,
        'voteLimit'  => $voteLimit,
    ];
}

$positionsJson = json_encode($positionsForJs, JSON_HEX_TAG);
$displayName   = ucwords(strtolower($name));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ballot &mdash; E-Ballot JRMSU</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body, html, * {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.1167;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f0f0;
            background-image: radial-gradient(circle, #c0c0c0 1px, transparent 1px);
            background-size: 22px 22px;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }

        /* ── Navbar ── */
        .navbar {
            width: 100%; background: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px; height: 56px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            position: sticky; top: 0; z-index: 200;
        }
        .navbar-brand { font-size: 17px; font-weight: 800; color: #222; text-decoration: none; }
        .navbar-links { display: flex; gap: 32px; list-style: none; align-items: center; }
        .navbar-links a { text-decoration: none; font-size: 13.5px; font-weight: 600; color: #444; transition: color .2s; }
        .navbar-links a:hover { color: #1a3a8f; }
        .navbar-links a.active { color: #1a3a8f; font-weight: 800; border-bottom: 2px solid #1a3a8f; padding-bottom: 2px; }

        /* ── Page wrapper ── */
        .page { flex: 1; padding: 36px 24px 60px; display: flex; flex-direction: column; align-items: center; }

        /* ── Stepper ── */
        .stepper-wrap {
            width: 100%; max-width: 700px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 32px; position: relative;
        }
        .stepper-line {
            position: absolute; top: 11px; left: 0; right: 0;
            height: 2px; background: #333; z-index: 0; border-radius: 1px;
        }
        .stepper-progress {
            position: absolute; top: 11px; left: 0;
            height: 2px; background: #1a3a8f;
            z-index: 0; transition: width .3s ease; border-radius: 1px;
        }
        .stepper-items {
            display: flex; align-items: flex-start; justify-content: space-between;
            width: 100%; position: relative; z-index: 2;
            padding: 0;
        }
        .step-item { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; }
        .step-dot {
            width: 22px; height: 22px; border-radius: 50%;
            background: #fff; border: 3px solid #e0e0e0;
            box-shadow: 0 0 0 0 #e0e0e0;
            transition: background .3s, border-color .3s, box-shadow .3s;
            flex-shrink: 0;
            position: relative; z-index: 3;
        }
        .step-dot.active   { background: #1a3a8f; border-color: #1a3a8f; box-shadow: 0 0 8px rgba(26,58,143,.3); }
        .step-dot.done     { background: #1a3a8f; border-color: #1a3a8f; box-shadow: 0 0 8px rgba(26,58,143,.2); }
        .step-dot.upcoming { background: #f5c400; border-color: #f5c400; box-shadow: 0 0 8px rgba(245,196,0,.3); }
        .step-label {
            font-size: 10.5px; font-weight: 700; color: #000; text-align: center;
            white-space: nowrap; word-break: normal;
            max-width: 120px; line-height: 1.2;
        }
        .step-label.active   { color: #000; }
        .step-label.done     { color: #000; }
        .step-label.upcoming { color: #000; }
        
        /* Mobile stepper */
        @media(max-width: 540px) {
            .stepper-wrap { margin-bottom: 24px; }
            .step-dot { width: 20px; height: 20px; border-width: 2px; }
            .stepper-line { top: 10px; height: 2px; }
            .stepper-progress { top: 10px; height: 2px; }
            .step-label { font-size: 9px; max-width: 50px; }
        }
        
        @media(max-width: 360px) {
            .stepper-wrap { margin-bottom: 18px; }
            .step-dot { width: 18px; height: 18px; border-width: 2px; }
            .stepper-line { top: 9px; height: 2px; }
            .stepper-progress { top: 9px; height: 2px; }
            .step-label { font-size: 8px; max-width: 45px; gap: 4px; }
        }

        /* ── Main card ── */
        .ballot-card {
            background: #fff; border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,.09);
            width: 100%; max-width: 700px;
            padding: 36px 36px 28px;
        }

        /* ── Position title ── */
        .position-title-wrap { display: flex; justify-content: center; margin-bottom: 28px; }
        .position-title {
            font-size: 17px; font-weight: 700; color: #1a2a44;
            border: 1.5px solid #d0d5e0; border-radius: 24px;
            padding: 8px 32px; display: inline-block;
        }

        /* ── Candidate grid ── */
        .candidates-grid {
            display: grid; 
            grid-template-columns: auto auto;
            gap: 16px 10px; 
            margin-bottom: 28px;
            justify-content: center;
            place-items: center;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Tablet and larger phones */
        @media(max-width: 768px) {
            .candidates-grid { gap: 12px 8px; }
            .page { padding: 24px 16px 40px; }
            .c-card-bg { padding: 12px 90px 12px 12px; }
            .c-photo-wrap { width: 95px; top: -45px; }
            .c-photo, .c-photo-placeholder { width: 95px; height: 120px; }
        }
        
        /* Mobile phones */
        @media(max-width: 540px) {
            .candidates-grid { grid-template-columns: 1fr; gap: 12px 8px; }
            .c-num { width: 48px; font-size: 42px; height: 90px; }
            .c-card-bg { 
                padding: 15px 90px 15px 11px; 
                min-height: 145px;
            }
            .c-photo-wrap { width: 95px; top: -47px; }
            .c-photo, .c-photo-placeholder { width: 95px; height: 125px; }
            .c-name { font-size: 13px; }
            .c-nickname { font-size: 14px; }
            .c-college { font-size: 10px; }
            .c-detail { font-size: 9px; }
            .page { padding: 20px 12px 35px; }
            .position-title { font-size: 18px; }
        }
        
        /* Small phones */
        @media(max-width: 420px) {
            .candidates-grid { grid-template-columns: 1fr; }
            .c-num { width: 44px; font-size: 36px; height: 85px; }
            .c-card-bg { 
                padding: 14px 72px 14px 10px;
                min-height: 140px;
            }
            .c-photo-wrap { width: 88px; top: -45px; }
            .c-photo, .c-photo-placeholder { width: 88px; height: 120px; }
            .c-name { font-size: 12px; }
            .c-nickname { font-size: 13px; }
            .c-college { font-size: 9px; }
            .c-detail { font-size: 8.5px; }
            .page { padding: 16px 10px 30px; }
            .position-title { font-size: 16px; }
        }
        
        /* Extra small phones (320px) */
        @media(max-width: 360px) {
            .candidates-grid { gap: 10px 6px; }
            .c-num { width: 42px; font-size: 32px; height: 80px; }
            .c-card-bg { 
                padding: 12px 65px 12px 9px;
                min-height: 135px;
            }
            .c-photo-wrap { width: 80px; top: -43px; }
            .c-photo, .c-photo-placeholder { width: 80px; height: 115px; }
            .c-name { font-size: 11px; }
            .c-nickname { font-size: 12px; line-height: 1.2; }
            .c-college { font-size: 8.5px; }
            .c-detail { font-size: 8px; line-height: 1.5; }
            .page { padding: 14px 8px 25px; }
            .position-title { font-size: 15px; }
        }

        /* Outer wrapper — number + card side by side */
        .c-card {
            display: flex;
            align-items: flex-start;
            gap: 0;
            cursor: pointer;
            position: relative;
            margin-bottom: 6px;
        }

        /* Number — sits OUTSIDE the card to the left, inside a pill badge */
        .c-num {
            font-family: 'Poppins', sans-serif;
            font-size: 48px; font-weight: 800;
            color: #FFFFFF;
            line-height: 0.85; flex-shrink: 0;
            width: 52px; height: 95px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #6b84d1 0%, #8a9cc8 100%);
            border: 2px solid transparent;
            border-radius: 5px;
            letter-spacing: -2px;
            transition: border-color .2s, box-shadow .2s, transform .2s;
            box-shadow: 0 5px 16px rgba(107, 132, 209, 0.3);
            margin-right: -6px;
            margin-top: 14px;
            z-index: 1;
        }
        .c-card.selected .c-num { 
            border-color: #F9C301; 
            box-shadow: 0 5px 20px rgba(245,196,0,.45);
            transform: scale(1.04);
        }

        /* Inner card background */
        .c-card-bg {
            flex: 1; min-width: 0;
            background: linear-gradient(135deg, #e5eaf7 0%, #d8dff2 100%);
            border-radius: 25px;
            padding: 18px 122px 18px 18px;
            position: relative;
            overflow: visible;
            border: 2px solid transparent;
            box-shadow: 0 6px 20px rgba(0,0,0,.08);
            transition: border-color .5s ease, box-shadow .5s ease, background .5s ease, transform .5s ease;
            display: flex;
            align-items: center;
            min-height: 160px;
            z-index: 2;
        }
        .c-card:hover .c-card-bg { 
            box-shadow: 0 10px 28px rgba(0,0,0,.16); 
            background: linear-gradient(135deg, #d6deef 0%, #cad6e8 100%);
            transform: translateY(-2px);
        }
        .c-card.selected .c-card-bg {
            border-color: #F9C301;
            background: linear-gradient(135deg, #cfd9e8 0%, #c3cfe0 100%);
            box-shadow: 0 8px 28px rgba(245,196,0,.3);
        }

        /* Info column */
        .c-info { flex: 1; min-width: 0; }
        .c-name { 
            font-size: 14px; font-weight: 800; color: #1a2a44; 
            line-height: 1.3; margin-bottom: 4px;
        }
        .c-college { 
            font-size: 11px; color: #5a6a84; margin-bottom: 6px; 
            line-height: 1.3; font-weight: 500;
        }
        .c-nickname {
            font-size: 16px; font-weight: 700; font-style: italic;
            color: #7b92d4; margin-bottom: 6px; line-height: 1.3;
            letter-spacing: 0.3px;
        }
        .c-detail { 
            font-size: 10px; color: #5a6a84; line-height: 1.7;
            font-weight: 500;
        }
        .c-detail strong { color: #1a2a44; font-weight: 700; }
        .c-color-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            vertical-align: middle; margin-left: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,.15), inset 0 -1px 2px rgba(0,0,0,.1);
            border: 1px solid rgba(255,255,255,.6);
        }

        /* Photo — overflows above the card-bg top */
        .c-photo-wrap {
            position: absolute;
            right: 5px; bottom: 0;
            top: -55px;
            width: 118px;
            display: flex; align-items: flex-end; justify-content: center;
            pointer-events: none;
        }
        .c-photo {
            width: 118px; height: 150px;
            object-fit: cover; object-position: center top;
            border-radius: 18px 16px 16px 16px;
            filter: drop-shadow(-3px 2px 10px rgba(0,0,0,.18));
        }
        .c-photo-placeholder {
            width: 118px; height: 150px;
            background: linear-gradient(160deg, #b8c8e0, #a5b5d0);
            border-radius: 18px 16px 16px 16px;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.7);
            box-shadow: -3px 2px 10px rgba(0,0,0,.15);
        }

        /* ── Bottom buttons ── */
        .ballot-actions {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; padding-top: 8px; width: 100%;
        }
        .btn-reset {
            background: #1a3a8f; color: #fff;
            border: none; padding: 11px 32px; border-radius: 30px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: background .2s;
        }
        .btn-reset:hover { background: #152e72; }
        .btn-skip {
            background: #f3f4f6; color: #444;
            border: 1.5px solid #d1d5db; padding: 11px 32px; border-radius: 30px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: background .2s, border-color .2s;
        }
        .btn-skip:hover { background: #e5e7eb; border-color: #9ca3af; }
        .btn-next {
            background: #f5c400; color: #1a1a1a;
            border: none; padding: 11px 32px; border-radius: 30px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 3px 12px rgba(245,196,0,.4);
            transition: background .2s;
        }
        .btn-next:hover { background: #e6b800; }
        
        /* Mobile button sizing */
        @media(max-width: 540px) {
            .ballot-actions { gap: 8px; }
            .btn-reset, .btn-skip, .btn-next { 
                padding: 10px 20px; 
                font-size: 13px;
                flex: 1;
                justify-content: center;
            }
        }
        
        @media(max-width: 360px) {
            .ballot-actions { gap: 6px; }
            .btn-reset, .btn-skip, .btn-next { 
                padding: 9px 14px; 
                font-size: 12px;
            }
        }

        /* ── Warning bar ── */
        .warn-bar {
            background: #fff3e0; border-left: 4px solid #f87171;
            border-radius: 0 8px 8px 0; padding: 12px 18px;
            margin-top: 16px; display: none;
        }
        .warn-bar.show { display: block; }
        .warn-bar-title { font-size: 13px; font-weight: 800; color: #b91c1c; margin-bottom: 2px; }
        .warn-bar-text  { font-size: 12px; color: #7c2020; }

        /* ── Modal backdrop ── */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.35);
            display: flex; align-items: center; justify-content: center;
            z-index: 500; opacity: 0; pointer-events: none;
            transition: opacity .2s;
        }
        .modal-backdrop.open { opacity: 1; pointer-events: all; }

        .modal {
            background: #fff; border-radius: 16px;
            padding: 36px 32px; max-width: 420px; width: 90%;
            box-shadow: 0 8px 40px rgba(0,0,0,.2);
            transform: scale(.95); transition: transform .2s;
        }
        .modal-backdrop.open .modal { transform: scale(1); }

        .modal-title { font-size: 20px; font-weight: 800; color: #1a2a44; text-align: center; margin-bottom: 8px; }
        .modal-sub   { font-size: 14px; font-weight: 600; color: #333; text-align: center; margin-bottom: 18px; }
        .modal-warn {
            background: #fff3e0; border-left: 4px solid #f87171;
            border-radius: 0 8px 8px 0; padding: 12px 14px; margin-bottom: 14px;
            font-size: 12.5px; color: #7c2020;
        }
        .modal-warn strong { color: #b91c1c; font-size: 13px; display: block; margin-bottom: 4px; }
        .modal-icon { font-size: 22px; margin-bottom: 14px; display: block; }
        .modal-actions { display: flex; justify-content: space-between; gap: 12px; }
        .btn-go-back {
            background: #1a3a8f; color: #fff; border: none;
            padding: 10px 24px; border-radius: 24px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            transition: background .2s;
        }
        .btn-go-back:hover { background: #152e72; }
        .btn-confirm-sel {
            background: #f5c400; color: #1a1a1a; border: none;
            padding: 10px 20px; border-radius: 24px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            box-shadow: 0 3px 10px rgba(245,196,0,.4);
            transition: background .2s;
            flex: 1; text-align: center;
        }
        .btn-confirm-sel:hover { background: #e6b800; }

        /* ── Review page ── */
        .review-header {
            display: flex; justify-content: center; margin-bottom: 20px;
        }
        .review-title-pill {
            font-size: 16px; font-weight: 700; color: #1a2a44;
            border: 1.5px solid #d0d5e0; border-radius: 24px;
            padding: 8px 28px; display: inline-block;
        }

        .review-contact {
            display: flex; align-items: flex-start; gap: 12px;
            background: #f8f9fc; border-radius: 10px;
            padding: 14px 16px; margin-bottom: 20px;
        }
        .review-contact-icon {
            width: 18px; height: 18px; color: #4a6cf7; flex-shrink: 0; margin-top: 1px;
        }
        .review-contact p { font-size: 11.5px; color: #555; font-weight: 500; line-height: 1.6; margin-bottom: 8px; }
        .review-socials { display: flex; gap: 10px; }
        .review-socials a {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; text-decoration: none; color: #fff;
            transition: opacity .2s;
        }
        .review-socials a:hover { opacity: .85; }
        .social-fb { background: #1877f2; }
        .social-yt { background: #ff0000; }
        .social-ig { background: linear-gradient(45deg, #f09433,#e6683c,#dc2743,#cc2366,#bc1888); }

        .review-section-label {
            font-size: 10px; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: .8px;
            margin-bottom: 8px; margin-top: 16px;
        }

        .review-card-abstain {
            background: #f8f9fc; border-radius: 12px;
            padding: 14px 18px; margin-bottom: 4px;
            display: flex; align-items: center; gap: 10px;
            border: 1.5px dashed #d0d5e0; min-height: 56px;
            cursor: pointer;
        }
        .review-card-abstain span { font-size: 12.5px; color: #999; font-style: italic; font-weight: 500; }
        .review-card-abstain .review-edit { margin-left: auto; }

        .review-edit { font-size: 11px; color: #1a3a8f; cursor: pointer; font-weight: 700; text-decoration: underline; flex-shrink: 0; }

        .review-undervote-warn {
            background: #fff3e0; border-left: 4px solid #f87171;
            border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 16px 0 8px;
            font-size: 12px; color: #7c2020; display: none;
        }
        .review-undervote-warn.show { display: block; }
        .review-undervote-warn strong { display: block; color: #b91c1c; margin-bottom: 3px; }

        .review-confirm-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin: 16px 0 4px; padding: 0 2px;
        }
        .review-confirm-row input[type=checkbox] {
            width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;
            accent-color: #1a3a8f; cursor: pointer;
        }
        .review-confirm-row label { font-size: 11.5px; color: #444; font-weight: 500; line-height: 1.6; cursor: pointer; }
        .review-confirm-row label strong { color: #b91c1c; }

        .review-actions {
            display: flex; gap: 10px; margin-top: 16px;
        }
        .btn-back {
            flex: 1; background: #ef4444; color: #fff;
            border: none; padding: 13px; border-radius: 30px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: background .2s;
        }
        .btn-back:hover { background: #dc2626; }
        .btn-submit {
            flex: 1; background: #f5c400; color: #1a1a1a;
            border: none; padding: 13px; border-radius: 30px;
            font-size: 14px; font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(245,196,0,.4);
            transition: background .2s, opacity .2s;
        }
        .btn-submit:hover { background: #e6b800; }
        .btn-submit:disabled { opacity: .45; cursor: not-allowed; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 48px 24px; }
        .empty-state .icon { font-size: 52px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 800; color: #1a3a8f; margin-bottom: 8px; }
        .empty-state p  { font-size: 13.5px; color: #666; }

        /* ── Server error banner ── */
        .error-banner {
            background: #fee2e2; border: 1.5px solid #fca5a5;
            border-radius: 10px; padding: 12px 18px; margin-bottom: 24px;
            font-size: 13px; color: #991b1b; font-weight: 600;
        }

        /* ── Hamburger (mobile) ── */
        .nav-hamburger {
            display: none;
            background: none; border: none; cursor: pointer;
            padding: 6px; border-radius: 6px;
            flex-direction: column; gap: 5px;
            align-items: center; justify-content: center;
            transition: background .2s;
        }
        .nav-hamburger:hover { background: #f0f4ff; }
        .nav-hamburger span {
            display: block; width: 22px; height: 2.5px;
            background: #222; border-radius: 2px;
            transition: transform .3s, opacity .3s;
        }
        .nav-hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
        .nav-hamburger.open span:nth-child(2) { opacity: 0; }
        .nav-hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }

        /* ── Mobile nav menu ── */
        .nav-mobile-menu {
            display: none; position: fixed;
            top: 56px; left: 0; right: 0;
            background: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            z-index: 199; padding: 8px 0 16px;
            flex-direction: column;
            border-top: 1px solid #f0f0f0;
        }
        .nav-mobile-menu.open { display: flex; }
        .nav-mobile-menu a {
            display: block; padding: 13px 24px;
            font-size: 14px; font-weight: 600;
            color: #444; text-decoration: none;
            transition: color .2s, background .2s;
            border-bottom: 1px solid #f8f8f8;
        }
        .nav-mobile-menu a:last-child { border-bottom: none; }
        .nav-mobile-menu a:hover { color: #1a3a8f; background: #f5f7ff; }
        .nav-mobile-menu a.active { color: #1a3a8f; font-weight: 800; background: #f0f4ff; border-left: 3px solid #1a3a8f; }

        @media (max-width: 768px) {
            .navbar { padding: 0 20px; }
            .navbar-links { display: none; }
            .nav-hamburger { display: flex; }
            .page { padding: 24px 14px 48px; }
            .ballot-card { padding: 24px 18px 20px; }
            .stepper-wrap { margin-bottom: 24px; overflow-x: hidden; }
            .step-label { font-size: 9px; max-width: 48px; }
            .step-dot { width: 18px; height: 18px; }
        }
        @media (max-width: 480px) {
            .page { padding: 16px 10px 40px; }
            .ballot-card { padding: 18px 12px 16px; border-radius: 14px; }
            .position-title { font-size: 13px; padding: 7px 16px; }
            .step-label { font-size: 8px; max-width: 40px; }
        }
        @media (max-width: 420px) {
            .ballot-actions { flex-direction: column-reverse; gap: 10px; }
            .btn-reset, .btn-skip, .btn-next { width: 100%; justify-content: center; }
            .position-title { font-size: 13px; padding: 7px 14px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="/" class="navbar-brand">E-Ballot</a>
    <ul class="navbar-links">
        <li><a href="/#leaders">Candidates</a></li>
        <li><a href="/contact.php">Contact</a></li>
        <li><a href="/tally.php">Tally</a></li>

        <li><a href="/dashboard.php">Profile</a></li>
    </ul>
    <button class="nav-hamburger" id="navHamburger" onclick="toggleMobileNav()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</nav>
<div class="nav-mobile-menu" id="navMobileMenu">
    <a href="/#leaders">Candidates</a>
    <a href="/contact.php">Contact</a>
    <a href="/tally.php">Tally</a>
    <a href="/dashboard.php">Profile</a>
</div>
<script>
function toggleMobileNav() {
    var btn = document.getElementById('navHamburger');
    var menu = document.getElementById('navMobileMenu');
    var open = menu.classList.toggle('open');
    btn.classList.toggle('open', open);
}
</script>

<div class="page">

    <?php if (!empty($voteError)): ?>
    <div class="error-banner" style="width:100%;max-width:700px;">&#9888; <?= htmlspecialchars($voteError) ?></div>
    <?php endif; ?>

    <?php if (!$votingOpen): ?>
    <!-- Voting window closed for this college -->
    <div class="ballot-card">
        <div class="empty-state">
            <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg></div>
            <h3>Voting Not Available</h3>
            <p><?= $votingClosedMsg ?></p>
            <p style="margin-top:12px;font-size:12px;color:#9ca3af;">Please return during your college's scheduled voting window.</p>
        </div>
    </div>

    <?php elseif (empty($positionsForJs)): ?>
    <!-- No candidates available -->
    <div class="ballot-card">
        <div class="empty-state">
            <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
            <h3>No Candidates Available</h3>
            <p>There are no approved candidates for this election period yet.<br/>Please check back later or contact the SSG Election Committee.</p>
        </div>
    </div>

    <?php else: ?>

    <!-- Stepper (rendered by JS) -->
    <div class="stepper-wrap" id="stepperWrap">
        <div class="stepper-line" id="stepperLine"></div>
        <div class="stepper-progress" id="stepperProgress"></div>
        <div class="stepper-items" id="stepperItems"></div>
    </div>

    <!-- Main ballot card (rendered by JS) -->
    <div class="ballot-card" id="ballotCard"></div>

    <!-- Confirmation modal -->
    <div class="modal-backdrop" id="modalBackdrop">
        <div class="modal">
            <div class="modal-title">Confirm Your Selection</div>
            <div class="modal-sub" id="modalSub"></div>
            <div class="modal-warn">
                <strong>&#9888; Warning</strong>
                You can only choose one candidate for this position. Do you want to lock in your choice?
            </div>
            <span class="modal-icon">&#9888;</span>
            <div class="modal-actions">
                <button class="btn-go-back" onclick="closeModal()">No, Go Back</button>
                <button class="btn-confirm-sel" onclick="confirmSelection()">Yes, Confirm Selection &rarr;</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for submission -->
    <form method="POST" id="submitForm" style="display:none;">
        <input type="hidden" name="submit_vote" value="1"/>
        <input type="hidden" name="ballot_csrf" value="<?= htmlspecialchars($ballotCsrfToken) ?>"/>
        <div id="voteInputs"></div>
    </form>

    <?php endif; ?>
</div>

<script>
const POSITIONS  = <?= $positionsJson ?>;
const TOTAL      = POSITIONS.length;
const REVIEW_IDX = TOTAL; // review page index

let currentStep  = 0;
let selections   = {}; // key => {id, name, nickname, party}
let confirmed    = {}; // key => true (locked in by modal)
let pendingConfirm = null; // {posKey, candidate} waiting for modal

// ── Stepper ────────────────────────────────────────────────────────────────
function renderStepper() {
    const items = document.getElementById('stepperItems');
    if (!items) return;
    items.innerHTML = '';
    POSITIONS.forEach((pos, i) => {
        let dotClass = 'step-dot';
        let lblClass = 'step-label';
        if (i < currentStep || currentStep === REVIEW_IDX) {
            dotClass += ' done'; lblClass += ' done';
        } else if (i === currentStep) {
            dotClass += ' active'; lblClass += ' active';
        } else {
            dotClass += ' upcoming'; lblClass += ' upcoming';
        }
        const div = document.createElement('div');
        div.className = 'step-item';
        let displayName = pos.name;
        const nameLower = displayName.toLowerCase();
        if (nameLower.startsWith('representative-')) {
            displayName = 'Representative';
        } else {
            displayName = nameLower
                .split('-')
                .map(part => part.charAt(0).toUpperCase() + part.slice(1))
                .join('-');
        }
        div.innerHTML = `<div class="${dotClass}"></div><span class="${lblClass}">${displayName}</span>`;
        items.appendChild(div);
    });

    const stepperWrap = document.getElementById('stepperWrap');
    const line = document.getElementById('stepperLine');
    const progressBar = document.getElementById('stepperProgress');
    if (stepperWrap && line && progressBar) {
        requestAnimationFrame(() => {
            const dots = [...items.querySelectorAll('.step-dot')];
            if (dots.length >= 2) {
                const wrapRect = stepperWrap.getBoundingClientRect();
                const firstCenter = dots[0].getBoundingClientRect().left - wrapRect.left + (dots[0].offsetWidth / 2);
                const lastCenter = dots[dots.length - 1].getBoundingClientRect().left - wrapRect.left + (dots[dots.length - 1].offsetWidth / 2);
                const trackLength = Math.max(lastCenter - firstCenter, 0);
                const progressLength = trackLength * (TOTAL > 1 ? (currentStep / (TOTAL - 1)) : 0);

                line.style.left = firstCenter + 'px';
                line.style.right = (wrapRect.width - lastCenter) + 'px';
                progressBar.style.left = firstCenter + 'px';
                progressBar.style.width = progressLength + 'px';
            } else {
                line.style.left = '0px';
                line.style.right = '0px';
                progressBar.style.left = '0px';
                progressBar.style.width = '0px';
            }
        });
    }
}

// ── Candidate card HTML ────────────────────────────────────────────────────
function candidateCardHtml(c, posKey) {
    const pos = POSITIONS.find(p => p.key === posKey);
    const isMultipleSelection = pos && pos.isRepresentative && pos.voteLimit > 1;
    
    let sel = false;
    if (isMultipleSelection) {
        // For multiple selection positions, check if candidate is in the array
        sel = Array.isArray(selections[posKey]) && 
              selections[posKey].some(s => String(s.id) === String(c.id));
    } else {
        // For single selection positions
        sel = selections[posKey]?.id === c.id;
    }
    
    const photoSrc = c.photo
        ? (c.photo.startsWith('data:') || c.photo.startsWith('/') || c.photo.startsWith('http')
            ? c.photo
            : c.photo.startsWith('iVBOR')
                ? 'data:image/png;base64,' + c.photo
                : 'data:image/jpeg;base64,' + c.photo)
        : '';
    const photoHtml = photoSrc
        ? `<img src="${photoSrc}" class="c-photo" alt="${escHtml(c.name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/><div class="c-photo-placeholder" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>`
        : `<div class="c-photo-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>`;

    const colorDot = c.color
        ? `<span class="c-color-dot" style="background:${escHtml(c.color)};"></span>`
        : '';

    const details = [
        c.color   ? `<strong>Color:</strong>${colorDot}` : '',
        c.party   ? `<strong>Party List:</strong> ${escHtml(c.party)}` : '',
        c.program ? `<strong>Program:</strong> ${escHtml(c.program)}` : '',
    ].filter(Boolean).join('<br/>');

    // Use data attributes for click — avoids ANY quote/apostrophe escaping issues
    // with names, nicknames, or party names containing special characters.
    return `
    <div class="c-card${sel ? ' selected' : ''}"
         data-pos-key="${escHtml(posKey)}"
         data-cid="${escHtml(c.id)}"
         onclick="selectCandidateByEl(this)">
        <div class="c-num">${c.num}</div>
        <div class="c-card-bg">
            <div class="c-info">
                <div class="c-name">${escHtml(c.name)}</div>
                ${c.college ? `<div class="c-college">from the ${escHtml(c.college)}</div>` : ''}
                <div class="c-nickname">${escHtml(c.nickname)}</div>
                <div class="c-detail">${details}</div>
            </div>
            <div class="c-photo-wrap">${photoHtml}</div>
        </div>
    </div>`;
}

// ── Render ballot step ─────────────────────────────────────────────────────
function renderStep() {
    const ballotCard = document.getElementById('ballotCard');
    if (!ballotCard) return;
    if (currentStep === REVIEW_IDX) { renderReview(); return; }
    const pos    = POSITIONS[currentStep];
    const posKey = pos.key;

    const cardsHtml = pos.candidates.map(c => candidateCardHtml(c, posKey)).join('');

    const isLast = currentStep === TOTAL - 1;
    
    // Determine warning message based on position type
    let warningHtml = '';
    if (pos.isRepresentative && pos.voteLimit > 1) {
        const selectedCount = Array.isArray(selections[posKey]) ? selections[posKey].length : 0;
        warningHtml = `
            <div class="warn-bar" id="warnBar">
                <div class="warn-bar-title">ℹ️ Multiple Representatives</div>
                <div class="warn-bar-text">You can select up to ${pos.voteLimit} representatives. Currently selected: ${selectedCount}/${pos.voteLimit}</div>
            </div>`;
    } else {
        warningHtml = `
            <div class="warn-bar" id="warnBar">
                <div class="warn-bar-title">&#9888; Warning</div>
                <div class="warn-bar-text">You can only choose one candidate for this position. Do you want to lock in your choice?</div>
            </div>`;
    }

    ballotCard.innerHTML = `
        <div class="position-title-wrap">
            <div class="position-title">${escHtml(pos.name)}</div>
        </div>
        <div class="candidates-grid" id="candidatesGrid">${cardsHtml}</div>
        ${warningHtml}
        <div class="ballot-actions">
            <button class="btn-reset" onclick="goBack()">Back</button>
            <button class="btn-skip" onclick="skipStep()">Skip</button>
            <button class="btn-next" onclick="tryNext()">
                ${isLast ? 'Review' : 'Next'} &rarr;
            </button>
        </div>`;
}

// ── Review page ────────────────────────────────────────────────────────────
function reviewCandidateCardHtml(pos, sel, stepIdx) {
    if (!sel) {
        return `
        <div class="review-section-label">${escHtml(pos.name)}</div>
        <div class="review-card-abstain" onclick="goToStep(${stepIdx})">
            <span>Abstain / No vote for this position</span>
            <span class="review-edit">Edit</span>
        </div>`;
    }

    const photoSrc = sel.photo
        ? (sel.photo.startsWith('data:') || sel.photo.startsWith('/') || sel.photo.startsWith('http')
            ? sel.photo
            : sel.photo.startsWith('iVBOR')
                ? 'data:image/png;base64,' + sel.photo
                : 'data:image/jpeg;base64,' + sel.photo)
        : '';
    const photoHtml = photoSrc
        ? `<img src="${photoSrc}" class="c-photo" alt="${escHtml(sel.name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/><div class="c-photo-placeholder" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>`
        : `<div class="c-photo-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>`;

    const colorDot = sel.color
        ? `<span class="c-color-dot" style="background:${escHtml(sel.color)};"></span>`
        : '';
    const details = [
        sel.color   ? `<strong>Color:</strong>${colorDot}` : '',
        sel.party   ? `<strong>Party List:</strong> ${escHtml(sel.party)}` : '',
        sel.program ? `<strong>Program:</strong> ${escHtml(sel.program)}` : '',
    ].filter(Boolean).join('<br/>');

    return `
    <div class="review-section-label">${escHtml(pos.name)}</div>
    <div class="c-card selected" style="cursor:default;">
        <div class="c-num">${sel.num ?? ''}</div>
        <div class="c-card-bg">
            <div class="c-info">
                <div class="c-name">${escHtml(sel.name)}</div>
                ${sel.college ? `<div class="c-college">from the ${escHtml(sel.college)}</div>` : ''}
                <div class="c-nickname">${escHtml(sel.nickname)}</div>
                <div class="c-detail">${details}</div>
            </div>
            <div class="c-photo-wrap">${photoHtml}</div>
        </div>
    </div>`;
}

function renderReview() {
    const ballotCard = document.getElementById('ballotCard');
    if (!ballotCard) return;
    const hasAbstain = POSITIONS.some(p => !selections[p.key] || (Array.isArray(selections[p.key]) && selections[p.key].length === 0));

    const rows = POSITIONS.map((pos, idx) => {
        const sel = selections[pos.key];
        
        // Handle multiple selections (representative positions)
        if (Array.isArray(sel) && sel.length > 0) {
            const cardHtml = sel.map(candidate => {
                const fullSel = Object.assign({}, candidate, (() => {
                    const posData = POSITIONS.find(p => p.key === pos.key);
                    const cData   = posData ? posData.candidates.find(c => String(c.id) === String(candidate.id)) : null;
                    return cData || {};
                })());
                return reviewCandidateCardHtml(pos, fullSel, idx);
            }).join('');
            return `<div class="review-section-label">${escHtml(pos.name)}</div>${cardHtml}`;
        }
        
        // Handle single selection
        const fullSel = sel ? Object.assign({}, sel, (() => {
            const posData = POSITIONS.find(p => p.key === pos.key);
            const cData   = posData ? posData.candidates.find(c => String(c.id) === String(sel.id)) : null;
            return cData || {};
        })()) : null;
        return reviewCandidateCardHtml(pos, fullSel, idx);
    }).join('');

    document.getElementById('ballotCard').innerHTML = `
        <div class="review-header">
            <div class="review-title-pill">Review your vote</div>
        </div>
        <div class="review-contact">
            <svg class="review-contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
            <div>
                <p>For problems during voting, please reach the Creatives Team by clicking the social icons below.</p>
                <div class="review-socials">
                    <a href="#" class="social-fb" title="Facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                    </a>
                    <a href="#" class="social-yt" title="YouTube">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 001.46 6.42 29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.96A29 29 0 0023 12a29 29 0 00-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
                    </a>
                    <a href="#" class="social-ig" title="Instagram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </a>
                </div>
            </div>
        </div>
        ${rows}
        <div class="review-undervote-warn ${hasAbstain ? 'show' : ''}" id="undervoteWarn">
            <strong>&#9650; Warning</strong>
            You are undervote. (You can still submit your vote if you wish.)
        </div>
        <div class="review-confirm-row">
            <input type="checkbox" id="confirmCheck" onchange="toggleSubmitBtn()"/>
            <label for="confirmCheck">
                I hereby confirm that all of the candidates shown above is the one that I have selected. Also I confirm that my vote can not be changed after I submitted.
            </label>
        </div>
        <div class="review-actions">
            <button class="btn-back" onclick="goBack()">Back</button>
            <button class="btn-submit" id="submitBtn" onclick="openReviewModal()" disabled>Submit Vote →</button>
        </div>`;
}

// ── Selection & navigation ─────────────────────────────────────────────────

// Called from data-attribute onclick — looks up full candidate object from
// POSITIONS so no values need to be embedded in the HTML attribute string.
function selectCandidateByEl(el) {
    const posKey = el.dataset.posKey;
    const cid    = el.dataset.cid;
    const pos    = POSITIONS.find(p => p.key === posKey);
    if (!pos) return;
    const c = pos.candidates.find(x => String(x.id) === String(cid));
    if (!c) return;
    selectCandidate(posKey, c.id, c.name, c.nickname, c.party);
}

function selectCandidate(posKey, id, name, nickname, party) {
    const pos = POSITIONS.find(p => p.key === posKey);
    if (!pos) return;
    
    const cData = pos.candidates.find(c => String(c.id) === String(id));
    const candidateData = Object.assign({ id, name, nickname, party }, cData || {});
    
    // For representative positions with multiple vote limits, handle as array
    if (pos.isRepresentative && pos.voteLimit > 1) {
        if (!selections[posKey]) {
            selections[posKey] = [];
        }
        
        // Toggle: check if candidate is already selected
        const existingIdx = selections[posKey].findIndex(c => String(c.id) === String(id));
        if (existingIdx >= 0) {
            // Deselect
            selections[posKey].splice(existingIdx, 1);
        } else if (selections[posKey].length < pos.voteLimit) {
            // Add if under limit
            selections[posKey].push(candidateData);
        } else {
            // Show warning if limit reached
            alert(`You can only select up to ${pos.voteLimit} representatives from this college.`);
            return;
        }
    } else {
        // Single selection mode (standard positions and single-vote representatives)
        if (selections[posKey]?.id === id) {
            delete selections[posKey];
        } else {
            selections[posKey] = candidateData;
        }
    }
    renderStep();
}

function resetStep() {
    const posKey = POSITIONS[currentStep].key;
    delete selections[posKey];
    delete confirmed[posKey];
    renderStep();
    document.getElementById('warnBar')?.classList.remove('show');
}

function skipStep() {
    // Show modal warning for skipping position
    const pos = POSITIONS[currentStep];
    pendingConfirm = { posKey: pos.key, isSkip: true };
    
    // Update modal for SKIP action
    document.querySelector('.modal-title').textContent = '⚠️ Skip This Position';
    document.getElementById('modalSub').textContent = `You are about to skip ${pos.name}. No candidate will be selected for this position.`;
    document.querySelector('.modal-warn').innerHTML = '<strong>⚠️ Warning:</strong> If you skip this position, your vote for it will be empty. You can still submit your ballot with blank votes.';
    document.querySelector('.btn-go-back').textContent = 'No, Select Candidate';
    document.querySelector('.btn-confirm-sel').textContent = 'Yes, Skip This';
    
    document.getElementById('modalBackdrop').classList.add('open');
}

function tryNext() {
    const pos    = POSITIONS[currentStep];
    const posKey = pos.key;
    const sel    = selections[posKey];

    if (!sel || (Array.isArray(sel) && sel.length === 0)) {
        // No selection – show inline warning
        const wb = document.getElementById('warnBar');
        wb?.classList.add('show');
        return;
    }

    // For representative positions with multiple votes, just proceed
    if (pos.isRepresentative && pos.voteLimit > 1) {
        confirmed[posKey] = true;
        currentStep++;
        renderStepper();
        renderStep();
        return;
    }

    // For single-selection positions – show confirmation modal
    pendingConfirm = { posKey, candidate: sel, isConfirmation: true };
    
    // Update modal for NEXT/CONFIRM action
    document.querySelector('.modal-title').textContent = '✓ Confirm Your Selection';
    document.getElementById('modalSub').textContent = `You have selected ${sel.nickname} for ${pos.name}. Is this correct?`;
    document.querySelector('.modal-warn').innerHTML = '<strong>⚠️ Warning:</strong> You can only choose one candidate for this position. You cannot change this selection after confirming.';
    document.querySelector('.btn-go-back').textContent = 'No, Go Back';
    document.querySelector('.btn-confirm-sel').textContent = 'Yes, Confirm →';
    
    document.getElementById('modalBackdrop').classList.add('open');
}

function closeModal() {
    document.getElementById('modalBackdrop').classList.remove('open');
    pendingConfirm = null;
}

function openReviewModal() {
    // Show warning modal before final submission
    pendingConfirm = { isReviewSubmit: true };
    
    // Update modal for FINAL SUBMISSION
    document.querySelector('.modal-title').textContent = '⚠️ Final Submission Warning';
    document.getElementById('modalSub').textContent = 'You are about to submit your ballot. Once submitted, you CANNOT change your vote.';
    document.querySelector('.modal-warn').innerHTML = '<strong>⚠️ Important Notice:</strong> Please review all your selections carefully. Your vote is permanent after submission. Make sure all selections are correct before proceeding.';
    document.querySelector('.btn-go-back').textContent = 'No, Go Back';
    document.querySelector('.btn-confirm-sel').textContent = 'Yes, Submit Vote';
    
    document.getElementById('modalBackdrop').classList.add('open');
}

function confirmSelection() {
    if (pendingConfirm) {
        // Check the type of confirmation action
        if (pendingConfirm.isReviewSubmit) {
            // Final submission confirmed
            closeModal();
            submitBallot();
            return;
        } else if (pendingConfirm.isSkip) {
            // Skip action: remove selections and mark as confirmed
            const posKey = pendingConfirm.posKey;
            delete selections[posKey];
            delete confirmed[posKey];
            confirmed[posKey] = true;
        } else if (pendingConfirm.isConfirmation) {
            // Normal confirmation: mark as confirmed
            confirmed[pendingConfirm.posKey] = true;
        }
    }
    
    closeModal();
    
    // Move to next step if not a review submission
    if (!pendingConfirm || !pendingConfirm.isReviewSubmit) {
        currentStep++;
        renderStepper();
        renderStep();
    }
}

function goToStep(idx) {
    currentStep = idx;
    renderStepper();
    renderStep();
}

function goBack() {
    if (currentStep === REVIEW_IDX) {
        currentStep = TOTAL - 1;
    } else if (currentStep > 0) {
        currentStep--;
    }
    renderStepper();
    renderStep();
}

function toggleSubmitBtn() {
    const cb  = document.getElementById('confirmCheck');
    const btn = document.getElementById('submitBtn');
    if (cb && btn) btn.disabled = !cb.checked;
}

// ── Submit ─────────────────────────────────────────────────────────────────
function submitBallot() {
    const inputs = document.getElementById('voteInputs');
    inputs.innerHTML = '';
    POSITIONS.forEach(pos => {
        const sel = selections[pos.key];
        if (!sel) return;
        
        // Handle multiple selections (array for representative positions)
        if (Array.isArray(sel)) {
            sel.forEach(candidate => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = `vote[${pos.key}][]`;
                inp.value = candidate.id;
                inputs.appendChild(inp);
            });
        } else {
            // Single selection
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = `vote[${pos.key}]`;
            inp.value = sel.id;
            inputs.appendChild(inp);
        }
    });
    document.getElementById('submitForm').submit();
}

// ── Escape helpers ─────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) {
    return String(s ?? '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

// ── Init ───────────────────────────────────────────────────────────────────
if (POSITIONS.length > 0) {
    renderStepper();
    renderStep();
}
</script>

</body>
</html>
