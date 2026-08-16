<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Election countdown
$_sched            = loadElectionSchedule(ELECTION_SCHOOL_YEAR);
$electionTimestamp = $_sched ? (int)($_sched['Time_Start'] ?? 0) : 0;
$_electionEnd      = $_sched ? (int)($_sched['Time_End']   ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Contact &mdash; JRMSU SSG Election Portal</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Unbounded:wght@800&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --blue:#1a3a8f; --yellow:#f5c400; --light:#f4f4f0; --text:#1a2744; --sub:#555e7a; }
        body { font-family:'Poppins',sans-serif; font-weight:700; letter-spacing:-0.02em; line-height:1.1167; background:var(--light); background-image:radial-gradient(circle,#c8c8c4 1px,transparent 1px); background-size:22px 22px; color:var(--text); min-height:100vh; }

        /* Navbar */
        .navbar { position:sticky; top:0; z-index:200; width:100%; height:58px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.07); display:flex; align-items:center; justify-content:space-between; padding:0 48px; }
        .navbar-brand { font-size:18px; font-weight:800; color:var(--blue); text-decoration:none; }
        .navbar-links { display:flex; gap:32px; list-style:none; }
        .navbar-links a { text-decoration:none; font-size:14px; font-weight:600; color:#444; transition:color .2s; }
        .navbar-links a:hover { color:var(--blue); }
        .navbar-links a.nav-active { color:var(--yellow); font-weight:800; }

        /* ── Hero ── */
        .hero { max-width:1140px; margin:0 auto; padding:36px 40px 8px; display:grid; grid-template-columns:1fr 420px; grid-template-rows:auto auto; grid-template-areas:"left right" "countdown right"; column-gap:56px; }
        .hero-left { grid-area:left; min-width:0; }
        .hero-right { grid-area:right; display:flex; align-items:center; justify-content:center; }
        .hero-countdown { grid-area:countdown; padding-top:4px; display:flex; flex-direction:column; align-items:flex-start; justify-content:flex-start; }
        .hero-logo-row { display:flex; align-items:center; gap:14px; margin-bottom:18px; }
        .hero-logo { width:72px; height:72px; border-radius:50%; background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .hero-logo img { width:62px; height:62px; object-fit:contain; }
        .hero-logo-label { font-size:12px; font-weight:700; color:var(--sub); letter-spacing:.5px; text-transform:uppercase; }
        .hero-title { font-size:60px; font-weight:700; line-height:67px; letter-spacing:-0.02em; color:var(--yellow); margin-bottom:12px; }
        .hero-title span { color:var(--yellow); }
        .hero-desc { font-size:14.5px; font-weight:400; color:var(--blue); line-height:1.7; margin-bottom:18px; max-width:480px; }
        .hero-actions { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
        .btn-vote { background:transparent; color:var(--blue); border:0.3px solid #6A7FC0; width:124px; height:35px; border-radius:23px; font-family:'Poppins',sans-serif; font-weight:800; font-size:10px; line-height:100%; letter-spacing:0; text-decoration:none; text-transform:uppercase; text-align:center; transition:background .2s, transform .15s, box-shadow .2s; display:inline-flex; align-items:center; justify-content:center; touch-action:manipulation; -webkit-tap-highlight-color:transparent; flex-shrink:0; }
        .btn-vote:hover { background:var(--blue); color:#fff; border-color:var(--blue); transform:translateY(-2px); box-shadow:0 4px 14px rgba(13,42,110,.22); }
        .btn-vote:active { transform:scale(.97); }
        .btn-results { background:transparent; color:var(--yellow); border:2px solid var(--yellow); width:124px; height:35px; border-radius:23px; font-family:'Poppins',sans-serif; font-weight:800; font-size:10px; line-height:100%; letter-spacing:0; text-decoration:none; text-transform:uppercase; text-align:center; transition:background .2s, transform .15s, box-shadow .2s; display:inline-flex; align-items:center; justify-content:center; gap:6px; touch-action:manipulation; -webkit-tap-highlight-color:transparent; flex-shrink:0; }
        .btn-results:hover { background:var(--yellow); color:var(--blue); transform:translateY(-2px); box-shadow:0 4px 14px rgba(245,196,0,.22); }
        .btn-results:active { transform:scale(.97); }
        .live-indicator { width:7px; height:7px; border-radius:50%; background:#22c55e; display:inline-block; animation:lbpulse 1.2s ease-in-out infinite; }
        @keyframes lbpulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        .countdown-label {
            font-size: 22px; font-weight: 900; color: var(--blue);
            margin-bottom: 16px;
        }
        .countdown { display: flex; gap: 14px; }
        .cd-box {
            background: linear-gradient(to right, #b0b0b0 0%, #f5c400 100%);
            border-radius: 16px;
            padding: 18px 10px 14px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(245,196,0,.35);
            min-width: 76px;
            transition: background .5s ease, box-shadow .5s ease;
        }
        .cd-box.live { background:linear-gradient(to right,#1a3a8f 0%,#2563eb 100%); box-shadow:0 6px 20px rgba(37,99,235,.4); }
        .cd-box.live .cd-unit { color: #bfdbfe; }
        .cd-num {
            font-size: 42px; font-weight: 900; color: #fff;
            display: block; line-height: 1;
            text-shadow: 0 2px 6px rgba(0,0,0,.2);
        }
        .cd-unit { font-size: 11px; font-weight: 800; color: var(--blue); letter-spacing: 1px; text-transform: uppercase; margin-top: 8px; background: none; }
        .hero-right img { max-width:100%; max-height:440px; object-fit:contain; filter:drop-shadow(0 12px 40px rgba(0,0,0,.15)); animation:floatHero 4s ease-in-out infinite; }
        @keyframes floatHero { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }

        /* Contact page */
        .contact-page { padding:80px 48px; min-height:calc(100vh - 58px - 81px); }
        .contact-inner { max-width:600px; margin:0 auto; text-align:center; }
        .contact-tag { display:inline-block; font-size:13px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:var(--yellow); background:rgba(245,196,0,.12); border-radius:20px; padding:4px 14px; margin-bottom:14px; }
        .contact-title { font-size:36px; font-weight:900; color:var(--text); line-height:1.1; margin-bottom:14px; }
        .contact-desc { font-size:14px; font-weight:500; color:#6b7280; margin-bottom:48px; line-height:1.8; max-width:440px; margin-left:auto; margin-right:auto; }
        .contact-cards { display:flex; flex-direction:column; gap:16px; align-items:center; }
        .contact-card {
            display:flex; align-items:center; gap:16px; background:#fff; border-radius:16px;
            padding:20px 28px; box-shadow:0 2px 12px rgba(0,0,0,.07); text-decoration:none;
            transition:transform .2s, box-shadow .2s; width:100%; max-width:420px; border:1px solid #eef0f5;
        }
        .contact-card:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(0,0,0,.12); }
        .contact-card-icon { width:46px; height:46px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .contact-card-text { text-align:left; flex:1; min-width:0; }
        .contact-card-label { display:block; font-size:11px; font-weight:700; color:#9ca3af; letter-spacing:1px; text-transform:uppercase; margin-bottom:3px; }
        .contact-card-value { display:block; font-size:14px; font-weight:800; color:#0d1b3e; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .contact-arrow { flex-shrink:0; }

        /* Footer */
        .footer { background:#fff; border-top:1px solid #e5e8f0; padding:24px 48px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .footer-left { display:flex; align-items:center; gap:12px; }
        .footer-left img { width:56px; height:auto; object-fit:contain; }
        .footer-brand { font-size:13px; font-weight:700; color:var(--text); }
        .footer-links { display:flex; gap:20px; flex-wrap:wrap; }
        .footer-links a { font-size:12px; color:var(--sub); text-decoration:none; transition:color .2s; cursor:pointer; }
        .footer-links a:hover { color:var(--blue); }
        .footer-links span { font-size:12px; color:#b0b0b0; cursor:default; }
        .footer-copy { font-size:11.5px; color:#9ca3af; width:100%; padding-top:10px; border-top:1px solid #f0f0f0; margin-top:8px; }

        /* Nav toggle (mobile) */
        .nav-toggle { display:none; flex-direction:column; gap:5px; background:none; border:none; cursor:pointer; padding:8px; margin:0; }
        .nav-toggle span { display:block; width:22px; height:2.5px; background:var(--blue); border-radius:2px; transition:transform .3s, opacity .3s; }
        .nav-open .nav-toggle span:nth-child(1) { transform:translateY(8px) rotate(45deg); }
        .nav-open .nav-toggle span:nth-child(2) { opacity:0; }
        .nav-open .nav-toggle span:nth-child(3) { transform:translateY(-8px) rotate(-45deg); }

        @media (max-width:768px) {
            .navbar { padding:0 16px; height:auto; min-height:56px; flex-wrap:wrap; gap:0; align-content:flex-start; }
            .navbar-brand { order:1; flex-basis:auto; padding:8px 0; align-self:center; }
            .nav-toggle { display:flex; order:2; margin-left:auto; align-self:center; }
            .navbar-links { order:3; display:none; flex-direction:column; gap:0; width:100%; padding:0; margin:0; border-top:1px solid #e5e7eb; }
            .navbar-links.open { display:flex; }
            .navbar-links li { width:100%; }
            .navbar-links li a { display:flex; align-items:center; padding:16px 12px; font-size:14px; font-weight:600; border-bottom:1px solid #f0f0f0; min-height:48px; width:100%; }
            .hero { padding:40px 20px 32px; display:flex; flex-direction:column; align-items:center; justify-content:center; grid:unset; grid-template-columns:unset; grid-template-areas:unset; column-gap:unset; }
            .hero-left { grid-area:unset; width:100%; max-width:600px; }
            .hero-right { grid-area:unset; display:flex; align-items:center; justify-content:center; margin-top:24px; max-height:280px; }
            .hero-right img { max-height:280px; }
            .hero-countdown { grid-area:unset; padding-top:32px; width:100%; }
            .hero-title { font-size:38px; line-height:48px; text-align:center; }
            .hero-logo-row { justify-content:center; margin-bottom:16px; }
            .hero-logo { width:60px; height:60px; }
            .hero-logo img { width:52px; height:52px; }
            .hero-logo-label { font-size:11px; }
            .hero-desc { font-size:13px; margin-bottom:24px; text-align:center; }
            .hero-actions { flex-direction:column; align-items:center; width:100%; gap:12px; margin-bottom:20px; }
            .btn-vote { width:160px; height:42px; font-size:10px; }
            .btn-results { width:160px; height:42px; font-size:10px; }
            .countdown-label { font-size:18px; margin-bottom:12px; }
            .countdown { gap:8px; flex-direction:row; }
            .cd-box { padding:14px 10px 10px; }
            .cd-num { font-size:28px; }
            .cd-unit { font-size:8px; }
            .contact-page { padding:48px 20px; }
            .contact-card { padding:18px 20px; }
            .contact-card-value { font-size:13px; word-break:break-all; white-space:normal; }
            .footer { flex-direction:column; align-items:flex-start; padding:24px 16px; }
        }
        @media (max-width:480px) {
            .hero { padding:24px 14px 28px; }
            .hero-logo-row { margin-bottom:12px; }
            .hero-logo { width:52px; height:52px; }
            .hero-logo img { width:46px; height:46px; }
            .hero-logo-label { font-size:10px; }
            .hero-title { font-size:32px; line-height:40px; margin-bottom:12px; }
            .hero-desc { font-size:12.5px; margin-bottom:20px; }
            .hero-right img { max-height:240px; }
            .countdown-label { font-size:16px; margin-bottom:10px; }
            .countdown { gap:6px; }
            .cd-box { padding:12px 8px 8px; }
            .cd-num { font-size:24px; }
            .cd-unit { font-size:7px; }
            .contact-page { padding:28px 14px; }
            .contact-card { padding:14px 16px; gap:10px; }
            .contact-card-value { font-size:12px; }
            .footer { padding:20px 14px; }
        }
    </style>
</head>
<body>

<nav class="navbar" id="navbar">
    <a href="/" class="navbar-brand">E-Ballot</a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <span></span><span></span><span></span>
    </button>
    <ul class="navbar-links" id="navLinks">
        <li><a href="/">Candidates</a></li>
        <li><a href="/contact.php" class="nav-active">Contact</a></li>
        <li><a href="/tally.php">Tally</a></li>
        <li><a href="<?= !empty($_SESSION['logged_in']) ? '/dashboard.php' : '/login.php' ?>">Profile</a></li>
        <?php if (!empty($_SESSION['logged_in'])): ?>
        <li><a href="/logout.php">Sign Out</a></li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Hero -->
<section class="section hero-wrapper" style="padding-top:28px;padding-bottom:12px;">
    <div class="hero" style="padding:0;">
        <div class="hero-left">
            <div class="hero-logo-row">
                <div class="hero-logo">
                    <img src="/Presets/jrmsu-logo.png" alt="JRMSU Logo"/>
                </div>
                <span class="hero-logo-label">JRMSU &middot; SSG Election Portal</span>
            </div>
            <h1 class="hero-title">
                Jose Rizal Memorial<br>
                State University<br>
                <span>E-Ballot Portal</span>
            </h1>
            <p class="hero-desc">
                The Jose Rizal Memorial State University E-Ballot Portal is a secure, streamlined
                digital platform designed to modernize the university's student government elections.
            </p>
            <div class="hero-actions">
                <a href="/login.php" class="btn-vote">Vote Now &rarr;</a>
                <a href="/tally.php" class="btn-results">
                    View Results &rarr;
                </a>
            </div>
        </div>
        <div class="hero-right">
            <img src="/Presets/login-hero-real.png" alt="Election Portal Illustration" loading="eager" decoding="async"/>
        </div>
        <div class="hero-countdown">
            <div class="countdown-label" id="countdownLabel">Voting Closed</div>
            <div class="countdown" id="countdown">
                <div class="cd-box" id="cd-box-days"><span class="cd-num" id="cd-days">00</span><div class="cd-unit">Days</div></div>
                <div class="cd-box" id="cd-box-hours"><span class="cd-num" id="cd-hours">00</span><div class="cd-unit">Hours</div></div>
                <div class="cd-box" id="cd-box-mins"><span class="cd-num" id="cd-mins">00</span><div class="cd-unit">Minutes</div></div>
                <div class="cd-box" id="cd-box-secs"><span class="cd-num" id="cd-secs">00</span><div class="cd-unit">Seconds</div></div>
            </div>
        </div>
    </div>
</section>

<div class="contact-page">
    <div class="contact-inner">
        <div class="contact-tag">Get In Touch</div>
        <h1 class="contact-title">Contact Us</h1>
        <p class="contact-desc">
            Have questions about the JRMSU SSG E-Ballot Portal? Reach out to the CCS Creatives Society and we'll get back to you.
        </p>

        <div class="contact-cards">

            <a href="mailto:creativessociety8@gmail.com" class="contact-card">
                <span class="contact-card-icon" style="background:var(--yellow);box-shadow:0 3px 10px rgba(245,196,0,.4);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <span class="contact-card-text">
                    <span class="contact-card-label">Email Us</span>
                    <span class="contact-card-value">creativessociety8@gmail.com</span>
                </span>
                <svg class="contact-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c0c7d6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>

            <a href="https://www.facebook.com/CCSCreativesSociety" target="_blank" rel="noopener" class="contact-card">
                <span class="contact-card-icon" style="background:#1877f2;box-shadow:0 3px 10px rgba(24,119,242,.35);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </span>
                <span class="contact-card-text">
                    <span class="contact-card-label">Facebook</span>
                    <span class="contact-card-value">CCS Creatives Society</span>
                </span>
                <svg class="contact-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c0c7d6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>

            <div class="contact-card" style="cursor:default;">
                <span class="contact-card-icon" style="background:var(--blue);box-shadow:0 3px 10px rgba(26,58,143,.3);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <span class="contact-card-text">
                    <span class="contact-card-label">Location</span>
                    <span class="contact-card-value">JRMSU Main Campus, Dapitan City</span>
                </span>
            </div>

        </div>
    </div>
</div>

<footer class="footer">
    <div class="footer-left">
        <img src="/Presets/ccs-logo.png" alt="CCS-Creatives Logo"/>
        <div>
            <div class="footer-brand"><a href="#" onclick="openTeamModal();return false;" style="color:#f5c400;text-decoration:none;border-bottom:1px solid #f5c400;">CCS-Creatives Society</a></div>
        </div>
    </div>
    <div class="footer-links">
        <span>Security Policy</span>
        <span>Terms of Service</span>
        <span>Accessibility</span>
        <a href="/contact.php">Contact Support</a>
    </div>
    <div class="footer-copy">&copy; <?= date('Y') ?> CCS-Creatives Society - All rights reserved</div>
</footer>

<script>
var navToggle = document.getElementById('navToggle');
var navLinks  = document.getElementById('navLinks');
var navbar    = document.getElementById('navbar');
navToggle.addEventListener('click', function() {
    navbar.classList.toggle('nav-open');
    navLinks.classList.toggle('open');
});
document.addEventListener('click', function(e) {
    if (navbar.classList.contains('nav-open') && !navbar.contains(e.target)) {
        navbar.classList.remove('nav-open');
        navLinks.classList.remove('open');
    }
});
</script>

<!-- Countdown Timer -->
<script>
(function () {
    var target  = <?= $electionTimestamp ?> * 1000;
    var endTime = <?= $_electionEnd ?>     * 1000;

    function pad(n) { return String(n).padStart(2, '0'); }

    var boxes    = ['cd-box-days','cd-box-hours','cd-box-mins','cd-box-secs'].map(function(id){ return document.getElementById(id); });
    var label    = document.getElementById('countdownLabel');
    var curState = '';

    function applyState(state) {
        if (curState === state) return;
        curState = state;
        if (state === 'live') {
            if (label) label.textContent = 'Voting is Open!';
            boxes.forEach(function(b){ if (b) { b.classList.add('live'); b.classList.remove('closed'); }});
        } else if (state === 'closed') {
            if (label) label.textContent = 'Voting Closed';
            boxes.forEach(function(b){ if (b) { b.classList.remove('live'); b.classList.remove('closed'); }});
        } else {
            if (label) label.textContent = 'Coming Soon';
            boxes.forEach(function(b){ if (b) { b.classList.remove('live'); b.classList.remove('closed'); }});
        }
    }

    function setNums(diff) {
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000)  / 60000);
        var s = Math.floor((diff % 60000)    / 1000);
        document.getElementById('cd-days').textContent  = pad(d);
        document.getElementById('cd-hours').textContent = pad(h);
        document.getElementById('cd-mins').textContent  = pad(m);
        document.getElementById('cd-secs').textContent  = pad(s);
    }

    function setZeros() {
        ['cd-days','cd-hours','cd-mins','cd-secs'].forEach(function(id){
            document.getElementById(id).textContent = '00';
        });
    }

    function tick() {
        var now    = Date.now();
        var live   = target && endTime && now >= target && now <= endTime;
        var closed = endTime && now > endTime;

        if (closed) {
            applyState('closed');
            setZeros();
            return;
        }
        if (live) {
            applyState('live');
            setNums(endTime - now);
            return;
        }
        applyState('before');
        var diff = target - now;
        if (diff <= 0) { setZeros(); return; }
        setNums(diff);
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>
</body>
</html>
