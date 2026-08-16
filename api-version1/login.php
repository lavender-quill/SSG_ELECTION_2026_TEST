<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/rate-limit.php';

// Already logged in
if (!empty($_SESSION['logged_in'])) {
    header('Location: /dashboard.php');
    exit;
}

$error      = '';
$errorIsHtml = false; // set true when $error already contains safe HTML

// Reason redirected from ballot gate
if (($_GET['reason'] ?? '') === 'not_enrolled') {
    $error = 'Your session was ended because you are not currently enrolled. Only enrolled students may vote. Please contact the Registrar\'s Office if you believe this is an error.';
}

// ── ARMS API helpers ──────────────────────────────────────────────────────────
define('ARMS_TOKEN_URL', 'https://jrmsu-arms.online/api/version-2/services/credential/token/request');
define('ARMS_LOGIN_URL', 'https://jrmsu-arms.online/api/version-2/services/student/account/login');

$JRMSU_HEADERS = [
    'User-Agent: Coderstation-Protocol',
    'Referer: https://jrmsu-election-system.vercel.app/',
    'Origin: https://jrmsu-election-system.vercel.app',
];

function armsPost(string $url, array $headers, ?string $body = null): array {
    // Use the system curl binary — PHP libcurl is blocked by Cloudflare (TLS fingerprint),
    // while the system curl binary passes the check correctly.
    $headerFlags = '';
    foreach ($headers as $h) {
        $headerFlags .= ' -H ' . escapeshellarg($h);
    }

    $bodyFlag = '';
    if ($body !== null) {
        $bodyFlag = ' -d ' . escapeshellarg($body);
    }

    // -w appends http_code after a separator so we can parse it cleanly
    $cmd = 'curl -s -X POST --max-time 15 -k'
         . $headerFlags
         . $bodyFlag
         . ' -w ' . escapeshellarg("\n__CODE__%{http_code}")
         . ' ' . escapeshellarg($url)
         . ' 2>/dev/null';

    $output = shell_exec($cmd) ?? '';
    $parts  = explode("\n__CODE__", $output);
    $raw    = trim($parts[0] ?? '');
    $code   = intval($parts[1] ?? 0);

    if ($raw === '') return ['__error' => 'Empty response from server.', '__code' => $code];
    $json = json_decode($raw, true);
    if (!is_array($json)) return ['__error' => 'Unexpected response.', '__code' => $code];
    $json['__code'] = $code;
    return $json;
}

function armsGetToken(array $baseHeaders): array {
    $headers = array_merge($baseHeaders, [
        'Api-Key: '    . ARMS_API_KEY,
        'Api-Secret: ' . ARMS_API_SECRET,
    ]);
    return armsPost(ARMS_TOKEN_URL, $headers);
}

