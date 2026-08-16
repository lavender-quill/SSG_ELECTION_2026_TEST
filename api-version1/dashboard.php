<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

// Pull full voter record from session
$voter      = $_SESSION['voter']        ?? [];
$studentId  = $_SESSION['student_id']   ?? '';
$schoolYear = $_SESSION['school_year']  ?? ELECTION_SCHOOL_YEAR;
$semester   = $_SESSION['semester']     ?? ELECTION_SEMESTER;

// Parse names from Student_Name: format is "LASTNAME, FIRSTNAME [SECONDNAME...] MIDDLENAME"
$lastName   = '';
$firstName  = '';
$middleName = '';
$_rawName   = trim($_SESSION['student_name'] ?? $voter['Student_Name'] ?? '');
if ($_rawName !== '') {
    $_split   = explode(',', $_rawName, 2);
    $lastName = ucwords(strtolower(trim($_split[0])));
    if (!empty($_split[1])) {
        $_given = array_values(array_filter(array_map('trim', explode(' ', trim($_split[1])))));
        if (count($_given) > 1) {
            // Last token = middle name, everything before = first name(s)
            $middleName = ucwords(strtolower(array_pop($_given)));
            $firstName  = ucwords(strtolower(implode(' ', $_given)));
        } elseif (count($_given) === 1) {
            $firstName = ucwords(strtolower($_given[0]));
        }
    }
} else {
    // Fallback: individual DB fields (LASTNAME in First_Name col, MIDDLENAME in Last_Name col)
    $lastName   = rtrim($voter['First_Name']  ?? $voter['FirstName']  ?? '', ', ');
    $middleName = rtrim($voter['Last_Name']   ?? $voter['LastName']   ?? '', ', ');
    $firstName  = rtrim($voter['Middle_Name'] ?? $voter['MiddleName'] ?? '', ', ');
}

$gender    = $voter['Gender']         ?? $voter['Sex']           ?? '';
$birthDate = $voter['Birth_Date']     ?? $voter['BirthDate']     ?? $voter['Date_of_Birth'] ?? '';
$email     = $voter['Email_Address']  ?? $voter['Email']         ?? '';
$mobile    = $voter['Mobile_Number']  ?? $voter['Contact_Number']?? $voter['Mobile']        ?? '';
$course    = $voter['Course_Description'] ?? $voter['Program_Enrolled'] ?? $_SESSION['program']    ?? '';
$college   = $voter['College']        ?? $voter['College_Name']  ?? $_SESSION['college']    ?? '';
$yearLevel = $voter['Year_Level']     ?? $_SESSION['year_level'] ?? '';
$yearDisp  = $yearLevel ? $yearLevel . ' Year' : '';

// Format semester display
$semDisp = $semester;
if (is_numeric($semester)) {
    $semDisp = $semester == 1 ? '1st Sem' : ($semester == 2 ? '2nd Sem' : $semester . ' Sem');
} elseif (stripos($semester, 'sem') === false) {
    $semDisp = $semester . ' Sem';
}

// Handle profile confirmation POST
$confirmed = !empty($_SESSION['profile_confirmed']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_profile'])) {
    if (!empty($_POST['chk1']) && !empty($_POST['chk2'])) {
        // Save any editable fields the student filled in back into the session
        if (!empty($_POST['gender']))     { $_SESSION['voter']['Sex']        = htmlspecialchars(trim($_POST['gender']));     $gender    = $_SESSION['voter']['Sex']; }
        if (!empty($_POST['birth_date'])) { $_SESSION['voter']['Birth_Date'] = htmlspecialchars(trim($_POST['birth_date'])); $birthDate = $_SESSION['voter']['Birth_Date']; }
        if (!empty($_POST['email']))      { $_SESSION['voter']['Email']      = htmlspecialchars(trim($_POST['email']));      $email     = $_SESSION['voter']['Email']; }
        if (!empty($_POST['mobile']))     { $_SESSION['voter']['Mobile']     = htmlspecialchars(trim($_POST['mobile']));     $mobile    = $_SESSION['voter']['Mobile']; }
        $_SESSION['profile_confirmed'] = true;
        markProfileConfirmed($studentId, ELECTION_SCHOOL_YEAR);
        header('Location: /dashboard.php');
        exit;
    }
    $confirmError = 'Please check both boxes to continue.';
}

