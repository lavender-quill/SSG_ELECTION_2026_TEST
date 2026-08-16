<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$success = '';
$error   = '';
$searchResult = null;

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $_csrfOk = hash_equals(adminCsrfToken(), trim($_POST['_csrf'] ?? ''));
    if (!$_csrfOk) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {

    if ($_POST['action'] === 'register') {
        $name  = trim($_POST['account_name'] ?? '');
        $email = trim($_POST['email']        ?? '');
        $pass  = trim($_POST['password']     ?? '');
        if (!$name || !$email || !$pass) {
            $error = 'Account name, email, and password are all required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $res = callModel(function() use ($name, $email, $pass) {
                API_Account::Register_Record(['Account_Name' => $name, 'Email' => $email, 'Password' => $pass]);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to register account.'; }
            else                { $success = 'API account "' . htmlspecialchars($name) . '" registered. It starts as Inactive — activate it below.'; }
        }
    }

    if ($_POST['action'] === 'update_status') {
        $email  = trim($_POST['status_email']  ?? '');
        $status = trim($_POST['status_value']  ?? '');
        if (!$email || !$status) {
            $error = 'Email and status are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $error = 'Invalid status value.';
        } else {
            $res = callModel(function() use ($email, $status) {
                API_Account::Update_Status_Record(['Email' => $email, 'Status' => $status]);
            });
            if (isError($res)) { $error = $res['Status'] ?? 'Failed to update status.'; }
            else                { $success = 'Status for ' . htmlspecialchars($email) . ' set to ' . htmlspecialchars($status) . '.'; }
        }
    }

    if ($_POST['action'] === 'search') {
        $key = trim($_POST['search_key'] ?? '');
        if (!$key) {
            $error = 'Enter a search keyword.';
        } else {
            $res = callModel(function() use ($key) {
                API_Account::Search_Record(['Seach_Key' => $key]);
            });
            $searchResult = unwrap($res);
        }
    }

    } // end CSRF else
}

$schoolYear = ELECTION_SCHOOL_YEAR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>API Accounts &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .content { max-width:900px; }
        table.result-list { width:100%; border-collapse:collapse; }
        table.result-list th { background:#fafafa; padding:9px 14px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:left; }
        table.result-list td { padding:9px 14px; font-size:13px; color:#374151; border-bottom:1px solid #f5f5f5; }
        @media(max-width:768px){
            .content { max-width:100% !important; }
        }
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
            <a href="/admin/settings.php" class="nav-item">Settings</a>
            <a href="/admin/api-accounts.php" class="nav-item active">API Accounts</a>
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
            <div class="topbar-title">API Accounts</div>
            <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
        </div>

        <div class="content">

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="info-box"> API accounts are external application credentials stored in the <strong>SSG_API_Manage</strong> database. Each account receives an API key and secret used by third-party services to authenticate with this election system.</div>

            <!-- Register -->
            <div class="section-title">Register New API Account</div>
            <div class="card">
                <div class="card-header-bar"><h3>New Account</h3><span>New accounts start as Inactive — activate after creation</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="register"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Account Name</label>
                                <input type="text" name="account_name" placeholder="e.g. VoterPortalApp" required/>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="e.g. app@jrmsu.edu.ph" required/>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" placeholder="Secure password" required/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Register Account</button>
                    </form>
                </div>
            </div>

            <!-- Search -->
            <div class="section-title">Search API Accounts</div>
            <div class="card">
                <div class="card-header-bar"><h3> Lookup Account</h3><span>Search by name, email, or any keyword</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="search"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Search Keyword</label>
                                <input type="text" name="search_key" placeholder="Name, email, etc."
                                       value="<?= htmlspecialchars($_POST['search_key'] ?? '') ?>" required/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-blue"> Search</button>
                    </form>

                    <?php if ($searchResult !== null): ?>
                    <div class="result-box">
                        <strong>Search Results</strong>
                        <?php if (isset($searchResult['Status']) && stripos($searchResult['Status'],'Error')!==false): ?>
                        <p style="color:#dc2626;"><?= htmlspecialchars($searchResult['Status']) ?></p>
                        <?php elseif (!empty($searchResult)): ?>
                            <?php
                            $records = $searchResult['Record'] ?? $searchResult;
                            if (is_array($records) && !empty($records) && is_array(reset($records))):
                                $cols = array_keys(reset($records));
                            ?>
                            <table class="result-list">
                                <thead><tr><?php foreach($cols as $c): ?><th><?= htmlspecialchars(str_replace('_',' ',$c)) ?></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                <?php foreach($records as $rec): ?>
                                <tr><?php foreach($cols as $c): $rv=$rec[$c]??'—'; $cl=strtolower($c);
                                    if(stripos($cl,'status')!==false){
                                        $sc=strtolower($rv??'');
                                        $bc=$sc==='active'?'badge-active':($sc==='inactive'?'badge-inactive':'badge-other');
                                        echo '<td><span class="badge '.$bc.'">'.htmlspecialchars($rv).'</span></td>';
                                    } else { echo '<td>'.htmlspecialchars(is_scalar($rv)?$rv:'—').'</td>'; }
                                endforeach; ?></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php elseif(is_array($records)): ?>
                            <table class="kv-table">
                                <?php foreach($records as $k=>$v): if(!is_scalar($v)) continue; ?>
                                <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                            <?php else: ?>
                            <p style="color:#9ca3af;">No accounts found.</p>
                            <?php endif; ?>
                        <?php else: ?>
                        <p style="color:#9ca3af;">No results found.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update Status -->
            <div class="section-title">Activate / Deactivate Account</div>
            <div class="card">
                <div class="card-header-bar"><h3> Update Account Status</h3><span>Enable or disable an API account by email</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Account Email</label>
                                <input type="email" name="status_email" placeholder="e.g. app@jrmsu.edu.ph" required/>
                            </div>
                            <div class="form-group">
                                <label>New Status</label>
                                <select name="status_value" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="submit" name="status_value_override" class="btn btn-green" onclick="document.querySelector('[name=status_value]').value='Active';">Activate</button>
                            <button type="submit" name="status_value_override" class="btn btn-red"   onclick="document.querySelector('[name=status_value]').value='Inactive';"> Deactivate</button>
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
</body>
</html>
