<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$success = '';
$error   = '';
$schoolYear = ELECTION_SCHOOL_YEAR;

// Handle school year / semester update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $_csrfOk = hash_equals(adminCsrfToken(), trim($_POST['_csrf'] ?? ''));
    if (!$_csrfOk) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {

    if ($_POST['action'] === 'save_college_schedule') {
        $college = trim($_POST['sched_college']    ?? '');
        $ts      = strtotime(str_replace('T', ' ', trim($_POST['sched_time_start'] ?? ''))) ?: trim($_POST['sched_time_start'] ?? '');
        $te      = strtotime(str_replace('T', ' ', trim($_POST['sched_time_end']   ?? ''))) ?: trim($_POST['sched_time_end']   ?? '');
        $sy      = trim($_POST['sched_year']       ?? $schoolYear);
        if (!$college || !$ts || !$te || !$sy) {
            $error = 'All schedule fields are required.';
        } else {
            @mkdir(DATA_DIR, 0755, true); // Ensure data directory exists
            $schedFile  = DATA_DIR . '/college_schedules.json';
            $schedStore = file_exists($schedFile) ? (json_decode(file_get_contents($schedFile), true) ?: []) : [];
            $schedStore[$college] = [
                'College'    => $college,
                'Time_Start' => $ts,
                'Time_End'   => $te,
                'School_Year'=> $sy,
                'Saved_At'   => date('Y-m-d H:i:s'),
            ];
            file_put_contents($schedFile, json_encode($schedStore, JSON_PRETTY_PRINT), LOCK_EX);
            // Also persist to DB (survives redeploys)
            try {
                $_eCfg = \Configuration\Application::$SSG_Election_DBase;
                $_ePdo = new PDO("mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                    $_eCfg['Username'], $_eCfg['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $_ex = $_ePdo->prepare('SELECT Record_ID FROM election_schedule WHERE School_Year=? AND College=? LIMIT 1');
                $_ex->execute([$sy, $college]);
                if ($_ex->fetchColumn()) {
                    $_ePdo->prepare('UPDATE election_schedule SET Time_Start=?,Time_End=? WHERE School_Year=? AND College=?')
                          ->execute([$ts, $te, $sy, $college]);
                } else {
                    $_ePdo->prepare('INSERT INTO election_schedule (Time_Start,Time_End,School_Year,College) VALUES (?,?,?,?)')
                          ->execute([$ts, $te, $sy, $college]);
                }
            } catch (\Throwable $_se) { error_log('college schedule DB save failed: ' . $_se->getMessage()); }
            $success = 'Voting schedule for ' . htmlspecialchars($college) . ' saved successfully.';
        }
    }

    if ($_POST['action'] === 'clear_college_schedule') {
        $college   = trim($_POST['clear_college'] ?? '');
        @mkdir(DATA_DIR, 0755, true); // Ensure data directory exists
        $schedFile = DATA_DIR . '/college_schedules.json';
        if ($college) {
            if (file_exists($schedFile)) {
                $schedStore = json_decode(file_get_contents($schedFile), true) ?: [];
                unset($schedStore[$college]);
                file_put_contents($schedFile, json_encode($schedStore, JSON_PRETTY_PRINT), LOCK_EX);
            }
            // Also remove from DB
            try {
                $_eCfg = \Configuration\Application::$SSG_Election_DBase;
                $_ePdo = new PDO("mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                    $_eCfg['Username'], $_eCfg['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $_ePdo->prepare('DELETE FROM election_schedule WHERE College = ?')->execute([$college]);
            } catch (\Throwable $_se) { error_log('college schedule DB clear failed: ' . $_se->getMessage()); }
            $success = 'Schedule for ' . htmlspecialchars($college) . ' cleared.';
        }
    }

    if ($_POST['action'] === 'update_settings') {
        $sy = trim($_POST['school_year'] ?? '');
        $sm = trim($_POST['semester'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}$/', $sy)) {
            $error = 'School year must be in the format YYYY-YYYY (e.g. 2026-2027).';
        } elseif (!in_array($sm, ['1st', '2nd', 'Summer'])) {
            $error = 'Semester must be 1st, 2nd, or Summer.';
        } else {
            if (saveRuntimeSettings(['school_year' => $sy, 'semester' => $sm])) {
                $success = 'Settings saved. School year is now ' . htmlspecialchars($sy) . ', ' . htmlspecialchars($sm) . ' Semester.';
            } else {
                $error = 'Could not write settings file. Check folder permissions.';
            }
        }
    }

    if ($_POST['action'] === 'create_schedule') {
        $tsRaw = trim($_POST['time_start'] ?? '');
        $teRaw = trim($_POST['time_end']   ?? '');
        $sy    = trim($_POST['sched_year'] ?? ELECTION_SCHOOL_YEAR);
        if (!$tsRaw || !$teRaw || !$sy) {
            $error = 'All schedule fields are required.';
        } else {
            // Convert datetime-local (YYYY-MM-DDTHH:MM) to Unix timestamp
            $ts = strtotime(str_replace('T', ' ', $tsRaw));
            $te = strtotime(str_replace('T', ' ', $teRaw));
            if (!$ts || !$te) {
                $error = 'Invalid date/time format. Please use the date picker.';
            } elseif ($te <= $ts) {
                $error = 'End time must be after start time.';
            } else {
                $result = callModel(function() use ($ts, $te, $sy) {
                    Election::Create_Schedule([
                        'Time_Start'  => (string)$ts,
                        'Time_End'    => (string)$te,
                        'School_Year' => $sy,
                    ]);
                });
                if (isError($result)) {
                    $error = $result['Status'] ?? 'Failed to create schedule.';
                } else {
                    // Save timestamps locally so ballot.php and settings can rely on them
                    // (the DB stored procedure does not return Time_Start/Time_End on read)
                    @mkdir(DATA_DIR, 0755, true); // Ensure data directory exists
                    $localSchedFile = DATA_DIR . '/election_schedule.json';
                    $localScheds = file_exists($localSchedFile)
                        ? (json_decode(file_get_contents($localSchedFile), true) ?: [])
                        : [];
                    $localScheds[$sy] = [
                        'School_Year' => $sy,
                        'Time_Start'  => $ts,
                        'Time_End'    => $te,
                    ];
                    file_put_contents($localSchedFile, json_encode($localScheds, JSON_PRETTY_PRINT), LOCK_EX);
                    // Also persist directly to DB (College=NULL = global schedule)
                    try {
                        $_eCfg = \Configuration\Application::$SSG_Election_DBase;
                        $_ePdo = new PDO("mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                            $_eCfg['Username'], $_eCfg['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                        $_ex = $_ePdo->prepare("SELECT Record_ID FROM election_schedule WHERE School_Year=? AND (College IS NULL OR College='') LIMIT 1");
                        $_ex->execute([$sy]);
                        if ($_ex->fetchColumn()) {
                            $_ePdo->prepare("UPDATE election_schedule SET Time_Start=?,Time_End=? WHERE School_Year=? AND (College IS NULL OR College='')")
                                  ->execute([$ts, $te, $sy]);
                        } else {
                            $_ePdo->prepare('INSERT INTO election_schedule (Time_Start,Time_End,School_Year,College) VALUES (?,?,?,NULL)')
                                  ->execute([$ts, $te, $sy]);
                        }
                    } catch (\Throwable $_se) { error_log('global schedule DB save failed: ' . $_se->getMessage()); }

                    $success = 'Election schedule created/updated for S.Y. ' . htmlspecialchars($sy)
                             . ' (' . date('M d, Y H:i', $ts) . ' – ' . date('M d, Y H:i', $te) . ')';
                }
            }
        }
    }

    if ($_POST['action'] === 'sync_party_lists') {
        $partiesFile = DATA_DIR . '/parties.json';
        $parties = file_exists($partiesFile)
            ? (json_decode(file_get_contents($partiesFile), true) ?: [])
            : [];
        if (empty($parties)) {
            $error = 'No parties found in parties.json. Add parties first.';
        } else {
            try {
                $db  = \Configuration\Application::$SSG_Candidate_DBase;
                $pdo = new PDO(
                    "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
                    $db['Username'],
                    $db['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $upsert = $pdo->prepare(
                    "INSERT INTO candidate_slate (Candidate_Slate_ID, Candidate_Slate)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE Candidate_Slate = VALUES(Candidate_Slate)"
                );
                $synced = 0;
                $ids = [];
                foreach ($parties as $p) {
                    $id   = (int)($p['id']   ?? 0);
                    $name = trim($p['name']   ?? '');
                    if (!$id || !$name) continue;
                    $upsert->execute([$id, $name]);
                    $ids[]  = $id;
                    $synced++;
                }
                // Remove stale slates that no longer exist in parties.json
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $delStmt = $pdo->prepare("DELETE FROM candidate_slate WHERE Candidate_Slate_ID NOT IN ({$placeholders})");
                    $delStmt->execute($ids);
                    $removed = $delStmt->rowCount();
                } else {
                    $removed = 0;
                }
                $success = "Party lists synced: {$synced} updated/added"
                         . ($removed ? ", {$removed} stale record(s) removed." : '.');
            } catch (PDOException $e) {
                error_log('sync_party_lists PDOException: ' . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }

    if ($_POST['action'] === 'reset_votes') {
        $yr = trim($_POST['election_year_votes'] ?? '');
        if (!$yr) {
            $error = 'Election year is required.';
        } else {
            try {
                // 1. Delete from votes DB table
                $db  = \Configuration\Application::$SSG_Election_DBase;
                $pdo = new PDO(
                    "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
                    $db['Username'], $db['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $stmt = $pdo->prepare("DELETE FROM votes WHERE School_Year = ?");
                $stmt->execute([$yr]);
                $dbDeleted = $stmt->rowCount();

                // 2. Clear matching entries from cast_votes DB table + JSON cache
                try {
                    $_ePdo2 = new PDO("mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                        $_eCfg['Username'], $_eCfg['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $_ePdo2->prepare('DELETE FROM cast_votes WHERE School_Year = ?')->execute([$yr]);
                } catch (\Throwable $_cve) { error_log('cast_votes DB clear failed: ' . $_cve->getMessage()); }
                @mkdir(DATA_DIR, 0755, true); // Ensure data directory exists
                $cvFile = DATA_DIR . '/cast_votes.json';
                $cvData = file_exists($cvFile) ? (json_decode(file_get_contents($cvFile), true) ?: []) : [];
                $prefix = $yr . '::';
                $cvData = array_filter($cvData, fn($k) => strpos($k, $prefix) !== 0, ARRAY_FILTER_USE_KEY);
                file_put_contents($cvFile, json_encode($cvData, JSON_PRETTY_PRINT), LOCK_EX);

                // 3. Clear matching entries from confirmed_profiles.json
                $cpFile = DATA_DIR . '/confirmed_profiles.json';
                $cpData = file_exists($cpFile) ? (json_decode(file_get_contents($cpFile), true) ?: []) : [];
                $cpData = array_filter($cpData, fn($k) => strpos($k, $prefix) !== 0, ARRAY_FILTER_USE_KEY);
                file_put_contents($cpFile, json_encode($cpData, JSON_PRETTY_PRINT), LOCK_EX);

                // 4. Write reset timestamp so active voter sessions are invalidated on next page load
                $voteResetFile = DATA_DIR . '/vote_reset.json';
                $voteResets    = file_exists($voteResetFile) ? (json_decode(file_get_contents($voteResetFile), true) ?: []) : [];
                $voteResets[$yr] = time();
                file_put_contents($voteResetFile, json_encode($voteResets, JSON_PRETTY_PRINT), LOCK_EX);

                // 5. Append reset notice to audit log
                $logFile = dirname(dirname(__DIR__)) . '/logs/vote_audit.log';
                @file_put_contents($logFile,
                    sprintf("[%s] VOTE_RESET year=%s ip=%s admin=%s\n",
                        date('Y-m-d H:i:s T'), $yr,
                        filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown',
                        preg_replace('/[^A-Za-z0-9\-_]/', '', $_SESSION['admin_user'] ?? 'admin')
                    ), FILE_APPEND | LOCK_EX);

                $success = "Votes reset for S.Y. {$yr}: {$dbDeleted} vote record(s) deleted. All students can vote again — active sessions will be cleared on their next visit.";
            } catch (PDOException $e) {
                error_log('reset_votes PDOException: ' . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }

    if ($_POST['action'] === 'reset_candidates') {
        $yr = trim($_POST['election_year'] ?? '');
        if (!$yr) {
            $error = 'Election year is required.';
        } else {
            try {
                $db  = \Configuration\Application::$SSG_Candidate_DBase;
                $pdo = new PDO(
                    "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4;",
                    $db['Username'],
                    $db['Password'],
                    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $stmt = $pdo->prepare("DELETE FROM candidate_position WHERE Election_Year = ?");
                $stmt->execute([$yr]);
                $deleted = $stmt->rowCount();
                $success = "All {$deleted} candidate record(s) for S.Y. " . htmlspecialchars($yr) . " have been permanently deleted.";
            } catch (PDOException $e) {
                error_log('reset_candidates PDOException: ' . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }

    } // end CSRF else
}

// Load college schedules from JSON file, fallback to DB
$collegeSchedFile = DATA_DIR . '/college_schedules.json';
$collegeSchedules = file_exists($collegeSchedFile)
    ? (json_decode(file_get_contents($collegeSchedFile), true) ?: [])
    : [];

// If no schedules in JSON, try to load from database
if (empty($collegeSchedules)) {
    try {
        $_eCfg = \Configuration\Application::$SSG_Election_DBase;
        $_ePdo = new PDO("mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
            $_eCfg['Username'], $_eCfg['Password'], [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $_cStmt = $_ePdo->prepare("SELECT Record_ID, Time_Start, Time_End, School_Year, College FROM election_schedule WHERE College IS NOT NULL AND College != '' ORDER BY College");
        $_cStmt->execute();
        foreach ($_cStmt->fetchAll(PDO::FETCH_ASSOC) as $_row) {
            $_college = trim($_row['College'] ?? '');
            if ($_college !== '') {
                $collegeSchedules[$_college] = [
                    'College'    => $_college,
                    'Time_Start' => (int)($_row['Time_Start'] ?? 0),
                    'Time_End'   => (int)($_row['Time_End'] ?? 0),
                    'School_Year'=> $_row['School_Year'] ?? '',
                    'Saved_At'   => '—',
                ];
            }
        }
    } catch (\Throwable $_dbErr) {
        // Database unavailable, just use empty array
    }
}

// Reload constants after possible save
$settingsFile = DATA_DIR . '/settings.json';
$currentSettings = file_exists($settingsFile)
    ? (json_decode(file_get_contents($settingsFile), true) ?: [])
    : [];
$currentSY = $currentSettings['school_year'] ?? ELECTION_SCHOOL_YEAR;
$currentSM = $currentSettings['semester']    ?? ELECTION_SEMESTER;

// Load current schedule from local JSON (source of truth for timestamps)
$localSchedFile = DATA_DIR . '/election_schedule.json';
$localScheds    = file_exists($localSchedFile)
    ? (json_decode(file_get_contents($localSchedFile), true) ?: [])
    : [];
$localSched = $localScheds[$currentSY] ?? null;

// Compute status from local timestamps
$schedStatus      = 'no_schedule';
$schedStatusLabel = '';
$schedStart       = $localSched ? (int)($localSched['Time_Start'] ?? 0) : 0;
$schedEnd         = $localSched ? (int)($localSched['Time_End']   ?? 0) : 0;
if ($schedStart && $schedEnd) {
    $now = time();
    if ($now < $schedStart) {
        $schedStatus      = 'upcoming';
        $schedStatusLabel = 'Not Yet Open';
    } elseif ($now > $schedEnd) {
        $schedStatus      = 'closed';
        $schedStatusLabel = 'Closed';
    } else {
        $schedStatus      = 'open';
        $schedStatusLabel = 'OPEN';
    }
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
    <title>Settings &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .content { max-width:860px; }
        .card { padding:26px 28px; }
        .card h3 { font-size:15px; font-weight:800; color:#1a3a8f; margin-bottom:18px; }
        .current-val { display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #86efac; color:#15803d; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:700; margin-bottom:18px; }
        .empty-note { color:#9ca3af; font-size:13px; padding:20px 0; text-align:center; }
        .form-row { grid-template-columns:1fr 1fr; }
        .form-row-single { display:grid; grid-template-columns:1fr; max-width:320px; }
        .sched-college-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
        @media(max-width:900px){ .sched-college-grid { grid-template-columns:1fr 1fr; } }
        @media(max-width:600px){ .sched-college-grid { grid-template-columns:1fr; } }
        @media(max-width:768px){
            .content { max-width:100% !important; }
            .form-row { grid-template-columns:1fr !important; }
            .card { padding:18px 16px; }
            .current-val { flex-direction:column; align-items:flex-start; }
        }
        /* ── Schedule Status Card ── */
        .sched-status-block { display:flex; align-items:center; gap:16px; padding:16px 20px; border-radius:12px; margin-bottom:20px; }
        .sched-open   { background:#f0fdf4; border:1.5px solid #86efac; }
        .sched-upcoming { background:#fffbeb; border:1.5px solid #fcd34d; }
        .sched-closed { background:#fef2f2; border:1.5px solid #fca5a5; }
        .sched-status-icon { font-size:28px; line-height:1; }
        .sched-status-text { display:flex; flex-direction:column; gap:3px; }
        .sched-status-text strong { font-size:15px; font-weight:800; }
        .sched-open   .sched-status-text strong { color:#15803d; }
        .sched-upcoming .sched-status-text strong { color:#92400e; }
        .sched-closed .sched-status-text strong { color:#b91c1c; }
        .sched-status-text span { font-size:12px; color:#6b7280; }
        .sched-detail-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:4px; }
        @media(max-width:640px){ .sched-detail-grid { grid-template-columns:1fr 1fr; } }
        .sched-detail-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
        .sched-detail-label { font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
        .sched-detail-val { font-size:14px; font-weight:700; color:#1a3a8f; line-height:1.4; }
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
            <a href="/admin/candidates.php" class="nav-item">Candidates</a>
            <a href="/admin/voters.php" class="nav-item">Voters</a>
            <a href="/admin/results.php" class="nav-item">Results</a>
            <a href="/admin/users.php" class="nav-item">Users</a>
            <a href="/admin/settings.php" class="nav-item active">Settings</a>
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
            <div class="topbar-title">Settings</div>
            <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
        </div>

        <div class="content">

            <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- School Year & Semester -->
            <div class="section-title">Election Period</div>
            <div class="card">
                <h3> School Year &amp; Semester</h3>
                <div class="current-val">
                    Currently active: S.Y. <?= htmlspecialchars($currentSY) ?> &bull; <?= htmlspecialchars($currentSM) ?> Semester
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings"/>
                    <?= adminCsrfField() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school_year">School Year</label>
                            <input type="text" id="school_year" name="school_year"
                                   value="<?= htmlspecialchars($currentSY) ?>"
                                   placeholder="e.g. 2026-2027" required/>
                            <div class="hint">Format: YYYY-YYYY</div>
                        </div>
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester">
                                <option value="1st"    <?= $currentSM === '1st'    ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2nd"    <?= $currentSM === '2nd'    ? 'selected' : '' ?>>2nd Semester</option>
                                <option value="Summer" <?= $currentSM === 'Summer' ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"> Save Settings</button>
                </form>
                <p style="font-size:12px;color:#9ca3af;margin-top:14px;">
                     Changing the school year affects which election data is shown across the entire voter portal and admin panel.
                </p>
            </div>

            <!-- Election Schedule -->
            <div class="section-title">Election Schedule</div>
            <div class="card">
                <h3> Create / Update Election Schedule</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_schedule"/>
                    <?= adminCsrfField() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="time_start">Start Date &amp; Time</label>
                            <input type="datetime-local" id="time_start" name="time_start" required/>
                        </div>
                        <div class="form-group">
                            <label for="time_end">End Date &amp; Time</label>
                            <input type="datetime-local" id="time_end" name="time_end" required/>
                        </div>
                    </div>
                    <div class="form-group" style="max-width:280px;">
                        <label for="sched_year">School Year for Schedule</label>
                        <input type="text" id="sched_year" name="sched_year"
                               value="<?= htmlspecialchars($currentSY) ?>" required/>
                    </div>
                    <button type="submit" class="btn btn-blue"> Create Schedule</button>
                </form>
            </div>

            <!-- Current Schedule Info -->
            <div class="card" id="currentSchedCard">
                <h3>Current Schedule &mdash; S.Y. <?= htmlspecialchars($currentSY) ?></h3>
                <?php if ($localSched && $schedStart && $schedEnd): ?>
                <div class="sched-status-block sched-<?= $schedStatus ?>">
                    <?php if ($schedStatus === 'open'): ?>
                        <div class="sched-status-icon">&#9989;</div>
                        <div class="sched-status-text">
                            <strong>Voting is currently OPEN</strong>
                            <span>Closes on <?= date('F j, Y \a\t g:i A', $schedEnd) ?></span>
                        </div>
                    <?php elseif ($schedStatus === 'upcoming'): ?>
                        <div class="sched-status-icon">&#9200;</div>
                        <div class="sched-status-text">
                            <strong>Voting is Not Yet Open</strong>
                            <span>Opens on <?= date('F j, Y \a\t g:i A', $schedStart) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="sched-status-icon">&#128274;</div>
                        <div class="sched-status-text">
                            <strong>Voting is Closed</strong>
                            <span>Ended on <?= date('F j, Y \a\t g:i A', $schedEnd) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sched-detail-grid">
                    <div class="sched-detail-item">
                        <div class="sched-detail-label">School Year</div>
                        <div class="sched-detail-val"><?= htmlspecialchars($localSched['School_Year']) ?></div>
                    </div>
                    <div class="sched-detail-item">
                        <div class="sched-detail-label">Start</div>
                        <div class="sched-detail-val"><?= date('F j, Y', $schedStart) ?><br><span style="font-size:13px;color:#6b7280;"><?= date('g:i A', $schedStart) ?></span></div>
                    </div>
                    <div class="sched-detail-item">
                        <div class="sched-detail-label">End</div>
                        <div class="sched-detail-val"><?= date('F j, Y', $schedEnd) ?><br><span style="font-size:13px;color:#6b7280;"><?= date('g:i A', $schedEnd) ?></span></div>
                    </div>
                    <div class="sched-detail-item">
                        <div class="sched-detail-label">Duration</div>
                        <?php $dur = $schedEnd - $schedStart; $h = floor($dur/3600); $m = floor(($dur%3600)/60); ?>
                        <div class="sched-detail-val"><?= $h ?>h <?= $m ?>m</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-note" style="padding:28px 0;">
                    <div style="font-size:32px;margin-bottom:8px;">&#128197;</div>
                    No schedule set for S.Y. <?= htmlspecialchars($currentSY) ?>.<br>Use the form above to create one.
                </div>
                <?php endif; ?>
            </div>

            <!-- Election Schedule Overview Table -->
            <div class="card">
                <div class="card-header-bar">
                    <h3>All Election Schedules</h3>
                    <span><?= count($localScheds) ?> schedule<?= count($localScheds) !== 1 ? 's' : '' ?> configured</span>
                </div>
                <?php if (empty($localScheds)): ?>
                <div class="empty-state" style="padding:28px 20px;">
                    <div class="icon"></div>
                    No election schedules saved yet. Use the form above to add one.
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>School Year</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $now = new DateTime();
                        foreach ($localScheds as $es) {
                            try {
                                $tsVal = $es['Time_Start'];
                                if (is_numeric($tsVal)) {
                                    $dtStart = (new DateTime())->setTimestamp((int)$tsVal);
                                } else {
                                    $dtStart = DateTime::createFromFormat('Y-m-d\TH:i', $tsVal) ?: new DateTime($tsVal);
                                }
                                $teVal = $es['Time_End'];
                                if (is_numeric($teVal)) {
                                    $dtEnd = (new DateTime())->setTimestamp((int)$teVal);
                                } else {
                                    $dtEnd = DateTime::createFromFormat('Y-m-d\TH:i', $teVal) ?: new DateTime($teVal);
                                }
                                $isActive = $now >= $dtStart && $now <= $dtEnd;
                                $isPast   = $now > $dtEnd;
                                $statusLabel = $isActive ? 'Open' : ($isPast ? 'Closed' : 'Upcoming');
                                $statusClass = $isActive ? 'badge-active' : ($isPast ? 'badge-inactive' : 'badge-other');
                                $dur = $dtEnd->getTimestamp() - $dtStart->getTimestamp(); 
                                $h = floor($dur/3600); 
                                $m = floor(($dur%3600)/60);
                        ?>
                                <tr>
                                    <td style="font-weight:700;color:#1a3a8f;">
                                        <?= htmlspecialchars($es['School_Year']) ?>
                                    </td>
                                    <td style="font-size:13px;white-space:nowrap;">
                                        <?= htmlspecialchars(is_numeric($es['Time_Start']) ? date('M d, Y H:i', (int)$es['Time_Start']) : str_replace('T', ' ', $es['Time_Start'])) ?>
                                    </td>
                                    <td style="font-size:13px;white-space:nowrap;">
                                        <?= htmlspecialchars(is_numeric($es['Time_End']) ? date('M d, Y H:i', (int)$es['Time_End']) : str_replace('T', ' ', $es['Time_End'])) ?>
                                    </td>
                                    <td><span class="badge-sm <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td style="font-size:13px;"><?= $h ?>h <?= $m ?>m</td>
                                </tr>
                        <?php
                            } catch (\Throwable $e) {
                                error_log('Invalid datetime in localScheds: ' . $e->getMessage());
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Voting Time Schedule by College -->
            <div class="section-title">Voting Time Schedule by College</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3>Set College Voting Window</h3>
                    <span>Assign a specific start and end time per college</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_college_schedule"/>
                        <?= adminCsrfField() ?>
                        <div class="sched-college-grid">
                            <div class="form-group">
                                <label>College</label>
                                <select name="sched_college" required>
                                    <option value="">-- Select College --</option>
                                    <option value="CAS">CAS — College of Arts & Sciences</option>
                                    <option value="CBA">CBA — College of Business Administration</option>
                                    <option value="CCJE">CCJE — College of Criminal Justice Education</option>
                                    <option value="CCS">CCS — College of Computer Studies</option>
                                    <option value="CIT">CIT — College of Industrial Technology</option>
                                    <option value="CME">CME — College of Marine Engineering</option>
                                    <option value="CNAHS">CNAHS — College of Nursing, Allied Health Sciences</option>
                                    <option value="COE">COE — College of Engineering</option>
                                    <option value="COL">COL — College of Law</option>
                                    <option value="CTED">CTED — College of Teacher Education</option>
                                    <option value="CTED_HS">CTED — Laboratory High School</option>
                                    <option value="GRAD">GRAD — Graduate School</option>
                                    <option value="HS">HS — High School</option>
                                    <option value="SOM">SOM — School of Medicine</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Start Date &amp; Time</label>
                                <input type="datetime-local" name="sched_time_start" required/>
                            </div>
                            <div class="form-group">
                                <label>End Date &amp; Time</label>
                                <input type="datetime-local" name="sched_time_end" required/>
                            </div>
                        </div>
                        <div class="form-row-single" style="margin-top:4px;">
                            <div class="form-group">
                                <label>School Year</label>
                                <input type="text" name="sched_year" value="<?= htmlspecialchars($currentSY) ?>" required/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Save Schedule</button>
                    </form>
                </div>
            </div>

            <!-- College Schedule Overview Table -->
            <div class="card">
                <div class="card-header-bar">
                    <h3>Current College Schedules</h3>
                    <span><?= count($collegeSchedules) ?> college<?= count($collegeSchedules) !== 1 ? 's' : '' ?> configured</span>
                </div>
                <?php if (empty($collegeSchedules)): ?>
                <div class="empty-state" style="padding:28px 20px;">
                    <div class="icon"></div>
                    No college schedules saved yet. Use the form above to add one.
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>College</th>
                                <th>School Year</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Saved At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $now = new DateTime();
                        foreach ($collegeSchedules as $cs) {
                            try {
                                $tsVal = $cs['Time_Start'];
                                if (is_numeric($tsVal)) {
                                    $dtStart = (new DateTime())->setTimestamp((int)$tsVal);
                                } else {
                                    $dtStart = DateTime::createFromFormat('Y-m-d\TH:i', $tsVal) ?: new DateTime($tsVal);
                                }
                                $teVal = $cs['Time_End'];
                                if (is_numeric($teVal)) {
                                    $dtEnd = (new DateTime())->setTimestamp((int)$teVal);
                                } else {
                                    $dtEnd = DateTime::createFromFormat('Y-m-d\TH:i', $teVal) ?: new DateTime($teVal);
                                }
                                $isActive = $now >= $dtStart && $now <= $dtEnd;
                                $isPast   = $now > $dtEnd;
                                $statusLabel = $isActive ? 'Open' : ($isPast ? 'Closed' : 'Upcoming');
                                $statusClass = $isActive ? 'badge-active' : ($isPast ? 'badge-inactive' : 'badge-other');
                        ?>
                                <tr>
                                    <td style="font-weight:700;color:#1a3a8f;max-width:220px;">
                                        <?= htmlspecialchars($cs['College']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($cs['School_Year'] ?? '—') ?></td>
                                    <td style="font-size:13px;white-space:nowrap;">
                                        <?= htmlspecialchars(is_numeric($cs['Time_Start']) ? date('M d, Y H:i', (int)$cs['Time_Start']) : str_replace('T', ' ', $cs['Time_Start'])) ?>
                                    </td>
                                    <td style="font-size:13px;white-space:nowrap;">
                                        <?= htmlspecialchars(is_numeric($cs['Time_End']) ? date('M d, Y H:i', (int)$cs['Time_End']) : str_replace('T', ' ', $cs['Time_End'])) ?>
                                    </td>
                                    <td><span class="badge-sm <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td style="font-size:12px;color:#9ca3af;">
                                        <?= htmlspecialchars($cs['Saved_At'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-red" style="padding:5px 12px;font-size:12px;" onclick="openClearScheduleModal('<?= htmlspecialchars(addslashes($cs['College'])) ?>');">Clear</button>
                                    </td>
                                </tr>
                        <?php
                            } catch (\Throwable $e) {
                                error_log('Invalid datetime in collegeSchedules: ' . $e->getMessage());
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Maintenance -->
            <div class="section-title">Maintenance</div>
            <div class="card">
                <h3>📋 Sync Governor & Vice-Governor Colleges</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:18px;">
                    Automatically assigns colleges to all governors and vice-governors based on their program codes.
                    This creates/updates the <code>candidate_college.json</code> file which is used to separate them by college on the tally page.
                </p>
                <button type="button" class="btn btn-primary" id="syncGovButton" onclick="syncGovernorColleges()" style="background:#0891b2;border:none;cursor:pointer;">
                    🔄 Sync Now
                </button>
                <div id="syncGovStatus" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </div>

            <!-- Danger Zone -->
            <div class="section-title" style="color:#b91c1c;">Danger Zone</div>
            <div class="card" style="border:2px solid #fca5a5;background:#fff8f8;">
                <h3 style="color:#b91c1c;">&#9888; Reset All Candidates</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:18px;">
                    Permanently deletes <strong>every candidate record</strong> for the selected school year from the database.
                    This is useful during testing. <strong>This cannot be undone.</strong>
                </p>
                <button type="button" class="btn btn-red" onclick="openResetModal()">&#128465; Reset All Candidates&hellip;</button>
            </div>

            <div class="card" style="border:2px solid #fca5a5;background:#fff8f8;margin-top:16px;">
                <h3 style="color:#b91c1c;">&#9888; Reset All Votes</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:18px;">
                    Permanently deletes <strong>every vote record</strong> for the selected school year from the database,
                    the cast-vote log, and profile confirmations — so all students can vote again from scratch.
                    <strong>This cannot be undone.</strong>
                </p>
                <button type="button" class="btn btn-red" onclick="openResetVotesModal()">&#128465; Reset All Votes&hellip;</button>
            </div>

            <!-- Reset Votes Modal -->
            <div class="modal-backdrop" id="resetVotesModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
                <div class="modal-box" style="background:#fff;border-radius:14px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.18);">
                    <h4 style="color:#b91c1c;margin:0 0 10px;">&#9888; Reset All Votes</h4>
                    <p style="font-size:13px;color:#374151;margin-bottom:16px;">
                        This will permanently delete <strong>all vote records</strong> for the school year below and allow all students to vote again.
                        Type the school year to confirm.
                    </p>
                    <form method="POST" id="resetVotesForm" onsubmit="return validateResetVotes()">
                        <input type="hidden" name="action" value="reset_votes"/>
                        <?= adminCsrfField() ?>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:700;">School Year to reset</label>
                            <input type="text" name="election_year_votes"
                                   value="<?= htmlspecialchars($currentSY) ?>"
                                   style="border:1.5px solid #fca5a5;border-radius:8px;padding:8px 12px;font-size:14px;width:100%;box-sizing:border-box;" readonly/>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label style="font-size:13px;font-weight:700;">Type <code><?= htmlspecialchars($currentSY) ?></code> to confirm</label>
                            <input type="text" id="resetVotesConfirmInput" autocomplete="off"
                                   placeholder="<?= htmlspecialchars($currentSY) ?>"
                                   style="border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;width:100%;box-sizing:border-box;"/>
                        </div>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" class="btn" onclick="closeResetVotesModal()" style="background:#f3f4f6;color:#374151;">Cancel</button>
                            <button type="submit" class="btn btn-red" id="resetVotesSubmitBtn" disabled>Delete All Votes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reset Candidates Modal -->
            <div class="modal-backdrop" id="resetCandidatesModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
                <div class="modal-box" style="background:#fff;border-radius:14px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.18);">
                    <h4 style="color:#b91c1c;margin:0 0 10px;">&#9888; Reset All Candidates</h4>
                    <p style="font-size:13px;color:#374151;margin-bottom:16px;">
                        This will permanently delete <strong>all candidate records</strong> for the school year below.
                        Type the school year to confirm.
                    </p>
                    <form method="POST" id="resetCandidatesForm" onsubmit="return validateReset()">
                        <input type="hidden" name="action" value="reset_candidates"/>
                        <?= adminCsrfField() ?>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:700;">School Year to reset</label>
                            <input type="text" name="election_year" id="resetYearValue"
                                   value="<?= htmlspecialchars($currentSY) ?>"
                                   style="border:1.5px solid #fca5a5;border-radius:8px;padding:8px 12px;font-size:14px;width:100%;box-sizing:border-box;" readonly/>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label style="font-size:13px;font-weight:700;">Type <code><?= htmlspecialchars($currentSY) ?></code> to confirm</label>
                            <input type="text" id="resetConfirmInput" autocomplete="off"
                                   placeholder="<?= htmlspecialchars($currentSY) ?>"
                                   style="border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:14px;width:100%;box-sizing:border-box;"/>
                        </div>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" class="btn" onclick="closeResetModal()" style="background:#f3f4f6;color:#374151;">Cancel</button>
                            <button type="submit" class="btn btn-red" id="resetSubmitBtn" disabled>Delete All</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Clear College Schedule Modal -->
            <div class="modal-backdrop" id="clearScheduleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
                <div class="modal-box" style="background:#fff;border-radius:14px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.18);">
                    <h4 style="color:#b91c1c;margin:0 0 10px;">&#9888; Clear Schedule</h4>
                    <p style="font-size:13px;color:#374151;margin-bottom:16px;">
                        Are you sure you want to clear the voting schedule for <strong id="clearCollegeName" style="color:#1a3a8f;">—</strong>?
                    </p>
                    <div style="background:#fef2f2;border-left:3px solid #b91c1c;padding:12px;border-radius:6px;font-size:13px;color:#7f1d1d;margin-bottom:20px;">
                        <strong>⚠️ Warning:</strong> This action will remove the voting schedule. Students will not be able to vote during this period.
                    </div>
                    <form id="clearScheduleForm" method="POST">
                        <input type="hidden" name="action" value="clear_college_schedule"/>
                        <?= adminCsrfField() ?>
                        <input type="hidden" name="clear_college" id="clearCollegeHidden" value=""/>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" class="btn" onclick="closeClearScheduleModal()" style="background:#f3f4f6;color:#374151;">Cancel</button>
                            <button type="submit" class="btn btn-red">Clear Schedule</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
<script>
var _resetExpected = <?= json_encode($currentSY) ?>;

// Clear College Schedule Modal
function openClearScheduleModal(collegeName) {
    document.getElementById('clearCollegeName').textContent = collegeName;
    document.getElementById('clearCollegeHidden').value = collegeName;
    document.getElementById('clearScheduleModal').style.display = 'flex';
}
function closeClearScheduleModal() {
    document.getElementById('clearScheduleModal').style.display = 'none';
}
document.getElementById('clearScheduleModal').addEventListener('click', function(e) {
    if (e.target === this) closeClearScheduleModal();
});

function openResetModal() {
    var m = document.getElementById('resetCandidatesModal');
    m.style.display = 'flex';
    document.getElementById('resetConfirmInput').value = '';
    document.getElementById('resetSubmitBtn').disabled = true;
    setTimeout(function(){ document.getElementById('resetConfirmInput').focus(); }, 80);
}
function closeResetModal() {
    document.getElementById('resetCandidatesModal').style.display = 'none';
}
document.getElementById('resetCandidatesModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
document.getElementById('resetConfirmInput').addEventListener('input', function() {
    document.getElementById('resetSubmitBtn').disabled = (this.value.trim() !== _resetExpected);
});
function validateReset() {
    if (document.getElementById('resetConfirmInput').value.trim() !== _resetExpected) {
        alert('School year does not match. Please type it exactly.');
        return false;
    }
    return true;
}

function openResetVotesModal() {
    var m = document.getElementById('resetVotesModal');
    m.style.display = 'flex';
    document.getElementById('resetVotesConfirmInput').value = '';
    document.getElementById('resetVotesSubmitBtn').disabled = true;
    setTimeout(function(){ document.getElementById('resetVotesConfirmInput').focus(); }, 80);
}
function closeResetVotesModal() {
    document.getElementById('resetVotesModal').style.display = 'none';
}
document.getElementById('resetVotesModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetVotesModal();
});
document.getElementById('resetVotesConfirmInput').addEventListener('input', function() {
    document.getElementById('resetVotesSubmitBtn').disabled = (this.value.trim() !== _resetExpected);
});
function validateResetVotes() {
    if (document.getElementById('resetVotesConfirmInput').value.trim() !== _resetExpected) {
        alert('School year does not match. Please type it exactly.');
        return false;
    }
    return true;
}

function syncGovernorColleges() {
    const btn = document.getElementById('syncGovButton');
    const statusDiv = document.getElementById('syncGovStatus');
    
    btn.disabled = true;
    btn.textContent = '⏳ Syncing...';
    statusDiv.innerHTML = '';
    statusDiv.style.display = 'none';
    
    fetch('/api-version1/admin/sync-governor-colleges.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                statusDiv.style.background = '#d1fae5';
                statusDiv.style.color = '#065f46';
                statusDiv.style.border = '1px solid #a7f3d0';
                statusDiv.innerHTML = `
                    <strong>✓ Sync Successful!</strong><br/>
                    <small>
                        Assigned colleges: ${data.colleges_assigned}<br/>
                        Names added: ${data.names_added}<br/>
                        Total entries: ${data.total_in_candidate_college_json}
                    </small>
                `;
            } else {
                statusDiv.style.background = '#fee2e2';
                statusDiv.style.color = '#991b1b';
                statusDiv.style.border = '1px solid #fecaca';
                statusDiv.innerHTML = `<strong>✗ Sync Failed</strong><br/><small>${data.error || 'Unknown error'}</small>`;
            }
            statusDiv.style.display = 'block';
        })
        .catch(err => {
            statusDiv.style.background = '#fee2e2';
            statusDiv.style.color = '#991b1b';
            statusDiv.style.border = '1px solid #fecaca';
            statusDiv.innerHTML = `<strong>✗ Error</strong><br/><small>${err.message}</small>`;
            statusDiv.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = '🔄 Sync Now';
        });
}
</script>
</body>
</html>