// Clear stale voted flag if admin reset votes after this session voted
clearStaleVoteSession($schoolYear);

// After confirmed – check election & vote status
$electionOpen = false;
$alreadyVoted = !empty($_SESSION['voted']);
if ($confirmed) {
    $sched        = loadElectionSchedule($schoolYear);
    $now          = time();
    $electionOpen = !empty($sched['Time_Start']) && !empty($sched['Time_End'])
                    && $now >= (int)$sched['Time_Start']
                    && $now <= (int)$sched['Time_End'];

    if (!$alreadyVoted) {
        $vsRaw  = callModel(function() use ($studentId, $schoolYear) {
            Election::Check_User_Vote_Status(['Voter_ID' => $studentId, 'School_Year' => $schoolYear]);
        });
        $vs = unwrap($vsRaw);
        if (!isError($vs)) {
            $alreadyVoted =
                (isset($vs['Has_Voted'])   && $vs['Has_Voted']) ||
                (isset($vs['Vote_Status']) && strtolower($vs['Vote_Status']) === 'voted') ||
                (isset($vs['Voted'])       && $vs['Voted']);
            if ($alreadyVoted) $_SESSION['voted'] = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard &mdash; E-Ballot JRMSU</title>
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
            display: flex;
            flex-direction: column;
        }

        /* ── Navbar ── */
        .navbar {
            width: 100%; background: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px; height: 58px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            position: sticky; top: 0; z-index: 100;
        }
        .navbar-brand { font-size: 18px; font-weight: 800; color: #1a3a8f; text-decoration: none; }
        .navbar-links { display: flex; gap: 32px; list-style: none; align-items: center; }
        .navbar-links a { text-decoration: none; font-size: 14px; font-weight: 600; color: #444; transition: color .2s; }
        .navbar-links a:hover { color: #1a3a8f; }
        .navbar-links a.active { color: #1a3a8f; font-weight: 800; border-bottom: 2px solid #1a3a8f; padding-bottom: 2px; }
        .btn-signout {
            background: none; border: 1.5px solid #ddd; color: #555;
            padding: 6px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: border-color .2s, color .2s;
        }
        .btn-signout:hover { border-color: #1a3a8f; color: #1a3a8f; }

        /* ── Main ── */
        .page { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 40px 24px 60px; }

        /* ── Avatar ── */
        .avatar-wrap {
            width: 90px; height: 90px; border-radius: 50%;
            overflow: hidden; border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.15);
            background: #dde4f0;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-placeholder { font-size: 40px; line-height: 1; }

        .greeting {
            font-size: 22px; font-weight: 800; color: #1a3a8f;
            margin-bottom: 8px;
        }
        .id-badge {
            display: inline-block;
            background: #f5c400; color: #1a1a1a;
            font-size: 13px; font-weight: 800;
            padding: 4px 18px; border-radius: 20px;
            margin-bottom: 20px;
            letter-spacing: .5px;
        }
        .intro-text {
            text-align: center; font-size: 13.5px; color: #444;
            max-width: 480px; line-height: 1.7; margin-bottom: 32px;
        }
        .intro-text a { color: #1a3a8f; font-weight: 700; text-decoration: none; }

        /* ── Form card ── */
        .form-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            width: 100%; max-width: 780px;
            padding: 32px 36px 36px;
            margin-bottom: 28px;
        }
        .section-label {
            font-size: 16px; font-weight: 800; color: #1a3a8f;
            text-align: center; margin-bottom: 24px;
        }

        /* ── Info grid ── */
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 20px; }
        @media(max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }
        @media(min-width: 641px) and (max-width: 900px) { .info-grid { grid-template-columns: repeat(2, 1fr); } }

        .field-group { display: flex; flex-direction: column; gap: 4px; }
        .field-label {
            font-size: 11.5px; font-weight: 600; color: #888;
            letter-spacing: .3px;
        }
        .field-value {
            background: #fff; border: 1.5px solid #dde1ea;
            border-radius: 8px; padding: 9px 14px;
            font-size: 13.5px; font-weight: 500; color: #1a2a44;
            min-height: 40px;
        }
        .field-value.empty { color: #bbb; }

        /* Editable input fields inside the confirmation form */
        .field-input-edit {
            width: 100%;
            background: #fff; border: 1.5px solid #1a3a8f;
            border-radius: 8px; padding: 9px 14px;
            font-size: 13.5px; font-weight: 500; color: #1a2a44;
            font-family: 'Poppins', sans-serif;
            min-height: 40px; outline: none;
            transition: box-shadow .2s;
        }
        .field-input-edit:focus {
            box-shadow: 0 0 0 3px rgba(26,58,143,.12);
        }
        .field-input-edit::placeholder { color: #bbb; font-weight: 400; }
        .editable-tag {
            font-size: 10px; font-weight: 600;
            color: #1a3a8f; background: #e8eeff;
            border-radius: 4px; padding: 1px 5px;
            vertical-align: middle; letter-spacing: 0;
        }

        /* single-col spanning */
        .span-1 { grid-column: span 1; }

        /* ── Confirmation ── */
        .confirm-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            width: 100%; max-width: 780px;
            padding: 32px 36px;
            margin-bottom: 24px;
        }
        .check-row {
            display: flex; gap: 12px; align-items: flex-start;
            margin-bottom: 16px;
        }
        .check-row:last-of-type { margin-bottom: 0; }
        .check-row input[type="checkbox"] {
            width: 16px; height: 16px; flex-shrink: 0;
            margin-top: 3px; accent-color: #1a3a8f; cursor: pointer;
        }
        .check-row label {
            font-size: 13px; color: #333; line-height: 1.6; cursor: pointer;
        }
        .check-row label a { color: #1a3a8f; font-weight: 700; text-decoration: underline; }

        .error-msg {
            color: #dc2626; font-size: 13px; font-weight: 600;
            margin-bottom: 16px; text-align: center;
        }

        .btn-continue {
            display: block; width: 100%; max-width: 780px;
            background: #f5c400; color: #1a1a1a;
            border: none; padding: 14px;
            border-radius: 30px; font-size: 14px; font-weight: 800;
            letter-spacing: 1.5px; text-transform: uppercase;
            cursor: pointer; text-align: center; text-decoration: none;
            box-shadow: 0 4px 14px rgba(245,196,0,.4);
            transition: background .2s, transform .15s;
        }
        .btn-continue:hover { background: #e6b800; transform: translateY(-1px); }

        /* ── Confirmed state ── */
        .status-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            width: 100%; max-width: 780px;
            padding: 40px 36px; text-align: center;
        }
        .status-icon { width: 72px; height: 72px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; }
        .status-icon svg { width: 72px; height: 72px; }
        .status-title { font-size: 22px; font-weight: 800; color: #1a3a8f; margin-bottom: 8px; }
        .status-sub { font-size: 14px; color: #666; margin-bottom: 28px; }
        .btn-vote {
            display: inline-block;
            background: #f5c400; color: #1a1a1a;
            border: none; padding: 14px 40px;
            border-radius: 30px; font-size: 15px; font-weight: 800;
            letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; text-decoration: none;
            box-shadow: 0 4px 14px rgba(245,196,0,.4);
            transition: background .2s, transform .15s;
        }
        .btn-vote:hover { background: #e6b800; transform: translateY(-1px); }
        .voted-text h2 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
        .voted-text p { font-size: 14px; }
        .closed-note { font-size: 13px; color: #888; margin-top: 16px; }

        /* ── Footer ── */
        footer {
            padding: 20px 48px 28px;
            display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
        }
        .footer-logo { width: 64px; height: auto; object-fit: contain; }
        .footer-text { font-size: 13px; font-weight: 700; color: #1a2a44; }
        .footer-text a { color: #e6a800; font-weight: 700; text-decoration: none; }

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
        .nav-mobile-menu a.active { color: #1a3a8f; font-weight: 800; background: #f0f4ff; border-left: 3px solid #1a3a8f; }
        .nav-mobile-menu .mob-signout {
            margin: 4px 16px 0; text-align: center;
            background: #f5f7ff; border: 1.5px solid #dde4f0;
            border-radius: 8px; color: #1a3a8f;
        }

        @media(max-width: 768px) {
            .navbar { padding: 0 20px; }
            .navbar-links { display: none; }
            .nav-hamburger { display: flex; }
        }
        @media(max-width: 600px) {
            .form-card, .confirm-card, .status-card { padding: 24px 18px; }
            footer { padding: 16px 20px; }
            .page { padding: 28px 16px 48px; }
            .voted-banner { flex-direction: column; align-items: flex-start; gap: 12px; padding: 24px 20px; }
        }
        @media(max-width: 420px) {
            .greeting { font-size: 18px; }
            .status-title { font-size: 18px; }
            .field-value { word-break: break-word; }
            .form-card, .confirm-card, .status-card { padding: 18px 14px; }
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
        <li><a href="/dashboard.php" class="active">Profile</a></li>
        <li><a href="/logout.php" class="btn-signout">Sign Out</a></li>
    </ul>
    <button class="nav-hamburger" id="navHamburger" onclick="toggleMobileNav()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</nav>
<div class="nav-mobile-menu" id="navMobileMenu">
    <a href="/">Candidates</a>
    <a href="/contact.php">Contact</a>
    <a href="/tally.php">Tally</a>
    <a href="/dashboard.php" class="active">Profile</a>
    <a href="/logout.php" class="mob-signout">Sign Out</a>
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

    <?php if (!$confirmed): ?>
    <!-- ── Profile Confirmation State ── -->

    <!-- Avatar -->
    <div class="avatar-wrap">
        <svg class="avatar-placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    </div>

    <div class="greeting">Hi <?= htmlspecialchars($firstName ?: 'Student') ?>!</div>
    <div class="id-badge"><?= htmlspecialchars($studentId) ?></div>

    <p class="intro-text">
        It looks like you are first time using the <a href="#">JRMSU-SSG EEP</a>.<br>
        Please confirm your details before we proceed
    </p>

    <!-- Account Identity + Confirmation wrapped in one form -->
    <form method="POST" style="width:100%;max-width:780px;">
        <input type="hidden" name="confirm_profile" value="1"/>

    <div class="form-card">
        <div class="section-label">Account Identity</div>
        <div class="info-grid">

            <?php
            // Locked display-only field
            function lockedField(string $label, string $value): void {
                $disp = trim($value) !== '' ? htmlspecialchars($value) : '<span style="color:#bbb;font-weight:400;">—</span>';
                echo '<div class="field-group">';
                echo '<span class="field-label">' . htmlspecialchars($label) . ' <span style="font-size:10px;opacity:.45;">🔒</span></span>';
                echo '<div class="field-value">' . $disp . '</div>';
                echo '</div>';
            }
            lockedField('Student ID',  $studentId);
            lockedField('Last Name',   $lastName);
            lockedField('First Name',  $firstName);
            lockedField('Middle Name', $middleName);
            lockedField('School Year', $schoolYear);
            lockedField('Course',      $course);
            lockedField('College',     $college);
            lockedField('Year',        $yearDisp);
            lockedField('Semester',    $semDisp);
            ?>

            <!-- Editable fields -->
            <div class="field-group">
                <span class="field-label">Gender <span class="editable-tag">editable</span></span>
                <input class="field-input-edit" type="text" name="gender"
                       value="<?= htmlspecialchars($gender !== '—' ? $gender : '') ?>"
                       placeholder="e.g. Male / Female"/>
            </div>
            <div class="field-group">
                <span class="field-label">Birth Date <span class="editable-tag">editable</span></span>
                <input class="field-input-edit" type="text" name="birth_date"
                       value="<?= htmlspecialchars($birthDate !== '—' ? $birthDate : '') ?>"
                       placeholder="e.g. 2000-01-15"/>
            </div>
            <div class="field-group">
                <span class="field-label">Email Address <span class="editable-tag">editable</span></span>
                <input class="field-input-edit" type="text" name="email"
                       value="<?= htmlspecialchars($email !== '—' ? $email : '') ?>"
                       placeholder="your@email.com"/>
            </div>
            <div class="field-group">
                <span class="field-label">Mobile Number <span class="editable-tag">editable</span></span>
                <input class="field-input-edit" type="text" name="mobile"
                       value="<?= htmlspecialchars($mobile !== '—' ? $mobile : '') ?>"
                       placeholder="e.g. 09123456789"/>
            </div>

        </div>
    </div>

    <!-- Confirmation checkboxes -->
        <div class="confirm-card">
            <div class="section-label">Confirmation</div>

            <?php if (!empty($confirmError)): ?>
            <p class="error-msg"><?= htmlspecialchars($confirmError) ?></p>
            <?php endif; ?>

            <div class="check-row">
                <input type="checkbox" id="chk1" name="chk1" value="1"/>
                <label for="chk1">I hereby confirm and declare that my details are correct</label>
            </div>
            <div class="check-row">
                <input type="checkbox" id="chk2" name="chk2" value="1"/>
                <label for="chk2">
                    I declare and authorize the <a href="#">CCS-Creatives Committee</a>,
                    the <a href="#">JRMSU-Main SSG</a> and the <a href="#">JRMSU Main Campus</a>
                    to use my data for the purpose of conducting online election.
                </label>
            </div>
        </div>

        <button type="submit" class="btn-continue" id="continueBtn" disabled
                style="opacity:.45;cursor:not-allowed;">Continue Signing Up</button>
    </form>
    <script>
        const chk1 = document.getElementById('chk1');
        const chk2 = document.getElementById('chk2');
        const btn  = document.getElementById('continueBtn');
        function sync() {
            const ok = chk1.checked && chk2.checked;
            btn.disabled = !ok;
            btn.style.opacity = ok ? '1' : '.45';
            btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
        }
        chk1.addEventListener('change', sync);
        chk2.addEventListener('change', sync);
    </script>

    <?php else: ?>
    <!-- ── Post-Confirmation State ── -->

    <div class="avatar-wrap">
        <svg class="avatar-placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    </div>
    <div class="greeting">Welcome, <?= htmlspecialchars($firstName ?: 'Student') ?>!</div>
    <div class="id-badge"><?= htmlspecialchars($studentId) ?></div>
    <br/>

    <?php if ($alreadyVoted): ?>
    <div class="status-card">
        <div class="status-icon" style="width:72px;height:72px;background:#dcfce7;border-radius:50%;margin:0 auto 20px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2" xmlns="http://www.w3.org/2000/svg" style="width:40px;height:40px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="status-title" style="color:#15803d;">You've Already Voted!</div>
        <div class="status-sub">Your vote for S.Y. <?= htmlspecialchars($schoolYear) ?> has been recorded.<br>Thank you for participating in the JRMSU SSG Election.</div>
        <span class="btn-vote" style="background:#e5e7eb;color:#9ca3af;cursor:not-allowed;box-shadow:none;pointer-events:none;">Vote Submitted &#10003;</span>
        <a href="/tally.php" class="btn-vote" style="margin-top:12px;background:linear-gradient(135deg,#f5c400,#d4a800);color:#0d2655;box-shadow:0 4px 16px rgba(245,196,0,.3);">Watch Live Results &rarr;</a>
    </div>

    <?php elseif ($electionOpen): ?>
    <div class="status-card">
        <div class="status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg></div>
        <div class="status-title">Ready to Vote?</div>
        <div class="status-sub">The election is now open. Cast your vote for your preferred SSG candidates.</div>
        <a href="/ballot.php" class="btn-vote">Cast My Vote &rarr;</a>
    </div>

    <?php else: ?>
    <div class="status-card">
        <div class="status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
        <div class="status-title">Voting is Not Yet Open</div>
        <div class="status-sub">The election window hasn't started yet. Please check back later.</div>
        <p class="closed-note">S.Y. <?= htmlspecialchars($schoolYear) ?> &bull; <?= htmlspecialchars($semDisp) ?></p>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<!-- Footer -->
<footer>
    <img src="/Presets/ccs-logo.png" class="footer-logo" alt="CCS-Creatives Society Logo"/>
    <span class="footer-text">Powered by <a href="#">CCS-Creatives Society</a></span>
</footer>

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>
</body>
</html>
