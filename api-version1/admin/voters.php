<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;
$success = '';
$error   = '';
$searchResult = null;
$voteStatusResult = null;

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $_csrfOk = hash_equals(adminCsrfToken(), trim($_POST['_csrf'] ?? ''));
    if (!$_csrfOk) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {

    if ($_POST['action'] === 'search_voter') {
        $sid = trim($_POST['search_student_id'] ?? '');
        $sem = trim($_POST['search_semester']   ?? $semester);
        $yr  = trim($_POST['search_year']       ?? $schoolYear);
        if (!$sid) {
            $error = 'Student ID is required to search.';
        } else {
            try {
                // Search voter DB by Student_ID — no school year filter so
                // 2024-2025 enrollment data is found even in a 2026-2027 election
                $_vCfg = \Configuration\Application::$SSG_Voter_DBase;
                $_vPdo = new PDO(
                    "mysql:host={$_vCfg['Host']};port={$_vCfg['Port']};dbname={$_vCfg['DBName']};charset=utf8mb4",
                    $_vCfg['Username'], $_vCfg['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
                $_sq = $_vPdo->prepare(
                    'SELECT s.Student_ID, s.Student_Name, s.Sex, s.Program_Code,
                            s.Major, s.Year_Level, s.Semester, s.School_Year,
                            s.Enrollment_Status,
                            COALESCE(c.College_Code, \'\') AS College_Code,
                            COALESCE(c.College_Description, \'Unknown\') AS College
                     FROM student s
                     LEFT JOIN program p ON s.Program_Code = p.Program_Code
                     LEFT JOIN college c ON p.College_Code = c.College_Code
                     WHERE s.Student_ID = ?
                     ORDER BY s.School_Year DESC
                     LIMIT 1'
                );
                $_sq->execute([$sid]);
                $_sRow = $_sq->fetch();

                // Check vote status from election DB
                $_eCfg = \Configuration\Application::$SSG_Election_DBase;
                $_ePdo = new PDO(
                    "mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                    $_eCfg['Username'], $_eCfg['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
                $_vs = $_ePdo->prepare(
                    'SELECT COUNT(DISTINCT Voter_ID) AS voted,
                            GROUP_CONCAT(DISTINCT Position ORDER BY Record_ID SEPARATOR ", ") AS positions
                     FROM votes WHERE Voter_ID = ? AND School_Year = ?'
                );
                $_vs->execute([$sid, $schoolYear]);
                $_vRow = $_vs->fetch();
                $_hasVoted = (int)($_vRow['voted'] ?? 0) > 0;

                if ($_sRow) {
                    $searchResult = array_merge($_sRow, [
                        'Vote_Status'      => $_hasVoted ? 'Voted' : 'Has not voted yet',
                        'Election_Year'    => $schoolYear,
                        'Positions_Voted'  => $_hasVoted ? ($_vRow['positions'] ?? '—') : '—',
                        'Local_Cache'      => isVoteCast($sid, $schoolYear) ? 'Yes' : 'No',
                    ]);
                } else {
                    // Not in local DB — check if they voted anyway (ARMS-only student)
                    if ($_hasVoted) {
                        $searchResult = [
                            'Student_ID'      => $sid,
                            'Student_Name'    => '(Not in local enrollment DB)',
                            'Vote_Status'     => 'Voted',
                            'Election_Year'   => $schoolYear,
                            'Positions_Voted' => $_vRow['positions'] ?? '—',
                            'Local_Cache'     => isVoteCast($sid, $schoolYear) ? 'Yes' : 'No',
                            'Note'            => 'Authenticated via ARMS but not found in 2024-2025 enrollment data.',
                        ];
                    } else {
                        $searchResult = ['Status' => 'Error: No student found with ID "' . $sid . '"'];
                    }
                }
            } catch (\Throwable $_ex) {
                $searchResult = ['Status' => 'Error: ' . $_ex->getMessage()];
            }
        }
    }

    if ($_POST['action'] === 'update_profile') {
        $fields = ['Student_ID','Student_Name','Sex','Program_Enrolled','Major','Year_Level',
                   'Semester','School_Year','Admission_Status','Enrollment_Status'];
        $rec = [];
        foreach ($fields as $f) { $rec[$f] = trim($_POST[$f] ?? ''); }
        if (!$rec['Student_ID']) {
            $error = 'Student ID is required.';
        } else {
            $res = callModel(function() use ($rec) {
                Voter::Account_Update($rec);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to update profile.'; }
            else                { $success = 'Profile for ' . htmlspecialchars($rec['Student_ID']) . ' updated.'; }
        }
    }

    if ($_POST['action'] === 'reset_password') {
        $sid  = trim($_POST['pw_student_id'] ?? '');
        $pass = trim($_POST['new_password']  ?? '');
        if (!$sid || !$pass) {
            $error = 'Student ID and new password are required.';
        } else {
            $res = callModel(function() use ($sid, $pass) {
                Voter::UpdatePassword(['Student_ID' => $sid, 'Password' => $pass]);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to reset password.'; }
            else                { $success = 'Password reset for ' . htmlspecialchars($sid) . '.'; }
        }
    }

    if ($_POST['action'] === 'check_vote_status') {
        $vid = trim($_POST['voter_id'] ?? '');
        $yr  = trim($_POST['vote_status_year'] ?? $schoolYear);
        if (!$vid) {
            $error = 'Voter/Student ID is required.';
        } else {
            // Query votes table directly — stored proc requires student enrollment data
            // that may not exist for the current school year, making it unreliable.
            try {
                $_eCfg = \Configuration\Application::$SSG_Election_DBase;
                $_ePdo = new PDO(
                    "mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                    $_eCfg['Username'], $_eCfg['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
                $_stmt = $_ePdo->prepare(
                    'SELECT COUNT(DISTINCT Voter_ID) AS cnt,
                            GROUP_CONCAT(DISTINCT Position ORDER BY Record_ID SEPARATOR ", ") AS positions
                     FROM votes
                     WHERE Voter_ID = ? AND School_Year = ?'
                );
                $_stmt->execute([$vid, $yr]);
                $_row = $_stmt->fetch();
                $_voted = (int)($_row['cnt'] ?? 0) > 0;
                $voteStatusResult = [
                    'Voter_ID'    => $vid,
                    'School_Year' => $yr,
                    'Status'      => $_voted
                        ? 'Voter already casted their votes for this School Year'
                        : 'Voter has not yet cast their vote for this School Year',
                    'Voted'       => $_voted ? 'Yes' : 'No',
                    'Positions'   => $_voted ? ($_row['positions'] ?? '') : '—',
                    'Local_Cache' => isVoteCast($vid, $yr) ? 'Yes' : 'No',
                ];
            } catch (\Throwable $_ex) {
                $error = 'Could not query vote status: ' . $_ex->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'sync_students') {
        $syncId = (int)($_POST['sync_semsy_id'] ?? 0);
        if (!$syncId) {
            $error = 'Please select a semester / school year to sync.';
        } else {
            try {
                $_vCfg = \Configuration\Application::$SSG_Voter_DBase;
                $_vPdo2 = new PDO(
                    "mysql:host={$_vCfg['Host']};port={$_vCfg['Port']};dbname={$_vCfg['DBName']};charset=utf8mb4",
                    $_vCfg['Username'], $_vCfg['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                // Verify the chosen semsy row exists
                $syStmt = $_vPdo2->prepare('SELECT sem, sy FROM xxxsemsy WHERE id = ?');
                $syStmt->execute([$syncId]);
                $syRow = $syStmt->fetch(PDO::FETCH_ASSOC);
                if (!$syRow) {
                    $error = 'Invalid semester / school year selection.';
                } else {
                    // Count how many are already synced for this exact sem+sy
                    $alreadyStmt = $_vPdo2->prepare(
                        'SELECT COUNT(*) FROM student WHERE School_Year = ? AND Semester = ?'
                    );
                    $alreadyStmt->execute([$syRow['sy'], $syRow['sem']]);
                    $alreadyCount = (int)$alreadyStmt->fetchColumn();

                    // INSERT only rows that don't already exist in student
                    $syncSql = "
                        INSERT INTO student
                            (Student_ID, Student_Name, Sex, Program_Code, Major,
                             Year_Level, Semester, School_Year, Enrollment_Information,
                             Enrollment_Status, stud_pass)
                        SELECT
                            xs.code,
                            TRIM(CONCAT(xs.lastName, ', ', xs.firstName,
                                IF(TRIM(IFNULL(xs.middleName,'')) != '',
                                   CONCAT(' ', TRIM(xs.middleName)), ''))),
                            CASE xs.sex WHEN 1 THEN 'Male' WHEN 2 THEN 'Female' ELSE 'Unknown' END,
                            xc.courseCode,
                            IFNULL(xc.major, ''),
                            xe.yearLevel,
                            xsy.sem,
                            xsy.sy,
                            JSON_OBJECT(
                                'Lecture_Units',    xe.lecUnits,
                                'Laboratory_Units', xe.labUnits,
                                'Admission_Status', 'Old'
                            ),
                            CASE xe.enrolmentStatus WHEN 2 THEN 'Enrolled' ELSE 'On-going' END,
                            IFNULL(xs.defaultPW, '')
                        FROM xxxstudentenrollment xe
                        INNER JOIN xxxstudents   xs  ON xe.studentId  = xs.id
                        INNER JOIN xxxcourses    xc  ON xe.courseId   = xc.id
                        INNER JOIN xxxsemsy      xsy ON xe.semSY      = xsy.id
                        WHERE xsy.id = ?
                          AND xe.enrolmentStatus IN (1, 2)
                          AND NOT EXISTS (
                              SELECT 1 FROM student s2
                              WHERE s2.Student_ID  = xs.code
                                AND s2.School_Year = xsy.sy
                                AND s2.Semester    = xsy.sem
                          )
                    ";
                    $syncStmt = $_vPdo2->prepare($syncSql);
                    $syncStmt->execute([$syncId]);
                    $inserted = $syncStmt->rowCount();

                    if ($inserted > 0) {
                        $success = "Sync complete for {$syRow['sem']} semester {$syRow['sy']}: "
                                 . number_format($inserted) . " new students added."
                                 . ($alreadyCount > 0 ? " ({$alreadyCount} already existed — skipped)" : '');
                    } else {
                        $success = "No new students to add for {$syRow['sem']} {$syRow['sy']} — "
                                 . "all {$alreadyCount} records are already synced.";
                    }
                }
            } catch (\Throwable $_ex) {
                $error = 'Sync failed: ' . $_ex->getMessage();
            }
        }
    }

    } // end CSRF else
}

// ── Load available school years for sync panel ────────────────────────────────
$_syncOptions   = [];   // [['id'=>54,'sem'=>'2nd','sy'=>'2024-2025','enrolled'=>8108,'synced'=>6100], ...]
try {
    $_vCfg2 = \Configuration\Application::$SSG_Voter_DBase;
    $_vPdo3 = new PDO(
        "mysql:host={$_vCfg2['Host']};port={$_vCfg2['Port']};dbname={$_vCfg2['DBName']};charset=utf8mb4",
        $_vCfg2['Username'], $_vCfg2['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $_syncRows = $_vPdo3->query("
        SELECT xsy.id, xsy.sem, xsy.sy,
               COUNT(xe.id)                                        AS enrolled_count,
               (SELECT COUNT(*) FROM student s
                WHERE s.School_Year = xsy.sy AND s.Semester = xsy.sem) AS synced_count
        FROM xxxsemsy xsy
        INNER JOIN xxxstudentenrollment xe ON xe.semSY = xsy.id AND xe.enrolmentStatus IN (1,2)
        WHERE xsy.sy = (SELECT sy FROM xxxsemsy ORDER BY id DESC LIMIT 1)
        GROUP BY xsy.id, xsy.sem, xsy.sy
        HAVING enrolled_count > 0
        ORDER BY xsy.id DESC
        LIMIT 5
    ")->fetchAll();
    $_syncOptions = $_syncRows;
} catch (\Throwable $_e) {
    // silently skip sync panel if xxx tables unavailable
}

// ── Pagination + filter params ─────────────────────────────────────────────────
$_allowedPer = [25, 50, 100, 200];
$_perRaw     = (int)($_GET['per'] ?? 50);
$_perPage    = in_array($_perRaw, $_allowedPer) ? $_perRaw : 50;
$_pg         = max(1, (int)($_GET['pg']     ?? 1));
$_search     = trim($_GET['q']             ?? '');
$_fCollege   = trim($_GET['college']       ?? '');
$_fVote      = trim($_GET['vote']          ?? ''); // 'voted' | 'notyet' | ''

// ── Load students (paginated) + vote stats ────────────────────────────────────
$voterList      = [];
$totalVoters    = 0;
$castedCount    = 0;
$_filteredTotal = 0;
$_totalPages    = 1;
$_baseYear      = '2024-2025';
$_collegeList   = [];
$_votedIds      = [];

try {
    // Voter DB
    $_vCfg = \Configuration\Application::$SSG_Voter_DBase;
    $_vPdo = new PDO(
        "mysql:host={$_vCfg['Host']};port={$_vCfg['Port']};dbname={$_vCfg['DBName']};charset=utf8mb4",
        $_vCfg['Username'], $_vCfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Most recent enrollment year
    $_baseYear = $_vPdo->query(
        'SELECT School_Year FROM student GROUP BY School_Year ORDER BY School_Year DESC LIMIT 1'
    )->fetchColumn() ?: '2024-2025';

    // Total enrolled (stat card — full count, no filter)
    $_tvStmt = $_vPdo->prepare(
        'SELECT COUNT(DISTINCT Student_ID) FROM student WHERE School_Year = ?'
    );
    $_tvStmt->execute([$_baseYear]);
    $totalVoters = (int)$_tvStmt->fetchColumn();

    // College list for dropdown
    $_cStmt = $_vPdo->prepare(
        'SELECT DISTINCT c.College_Description
         FROM student s
         JOIN program p ON s.Program_Code = p.Program_Code
         JOIN college c ON p.College_Code = c.College_Code
         WHERE s.School_Year = ?
         ORDER BY c.College_Description ASC'
    );
    $_cStmt->execute([$_baseYear]);
    $_collegeList = $_cStmt->fetchAll(PDO::FETCH_COLUMN);

    // Election DB — casted count + voted IDs
    $_eCfg = \Configuration\Application::$SSG_Election_DBase;
    $_ePdo = new PDO(
        "mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
        $_eCfg['Username'], $_eCfg['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $_cst = $_ePdo->prepare('SELECT COUNT(DISTINCT Voter_ID) FROM votes WHERE School_Year = ?');
    $_cst->execute([$schoolYear]);
    $castedCount = (int)$_cst->fetchColumn();

    $_vst = $_ePdo->prepare('SELECT DISTINCT Voter_ID FROM votes WHERE School_Year = ?');
    $_vst->execute([$schoolYear]);
    $_votedIds = array_flip($_vst->fetchAll(PDO::FETCH_COLUMN)); // flipped → O(1) isset()

    // ── WHERE clause ────────────────────────────────────────────────────────
    $_where  = ['s.School_Year = ?'];
    $_params = [$_baseYear];

    if ($_search !== '') {
        // Student_Name is stored ALL CAPS with utf8mb4_bin (case-sensitive) collation,
        // so uppercase the search term to match regardless of how the admin typed it.
        $_searchUpper = strtoupper($_search);
        $_where[]  = '(s.Student_ID LIKE ? OR s.Student_Name LIKE ?)';
        $_params[] = '%' . $_searchUpper . '%';
        $_params[] = '%' . $_searchUpper . '%';
    }
    if ($_fCollege !== '') {
        $_where[]  = 'c.College_Description = ?';
        $_params[] = $_fCollege;
    }
    if ($_fVote === 'voted') {
        if (!empty($_votedIds)) {
            $ph = implode(',', array_fill(0, count($_votedIds), '?'));
            $_where[]  = "s.Student_ID IN ($ph)";
            $_params   = array_merge($_params, array_keys($_votedIds));
        } else {
            $_where[] = '1=0';
        }
    } elseif ($_fVote === 'notyet' && !empty($_votedIds)) {
        $ph = implode(',', array_fill(0, count($_votedIds), '?'));
        $_where[]  = "s.Student_ID NOT IN ($ph)";
        $_params   = array_merge($_params, array_keys($_votedIds));
    }
    $_whereSql = implode(' AND ', $_where);
    $_joinSql  = 'FROM student s
         LEFT JOIN program p ON s.Program_Code = p.Program_Code
         LEFT JOIN college c ON p.College_Code = c.College_Code';

    // Filtered total (for pagination)
    $_cntStmt = $_vPdo->prepare("SELECT COUNT(DISTINCT s.Student_ID) $_joinSql WHERE $_whereSql");
    $_cntStmt->execute($_params);
    $_filteredTotal = (int)$_cntStmt->fetchColumn();
    $_totalPages    = max(1, (int)ceil($_filteredTotal / $_perPage));
    $_pg            = min($_pg, $_totalPages);
    $_offset        = ($_pg - 1) * $_perPage;

    // Fetch current page — GROUP BY Student_ID so each student appears only once
    // even if they have rows for both 1st and 2nd semester.
    // CASE puts 'Enrolled' above 'On-going' so MAX() picks the better status.
    $_pageStmt = $_vPdo->prepare(
        "SELECT s.Student_ID,
                MAX(s.Student_Name) AS Student_Name,
                MAX(s.Sex) AS Sex,
                MAX(s.Program_Code) AS Program_Code,
                MAX(s.Year_Level) AS Year_Level,
                MAX(s.School_Year) AS School_Year,
                MAX(CASE s.Enrollment_Status
                        WHEN 'Enrolled'  THEN 'Enrolled'
                        WHEN 'On-going'  THEN 'On-going'
                        ELSE s.Enrollment_Status END) AS Enrollment_Status,
                MAX(COALESCE(c.College_Code, '')) AS College_Code,
                MAX(COALESCE(c.College_Description, 'Unknown')) AS College
         $_joinSql
         WHERE $_whereSql
         GROUP BY s.Student_ID
         ORDER BY MAX(s.Student_Name) ASC
         LIMIT ? OFFSET ?"
    );
    // Bind WHERE params first, then LIMIT/OFFSET as integers (MariaDB rejects string-bound LIMIT/OFFSET)
    $_pi = 1;
    foreach ($_params as $_pv) {
        $_pageStmt->bindValue($_pi++, $_pv);
    }
    $_pageStmt->bindValue($_pi++, (int)$_perPage, PDO::PARAM_INT);
    $_pageStmt->bindValue($_pi,   (int)$_offset,  PDO::PARAM_INT);
    $_pageStmt->execute();

    foreach ($_pageStmt->fetchAll() as $_s) {
        $_s['Vote_Status'] = isset($_votedIds[$_s['Student_ID']]) ? 'Voted' : 'Not Yet';
        $voterList[] = $_s;
    }

} catch (\Throwable $_ex) {
    error_log('voters.php load error: ' . $_ex->getMessage());
}

// Derive counts from the full voted-IDs set (not the page slice)
$votedCount    = count($_votedIds);
$notVotedCount = $totalVoters - $votedCount;

// Column list from first row (for the table header)
$columns = [];
if (!empty($voterList)) {
    foreach (reset($voterList) as $_k => $_v) {
        if (is_scalar($_v)) $columns[] = $_k;
    }
}

// Helper: build page URL preserving active filters + per-page
function votersUrl(array $override = []): string {
    $allowedPer = [25, 50, 100, 200];
    $perRaw = (int)($_GET['per'] ?? 50);
    $per = in_array($perRaw, $allowedPer) ? $perRaw : 50;
    $p = [
        'q'       => trim($_GET['q']       ?? ''),
        'college' => trim($_GET['college'] ?? ''),
        'vote'    => trim($_GET['vote']    ?? ''),
        'per'     => $per !== 50 ? $per : '',   // omit default to keep URLs clean
        'pg'      => (int)($_GET['pg']     ?? 1),
    ];
    $p = array_merge($p, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return '/admin/voters.php' . ($p ? '?' . http_build_query($p) : '');
}

// Helper: highlight search term in output text
function hlSearch(string $text, string $q): string {
    if ($q === '') return htmlspecialchars($text);
    $safe = htmlspecialchars($text);
    $safeQ = preg_quote(htmlspecialchars($q), '/');
    return preg_replace('/(' . $safeQ . ')/i', '<mark>$1</mark>', $safe);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Voters &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .stats-grid { grid-template-columns: repeat(4,1fr); }
        @media(max-width:900px){ .stats-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:480px){ .stats-grid { grid-template-columns: 1fr; } }
        .progress-bar-wrap { margin-top:8px; height:5px; background:#e5e7eb; border-radius:4px; overflow:hidden; }
        .progress-bar { height:100%; background:linear-gradient(135deg,#16a34a,#15803d); border-radius:4px; }
        .sched-college-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
        @media(max-width:900px){ .sched-college-grid { grid-template-columns:1fr 1fr; } }
        @media(max-width:600px){ .sched-college-grid { grid-template-columns:1fr; } }
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; padding:16px 20px 10px; align-items:center; }
        .filter-bar select { min-width:140px; }
        @media(max-width:600px){
            .filter-bar { padding:12px 14px 8px; gap:8px; }
            .filter-bar select { min-width:0; flex:1; }
        }
        .form-row-single { display:grid; grid-template-columns:1fr; max-width:320px; }
        /* ── Search wrapper with clear button ── */
        .search-wrap { position:relative; flex:1; min-width:180px; display:flex; align-items:center; }
        .search-wrap input[type=text] { width:100%; padding-right:30px; }
        .search-clear {
            position:absolute; right:10px; top:50%; transform:translateY(-50%);
            background:none; border:none; cursor:pointer; color:#9ca3af;
            font-size:16px; line-height:1; padding:0; display:none;
            transition:color .15s;
        }
        .search-clear:hover { color:#374151; }
        /* ── Loading dim while navigating ── */
        .table-searching { opacity:.4; pointer-events:none; transition:opacity .15s; }
        /* ── Search highlight ── */
        mark { background:#fef08a; color:inherit; border-radius:2px; padding:0 1px; }
        /* ── Pagination ── */
        .pagination { display:flex; align-items:center; gap:6px; flex-wrap:wrap;
                      padding:14px 20px; border-top:1px solid #e5e7eb; }
        .pagination a, .pagination span {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:34px; height:34px; padding:0 10px; border-radius:6px;
            font-size:13px; font-weight:500; text-decoration:none; transition:background .15s;
        }
        .pagination a { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
        .pagination a:hover { background:#e5e7eb; }
        .pagination .pg-active { background:#16a34a; color:#fff; border-color:#16a34a; font-weight:700; }
        .pagination .pg-disabled { background:#f9fafb; color:#9ca3af; border:1px solid #e5e7eb; cursor:default; }
        .pagination .pg-gap { background:transparent; border:none; color:#9ca3af; cursor:default; }
        .pg-info { font-size:13px; color:#6b7280; margin-left:auto; }
        /* ── Jump-to-page ── */
        .pg-jump { display:flex; align-items:center; gap:6px; font-size:13px; color:#6b7280; }
        .pg-jump input[type=number] {
            width:56px; height:34px; padding:0 8px; border:1px solid #e5e7eb;
            border-radius:6px; font-size:13px; text-align:center;
            appearance:textfield; -moz-appearance:textfield;
        }
        .pg-jump input::-webkit-inner-spin-button,
        .pg-jump input::-webkit-outer-spin-button { -webkit-appearance:none; }
    </style>
    <script>
    var _filterTimer = null;
    var _lastNavUrl  = window.location.href;

    function _buildFilterUrl() {
        var q   = (document.getElementById('searchInput')   || {value:''}).value.trim();
        var cf  = (document.getElementById('collegeFilter') || {value:''}).value;
        var vf  = (document.getElementById('voteFilter')    || {value:''}).value;
        var per = (document.getElementById('perPage')       || {value:'50'}).value;
        var params = new URLSearchParams();
        if (q)          params.set('q', q);
        if (cf)         params.set('college', cf);
        if (vf)         params.set('vote', vf);
        if (per !== '50') params.set('per', per);
        // always reset to page 1 when filter changes
        return '/admin/voters.php' + (params.toString() ? '?' + params.toString() : '');
    }

    function _doNavigate(url) {
        if (url === _lastNavUrl) return; // avoid redundant reload
        _lastNavUrl = url;
        // dim the table so the user sees feedback immediately
        var tw = document.getElementById('voterTableWrap');
        if (tw) tw.classList.add('table-searching');
        window.location.href = url;
    }

    function filterTable() {
        clearTimeout(_filterTimer);
        _filterTimer = setTimeout(function() { _doNavigate(_buildFilterUrl()); }, 600);
    }

    function filterImmediate() {
        clearTimeout(_filterTimer);
        _doNavigate(_buildFilterUrl());
    }

    function searchKeydown(e) {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(_filterTimer); _doNavigate(_buildFilterUrl()); }
    }

    function clearSearch() {
        var inp = document.getElementById('searchInput');
        if (!inp) return;
        inp.value = '';
        document.getElementById('searchClear').style.display = 'none';
        clearTimeout(_filterTimer);
        _doNavigate(_buildFilterUrl());
    }

    function onSearchInput() {
        var inp = document.getElementById('searchInput');
        var btn = document.getElementById('searchClear');
        if (btn) btn.style.display = inp && inp.value ? 'block' : 'none';
        filterTable();
    }

    function jumpToPage() {
        var inp = document.getElementById('pgJumpInput');
        if (!inp) return;
        var pg = parseInt(inp.value, 10);
        var max = parseInt(inp.getAttribute('data-max'), 10);
        if (isNaN(pg) || pg < 1) pg = 1;
        if (pg > max) pg = max;
        var url = new URL(_buildFilterUrl(), window.location.href);
        url.searchParams.set('pg', pg);
        _doNavigate(url.toString());
    }

    // Restore clear button on load if search has a value
    document.addEventListener('DOMContentLoaded', function() {
        var inp = document.getElementById('searchInput');
        var btn = document.getElementById('searchClear');
        if (inp && btn && inp.value) btn.style.display = 'block';
    });
    </script>
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
            <a href="/admin/candidates.php" class="nav-item">Candidates</a>
            <a href="/admin/voters.php" class="nav-item active">Voters</a>
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
            <div class="topbar-title">Voters</div>
            <div class="topbar-right">
                <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
            </div>
        </div>

        <div class="content">

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon bg-blue"></div><div class="stat-value"><?= $totalVoters ?></div><div class="stat-label">Total Students</div></div>
                <div class="stat-card"><div class="stat-icon bg-green"></div><div class="stat-value"><?= $castedCount ?></div><div class="stat-label">Votes Cast</div>
                    <?php if ($totalVoters > 0): ?><div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= min(100,round($castedCount/$totalVoters*100)) ?>%"></div></div><?php endif; ?>
                </div>
                <div class="stat-card"><div class="stat-icon bg-orange"></div><div class="stat-value"><?= max(0,$totalVoters-$castedCount) ?></div><div class="stat-label">Not Yet Voted</div></div>
                <div class="stat-card"><div class="stat-icon bg-purple"></div><div class="stat-value"><?= $totalVoters > 0 ? min(100,round($castedCount/$totalVoters*100)) : 0 ?>%</div><div class="stat-label">Voter Turnout</div></div>
            </div>

            <!-- Sync Voter List -->
            <?php if (!empty($_syncOptions)):
                // Pre-compute totals for the summary bar
                $_totalArms    = array_sum(array_column($_syncOptions, 'enrolled_count'));
                $_totalSynced  = array_sum(array_column($_syncOptions, 'synced_count'));
                $_totalPending = max(0, $_totalArms - $_totalSynced);
                $_pendingRows  = array_filter($_syncOptions, fn($r) => (int)$r['synced_count'] < (int)$r['enrolled_count']);
            ?>
            <div class="section-title">Import Students from ARMS Mirror</div>
            <div class="card">
                <div class="card-body" style="padding:14px 18px;">
                    <?php $latestSy = !empty($_syncOptions) ? htmlspecialchars($_syncOptions[0]['sy']) : '—'; ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                        <div>
                            <span style="font-size:13px;font-weight:700;color:#1e293b;">&#x21BB; <?= $latestSy ?> Enrollment Sync</span>
                            <span style="font-size:12px;color:#6b7280;margin-left:8px;">Adds new students only — existing records are never overwritten</span>
                        </div>
                        <?php if (!empty($_pendingRows)): ?>
                        <form method="POST" id="syncAllForm">
                            <input type="hidden" name="action" value="sync_students"/>
                            <input type="hidden" name="sync_semsy_id" id="syncAllId" value=""/>
                            <?= adminCsrfField() ?>
                            <button type="button" class="btn btn-primary" id="syncAllBtn" onclick="syncAll()"
                                    style="font-size:12px;padding:6px 16px;white-space:nowrap;">
                                <span id="syncAllLabel">&#x21BB; Sync All (<?= number_format($_totalPending) ?> pending)</span>
                                <span id="syncAllSpinner" style="display:none;">&#x23F3; Syncing&hellip;</span>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;font-weight:600;color:#16a34a;">&#10003; All up to date</span>
                        <?php endif; ?>
                    </div>

                    <!-- Compact semester rows -->
                    <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php foreach ($_syncOptions as $_sopt):
                        $synced  = (int)$_sopt['synced_count'];
                        $avail   = (int)$_sopt['enrolled_count'];
                        $pending = max(0, $avail - $synced);
                        $pct     = $avail > 0 ? min(100, round($synced / $avail * 100)) : 100;
                        if ($synced >= $avail) {
                            $statusColor = '#16a34a'; $statusText = '&#10003; Up to date';
                        } elseif ($synced > 0) {
                            $statusColor = '#d97706'; $statusText = '&#9888; Partial';
                        } else {
                            $statusColor = '#6b7280'; $statusText = 'Not synced';
                        }
                    ?>
                    <div style="display:flex;align-items:center;gap:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;flex-wrap:wrap;" id="row-<?= (int)$_sopt['id'] ?>">
                        <div style="flex:1;min-width:120px;">
                            <div style="font-size:13px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($_sopt['sem']) ?> Semester</div>
                            <div style="font-size:11px;color:#6b7280;"><?= number_format($synced) ?> / <?= number_format($avail) ?> synced</div>
                        </div>
                        <div style="flex:2;min-width:100px;">
                            <div style="height:6px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#16a34a' : '#f59e0b' ?>;border-radius:4px;transition:width .3s;"></div>
                            </div>
                            <div style="font-size:10px;color:#9ca3af;margin-top:2px;"><?= $pct ?>%</div>
                        </div>
                        <div style="font-size:12px;font-weight:700;color:<?= $statusColor ?>;"><?= $statusText ?></div>
                        <?php if ($pending > 0): ?>
                        <form method="POST" style="margin:0;" onsubmit="return startSync(this, <?= (int)$_sopt['id'] ?>);">
                            <input type="hidden" name="action" value="sync_students"/>
                            <input type="hidden" name="sync_semsy_id" value="<?= (int)$_sopt['id'] ?>"/>
                            <?= adminCsrfField() ?>
                            <button type="submit" class="btn btn-primary" id="syncBtn-<?= (int)$_sopt['id'] ?>"
                                    style="font-size:12px;padding:5px 12px;white-space:nowrap;">
                                <span class="sync-label">&#x21BB; Sync <?= number_format($pending) ?></span>
                                <span class="sync-spinner" style="display:none;">&#x23F3;&hellip;</span>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;color:#9ca3af;min-width:60px;text-align:center;">—</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:8px;">
                        Showing latest school year only. When 2025-2026 data is uploaded to the ARMS mirror, it will appear here automatically.
                    </div>
                </div>
            </div>

            <script>
            // Sync All: sequentially submit for each pending semester
            var _syncAllQueue = <?= json_encode(array_values(array_map(fn($r) => (int)$r['id'], $_pendingRows))) ?>;
            var _syncAllIndex = 0;

            function syncAll() {
                if (_syncAllQueue.length === 0) return;
                if (!confirm('This will import all <?= number_format($_totalPending) ?> pending students across <?= count($_pendingRows) ?> semester(s). Existing records will not be changed. Continue?')) return;
                // Submit for first pending — server will reload, next run picks up remainder
                document.getElementById('syncAllLabel').style.display  = 'none';
                document.getElementById('syncAllSpinner').style.display = 'inline';
                document.getElementById('syncAllBtn').disabled = true;
                document.getElementById('syncAllId').value = _syncAllQueue[0];
                document.getElementById('syncAllForm').submit();
            }

            function startSync(form, id) {
                var btn   = document.getElementById('syncBtn-' + id);
                if (!confirm('Import pending students for this semester into the voter list? Existing records will not be changed.')) return false;
                if (btn) {
                    btn.querySelector('.sync-label').style.display  = 'none';
                    btn.querySelector('.sync-spinner').style.display = 'inline';
                    btn.disabled = true;
                }
                return true;
            }
            </script>
            <?php endif; ?>

            <!-- Search Voter -->
            <div class="section-title">Search Voter</div>
            <div class="card">
                <div class="card-header-bar"><h3> Lookup Voter Profile</h3><span>Search by Student ID</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="search_voter"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" name="search_student_id" placeholder="e.g. 2021-00123" required
                                       value="<?= htmlspecialchars($_POST['search_student_id'] ?? '') ?>"/>
                            </div>
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="search_semester">
                                    <option value="2nd" <?= ($semester==='2nd')?'selected':'' ?>>2nd Semester</option>
                                    <option value="1st" <?= ($semester==='1st')?'selected':'' ?>>1st Semester</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>School Year</label>
                                <input type="text" name="search_year" value="<?= htmlspecialchars($schoolYear) ?>"/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Search</button>
                    </form>
                    <?php if ($searchResult !== null): ?>
                    <div class="result-box">
                        <strong>Search Result</strong>
                        <?php if (isset($searchResult['Status'])): ?>
                        <p style="color:#dc2626;"><?= htmlspecialchars($searchResult['Status']) ?></p>
                        <?php elseif (!empty($searchResult)): ?>
                        <table class="kv-table">
                        <?php $rec = $searchResult['Record'] ?? $searchResult;
                              if (is_array($rec)) foreach ($rec as $k => $v): if (!is_scalar($v)) continue; ?>
                            <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
                        <?php endforeach; ?>
                        </table>
                        <?php else: ?>
                        <p style="color:#9ca3af;">No record found.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Check Vote Status -->
            <div class="section-title">Check Individual Vote Status</div>
            <div class="card">
                <div class="card-header-bar"><h3>Vote Status Lookup</h3><span>Has this voter already cast their vote?</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="check_vote_status"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Voter / Student ID</label>
                                <input type="text" name="voter_id" placeholder="e.g. 2021-00123" required
                                       value="<?= htmlspecialchars($_POST['voter_id'] ?? '') ?>"/>
                            </div>
                            <div class="form-group">
                                <label>School Year</label>
                                <input type="text" name="vote_status_year" value="<?= htmlspecialchars($schoolYear) ?>"/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-blue">Check Status</button>
                    </form>
                    <?php if ($voteStatusResult !== null): ?>
                    <div class="result-box">
                        <strong>Vote Status</strong>
                        <?php if (isset($voteStatusResult['Status'])): ?>
                        <p><?= htmlspecialchars($voteStatusResult['Status']) ?></p>
                        <?php else: ?>
                        <table class="kv-table">
                        <?php foreach ($voteStatusResult as $k => $v): if (!is_scalar($v)) continue; ?>
                            <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
                        <?php endforeach; ?>
                        </table>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update Profile -->
            <div class="section-title">Update Voter Profile</div>
            <div class="card">
                <div class="card-header-bar"><h3>Edit Voter Record</h3><span>Update a voter's enrollment details</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row">
                            <div class="form-group"><label>Student ID *</label><input type="text" name="Student_ID" placeholder="2021-00123" required/></div>
                            <div class="form-group"><label>Student Name</label><input type="text" name="Student_Name" placeholder="Full name"/></div>
                            <div class="form-group"><label>Sex</label>
                                <select name="Sex"><option value="Male">Male</option><option value="Female">Female</option></select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Program Enrolled</label><input type="text" name="Program_Enrolled" placeholder="e.g. BSIT"/></div>
                            <div class="form-group"><label>Major</label><input type="text" name="Major" placeholder="e.g. Web Development"/></div>
                            <div class="form-group"><label>Year Level</label>
                                <select name="Year_Level">
                                    <option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Semester</label>
                                <select name="Semester">
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd" selected>2nd Semester</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="form-group"><label>School Year</label><input type="text" name="School_Year" value="<?= htmlspecialchars($schoolYear) ?>"/></div>
                            <div class="form-group"><label>Admission Status</label>
                                <select name="Admission_Status"><option>Regular</option><option>Irregular</option><option>Transferee</option></select>
                            </div>
                        </div>
                        <div class="form-row-single">
                            <div class="form-group"><label>Enrollment Status</label>
                                <select name="Enrollment_Status"><option>Enrolled</option><option>Not Enrolled</option></select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Reset Password -->
            <div class="section-title">Reset Voter Password</div>
            <div class="card">
                <div class="card-header-bar"><h3> Password Reset</h3><span>Set a new password for a voter account</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_password"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" name="pw_student_id" placeholder="e.g. 2021-00123" required/>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="New password" required/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-orange"> Reset Password</button>
                    </form>
                </div>
            </div>

            <!-- All Voters list (paginated) -->
            <div class="section-title">All Registered Voters</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3>Voter List</h3>
                    <span>
                        <?php if ($_search !== '' || $_fCollege !== '' || $_fVote !== ''): ?>
                            <?= number_format($_filteredTotal) ?> of <?= number_format($totalVoters) ?> students &mdash; filtered
                        <?php else: ?>
                            <?= number_format($totalVoters) ?> students &mdash; S.Y. <?= htmlspecialchars($_baseYear) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Filter bar -->
                <div class="filter-bar">
                    <div class="search-wrap">
                        <input type="text" id="searchInput"
                               placeholder="Search name or ID&hellip;"
                               value="<?= htmlspecialchars($_search) ?>"
                               oninput="onSearchInput()"
                               onkeydown="searchKeydown(event)"
                               autocomplete="off"/>
                        <button id="searchClear" class="search-clear" type="button"
                                onclick="clearSearch()" title="Clear search">&times;</button>
                    </div>
                    <select id="collegeFilter" onchange="filterImmediate()">
                        <option value="">All Colleges</option>
                        <?php foreach ($_collegeList as $_col): ?>
                        <option value="<?= htmlspecialchars($_col) ?>"
                            <?= $_fCollege === $_col ? 'selected' : '' ?>>
                            <?= htmlspecialchars($_col) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="voteFilter" onchange="filterImmediate()">
                        <option value="">All Status</option>
                        <option value="voted"  <?= $_fVote === 'voted'  ? 'selected' : '' ?>>Voted</option>
                        <option value="notyet" <?= $_fVote === 'notyet' ? 'selected' : '' ?>>Not Yet Voted</option>
                    </select>
                    <select id="perPage" onchange="filterImmediate()" title="Rows per page">
                        <?php foreach ([25, 50, 100, 200] as $_pp): ?>
                        <option value="<?= $_pp ?>" <?= $_perPage === $_pp ? 'selected' : '' ?>><?= $_pp ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($_filteredTotal > 0): ?>
                    <span class="filter-count">
                        Showing <?= number_format(($_pg - 1) * $_perPage + 1) ?>–<?= number_format(min($_pg * $_perPage, $_filteredTotal)) ?>
                        of <?= number_format($_filteredTotal) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Table -->
                <?php if (empty($voterList)): ?>
                <div class="empty-state" style="padding:32px">
                    <div class="icon"></div>
                    <?php if ($_search !== '' || $_fCollege !== '' || $_fVote !== ''): ?>
                        No students match
                        <?php if ($_search !== ''): ?>&ldquo;<strong><?= htmlspecialchars($_search) ?></strong>&rdquo;<?php endif; ?>.
                        <a href="/admin/voters.php" style="margin-left:8px;color:#16a34a;">Clear filters</a>
                    <?php else: ?>
                        No students registered yet.
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-wrap" id="voterTableWrap">
                    <table id="voterTable">
                        <thead><tr>
                            <th>#</th>
                            <?php foreach ($columns as $col): ?>
                            <th><?= htmlspecialchars(str_replace('_', ' ', $col)) ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php $rn = ($_pg - 1) * $_perPage; foreach ($voterList as $v): $rn++; ?>
                        <tr>
                            <td><?= $rn ?></td>
                            <?php foreach ($columns as $col): $val = $v[$col] ?? '—'; $lower = strtolower($col); ?>
                            <td>
                                <?php if (stripos($lower, 'vote_status') !== false):
                                    $isV = strtolower($val) === 'voted'; ?>
                                <span class="badge-sm <?= $isV ? 'badge-voted' : 'badge-notvoted' ?>">
                                    <?= $isV ? 'Voted' : 'Not Yet Voted' ?>
                                </span>
                                <?php elseif (stripos($lower, '_id') !== false): ?>
                                <span style="font-family:monospace;font-size:12px"><?= hlSearch(is_scalar($val) ? (string)$val : '—', $_search) ?></span>
                                <?php elseif (stripos($lower, 'name') !== false): ?>
                                <?= hlSearch(is_scalar($val) ? (string)$val : '—', $_search) ?>
                                <?php else: ?>
                                <?= htmlspecialchars(is_scalar($val) ? $val : '—') ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <?php if ($_totalPages > 1):
                    $pages = [];
                    for ($i = 1; $i <= $_totalPages; $i++) {
                        if ($i === 1 || $i === $_totalPages || abs($i - $_pg) <= 2) {
                            $pages[] = $i;
                        } elseif (end($pages) !== '…') {
                            $pages[] = '…';
                        }
                    }
                ?>
                <div class="pagination">
                    <?php if ($_pg > 1): ?>
                        <a href="<?= htmlspecialchars(votersUrl(['pg' => $_pg - 1])) ?>">&lsaquo; Prev</a>
                    <?php else: ?>
                        <span class="pg-disabled">&lsaquo; Prev</span>
                    <?php endif; ?>

                    <?php foreach ($pages as $p): ?>
                        <?php if ($p === '…'): ?>
                            <span class="pg-gap">&hellip;</span>
                        <?php elseif ($p === $_pg): ?>
                            <span class="pg-active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars(votersUrl(['pg' => $p])) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($_pg < $_totalPages): ?>
                        <a href="<?= htmlspecialchars(votersUrl(['pg' => $_pg + 1])) ?>">Next &rsaquo;</a>
                    <?php else: ?>
                        <span class="pg-disabled">Next &rsaquo;</span>
                    <?php endif; ?>

                    <span class="pg-info">Page <?= $_pg ?> of <?= number_format($_totalPages) ?></span>

                    <?php if ($_totalPages > 10): ?>
                    <div class="pg-jump">
                        <label for="pgJumpInput">Go to:</label>
                        <input type="number" id="pgJumpInput" min="1" max="<?= $_totalPages ?>"
                               data-max="<?= $_totalPages ?>"
                               placeholder="<?= $_pg ?>"
                               onkeydown="if(event.key==='Enter'){event.preventDefault();jumpToPage();}"
                               onblur="jumpToPage()"/>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
</body>
</html>
