<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;

$scheduleRaw    = callModel(function() use ($schoolYear) {
    Election::Check_Voting_Availability(['School_Year' => $schoolYear]);
});
$scheduleStatus = strtolower($scheduleRaw['Status'] ?? '');
$electionOpen   = stripos($scheduleStatus, 'open') !== false
               && stripos($scheduleStatus, 'closed') === false
               && stripos($scheduleStatus, 'error') === false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        /* ── Dashboard-specific overrides ── */

        /* Stat cards with live-loaded values */
        .stat-card-top    { display:flex; align-items:flex-start; justify-content:space-between; }
        .stat-card-label  { font-size:12.5px; color:#6b7280; font-weight:600; margin-bottom:4px; }
        .stat-card-value  { font-size:30px; font-weight:900; color:#1a3a8f; letter-spacing:-.5px; line-height:1; }
        .stat-card-value.loading { color:#d1d5db; }
        .stat-card-trend  { font-size:11.5px; color:#9ca3af; font-weight:500; margin-top:10px; }
        .stat-icon.blue   { background:#dde4f0; color:#1a3a8f; }
        .stat-icon.green  { background:#dcfce7; color:#16a34a; }
        .stat-icon.amber  { background:#fef9c3; color:#92400e; }
        .stat-icon.purple { background:#f0eeff; color:#7c3aed; }

        /* Layout */
        .stats-grid    { grid-template-columns:repeat(4,1fr); }
        @media(max-width:960px){ .stats-grid { grid-template-columns:repeat(2,1fr); } }
        .analytics-grid { display:grid; grid-template-columns:1fr 320px; gap:20px; margin-bottom:20px; }
        @media(max-width:960px){ .analytics-grid { grid-template-columns:1fr; } }
        .card-mb { margin-bottom:20px; }

        /* Card header (live-badge variant) */
        .card-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #f0f0f0; }
        .card-header h3 { font-size:14px; font-weight:800; color:#1a3a8f; }
        .live-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:3px 9px; border-radius:20px; }
        .live-badge-dot { width:6px; height:6px; border-radius:50%; background:#16a34a; animation:blink 1.4s ease-in-out infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

        /* Page header */
        .page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .page-header h1 { font-size:22px; font-weight:800; color:#1a3a8f; letter-spacing:-.4px; }
        .page-header p  { font-size:13px; color:#6b7280; margin-top:2px; }
        .header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .live-indicator { display:flex; align-items:center; gap:6px; font-size:12px; color:#6b7280; font-weight:500; }
        .live-dot { width:8px; height:8px; border-radius:50%; background:#16a34a; animation:blink 1.4s ease-in-out infinite; }
        .filter-row { display:flex; align-items:center; gap:10px; }
        .filter-select { padding:7px 12px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; color:#374151; background:#fff; outline:none; cursor:pointer; min-width:180px; font-family:'Poppins',sans-serif; transition:border-color .15s; }
        .filter-select:focus { border-color:#1a3a8f; }

        /* College bars */
        .college-list { padding:16px 24px; }
        .college-row  { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .college-row:last-child { margin-bottom:0; }
        .college-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
        .college-info { flex:1; min-width:0; }
        .college-name { font-size:12px; color:#374151; font-weight:600; margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bar-wrap  { height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden; }
        .bar-fill  { height:100%; border-radius:99px; width:0%; transition:width 1s ease; }
        .college-pct { font-size:12px; font-weight:700; color:#1a3a8f; width:34px; text-align:right; flex-shrink:0; }

        /* Donut */
        .donut-wrap { display:flex; flex-direction:column; align-items:center; padding:24px 20px 20px; }
        .donut-container { position:relative; width:150px; height:150px; margin-bottom:20px; }
        .donut-svg   { width:150px; height:150px; transform:rotate(-90deg); }
        .donut-track { fill:none; stroke:#f0f0f0; stroke-width:16; }
        .donut-fill  { fill:none; stroke-width:16; stroke-linecap:round; stroke:url(#donutGrad); stroke-dasharray:384; stroke-dashoffset:384; transition:stroke-dashoffset 1.2s ease; }
        .donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; }
        .donut-pct { font-size:24px; font-weight:900; color:#1a3a8f; line-height:1; }
        .donut-sub { font-size:10.5px; color:#9ca3af; font-weight:500; margin-top:2px; }

        /* Summary */
        .summary-stats { width:100%; }
        .summary-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f5f5f5; font-size:13px; }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:#6b7280; font-weight:500; }
        .summary-value { font-weight:700; color:#1a3a8f; }
    </style>
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
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
            <a href="/admin/dashboard.php" class="nav-item active">Dashboard</a>
            <a href="/admin/candidates.php" class="nav-item">Candidates</a>
            <a href="/admin/voters.php" class="nav-item">Voters</a>
            <a href="/admin/results.php" class="nav-item">Results</a>
            <a href="/admin/users.php" class="nav-item">Users</a>
            <a href="/admin/settings.php" class="nav-item">Settings</a>
            <a href="/admin/api-accounts.php" class="nav-item">API Accounts</a>
            <a href="/admin/sync-candidates.php" class="nav-item" style="color:#16a34a; font-weight:600;">⟳ Sync Candidates</a>
        </nav>
        <div class="sidebar-footer">
            <a href="#" onclick="openTeamModal();return false;" class="sidebar-powered">Powered by CCS-Creatives Society</a>
            <a href="/admin/logout.php" class="btn-logout-side">Sign Out</a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main">

        <!-- TOPBAR -->
        <header class="topbar">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-title">Dashboard</div>
            <div class="topbar-right">
                <span class="status-pill <?= $electionOpen ? 'pill-open' : 'pill-closed' ?>">
                    Election <?= $electionOpen ? 'Open' : 'Closed' ?>
                </span>
                <div class="topbar-user"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
            </div>
        </header>

        <!-- CONTENT -->
        <div class="content">

            <!-- PAGE HEADER -->
            <div class="page-header">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p>Real-time voting monitoring &mdash; S.Y. <?= htmlspecialchars($schoolYear) ?>, <?= htmlspecialchars($semester) ?> Semester</p>
                </div>
                <div class="header-actions">
                    <div class="filter-row" style="margin-bottom:0;">
                        <select class="filter-select" id="collegeFilter" onchange="filterCollege(this.value)">
                            <option value="">All Colleges</option>
                        </select>
                    </div>
                    <div class="live-indicator">
                        <div class="live-dot"></div>
                        Auto-refresh in <span id="countdown" style="font-weight:700;color:#0f172a;">10</span>s
                    </div>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div>
                            <div class="stat-card-label">Total Voters</div>
                            <div class="stat-card-value loading" id="stat-total">—</div>
                        </div>
                        <div class="stat-icon blue">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card-trend">Registered voters this election</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-top">
                        <div>
                            <div class="stat-card-label">Already Voted</div>
                            <div class="stat-card-value loading" id="stat-voted">—</div>
                        </div>
                        <div class="stat-icon green">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card-trend">Ballots successfully cast</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-top">
                        <div>
                            <div class="stat-card-label">Pending</div>
                            <div class="stat-card-value loading" id="stat-pending">—</div>
                        </div>
                        <div class="stat-icon amber">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card-trend">Votes awaiting confirmation</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-top">
                        <div>
                            <div class="stat-card-label">Not Yet Voted</div>
                            <div class="stat-card-value loading" id="stat-notyet">—</div>
                        </div>
                        <div class="stat-icon purple">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card-trend">Voters yet to participate</div>
                </div>
            </div>

            <!-- ANALYTICS GRID -->
            <div class="analytics-grid">

                <!-- Left: College Bars -->
                <div class="card">
                    <div class="card-header">
                        <h3>Participation by College</h3>
                        <span class="live-badge">
                            <span class="live-badge-dot"></span>
                            Live
                        </span>
                    </div>
                    <div class="college-list" id="collegeBars">
                        <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="college-row">
                            <div class="college-icon" style="background:#f1f5f9;"></div>
                            <div class="college-info">
                                <div class="college-name" style="background:#f1f5f9;width:140px;height:10px;border-radius:4px;">&nbsp;</div>
                                <div class="bar-wrap" style="margin-top:7px;"><div class="bar-fill" style="width:0%;background:#e2e8f0;"></div></div>
                            </div>
                            <div class="college-pct" style="color:#cbd5e1;">—</div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Right: Donut + Summary -->
                <div class="card">
                    <div class="card-header">
                        <h3>Voter Turnout</h3>
                        <span class="live-badge">
                            <span class="live-badge-dot"></span>
                            Live
                        </span>
                    </div>
                    <div class="donut-wrap">
                        <div class="donut-container">
                            <svg class="donut-svg" viewBox="0 0 150 150">
                                <defs>
                                    <linearGradient id="donutGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%"   stop-color="#1a3a8f"/>
                                        <stop offset="100%" stop-color="#f5c400"/>
                                    </linearGradient>
                                </defs>
                                <circle class="donut-track" cx="75" cy="75" r="61"/>
                                <circle class="donut-fill" id="donutFill" cx="75" cy="75" r="61"/>
                            </svg>
                            <div class="donut-center">
                                <div class="donut-pct" id="donutPct">0%</div>
                                <div class="donut-sub">Turnout</div>
                            </div>
                        </div>
                        <div class="summary-stats">
                            <div class="summary-row">
                                <span class="summary-label">Total Voters</span>
                                <span class="summary-value" id="sum-total">—</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Already Voted</span>
                                <span class="summary-value" id="sum-voted">—</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Pending</span>
                                <span class="summary-value" id="sum-pending">—</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Not Yet Voted</span>
                                <span class="summary-value" id="sum-notyet">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT VOTER ACTIVITY TABLE -->
            <div class="card card-mb">
                <div class="card-header">
                    <h3>Recent Voter Activity</h3>
                    <span class="live-badge">
                        <span class="live-badge-dot"></span>
                        Live
                    </span>
                </div>
                <div id="recentTableWrap">
                    <div class="empty-state">
                        <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Loading voter activity...
                    </div>
                </div>
            </div>

        </div>

        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>

<script>
const REFRESH_INTERVAL = 10;
let countdown = REFRESH_INTERVAL;
let allColleges = [];
let currentFilter = '';

const circumference = 2 * Math.PI * 61; // ~383.3

function fmt(n) {
    return n === null || n === undefined ? '—' : Number(n).toLocaleString();
}

function getInitials(name) {
    if (!name) return '?';
    const words = name.replace(/college of /i, '').replace(/school of /i, '').trim().split(/\s+/);
    return words.slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

const collegePalette = [
    { bg: '#dde4f0', color: '#1a3a8f' },
    { bg: '#fef9c3', color: '#92400e' },
    { bg: '#dcfce7', color: '#15803d' },
    { bg: '#fff7ed', color: '#ea580c' },
    { bg: '#fee2e2', color: '#dc2626' },
    { bg: '#fef9c3', color: '#92400e' },
    { bg: '#f0fdfa', color: '#0d9488' },
    { bg: '#dde4f0', color: '#1a3a8f' },
    { bg: '#f0eeff', color: '#7c3aed' },
    { bg: '#dcfce7', color: '#15803d' },
];

const barColors = [
    'linear-gradient(90deg,#1a3a8f,#2563eb)',
    'linear-gradient(90deg,#f5c400,#d97706)',
    'linear-gradient(90deg,#16a34a,#22c55e)',
    'linear-gradient(90deg,#ea580c,#fb923c)',
    'linear-gradient(90deg,#dc2626,#f87171)',
    'linear-gradient(90deg,#f5c400,#fbbf24)',
    'linear-gradient(90deg,#0d9488,#2dd4bf)',
    'linear-gradient(90deg,#1a3a8f,#4f46e5)',
    'linear-gradient(90deg,#0d2a6e,#1a3a8f)',
    'linear-gradient(90deg,#15803d,#16a34a)',
];

function renderCollegeBars(colleges) {
    const container = document.getElementById('collegeBars');
    const select    = document.getElementById('collegeFilter');

    if (!colleges || colleges.length === 0) {
        container.innerHTML = '<div class="empty-state"><svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>No college data available yet.</div>';
        return;
    }

    const existing = Array.from(select.options).map(o => o.value);
    colleges.forEach(c => {
        const name = c.College_Description || c.College || c.college || 'Unknown';
        if (!existing.includes(name)) {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            select.appendChild(opt);
        }
    });

    const filtered = currentFilter
        ? colleges.filter(c => (c.College_Description || c.College || c.college || '') === currentFilter)
        : colleges;

    container.innerHTML = filtered.map((c, i) => {
        const name     = c.College_Description || c.College || c.college || 'College';
        const voted    = parseInt(c.Already_Voted ?? c.Already_Vote ?? c.VoterCount ?? c.Vote_Count ?? c.Voted ?? c.already_vote ?? 0);
        const total    = parseInt(c.Total_Voters ?? c.Total ?? c.total_voters ?? 0);
        const pctRaw   = total > 0 ? (voted / total) * 100 : 0;
        const pctRound = Math.round(pctRaw);
        const pctLabel = pctRound + '%';
        const barPct   = total > 0 ? Math.max(pctRaw, voted > 0 ? 0.4 : 0) : 0;
        const palette  = collegePalette[i % collegePalette.length];
        const barColor = barColors[i % barColors.length];
        const initials = getInitials(name);
        const countLabel = total > 0 ? `${voted.toLocaleString()} of ${total.toLocaleString()} students` : `${voted.toLocaleString()} voted`;

        return `<div class="college-row">
            <div class="college-icon" style="background:${palette.bg};color:${palette.color};">${initials}</div>
            <div class="college-info">
                <div class="college-name" title="${name}">${name}</div>
                <div class="bar-wrap">
                    <div class="bar-fill" style="width:0%;background:${barColor};" data-pct="${barPct}"></div>
                </div>
                <div style="font-size:10px;color:#9ca3af;margin-top:2px;">${countLabel}</div>
            </div>
            <div class="college-pct">${pctLabel}</div>
        </div>`;
    }).join('');

    requestAnimationFrame(() => {
        container.querySelectorAll('.bar-fill').forEach(bar => {
            bar.style.width = bar.dataset.pct + '%';
        });
    });
}

function renderRecentTable(recent) {
    const wrap = document.getElementById('recentTableWrap');

    if (!recent || recent.length === 0) {
        wrap.innerHTML = `<div class="empty-state">
            <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            No recent voter activity yet.</div>`;
        return;
    }

    function fmtTime(ts) {
        if (!ts) return '—';
        const d = new Date(ts.replace(' ', 'T'));
        if (isNaN(d)) return ts;
        return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})
             + ' · ' + d.toLocaleDateString([], {month: 'short', day: 'numeric'});
    }

    wrap.innerHTML = `<table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>College</th>
                <th>Time Voted</th>
            </tr>
        </thead>
        <tbody>
        ${recent.map(r => `<tr>
            <td style="font-family:ui-monospace,monospace;font-size:12px;color:#64748b;">${r.Voter_ID ?? '—'}</td>
            <td style="font-weight:500;">${r.Student_Name ?? '—'}</td>
            <td><span style="font-size:11px;font-weight:700;background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:20px;">${r.College_Code ?? '—'}</span></td>
            <td style="color:#94a3b8;font-size:12px;">${fmtTime(r.Voted_At)}</td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function updateDonut(pct) {
    const fill   = document.getElementById('donutFill');
    const label  = document.getElementById('donutPct');
    const offset = circumference - (pct / 100) * circumference;
    fill.style.strokeDasharray  = circumference;
    fill.style.strokeDashoffset = offset;
    label.textContent = pct + '%';
}

function animateValue(el, newVal) {
    if (newVal === null || newVal === undefined) return;
    el.classList.remove('loading');
    el.textContent = fmt(newVal);
}

function filterCollege(val) {
    currentFilter = val;
    renderCollegeBars(allColleges);
}

async function fetchStats() {
    try {
        const res  = await fetch('/admin/ajax/voting-stats.php?_=' + Date.now());
        const data = await res.json();

        allColleges = data.colleges || [];

        animateValue(document.getElementById('stat-total'),   data.total_voters);
        animateValue(document.getElementById('stat-voted'),   data.already_voted);
        animateValue(document.getElementById('stat-pending'), data.pending);
        animateValue(document.getElementById('stat-notyet'),  data.not_yet);

        document.getElementById('sum-total').textContent   = fmt(data.total_voters);
        document.getElementById('sum-voted').textContent   = fmt(data.already_voted);
        document.getElementById('sum-pending').textContent = fmt(data.pending);
        document.getElementById('sum-notyet').textContent  = fmt(data.not_yet);

        updateDonut(data.percent || 0);
        renderCollegeBars(allColleges);
        renderRecentTable(data.recent || []);

    } catch(e) {
        console.warn('Stats fetch failed:', e);
    }
}

function startCountdown() {
    countdown = REFRESH_INTERVAL;
    const el = document.getElementById('countdown');
    clearInterval(window._cdTimer);
    window._cdTimer = setInterval(() => {
        countdown--;
        if (el) el.textContent = countdown;
        if (countdown <= 0) {
            countdown = REFRESH_INTERVAL;
            fetchStats();
        }
    }, 1000);
}

fetchStats();
startCountdown();
</script>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
</body>
</html>
