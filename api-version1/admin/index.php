<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/rate-limit.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    $authenticated = false;

    // ── Rate limiting: max 10 attempts per IP per 15 minutes ─────────────────
    $_adminIp    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_adminRl    = rateLimit('admin_login', $_adminIp, 10, 900);
    if ($_adminRl['blocked']) {
        $minutes = (int)ceil($_adminRl['retry_after'] / 60);
        $error   = "Too many failed attempts. Please try again in {$minutes} minute" . ($minutes !== 1 ? 's' : '') . '.';
    } elseif ($user !== '' && $pass !== '') {
        try {
            $cfg = \Configuration\Application::$SSG_Election_DBase;
            $pdo = new PDO(
                "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
                $cfg['Username'], $cfg['Password'],
                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $stmt = $pdo->prepare(
                "SELECT Password_Hash, Userlevel, User_Status FROM user_account WHERE UserName = ? LIMIT 1"
            );
            $stmt->execute([$user]);
            $row = $stmt->fetch();

            if ($row && strtolower($row['User_Status']) === 'active' && !empty($row['Password_Hash'])
                && password_verify($pass, $row['Password_Hash'])) {
                $authenticated = true;
                $_SESSION['admin_userlevel'] = $row['Userlevel'];
            }
        } catch (\Throwable $e) {
            $error = 'Database unavailable. Please try again later.';
        }
    }

    if ($authenticated) {
        rateLimitReset('admin_login', $_adminIp);
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $user;
        header('Location: /admin/dashboard.php');
        exit;
    } elseif ($error === '') {
        rateLimitIncrement('admin_login', $_adminIp, 900);
        $error = 'Invalid username or password.';
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
    <title>Admin Login &mdash; SSG Election System</title>
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
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0a0f1e 0%, #1a1035 50%, #0a1628 100%);
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed; inset: 0;
            background:
                radial-gradient(ellipse 700px 500px at 15% 30%, rgba(120,60,220,.18) 0%, transparent 70%),
                radial-gradient(ellipse 600px 400px at 85% 70%, rgba(0,87,183,.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .card {
            background: rgba(255,255,255,.97);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0,0,0,.55);
            width: 100%; max-width: 420px;
            padding: 48px 44px 40px;
            animation: slideUp .5s cubic-bezier(.22,1,.36,1) both;
            position: relative;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
        .card-header { text-align: center; margin-bottom: 30px; }
        .logo-wrap {
            display: inline-flex; align-items: center; justify-content: center;
            background: #fff;
            border-radius: 50%; width: 88px; height: 88px;
            margin-bottom: 18px;
            box-shadow: 0 4px 20px rgba(90,40,200,.35);
            border: 3px solid #e5e7eb;
        }
        .logo-wrap img { width: 68px; height: 68px; object-fit: contain; }
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg,#7c3aed,#4f46e5);
            color: #fff; font-size: 10px; font-weight: 800;
            letter-spacing: 1.5px; text-transform: uppercase;
            padding: 3px 12px; border-radius: 20px; margin-bottom: 10px;
        }
        .system-name { font-size: 21px; font-weight: 800; color: #0d1a3a; line-height: 1.2; margin-bottom: 5px; }
        .system-sub  { font-size: 12.5px; color: #6b7280; font-weight: 500; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 0 0 26px; }
        .error-box {
            background: #fff0f0; border: 1px solid #ffc5c5; color: #c0392b;
            border-radius: 10px; padding: 11px 15px; font-size: 13.5px;
            margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
        }
        .error-box::before { content: '!'; font-size: 13px; font-weight: 800; width: 18px; height: 18px; border-radius: 50%; background: #c0392b; color: #fff; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .field { margin-bottom: 17px; }
        label { display: block; font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap .icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: #9ca3af; font-size: 15px; pointer-events: none;
        }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 12px 14px 12px 40px;
            border: 1.5px solid #d1d5db; border-radius: 10px;
            font-size: 15px; color: #111827; background: #f9fafb; outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,.12); }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 17px; padding: 4px;
        }
        .toggle-pw:hover { color: #7c3aed; }
        .btn-login {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg,#7c3aed,#4f46e5);
            color: #fff; font-size: 15px; font-weight: 700;
            border: none; border-radius: 11px; cursor: pointer;
            margin-top: 4px;
            box-shadow: 0 4px 16px rgba(124,58,237,.4);
            transition: opacity .2s, transform .15s;
        }
        .btn-login:hover { opacity: .9; transform: translateY(-1px); }
        .card-footer { margin-top: 22px; text-align: center; font-size: 12px; color: #9ca3af; line-height: 1.6; }
        .back-link { color: #6b7280; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px; margin-top: 14px; }
        .back-link:hover { color: #4f46e5; }
        .dots { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
        .dot { position: absolute; border-radius: 50%; background: rgba(255,255,255,.03); animation: float linear infinite; }
        @keyframes float {
            from { transform: translateY(100vh) scale(0); opacity: 0; }
            10%  { opacity: 1; } 90% { opacity: 1; }
            to   { transform: translateY(-200px) scale(1); opacity: 0; }
        }
    </style>
</head>
<body>
<div class="dots" aria-hidden="true">
    <?php for($i=0;$i<10;$i++): $s=rand(15,60);$l=rand(0,100);$d=rand(10,28);$del=rand(0,14); ?>
    <div class="dot" style="width:<?=$s?>px;height:<?=$s?>px;left:<?=$l?>%;animation-duration:<?=$d?>s;animation-delay:-<?=$del?>s;"></div>
    <?php endfor; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="logo-wrap">
            <img src="/Presets/jrmsu-logo.png" alt="JRMSU Logo"/>
        </div>
        <div class="admin-badge">Admin Panel</div>
        <div class="system-name">SSG Election System</div>
        <div class="system-sub">Administrator Access</div>
    </div>
    <hr class="divider"/>

    <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
                <span class="icon"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                <input type="text" id="username" name="username"
                       placeholder="Admin username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autofocus/>
            </div>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
                <span class="icon"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                <input type="password" id="password" name="password"
                       placeholder="Admin password" required/>
                <button type="button" class="toggle-pw" onclick="togglePw()"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
            </div>
        </div>
        <button type="submit" class="btn-login">Access Admin Panel &rarr;</button>
    </form>

    <div class="card-footer">
        &copy; <?= date('Y') ?> CCS-Creatives Society - All rights reserved
    </div>
    <a href="/" class="back-link">&#8592; Back to Voter Login</a>
</div>

<script>
function togglePw() {
    var f = document.getElementById('password');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
