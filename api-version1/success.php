<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

// Clear stale voted flag first — if an admin reset happened after this session voted,
// the student should be sent back to ballot rather than seeing the success screen.
clearStaleVoteSession(ELECTION_SCHOOL_YEAR);

if (empty($_SESSION['voted'])) {
    header('Location: /ballot.php');
    exit;
}

$name = htmlspecialchars($_SESSION['student_name'] ?? 'Voter');
$studentId = htmlspecialchars($_SESSION['student_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Vote Submitted &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
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
            background: linear-gradient(135deg, #0a1628 0%, #0d2655 50%, #0a3d8f 100%);
            font-family: 'Poppins', sans-serif;
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            max-width: 480px; width: 100%;
            padding: 56px 44px 48px;
            text-align: center;
            animation: pop .5s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes pop { from{opacity:0;transform:scale(.9)} to{opacity:1;transform:scale(1)} }
        .check-icon {
            width: 90px; height: 90px;
            background: linear-gradient(135deg,#22c55e,#16a34a);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 24px rgba(34,197,94,.35);
            font-size: 42px;
        }
        h1 { font-size: 26px; font-weight: 800; color: #0d2655; margin-bottom: 10px; }
        p { color: #5a7ba6; font-size: 15px; line-height: 1.6; margin-bottom: 8px; }
        .id-badge {
            display: inline-block;
            background: #e8f0fe; color: #0d2655;
            font-weight: 700; font-size: 14px;
            padding: 4px 14px; border-radius: 20px;
            margin: 12px 0 28px;
        }
        .btn-actions { display: flex; flex-direction: column; gap: 12px; align-items: center; width: 100%; }
        .btn {
            display: inline-block;
            width: 100%; max-width: 280px;
            padding: 14px 32px;
            background: linear-gradient(135deg,#f5c400,#d4a800);
            color: #0d2655; font-weight: 800; font-size: 15px;
            border-radius: 11px; text-decoration: none;
            box-shadow: 0 4px 16px rgba(245,196,0,.35);
            transition: opacity .2s, transform .15s;
        }
        .btn:hover { opacity: .9; transform: translateY(-1px); }
        .btn-secondary {
            display: inline-block;
            width: 100%; max-width: 280px;
            padding: 12px 32px;
            background: transparent;
            color: #5a7ba6; font-weight: 700; font-size: 14px;
            border-radius: 11px; text-decoration: none;
            border: 1.5px solid #dde4f0;
            transition: background .2s, color .2s;
        }
        .btn-secondary:hover { background: #f1f5fb; color: #0d2655; }
        .note { font-size: 12px; color: #94a3b8; margin-top: 16px; }

        @media (max-width: 520px) {
            .card { padding: 36px 20px 32px; border-radius: 0; min-height: 100vh;
                    display: flex; flex-direction: column; align-items: center; justify-content: center; }
            h1 { font-size: 22px; }
            .check-icon { width: 72px; height: 72px; font-size: 34px; margin-bottom: 18px; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="check-icon">&#10003;</div>
    <h1>Vote Successfully Cast!</h1>
    <p>Thank you, <strong><?= $name ?></strong>.</p>
    <p>Your vote has been recorded for the<br/>JRMSU SSG Election <?= htmlspecialchars(ELECTION_SCHOOL_YEAR) ?>.</p>
    <div class="id-badge">Student ID: <?= $studentId ?></div>
    <div class="btn-actions">
        <a href="/tally.php" class="btn">Watch Live Results &rarr;</a>
        <a href="/logout.php" class="btn-secondary">Sign Out</a>
    </div>
    <p class="note">Your vote is confidential and final. You cannot vote again.</p>
</div>
</body>
</html>

