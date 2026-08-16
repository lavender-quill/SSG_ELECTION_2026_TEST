<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

// Already voted — skip confirmation
if (!empty($_SESSION['voted'])) {
    header('Location: /success.php');
    exit;
}

// Already confirmed — go straight to ballot
if (!empty($_SESSION['profile_confirmed'])) {
    header('Location: /ballot.php');
    exit;
}

// Generate / retrieve profile CSRF token
if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}
$profileCsrf = $_SESSION['profile_csrf'];

// Handle confirmation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_profile'])) {
    if (!hash_equals($profileCsrf, trim($_POST['_csrf'] ?? ''))) {
        $confirmError = 'Invalid request. Please reload the page and try again.';
    } elseif (!empty($_POST['confirm1']) && !empty($_POST['confirm2'])) {
        // Persist any editable fields the student filled in back into the session
        if (!empty($_POST['gender']))     $_SESSION['voter']['Sex']        = htmlspecialchars(trim($_POST['gender']));
        if (!empty($_POST['birth_date'])) $_SESSION['voter']['Birth_Date'] = htmlspecialchars(trim($_POST['birth_date']));
        if (!empty($_POST['email']))      $_SESSION['voter']['Email']      = htmlspecialchars(trim($_POST['email']));
        if (!empty($_POST['mobile']))     $_SESSION['voter']['Mobile']     = htmlspecialchars(trim($_POST['mobile']));

        $_SESSION['profile_confirmed'] = true;
        markProfileConfirmed($_SESSION['student_id'] ?? '', $_SESSION['school_year'] ?? ELECTION_SCHOOL_YEAR);
        header('Location: /ballot.php');
        exit;
    } else {
        $confirmError = 'Please check both confirmation boxes to continue.';
    }
}

// Pull voter data from session
$voterRecord = $_SESSION['voter'] ?? [];
$rawFullName = $_SESSION['student_name'] ?? '';
$vStudentId  = htmlspecialchars($_SESSION['student_id']  ?? '—');
$vCollege    = htmlspecialchars($_SESSION['college']     ?? '—');
$vProgram    = htmlspecialchars($_SESSION['program']     ?? '—');
$vYearLevel  = htmlspecialchars((string)($_SESSION['year_level']  ?? '—'));
$vSemester   = htmlspecialchars($_SESSION['semester']    ?? '—');
$vSchoolYear = htmlspecialchars($_SESSION['school_year'] ?? ELECTION_SCHOOL_YEAR);

// Parse name: ARMS format is usually "LASTNAME, FIRSTNAME MIDDLENAME"
$vLastName = $vFirstName = $vMiddleName = '';
if (strpos($rawFullName, ',') !== false) {
    [$rawLast, $rawRest] = array_map('trim', explode(',', $rawFullName, 2));
    $vLastName = $rawLast;
    $nameParts = array_values(array_filter(explode(' ', $rawRest)));
    $vFirstName  = array_shift($nameParts) ?? '';
    $vMiddleName = implode(' ', $nameParts);
} else {
    $parts      = array_values(array_filter(explode(' ', trim($rawFullName))));
    $vFirstName = array_shift($parts) ?? '';
    $vLastName  = array_pop($parts) ?? '';
    $vMiddleName = implode(' ', $parts);
}
$vDisplayName = htmlspecialchars($rawFullName ?: 'Student');
$vFirstName   = htmlspecialchars($vFirstName);
$vLastName    = htmlspecialchars($vLastName);
$vMiddleName  = htmlspecialchars($vMiddleName);

