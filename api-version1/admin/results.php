<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;

// Load election results
$resultsRaw = callModel(function() use ($schoolYear) {
    Election::election_generate_result(['School_Year' => $schoolYear]);
});
$resultsList = [];
if (isset($resultsRaw['Record']) && is_array($resultsRaw['Record'])) {
    $resultsList = $resultsRaw['Record'];
} elseif (is_array($resultsRaw) && !empty($resultsRaw) && !isset($resultsRaw['Status'])) {
    $resultsList = $resultsRaw;
}

// Load vote counts per college
$voteCountsRaw = callModel(function() use ($schoolYear) {
    Election::Get_votes_count_per_College(['School_Year' => $schoolYear]);
});
$voteCounts = [];
if (isset($voteCountsRaw['Record']) && is_array($voteCountsRaw['Record'])) {
    $voteCounts = $voteCountsRaw['Record'];
}

// Load schedule
$scheduleRaw = callModel(function() use ($schoolYear) {
    Election::Check_Voting_Availability(['School_Year' => $schoolYear]);
});
$electionOpen = !isError($scheduleRaw);

// Group results by position
$byPosition = [];
foreach ($resultsList as $r) {
    $pos = $r['Position_ID'] ?? $r['Position'] ?? $r['Position_Name'] ?? 'Unknown';
    $byPosition[$pos][] = $r;
}
ksort($byPosition, SORT_NATURAL);

// Determine columns
$columns = [];
if (!empty($resultsList)) {
    $firstRow = reset($resultsList);
    if (is_array($firstRow)) {
        foreach ($firstRow as $k => $v) {
            if (is_scalar($v)) $columns[] = $k;
        }
    }
}

// Column label map
function colLabel(string $col): string {
    static $map = [
        'Candidate_ID'       => 'Candidate ID',
        'Student_ID'         => 'Student ID',
        'Student_Name'       => 'Name',
        'Position_ID'        => 'Position',
        'Candidate_Slate_ID' => 'Slate',
        'Election_Year'      => 'Election Year',
        'Application_Status' => 'Status',
        'Vote_Count'         => 'Votes',
        'School_Year'        => 'School Year',
        'Vote_Percentage'    => 'Vote %',
    ];
    return $map[$col] ?? ucwords(str_replace('_', ' ', $col));
}

// Position name map
$positionNameMap = [
    1=>'President', 2=>'Vice President', 3=>'Governor', 4=>'Vice Governor',
    5=>'Representative (CCS)', 6=>'Representative (CBA)', 7=>'Representative (CTED)',
    8=>'Representative (CAS)', 9=>'Representative (CCJE)', 10=>'Representative (CIT)',
    11=>'Representative (CTED-HS)', 12=>'Representative (CME)', 13=>'Representative (COE)',
    14=>'Representative (COL)', 15=>'Representative (HS)', 16=>'Representative (GRAD)',
    17=>'Representative (SOM)', 18=>'Representative (CNAHS)',
];

