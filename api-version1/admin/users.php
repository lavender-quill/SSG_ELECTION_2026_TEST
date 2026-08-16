<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$success = '';
$error   = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $_csrfOk = hash_equals(adminCsrfToken(), trim($_POST['_csrf'] ?? ''));
    if (!$_csrfOk) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {

    if ($_POST['action'] === 'upsert') {
        $username   = trim($_POST['username']   ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $userlevel  = trim($_POST['userlevel']  ?? '');
        $_allowedLevels = ['Admin', 'Voter', 'Moderator', 'Viewer'];
        if (!$username || !$student_id || !$userlevel) {
            $error = 'Username, Student ID, and User Level are all required.';
        } elseif (!in_array($userlevel, $_allowedLevels, true)) {
            $error = 'Invalid user level.';
        } else {
            $result = callModel(function() use ($username, $student_id, $userlevel) {
                Election::User_Account_CRUD([
                    'Action'      => 'UPSERT',
                    '_Username'   => $username,
                    '_Student_ID' => $student_id,
                    '_Userlevel'  => $userlevel,
                ]);
            });
            if (isError($result)) {
                $error = $result['Status'] ?? 'Failed to save user.';
            } else {
                $success = 'User "' . htmlspecialchars($username) . '" saved successfully.';
            }
        }
    }

    if ($_POST['action'] === 'delete') {
        $username   = trim($_POST['del_username']   ?? '');
        $student_id = trim($_POST['del_student_id'] ?? '');
        if (!$username || !$student_id) {
            $error = 'Username and Student ID are required to delete a user.';
        } else {
            $result = callModel(function() use ($username, $student_id) {
                Election::User_Account_CRUD([
                    'Action'      => 'DELETE',
                    '_Username'   => $username,
                    '_Student_ID' => $student_id,
                ]);
            });
            if (isError($result)) {
                $error = $result['Status'] ?? 'Failed to delete user.';
            } else {
                $success = 'User "' . htmlspecialchars($username) . '" deleted.';
            }
        }
    }

    if ($_POST['action'] === 'update_status') {
        $username = trim($_POST['status_username'] ?? '');
        $status   = trim($_POST['status_value']    ?? '');
        if (!$username || !$status) {
            $error = 'Username and status are required.';
        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $error = 'Invalid status value.';
        } else {
            $result = callModel(function() use ($username, $status) {
                Election::User_Account_Update_Status([
                    'UserName'    => $username,
                    'User_Status' => $status,
                ]);
            });
            if (isError($result)) {
                $error = $result['Status'] ?? 'Failed to update status.';
            } else {
                $success = 'Status for "' . htmlspecialchars($username) . '" updated to ' . htmlspecialchars($status) . '.';
            }
        }
    }

    if ($_POST['action'] === 'add_service') {
        $svc = trim($_POST['service_name'] ?? '');
        if (!$svc) {
            $error = 'Service name is required.';
        } else {
            $result = callModel(function() use ($svc) {
                Election::App_Service_CRUD(['Action' => 'INSERT', 'Service_Name' => $svc]);
            });
            if (isError($result)) { $error = $result['Status'] ?? 'Failed to add service.'; }
            else                   { $success = 'Service "' . htmlspecialchars($svc) . '" added.'; }
        }
    }

    if ($_POST['action'] === 'delete_service') {
        $svc = trim($_POST['del_service_name'] ?? '');
        if (!$svc) {
            $error = 'Service name is required.';
        } else {
            $result = callModel(function() use ($svc) {
                Election::App_Service_CRUD(['Action' => 'DELETE', 'Service_Name' => $svc]);
            });
            if (isError($result)) { $error = $result['Status'] ?? 'Failed to delete service.'; }
            else                   { $success = 'Service "' . htmlspecialchars($svc) . '" deleted.'; }
        }
    }

    if ($_POST['action'] === 'get_user_log') {
        $uid = trim($_POST['log_user_id'] ?? '');
        if (!$uid) {
            $error = 'User ID is required.';
        } else {
            $_SESSION['log_result'] = callModel(function() use ($uid) {
                Election::Get_User_Log(['User_ID' => $uid]);
            });
            $_SESSION['log_uid'] = $uid;
        }
    }

    if ($_POST['action'] === 'insert_user_log') {
        $uid = trim($_POST['log_insert_uid']     ?? '');
        $sid = trim($_POST['log_insert_service'] ?? '');
        if (!$uid) {
            $error = 'User ID is required.';
        } else {
            $payload = ['User_ID' => $uid];
            if ($sid) $payload['Service_ID'] = $sid;
            $result = callModel(function() use ($payload) {
                Election::Insert_User_Log($payload);
            });
            if (isError($result)) { $error = $result['Status'] ?? 'Failed to insert log.'; }
            else                   { $success = 'Log entry inserted for user "' . htmlspecialchars($uid) . '".'; }
        }
    }

    } // end CSRF else
}