// Optional ARMS fields that may or may not be present
$vGender    = htmlspecialchars($voterRecord['Sex']        ?? $voterRecord['Gender']     ?? '—');
$vBirthDate = htmlspecialchars($voterRecord['Birth_Date'] ?? $voterRecord['Birthdate']  ?? '—');
$vEmail     = htmlspecialchars($voterRecord['Email']      ?? $voterRecord['Email_Add']  ?? '—');
$vMobile    = htmlspecialchars($voterRecord['Mobile']     ?? $voterRecord['Contact_No'] ?? $voterRecord['Mobile_No'] ?? '—');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Profile &mdash; E-Ballot</title>
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
        .navbar-brand { font-size: 18px; font-weight: 800; color: #1a3a8f; text-decoration: none; cursor: pointer; }
        .navbar-links { display: flex; gap: 32px; list-style: none; }
        .navbar-links a {
            text-decoration: none; font-size: 14px; font-weight: 600; color: #444;
            transition: color .2s;
        }
        .navbar-links a:hover { color: #1a3a8f; }
        .navbar-links a.active { color: #f5c400; font-weight: 800; }

        /* ── Page ── */
        .page-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 24px 60px;
        }

        /* ── Avatar ── */
        .avatar-wrap {
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 3px solid #1a3a8f;
            background: #e0e7ff;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .avatar-wrap svg { width: 56px; height: 56px; color: #1a3a8f; opacity: .4; }

        .greeting {
            font-size: 22px; font-weight: 800; color: #1a3a8f; margin-bottom: 8px;
        }
        .student-badge {
            background: #f5c400; color: #1a1a1a;
            font-size: 13px; font-weight: 700;
            padding: 4px 18px; border-radius: 20px;
            margin-bottom: 20px;
        }
        .welcome-msg {
            font-size: 13.5px; color: #555; text-align: center; line-height: 1.6;
            margin-bottom: 32px;
        }
        .welcome-msg a { color: #1a3a8f; font-weight: 700; text-decoration: none; }

        /* ── Section Title ── */
        .section-title {
            font-size: 18px; font-weight: 800; color: #1a3a8f;
            margin-bottom: 24px; text-align: center;
        }

        /* ── Form Card ── */
        .form-card {
            width: 100%; max-width: 800px;
            background: transparent;
            margin-bottom: 40px;
        }

        .fields-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px 20px;
        }
        .field-group { display: flex; flex-direction: column; }
        .field-group.span-1 { grid-column: span 1; }

        .field-label {
            font-size: 12px; font-weight: 600; color: #555;
            text-align: center; margin-bottom: 6px;
        }
        .field-input {
            width: 100%;
            padding: 11px 16px;
            border: 1.5px solid #ccc;
            border-radius: 8px;
            font-size: 14px; font-family: 'Poppins', sans-serif;
            color: #222; background: #fff;
            outline: none;
            text-align: center;
            transition: border-color .2s, box-shadow .2s;
        }
        .field-input:focus {
            border-color: #1a3a8f;
            box-shadow: 0 0 0 3px rgba(26,58,143,.1);
        }
        .field-input::placeholder { color: #ccc; }
        .field-input:not([disabled]) {
            border-color: #1a3a8f;
            background: #fff;
        }
        .field-input:not([disabled]):focus {
            border-color: #1a3a8f;
            box-shadow: 0 0 0 3px rgba(26,58,143,.1);
        }
        .locked-badge {
            font-size: 10px;
            opacity: 0.5;
            vertical-align: middle;
        }
        .editable-badge {
            font-size: 10px;
            font-weight: 600;
            color: #1a3a8f;
            background: #e8eeff;
            border-radius: 4px;
            padding: 1px 5px;
            vertical-align: middle;
            letter-spacing: 0;
        }

        /* ── Confirmation ── */
        .confirmation-section {
            width: 100%; max-width: 800px;
            margin-bottom: 32px;
        }
        .confirmation-section .section-title { margin-bottom: 20px; }
        .check-item {
            display: flex; align-items: flex-start; gap: 12px;
            margin-bottom: 16px;
        }
        .check-item input[type="checkbox"] {
            width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0;
            accent-color: #1a3a8f; cursor: pointer;
        }
        .check-item label {
            font-size: 13.5px; color: #444; line-height: 1.55; cursor: pointer;
        }
        .check-item label a { color: #1a3a8f; font-weight: 600; text-decoration: none; }
        .check-item label a:hover { text-decoration: underline; }

        /* ── Continue Button ── */
        .btn-continue {
            padding: 13px 48px;
            background: #f5c400;
            color: #1a1a1a;
            font-size: 14px; font-weight: 800;
            border: none; border-radius: 8px;
            cursor: pointer; letter-spacing: 1.5px;
            text-transform: uppercase;
            box-shadow: 0 3px 10px rgba(245,196,0,.4);
            transition: background .2s, transform .15s, box-shadow .2s;
            margin-bottom: 48px;
        }
        .btn-continue:hover {
            background: #e6b800;
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(245,196,0,.5);
        }

        /* ── Footer ── */
        .footer {
            background: #fff;
            border-top: 1px solid #e5e8f0;
            padding: 24px 48px;
            display: flex; align-items: center;
            justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
        }
        .footer-left { display: flex; align-items: center; gap: 12px; }
        .footer-left img { width: 56px; height: auto; object-fit: contain; }
        .footer-brand { font-size: 13px; font-weight: 700; color: #1a2a44; }
        .footer-links { display: flex; gap: 20px; flex-wrap: wrap; }
        .footer-links a { font-size: 12px; color: #6b7280; text-decoration: none; font-weight: 600; transition: color .2s; }
        .footer-links a:hover { color: #1a3a8f; }
        .footer-copy { font-size: 11.5px; color: #9ca3af; width: 100%; padding-top: 10px; border-top: 1px solid #f0f0f0; margin-top: 8px; }

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

        @media (max-width: 768px) {
            .navbar { padding: 0 20px; }
            .navbar-links { display: none; }
            .nav-hamburger { display: flex; }
            .fields-grid { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 28px 16px 48px; }
            .field-value { word-break: break-word; overflow-wrap: anywhere; }
        }
        @media (max-width: 500px) {
            .fields-grid { grid-template-columns: 1fr; }
            .footer { flex-direction: column; align-items: flex-start; padding: 20px 20px; gap: 14px; }
            .footer-links { gap: 12px 16px; }
            .btn-continue { padding: 12px 32px; }
            .form-card { padding: 22px 18px; }
        }
        @media (max-width: 420px) {
            .page-content { padding: 20px 12px 40px; }
            .form-card { padding: 18px 14px; }
            .btn-continue { letter-spacing: 0.5px; font-size: 13px; padding: 12px 20px; width: 100%; }
            .field-value { word-break: break-word; }
            .field-label { font-size: 11px; }
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

        <li><a href="<?= !empty($_SESSION['logged_in']) ? '/dashboard.php' : '/login.php' ?>" class="active">Profile</a></li>
        <?php if (!empty($_SESSION['logged_in'])): ?>
        <li><a href="/logout.php" style="border:1.5px solid #ddd;padding:6px 16px;border-radius:8px;font-size:13px;">Sign Out</a></li>
        <?php else: ?>
        <li><a href="/login.php" style="background:#1a3a8f;color:#fff;padding:8px 22px;border-radius:8px;font-weight:700;font-size:14px;">Login</a></li>
        <?php endif; ?>
    </ul>
    <button class="nav-hamburger" id="navHamburger" onclick="toggleMobileNav()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</nav>
<div class="nav-mobile-menu" id="navMobileMenu">
    <a href="/">Candidates</a>
    <a href="/contact.php">Contact</a>
    <a href="/tally.php">Tally</a>
    <a href="<?= !empty($_SESSION['logged_in']) ? '/dashboard.php' : '/login.php' ?>" class="active">Profile</a>
    <?php if (!empty($_SESSION['logged_in'])): ?>
    <a href="/logout.php">Sign Out</a>
    <?php else: ?>
    <a href="/login.php">Login</a>
    <?php endif; ?>
</div>
<script>
function toggleMobileNav() {
    var btn = document.getElementById('navHamburger');
    var menu = document.getElementById('navMobileMenu');
    var open = menu.classList.toggle('open');
    btn.classList.toggle('open', open);
}
</script>

<!-- Page Content -->
<div class="page-content">

    <!-- Avatar & Greeting -->
    <div class="avatar-wrap">
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
        </svg>
    </div>
    <div class="greeting">Hi, <?= $vFirstName ?: $vDisplayName ?>!</div>
    <div class="student-badge"><?= $vStudentId ?></div>

    <div class="welcome-msg">
        Welcome to the <a href="#">JRMSU-SSG EEP</a>.<br/>
        Please confirm your details are correct before proceeding to the ballot.
    </div>

    <?php if (!empty($confirmError)): ?>
    <div style="background:#fff0f0;border:1px solid #ffc5c5;color:#c0392b;border-radius:8px;padding:11px 16px;font-size:13px;margin-bottom:16px;max-width:800px;width:100%;text-align:center;">
        <?= htmlspecialchars($confirmError) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/profile.php">
    <input type="hidden" name="confirm_profile" value="1"/>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($profileCsrf, ENT_QUOTES) ?>"/>

    <!-- Account Identity -->
    <div class="form-card">
        <div class="section-title">Account Identity</div>

        <div class="fields-grid">
            <div class="field-group">
                <div class="field-label">Student ID <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vStudentId ?>" disabled/>
            </div>
            <div class="field-group">
                <div class="field-label">Gender <span class="editable-badge">editable</span></div>
                <input class="field-input" type="text" name="gender" value="<?= $vGender !== '—' ? $vGender : '' ?>" placeholder="e.g. Male / Female"/>
            </div>
            <div class="field-group">
                <div class="field-label">Birth Date <span class="editable-badge">editable</span></div>
                <input class="field-input" type="text" name="birth_date" value="<?= $vBirthDate !== '—' ? $vBirthDate : '' ?>" placeholder="e.g. 2000-01-15"/>
            </div>

            <div class="field-group">
                <div class="field-label">First Name <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vFirstName ?>" disabled/>
            </div>
            <div class="field-group">
                <div class="field-label">Last Name <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vLastName ?>" disabled/>
            </div>
            <div class="field-group">
                <div class="field-label">Middle Name <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vMiddleName ?>" disabled/>
            </div>

            <div class="field-group">
                <div class="field-label">Email Address <span class="editable-badge">editable</span></div>
                <input class="field-input" type="text" name="email" value="<?= $vEmail !== '—' ? $vEmail : '' ?>" placeholder="your@email.com"/>
            </div>
            <div class="field-group">
                <div class="field-label">Mobile Number <span class="editable-badge">editable</span></div>
                <input class="field-input" type="text" name="mobile" value="<?= $vMobile !== '—' ? $vMobile : '' ?>" placeholder="e.g. 09123456789"/>
            </div>
            <div class="field-group">
                <div class="field-label">School Year <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vSchoolYear ?>" disabled/>
            </div>

            <div class="field-group">
                <div class="field-label">Course <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vProgram ?>" disabled/>
            </div>
            <div class="field-group">
                <div class="field-label">College <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vCollege ?>" disabled/>
            </div>
            <div class="field-group">
                <div class="field-label">Year <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vYearLevel ?>" disabled/>
            </div>

            <div class="field-group span-1">
                <div class="field-label">Semester <span class="locked-badge">🔒</span></div>
                <input class="field-input" type="text" value="<?= $vSemester ?>" disabled/>
            </div>
        </div>
    </div>

    <!-- Confirmation -->
    <div class="confirmation-section">
        <div class="section-title">Confirmation</div>
        <div class="check-item">
            <input type="checkbox" id="confirm1" name="confirm1" value="1"/>
            <label for="confirm1">I hereby confirm and declare that my details are correct</label>
        </div>
        <div class="check-item">
            <input type="checkbox" id="confirm2" name="confirm2" value="1"/>
            <label for="confirm2">
                I declare and authorize the <a href="#">CCS-Creatives Committee</a>,
                the <a href="#">JRMSU-Main SSG</a> and the <a href="#">JRMSU Main Campus</a> to
                use my data for the purpose of conducting online election.
            </label>
        </div>
    </div>

    <!-- Continue Button -->
    <button type="submit" class="btn-continue" id="btnContinue" disabled>Continue &nbsp; Signing Up</button>

    </form>

    <script>
    (function() {
        var c1 = document.getElementById('confirm1');
        var c2 = document.getElementById('confirm2');
        var btn = document.getElementById('btnContinue');
        function update() {
            btn.disabled = !(c1.checked && c2.checked);
            btn.style.opacity = btn.disabled ? '0.5' : '1';
            btn.style.cursor  = btn.disabled ? 'not-allowed' : 'pointer';
        }
        c1.addEventListener('change', update);
        c2.addEventListener('change', update);
        update();
    })();
    </script>

</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-left">
        <img src="/Presets/ccs-logo.png" alt="CCS-Creatives Society Logo"/>
        <div class="footer-brand"><a href="#" onclick="openTeamModal();return false;" style="color:#f5c400;text-decoration:none;border-bottom:1px solid #f5c400;">CCS-Creatives Society</a></div>
    </div>
    <div class="footer-links">
        <a href="/">Candidates</a>
        <a href="/tally.php">Live Tally</a>
        <a href="/contact.php">Contact</a>
        <a href="/dashboard.php">Dashboard</a>
    </div>
    <div class="footer-copy">&copy; <?= date('Y') ?> CCS-Creatives Society - All rights reserved</div>
</footer>

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>
</body>
</html>