// Total votes cast
$totalVotesCast = 0;
foreach ($voteCounts as $vc) {
    $totalVotesCast += (int)($vc['Vote_Count'] ?? $vc['Count'] ?? $vc['Votes'] ?? 0);
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
    <title>Results &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .stats-grid { grid-template-columns: repeat(3,1fr); }
        .pos-card  { margin-bottom:24px; }
        .pos-header { padding:14px 22px; background:linear-gradient(135deg,#0d2a6e,#1a3a8f); display:flex; align-items:center; justify-content:space-between; }
        .pos-header h4 { color:#fff; font-size:14px; font-weight:800; }
        .pos-header span { color:#a8c4f0; font-size:12px; }
        .vote-bar-wrap { display:flex; align-items:center; gap:12px; min-width:160px; }
        .vote-bar { flex:1; height:8px; background:#e5e7eb; border-radius:4px; overflow:hidden; }
        .vote-bar-fill { height:100%; border-radius:4px; background:linear-gradient(135deg,#1a3a8f,#0d2a6e); transition:width .6s; }
        .vote-bar-count { font-size:13px; font-weight:700; color:#1a3a8f; white-space:nowrap; }
        .winner-row td { background:#fefce8 !important; }
        .winner-row:hover td { background:#fef9c3 !important; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
        @media(max-width:800px){ .grid-2 { grid-template-columns:1fr; } }
        @media(max-width:768px){
            .stats-grid { grid-template-columns:repeat(2,1fr) !important; }
            .pos-header { flex-direction:column; align-items:flex-start; gap:4px; }
            .vote-bar-wrap { min-width:100px; gap:8px; }
        }
        @media(max-width:480px){
            .stats-grid { grid-template-columns:1fr !important; }
            .vote-bar-wrap { min-width:70px; }
            .vote-bar-count { font-size:11px; }
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }
        .live-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700;
                      background:#dcfce7; color:#15803d; padding:3px 10px; border-radius:20px; }
        .live-dot   { width:7px; height:7px; border-radius:50%; background:#22c55e;
                      animation:blink 1.2s ease-in-out infinite; }
        .tally-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:8px; }
        .tally-updated { font-size:12px; color:#94a3b8; }
        .winner-crown { font-size:13px; margin-right:2px; }
        .winner-badge { background:#fef9c3; color:#854d0e; font-size:11px; font-weight:800;
                        padding:2px 8px; border-radius:20px; border:1px solid #fde047; }
        .elected-badge { background:#dcfce7; color:#15803d; font-size:11px; font-weight:700;
                         padding:2px 8px; border-radius:20px; border:1px solid #86efac; }
        .tally-skeleton { height:200px; background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
                          background-size:400% 100%; border-radius:12px;
                          animation:shimmer 1.4s ease infinite; }
        @keyframes shimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }
        .slate-pill { font-size:11px; font-weight:600; background:#eff6ff; color:#1d4ed8;
                      padding:2px 8px; border-radius:20px; }
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
            <a href="/admin/results.php" class="nav-item active">Results</a>
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
            <div class="topbar-title">Election Results</div>
            <div class="topbar-right">
                <span class="status-pill <?= $electionOpen ? 'pill-open' : 'pill-closed' ?>">
                    Election <?= $electionOpen ? 'Open' : 'Closed' ?>
                </span>
                <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
            </div>
        </div>

        <div class="content">
            <div class="section-title">Election Results &mdash; S.Y. <?= htmlspecialchars($schoolYear) ?></div>

            <?php if ($electionOpen): ?>
            <div class="notice">
                 The election is currently <strong>open</strong>. Results shown are live and will update as votes are cast.
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-purple"></div>
                    <div class="stat-value"><?= count($byPosition) ?></div>
                    <div class="stat-label">Positions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-blue"></div>
                    <div class="stat-value" id="statTotalVotes"><?= $totalVotesCast ?></div>
                    <div class="stat-label">Votes Cast</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-<?= $electionOpen ? 'green' : 'orange' ?>">
                        <?= $electionOpen ? '' : '' ?>
                    </div>
                    <div class="stat-value"><?= $electionOpen ? 'Live' : 'Final' ?></div>
                    <div class="stat-label">Result Status</div>
                </div>
            </div>

            <?php if (!empty($voteCounts)): ?>
            <div class="card">
                <div class="card-header-bar">
                    <h3> Votes Cast by College</h3>
                    <span>S.Y. <?= htmlspecialchars($schoolYear) ?></span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($voteCounts[0] as $k => $v): if (is_scalar($v)): ?>
                                <th><?= htmlspecialchars(colLabel($k)) ?></th>
                                <?php endif; endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($voteCounts as $vc): ?>
                        <tr>
                            <?php foreach ($vc as $k => $v): if (is_scalar($v)): ?>
                            <td><?= htmlspecialchars($v) ?></td>
                            <?php endif; endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Live tally section (JS-rendered) -->
            <div class="tally-meta">
                <div class="section-title" style="margin:0">Results by Position</div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <?php if ($electionOpen): ?>
                    <span class="live-badge"><span class="live-dot"></span>Live</span>
                    <?php endif; ?>
                    <span class="tally-updated" id="tallyUpdated">Loading…</span>
                </div>
            </div>
            <div id="tallyWrap">
                <div class="tally-skeleton"></div>
            </div>

            <!-- API Debug Info -->
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;font-size:13px;color:#9ca3af;padding:8px 0;user-select:none;">
                     API Debug Info (click to expand)
                </summary>
                <div class="card" style="margin-top:10px;">
                    <div class="card-header-bar"><h3>Raw API Responses</h3></div>
                    <div style="padding:16px 20px;font-size:12px;font-family:monospace;background:#f9fafb;overflow-x:auto;white-space:pre-wrap;word-break:break-all;">
                        <strong>School Year queried:</strong> <?= htmlspecialchars($schoolYear) ?><br/><br/>
                        <strong>Results response:</strong><br/>
                        <?= htmlspecialchars(json_encode($resultsRaw, JSON_PRETTY_PRINT)) ?><br/><br/>
                        <strong>Vote counts by college:</strong><br/>
                        <?= htmlspecialchars(json_encode($voteCountsRaw, JSON_PRETTY_PRINT)) ?><br/><br/>
                        <strong>Election schedule:</strong><br/>
                        <?= htmlspecialchars(json_encode($scheduleRaw, JSON_PRETTY_PRINT)) ?>
                    </div>
                </div>
            </details>

        </div>
        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
<script>
(function () {
    const POLL_MS  = 10000;
    const isLive   = <?= $electionOpen ? 'true' : 'false' ?>;
    let   lastFetch = null;
    let   timer     = null;

    function fmtAgo(d) {
        if (!d) return '';
        const sec = Math.round((Date.now() - d) / 1000);
        if (sec < 5)  return 'just now';
        if (sec < 60) return sec + 's ago';
        return Math.round(sec / 60) + 'm ago';
    }

    function startAgoTick() {
        setInterval(() => {
            const el = document.getElementById('tallyUpdated');
            if (el && lastFetch) el.textContent = 'Updated ' + fmtAgo(lastFetch);
        }, 5000);
    }

    function renderTally(data) {
        const wrap = document.getElementById('tallyWrap');
        if (!data.ok || !data.positions || data.positions.length === 0) {
            wrap.innerHTML = `<div class="card"><div class="empty-state">
                No approved candidates found for S.Y. ${data.school_year || ''}.
                <br/><br/>
                <small style="font-size:12px;color:#cbd5e1">Add approved candidates to see live tallies.</small>
            </div></div>`;
            return;
        }

        let html = '';
        for (const pos of data.positions) {
            const total    = pos.candidates.reduce((s, c) => s + c.votes, 0);
            const maxVotes = pos.candidates.length > 0 ? pos.candidates[0].votes : 0;
            const numElect = pos.num_elected || 1;

            html += `<div class="card pos-card">
                <div class="pos-header">
                    <h4>${esc(pos.position_name)}</h4>
                    <span>${pos.candidates.length} candidate${pos.candidates.length !== 1 ? 's' : ''} &bull; ${total} vote${total !== 1 ? 's' : ''}</span>
                </div>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Student ID</th>
                        <th>Slate</th>
                        <th>Votes</th>
                    </tr></thead>
                    <tbody>`;

            pos.candidates.forEach((c, idx) => {
                const pct       = maxVotes > 0 ? Math.round(c.votes / maxVotes * 100) : 0;
                const isWinner  = idx === 0 && c.votes > 0;
                const isElected = idx < numElect && c.votes > 0;
                const rowCls    = isWinner ? 'winner-row' : '';

                let rankBadge = `<span style="color:#9ca3af;font-weight:700">#${idx + 1}</span>`;
                if (isWinner) {
                    rankBadge = `<span class="winner-badge">&#x1F451; #1</span>`;
                } else if (isElected) {
                    rankBadge = `<span class="elected-badge">#${idx + 1}</span>`;
                }

                const slatePill = c.slate && c.slate !== '—'
                    ? `<span class="slate-pill">${esc(c.slate)}</span>`
                    : `<span style="color:#cbd5e1">—</span>`;

                html += `<tr class="${rowCls}">
                    <td>${rankBadge}</td>
                    <td style="font-weight:600">${esc(c.name)}</td>
                    <td style="font-family:ui-monospace,monospace;font-size:12px;color:#64748b">${esc(c.student_id)}</td>
                    <td>${slatePill}</td>
                    <td>
                        <div class="vote-bar-wrap">
                            <div class="vote-bar">
                                <div class="vote-bar-fill" style="width:${pct}%"></div>
                            </div>
                            <span class="vote-bar-count">${c.votes}</span>
                        </div>
                    </td>
                </tr>`;
            });

            html += `</tbody></table></div></div>`;
        }

        // Animate bar fills: set to 0 first, then transition
        wrap.innerHTML = html;
        requestAnimationFrame(() => {
            wrap.querySelectorAll('.vote-bar-fill').forEach(el => {
                const target = el.style.width;
                el.style.width = '0%';
                requestAnimationFrame(() => { el.style.width = target; });
            });
        });
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    async function fetchTally() {
        try {
            const r = await fetch('/admin/ajax/results-live.php?_=' + Date.now());
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            renderTally(data);
            lastFetch = Date.now();

            // Update total votes card
            const el = document.getElementById('statTotalVotes');
            if (el) el.textContent = data.total_votes ?? 0;

            const upEl = document.getElementById('tallyUpdated');
            if (upEl) upEl.textContent = 'Updated just now';
        } catch (err) {
            console.warn('Results tally fetch failed:', err);
            const upEl = document.getElementById('tallyUpdated');
            if (upEl) upEl.textContent = 'Update failed — retrying…';
        }
    }

    fetchTally();
    startAgoTick();
    if (isLive) {
        timer = setInterval(fetchTally, POLL_MS);
    }
})();
</script>
</body>
</html>