// Load all users
$usersRaw  = callModel(function() {
    Election::User_Account_CRUD(['Action' => 'VIEW_ALL']);
});

// Load app services
$servicesRaw = callModel(function() {
    Election::App_Service_CRUD(['Action' => 'VIEW_ALL']);
});
$serviceList = [];
if (isset($servicesRaw['Record']) && is_array($servicesRaw['Record'])) {
    $serviceList = $servicesRaw['Record'];
} elseif (is_array($servicesRaw) && !empty($servicesRaw) && !isset($servicesRaw['Status'])) {
    $serviceList = $servicesRaw;
}

// Retrieve any log result from session
$logResult = $_SESSION['log_result'] ?? null;
$logUid    = $_SESSION['log_uid']    ?? '';
unset($_SESSION['log_result'], $_SESSION['log_uid']);
$userList = [];
if (isset($usersRaw['Record']) && is_array($usersRaw['Record'])) {
    $userList = $usersRaw['Record'];
} elseif (is_array($usersRaw) && !empty($usersRaw) && !isset($usersRaw['Status'])) {
    $userList = $usersRaw;
}

// Determine columns from first record
$columns = [];
if (!empty($userList)) {
    $first = reset($userList);
    if (is_array($first)) {
        foreach ($first as $k => $v) {
            if (is_scalar($v)) $columns[] = $k;
        }
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
    <title>Users &mdash; SSG Election System</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
        <link rel="stylesheet" href="/Presets/admin.css"/>
    <style>
        .btn-danger  { background:#dc2626; color:#fff; padding:6px 14px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:'Poppins',sans-serif; font-weight:700; }
        .btn-status  { background:#f0f0f0; color:#374151; padding:6px 14px; font-size:12px; border:1px solid #dde1ea; border-radius:8px; cursor:pointer; font-family:'Poppins',sans-serif; font-weight:600; }
        .btn-status:hover { background:#dde4f0; color:#1a3a8f; border-color:#a8c4f0; }
        @media(max-width:768px){
            .actions { flex-wrap:wrap; gap:6px; }
            .actions form { flex:1; min-width:0; }
            .actions .btn-status,
            .actions .btn-danger { width:100%; text-align:center; }
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
            <a href="/admin/users.php" class="nav-item active">Users</a>
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
            <div class="topbar-title">Users</div>
            <div class="topbar-user"> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
        </div>

        <div class="content">

            <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Add / Edit User -->
            <div class="section-title">Add / Update User</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3>New User</h3>
                    <span>Creates or updates an election system user</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="upsert"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" placeholder="e.g. jdoe2025" required/>
                            </div>
                            <div class="form-group">
                                <label for="student_id">Student ID</label>
                                <input type="text" id="student_id" name="student_id" placeholder="e.g. 2021-00123" required/>
                            </div>
                            <div class="form-group">
                                <label for="userlevel">User Level</label>
                                <select id="userlevel" name="userlevel" required>
                                    <option value="">Select level...</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Voter">Voter</option>
                                    <option value="Moderator">Moderator</option>
                                    <option value="Viewer">Viewer</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Save User</button>
                    </form>
                </div>
            </div>

            <!-- User List -->
            <div class="section-title">All Users</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3> Election System Users</h3>
                    <span><?= count($userList) ?> user<?= count($userList) !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($userList)): ?>
                <div class="empty-state">
                     No users found. Add one above.<br/>
                    <small style="font-size:12px;color:#cbd5e1;margin-top:8px;display:block;">
                        Raw API response: <?= htmlspecialchars(json_encode($usersRaw)) ?>
                    </small>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <?php foreach ($columns as $col): ?>
                                <th><?= htmlspecialchars(str_replace('_', ' ', $col)) ?></th>
                                <?php endforeach; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $n = 0; foreach ($userList as $u): $n++;
                            // Case-insensitive key search — stored proc may return any casing
                            $uname = ''; $uid = ''; $ustat = '';
                            foreach ($u as $_k => $_v) {
                                $_kl = strtolower(ltrim($_k, '_'));
                                if (!$uname && in_array($_kl, ['username','user_name'], true)) $uname = (string)$_v;
                                if (!$uid   && in_array($_kl, ['student_id','studentid'], true)) $uid   = (string)$_v;
                                if (!$ustat && in_array($_kl, ['status','user_status'], true))   $ustat = strtolower((string)$_v);
                            }
                        ?>
                        <tr>
                            <td><?= $n ?></td>
                            <?php foreach ($columns as $col): ?>
                            <td>
                                <?php $val = $u[$col] ?? '—'; $cl = strtolower($col);
                                if (stripos($cl, 'status') !== false):
                                    $sc = strtolower($val);
                                    $cls = $sc === 'active' ? 'badge-active' : ($sc === 'inactive' ? 'badge-inactive' : 'badge-other');
                                ?><span class="<?= $cls ?>"><?= htmlspecialchars($val) ?></span>
                                <?php else: ?>
                                <?= htmlspecialchars(is_scalar($val) ? $val : '—') ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <div class="actions">
                                    <!-- Toggle Status -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status"/>
                                        <?= adminCsrfField() ?>
                                        <input type="hidden" name="status_username" value="<?= htmlspecialchars($uname) ?>"/>
                                        <input type="hidden" name="status_value" value="<?= $ustat === 'active' ? 'Inactive' : 'Active' ?>"/>
                                        <button type="submit" class="btn btn-status">
                                            <?= $ustat === 'active' ? ' Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <!-- Delete -->
                                    <button type="button" class="btn btn-danger"
                                        title="Username: <?= htmlspecialchars($uname) ?> | Student ID: <?= htmlspecialchars($uid) ?>"
                                        onclick="confirmDelete('<?= htmlspecialchars($uname, ENT_QUOTES) ?>', '<?= htmlspecialchars($uid, ENT_QUOTES) ?>')">
                                         Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- App Services -->
            <div class="section-title">App Services</div>
            <div class="card">
                <div class="card-header-bar">
                    <h3> Registered App Services</h3>
                    <span><?= count($serviceList) ?> service<?= count($serviceList)!==1?'s':'' ?></span>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px;">
                        <input type="hidden" name="action" value="add_service"/>
                        <?= adminCsrfField() ?>
                        <div class="form-group" style="flex:1;min-width:200px;">
                            <label>Service Name</label>
                            <input type="text" name="service_name" placeholder="e.g. VoterPortal" required/>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-bottom:0;">Add Service</button>
                    </form>
                </div>
                <?php if (empty($serviceList)): ?>
                <div class="empty-state">No services registered yet.</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <?php $svcCols = array_keys(is_array(reset($serviceList)) ? reset($serviceList) : []); foreach ($svcCols as $sc): ?><th><?= htmlspecialchars(str_replace('_',' ',$sc)) ?></th><?php endforeach; ?>
                            <th>Action</th>
                        </tr></thead>
                        <tbody>
                        <?php $sn=0; foreach ($serviceList as $svc): $sn++;
                            $svcName = $svc['Service_Name'] ?? (is_scalar($svc) ? $svc : '');
                        ?>
                        <tr>
                            <td><?= $sn ?></td>
                            <?php foreach ($svcCols as $sc): ?><td><?= htmlspecialchars(is_scalar($svc[$sc]??'—')?($svc[$sc]??'—'):'—') ?></td><?php endforeach; ?>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete service &quot;<?= htmlspecialchars($svcName,ENT_QUOTES) ?>&quot;?')">
                                    <input type="hidden" name="action" value="delete_service"/>
                                    <?= adminCsrfField() ?>
                                    <input type="hidden" name="del_service_name" value="<?= htmlspecialchars($svcName) ?>"/>
                                    <button type="submit" class="btn btn-danger"> Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- User Activity Log -->
            <div class="section-title">User Activity Log</div>
            <div class="card">
                <div class="card-header-bar"><h3> View User Log</h3><span>Retrieve activity log for a user</span></div>
                <div class="card-body">
                    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
                        <input type="hidden" name="action" value="get_user_log"/>
                        <?= adminCsrfField() ?>
                        <div class="form-group" style="flex:1;min-width:200px;">
                            <label>User ID</label>
                            <input type="text" name="log_user_id" placeholder="Username or User ID"
                                   value="<?= htmlspecialchars($logUid) ?>" required/>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-bottom:0;"> Get Log</button>
                    </form>
                    <?php if ($logResult !== null): ?>
                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px;font-size:13px;">
                        <strong style="font-size:12px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:8px;">Log for: <?= htmlspecialchars($logUid) ?></strong>
                        <?php
                        $logList = $logResult['Record'] ?? $logResult;
                        if (is_array($logList) && !empty($logList) && is_array(reset($logList))):
                            $lcols = array_keys(reset($logList));
                        ?>
                        <div style="overflow-x:auto;"><table>
                            <thead><tr><?php foreach($lcols as $lc): ?><th><?= htmlspecialchars(str_replace('_',' ',$lc)) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php foreach($logList as $le): ?>
                            <tr><?php foreach($lcols as $lc): $lv=$le[$lc]??'—'; ?>
                                <td><?= htmlspecialchars(is_scalar($lv)?$lv:'—') ?></td>
                            <?php endforeach; ?></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                        <?php elseif (isset($logResult['Status'])): ?>
                        <p style="color:#dc2626;"><?= htmlspecialchars($logResult['Status']) ?></p>
                        <?php else: ?>
                        <pre style="font-size:12px;white-space:pre-wrap;"><?= htmlspecialchars(json_encode($logResult, JSON_PRETTY_PRINT)) ?></pre>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Insert Log Entry -->
            <div class="card">
                <div class="card-header-bar"><h3> Insert Log Entry</h3><span>Record a manual activity entry for a user</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="insert_user_log"/>
                        <?= adminCsrfField() ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label>User ID</label>
                                <input type="text" name="log_insert_uid" placeholder="Username or User ID" required/>
                            </div>
                            <div class="form-group">
                                <label>Service ID <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                                <input type="text" name="log_insert_service" placeholder="e.g. 1"/>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"> Insert Log</button>
                    </form>
                </div>
            </div>

        </div>
        <footer>&copy; <?= date('Y') ?> Coderstation Information System Innovator &bull; Admin Panel</footer>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <h4>Confirm Delete</h4>
        <p id="deleteMsg">Are you sure you want to delete this user? This cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete"/>
            <?= adminCsrfField() ?>
            <input type="hidden" name="del_username"   id="del_username"/>
            <input type="hidden" name="del_student_id" id="del_student_id"/>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(username, studentId) {
    document.getElementById('del_username').value   = username;
    document.getElementById('del_student_id').value = studentId;
    document.getElementById('deleteMsg').textContent = 'Delete user "' + username + '"? This cannot be undone.';
    document.getElementById('deleteModal').classList.add('open');
}
function closeModal() {
    document.getElementById('deleteModal').classList.remove('open');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
<?php require_once dirname(__DIR__) . '/includes/team-modal.php'; ?>
<script src="/Presets/admin-mobile.js"></script>
</body>
</html>