function armsLogin(string $secretKey, string $jwToken, string $username, string $password, array $baseHeaders): array {
    $headers = array_merge($baseHeaders, [
        'Secret-Key: ' . $secretKey,
        'Token: ' . $jwToken,
        'Authorization: Bearer ' . $jwToken,
        'Content-Type: application/json',
    ]);
    $body = json_encode(['Username' => $username, 'Password' => $password]);
    return armsPost(ARMS_LOGIN_URL, $headers, $body);
}
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $password  = trim($_POST['password']   ?? '');

    if (empty($studentId) || empty($password)) {
        $error = 'Please enter your Student ID and Password.';
    } else {
        // ── Rate limiting: max 5 attempts per IP per 15 minutes ──────────────
        $_ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $_rlCheck = rateLimit('login', $_ip, 5, 900);
        if ($_rlCheck['blocked']) {
            $minutes = (int)ceil($_rlCheck['retry_after'] / 60);
            $error   = "Too many failed attempts. Please try again in {$minutes} minute" . ($minutes !== 1 ? 's' : '') . '.';
        } else {
        // ─────────────────────────────────────────────────────────────────────

        // Step 1 — get token
        $tokenResp = armsGetToken($JRMSU_HEADERS);

        if (isset($tokenResp['__error'])) {
            $error = 'Could not reach the authentication server. Please try again.';
        } elseif ($tokenResp['__code'] >= 400) {
            $error = $tokenResp['message'] ?? 'Authentication service error. Please try again.';
        } else {
            $secretKey = $tokenResp['Secret_Key'] ?? $tokenResp['SecretKey'] ?? $tokenResp['secretKey'] ?? '';
            $jwToken   = $tokenResp['JWToken']    ?? $tokenResp['Token']    ?? $tokenResp['jwToken']   ?? '';

            if (!$secretKey || !$jwToken) {
                $error = 'Could not retrieve access token. Please try again later.';
            } else {
                // Step 2 — student login
                $loginResp = armsLogin($secretKey, $jwToken, $studentId, $password, $JRMSU_HEADERS);

                if (isset($loginResp['__error'])) {
                    $error = 'Login service unreachable. Please try again.';
                    rateLimitIncrement('login', $_ip, 900);
                } elseif ($loginResp['__code'] >= 400 || !isset($loginResp['Record']) || $loginResp['Record'] === null) {
                    $error = $loginResp['message'] ?? 'Incorrect Student ID or password. Please try again.';
                    rateLimitIncrement('login', $_ip, 900);
                } else {
                    $record = $loginResp['Record'];

                    // ── Enrollment check ─────────────────────────────────────
                    // ARMS is the authoritative source. If it explicitly tells us
                    // the student is NOT enrolled, block login immediately.
                    // If the field is absent (ARMS did not return it), we allow
                    // the login so a missing field never locks out valid voters.
                    $_rawEnrolStatus = $record['Enrollment_Status']
                                    ?? $record['enrollment_status']
                                    ?? $record['EnrollmentStatus']
                                    ?? null;
                    $_enrolStatus = $_rawEnrolStatus !== null
                                  ? strtolower(trim((string)$_rawEnrolStatus))
                                  : null;

                    // Accept "enrolled", "active", or any string containing "enroll"
                    $_isEnrolled = $_enrolStatus === null               // field absent → allow
                                || str_contains($_enrolStatus, 'enroll')
                                || $_enrolStatus === 'active';

                    if (!$_isEnrolled) {
                        // Count this as a failed attempt to prevent brute-force probing
                        rateLimitIncrement('login', $_ip, 900);
                        $error = 'Access denied. Only currently enrolled students may vote. '
                               . 'Your enrollment status from JRMSU-ARMS: <strong>'
                               . htmlspecialchars(ucfirst($_enrolStatus))
                               . '</strong>. If you believe this is an error, please contact the Registrar\'s Office.';
                        $errorIsHtml = true;
                    } else {
                    // ─────────────────────────────────────────────────────────

                    rateLimitReset('login', $_ip);
                    session_regenerate_id(true);

                    $_SESSION['logged_in']        = true;
                    $_SESSION['enrollment_verified'] = true; // passed ARMS enrollment gate
                    $_SESSION['student_id']  = $record['Student_ID']       ?? $record['student_id']  ?? $studentId;
                    $_SESSION['student_name']= $record['Student_Name']     ?? $record['student_name'] ?? '';
                    $_SESSION['college']     = $record['College']          ?? $record['college']      ?? '';
                    $_SESSION['year_level']  = $record['Year_Level']       ?? $record['year_level']   ?? '';
                    $_SESSION['program']     = $record['Program_Enrolled'] ?? $record['program']      ?? '';
                    $_SESSION['semester']    = $record['Semester']         ?? ELECTION_SEMESTER;
                    $_SESSION['school_year'] = $record['School_Year']      ?? ELECTION_SCHOOL_YEAR;
                    // Store only the fields needed by dashboard/profile pages — never the full API record
                    $_SESSION['voter'] = array_intersect_key($record, array_flip([
                        'Student_ID', 'Student_Name', 'Sex', 'Gender', 'Birth_Date',
                        'Email', 'Mobile', 'College', 'College_Code',
                        'Program_Enrolled', 'Program_Code', 'Year_Level',
                        'Semester', 'School_Year', 'Enrollment_Status',
                    ]));

                    // Resolve college code — ARMS may return it directly, or embed it
                    // as the first token of the College field (e.g. "CCS COLLEGE OF COMPUTER STUDIES")
                    $_knownCodes = ['CCS','CBA','CTED','CAS','CCJE','CIT','CME','CNAHS','COE','COL','GRAD','HS','SOM'];
                    $_cc = strtoupper(trim($record['College_Code'] ?? $record['college_code'] ?? ''));
                    if ($_cc === '' || !in_array($_cc, $_knownCodes, true)) {
                        // Try first token of College field
                        $_collegeStr = strtoupper(trim($_SESSION['college']));
                        $_firstToken = preg_split('/[\s\-]+/', $_collegeStr)[0] ?? '';
                        if (in_array($_firstToken, $_knownCodes, true)) {
                            $_cc = $_firstToken;
                        }
                    }
                    if ($_cc === '') {
                        // Derive from program code
                        $_prog = strtoupper(trim($_SESSION['program']));
                        $_progMap = [
                            'BSCS'=>'CCS','BSIT'=>'CCS','ACT'=>'CCS',
                            'BSA'=>'CBA','BSBA'=>'CBA','BSMA'=>'CBA','BSAIS'=>'CBA',
                            'BEED'=>'CTED','BSED'=>'CTED','MAED'=>'CTED',
                            'AB'=>'CAS','BSMATH'=>'CAS','BSSTAT'=>'CAS',
                            'BSCRIM'=>'CCJE',
                            'BSMT'=>'CIT','BSET'=>'CIT',
                            'BSME'=>'CME','BSMARE'=>'CME',
                            'BSN'=>'CNAHS','BSPT'=>'CNAHS',
                            'BSCE'=>'COE','BSEE'=>'COE','BSECE'=>'COE','BSCHE'=>'COE',
                            'LLB'=>'COL','JD'=>'COL',
                            'MD'=>'SOM',
                        ];
                        if (isset($_progMap[$_prog])) $_cc = $_progMap[$_prog];
                    }
                    $_SESSION['college_code'] = $_cc;

                    // Auto-register voter in SSG_Voter DB so admin panel tracks them.
                    // Runs silently — login still succeeds even if DB is unreachable.
                    try {
                        $_armsId   = $_SESSION['student_id'];
                        $_armsSY   = ELECTION_SCHOOL_YEAR;
                        $_armsSem  = ELECTION_SEMESTER;
                        $_armsProg = trim($record['Program_Code'] ?? $record['Program_Enrolled'] ?? '');
                        // Program_Code is required for the student table primary key
                        if ($_armsId !== '' && $_armsProg !== '') {
                            $_vDb  = \Configuration\Application::$SSG_Voter_DBase;
                            $_vPdo = new PDO(
                                "mysql:host={$_vDb['Host']};port={$_vDb['Port']};dbname={$_vDb['DBName']};charset=utf8mb4",
                                $_vDb['Username'], $_vDb['Password'],
                                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                            );
                            // Check if already registered for this election year
                            $_chk = $_vPdo->prepare(
                                'SELECT COUNT(*) FROM student WHERE Student_ID=? AND School_Year=? AND Semester=? AND Program_Code=?'
                            );
                            $_chk->execute([$_armsId, $_armsSY, $_armsSem, $_armsProg]);
                            if ((int)$_chk->fetchColumn() === 0) {
                                $_ins = $_vPdo->prepare(
                                    'INSERT INTO student
                                     (Student_ID, Student_Name, Sex, Program_Code, Major, Year_Level,
                                      Semester, School_Year, Enrollment_Information, Enrollment_Status, stud_pass)
                                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                                );
                                $_ins->execute([
                                    $_armsId,
                                    $record['Student_Name']     ?? $record['student_name']  ?? '',
                                    $record['Sex']              ?? $record['Gender']         ?? '',
                                    $_armsProg,
                                    $record['Major']            ?? '',
                                    $record['Year_Level']       ?? $record['year_level']     ?? null,
                                    $_armsSem,
                                    $_armsSY,
                                    '{}',
                                    $record['Enrollment_Status'] ?? 'Enrolled',
                                    password_hash($_armsId, PASSWORD_BCRYPT), // hashed placeholder — real auth is via ARMS
                                ]);
                            }
                        }
                    } catch (\Throwable $_re) {
                        // Voter DB unreachable — log silently, do not block login
                        error_log('voter auto-register failed: ' . $_re->getMessage());
                    }

                    // Restore profile confirmation and vote status from persistent storage.
                    // Always use ELECTION_SCHOOL_YEAR (admin-configured) as the canonical key
                    // so it matches what ballot.php, dashboard.php, etc. write.
                    $sid = $_SESSION['student_id'];
                    $syr = ELECTION_SCHOOL_YEAR;

                    if (isProfileConfirmed($sid, $syr)) {
                        $_SESSION['profile_confirmed'] = true;
                    }

                    // Check vote status: fast local cache first, then authoritative DB
                    if (isVoteCast($sid, $syr)) {
                        $_SESSION['voted'] = true;
                    } else {
                        // Fallback: query votes table directly — reliable regardless of stored proc behavior
                        try {
                            $_eCfg  = \Configuration\Application::$SSG_Election_DBase;
                            $_ePdo  = new PDO(
                                "mysql:host={$_eCfg['Host']};port={$_eCfg['Port']};dbname={$_eCfg['DBName']};charset=utf8mb4",
                                $_eCfg['Username'], $_eCfg['Password'],
                                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                            );
                            $_stmt = $_ePdo->prepare(
                                'SELECT COUNT(*) FROM votes WHERE Voter_ID = ? AND School_Year = ?'
                            );
                            $_stmt->execute([$sid, $syr]);
                            if ((int)$_stmt->fetchColumn() > 0) {
                                markVoteCast($sid, $syr); // update local cache
                                $_SESSION['voted'] = true;
                            }
                        } catch (\Throwable $e) {
                            // DB unreachable — rely on local cache only; fail silently
                        }
                    }

                    header('Location: /dashboard.php');
                    exit;
                    } // end enrollment else
                } // end ARMS login success
            }
        }
        } // end rate-limit else
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
    <title>E-Ballot &mdash; JRMSU SSG Election Portal</title>
    <link rel="icon" href="Presets/favicon.png" type="image/x-icon"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body, html, * {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.1167;
        }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background-color: #f0f0f0;
            background-image: radial-gradient(circle, #c0c0c0 1px, transparent 1px);
            background-size: 22px 22px;
            display: flex;
            flex-direction: column;
            zoom: 1;
        }

        /* ── Navbar ── */
        @keyframes navSlideIn {
            from { opacity: 0; transform: translateY(-100%); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .navbar {
            width: 100%;
            background: #fff;
            border-bottom: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 48px;
            height: 58px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            animation: navSlideIn .45s cubic-bezier(.4,0,.2,1) both;
        }
        .navbar-brand {
            font-size: 18px;
            font-weight: 800;
            color: #1a3a8f;
            letter-spacing: .5px;
            text-decoration: none;
            cursor: pointer;
        }
        .navbar-links {
            display: flex;
            gap: 32px;
            list-style: none;
        }
        .navbar-links a {
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: #444;
            transition: color .2s;
        }
        .navbar-links a:hover { color: #1a3a8f; }
        .navbar-links a.active { color: #f5c400; font-weight: 800; }

        /* ── Main Layout ── */
        .page-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 24px;
        }
        .layout {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            max-width: 1100px;
            gap: 16px;
        }

        /* ── Left: Form Panel ── */
        .form-panel {
            flex: 0 0 340px;
            max-width: 340px;
        }
        .logo-row {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,.12);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-circle img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        .portal-title {
            font-size: 19px;
            font-weight: 900;
            color: #1a3a8f;
            line-height: 1.25;
            margin-bottom: 6px;
            text-align: center;
            white-space: normal;
            word-break: break-word;
        }
        .portal-sub {
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: 13px;
            line-height: 100%;
            letter-spacing: 0%;
            color: #505050;
            margin-bottom: 28px;
            text-align: center;
        }

        .error-box {
            background: #fff0f0;
            border: 1px solid #ffc5c5;
            color: #c0392b;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 7px;
        }
        .error-box::before { content: '!'; flex-shrink: 0; margin-top: 1px; font-size: 12px; font-weight: 800; width: 18px; height: 18px; border-radius: 50%; background: #c0392b; color: #fff; display: inline-flex; align-items: center; justify-content: center; }

        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: 13px;
            line-height: 100%;
            letter-spacing: 0;
            color: #505050;
            margin-bottom: 6px;
            text-align: center;
        }
        .field input[type="text"],
        .field input[type="password"] {
            width: 334px;
            max-width: 100%;
            height: 51px;
            padding: 0 14px;
            border: 0.2px solid #3170FF;
            border-radius: 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 400;
            color: #222;
            background: #F8F8F8;
            outline: none;
            text-align: center;
            transition: border-color .2s, box-shadow .2s;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .field input:focus {
            border-color: #3170FF;
            box-shadow: 0 0 0 3px rgba(49,112,255,.1);
        }
        .field input::placeholder {
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: 15px;
            line-height: 100%;
            letter-spacing: 0;
            text-align: center;
            color: #C2C2C2;
        }

        .btn-submit {
            width: 179px;
            height: 48px;
            padding: 0;
            background: #F9C301;
            color: #1a1a1a;
            font-size: 15px;
            font-weight: 800;
            border: 0.2px solid #3170FF;
            border-radius: 24px;
            cursor: pointer;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 8px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 3px 10px rgba(249,195,1,.4);
        }
        .btn-submit:hover {
            background: #e6b000;
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(249,195,1,.5);
        }
        .btn-submit:active { transform: translateY(0); }

        /* ── Right: Hero Panel ── */
        .hero-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 420px;
            position: relative;
        }
        @keyframes heroSlideIn {
            from { opacity: 0; transform: translateX(60px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .hero-img {
            max-height: 480px;
            max-width: 100%;
            object-fit: contain;
            filter: drop-shadow(0 8px 32px rgba(0,0,0,.13));
            animation: heroSlideIn .65s cubic-bezier(.4,0,.2,1) .1s both;
        }

        /* ── Footer ── */
        .footer {
            padding: 14px 48px 24px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .footer-logo {
            width: 72px;
            height: auto;
            object-fit: contain;
        }
        .footer-text {
            font-size: 13px;
            font-weight: 700;
            color: #1a2a44;
        }
        .footer-text a {
            color: #e6a800;
            font-weight: 700;
            text-decoration: none;
        }
        .footer-text a:hover { text-decoration: underline; }

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
            background: #1a3a8f; border-radius: 2px;
            transition: transform .3s, opacity .3s;
        }
        .nav-hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
        .nav-hamburger.open span:nth-child(2) { opacity: 0; }
        .nav-hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }

        /* ── Mobile nav menu ── */
        .nav-mobile-menu {
            display: none; position: fixed;
            top: 58px; left: 0; right: 0;
            background: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            z-index: 99; padding: 8px 0 16px;
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

        @media (max-width: 768px) {
            .hero-panel { display: none; }
            .form-panel { flex: 0 0 100%; max-width: 100%; }
            .navbar { padding: 0 20px; }
            .navbar-links { display: none; }
            .nav-hamburger { display: flex; }
            .page-content { padding: 24px 16px; }
            .footer { padding: 14px 20px 20px; }
        }
        @media (max-width: 420px) {
            .portal-title { font-size: 16px; white-space: normal; text-align: center; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="/" class="navbar-brand">E-Ballot</a>
    <ul class="navbar-links">
        <li><a href="/">Candidates</a></li>
        <li><a href="/contact.php">Contact</a></li>
        <li><a href="/tally.php">Tally</a></li>

        <li><a href="/login.php" class="active">Profile</a></li>
    </ul>
    <button class="nav-hamburger" id="navHamburger" onclick="toggleMobileNav()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</nav>
<div class="nav-mobile-menu" id="navMobileMenu">
    <a href="/">Candidates</a>
    <a href="/contact.php">Contact</a>
    <a href="/tally.php">Tally</a>
    <a href="/login.php" class="active">Profile</a>
</div>
<script>
function toggleMobileNav() {
    var btn = document.getElementById('navHamburger');
    var menu = document.getElementById('navMobileMenu');
    var open = menu.classList.toggle('open');
    btn.classList.toggle('open', open);
}
</script>

<!-- Main Content -->
<div class="page-content">
    <div class="layout">

        <!-- Left: Login Form -->
        <div class="form-panel">
            <div class="logo-row">
                <div class="logo-circle">
                    <img src="Presets/jrmsu-logo.png" alt="JRMSU Logo"/>
                </div>
            </div>

            <div class="portal-title">JRMSU SSG Electronic Election Portal</div>
            <div class="portal-sub">Welcome to the JRMSU SSG Election Portal.</div>

            <?php if ($error): ?>
            <div class="error-box"><?= $errorIsHtml ? $error : htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id"
                           placeholder="Student Id: eg: 25-A-00000"
                           value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                           required autofocus/>
                </div>
                <div class="field">
                    <label for="password">Your JRMSU-ARMS Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required/>
                </div>
                <button type="submit" class="btn-submit">Submit</button>
            </form>
        </div>

        <!-- Right: Hero Image -->
        <div class="hero-panel">
            <img class="hero-img" src="Presets/login-hero-real.png" alt="Election Portal Illustration"/>
        </div>

    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <img src="Presets/ccs-logo.png" class="footer-logo" alt="CCS-Creatives Society Logo"/>
    <span class="footer-text">Powered by <a href="#" onclick="openTeamModal();return false;">CCS-Creatives Society</a></span>
</footer>

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>
</body>
</html>
