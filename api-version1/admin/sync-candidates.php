<?php
/**
 * Admin Candidate Sync Tool
 * Syncs candidates from data/candidate_names.json to the database
 * Allows you to refresh/update the candidate list without shell access
 */

session_start();

// Require authentication
require_once '../includes/admin-guard.php';
require_once '../Configuration/Application.Config.php';

$message = '';
$messageType = 'info'; // success, error, warning, info
$syncStats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_action'])) {
    $action = $_POST['sync_action'];
    
    if ($action === 'sync_from_json') {
        $syncStats = syncCandidatesFromJSON();
        
        if ($syncStats['success']) {
            $message = "✓ Sync successful! " . $syncStats['summary'];
            $messageType = 'success';
        } else {
            $message = "✗ Sync failed: " . $syncStats['error'];
            $messageType = 'error';
        }
    } elseif ($action === 'preview_json') {
        // Just preview without syncing
        $adminDir = __DIR__;                    // api-version1/admin
        $apiDir = dirname($adminDir);            // api-version1
        $workspaceRoot = dirname($apiDir);       // workspace root
        $jsonPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'candidate_names.json';
        
        if (file_exists($jsonPath)) {
            $jsonContent = json_decode(file_get_contents($jsonPath), true);
            $message = "JSON file contains " . count($jsonContent) . " candidates.";
            $messageType = 'info';
            $syncStats = ['preview' => true, 'data' => $jsonContent];
        } else {
            $message = "candidate_names.json not found at: " . $jsonPath;
            $messageType = 'error';
        }
    }
}

/**
 * Sync candidates from data/candidate_names.json to database
 */
function syncCandidatesFromJSON() {
    $result = [
        'success' => false,
        'summary' => '',
        'error' => '',
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0
    ];

    try {
        // Load JSON file - data folder is at workspace root
        // From admin/sync-candidates.php: __DIR__ = api-version1/admin
        $adminDir = __DIR__;                    // api-version1/admin
        $apiDir = dirname($adminDir);            // api-version1
        $workspaceRoot = dirname($apiDir);       // workspace root
        $jsonPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'candidate_names.json';
        
        if (!file_exists($jsonPath)) {
            throw new Exception("File not found at: " . $jsonPath . " (Workspace root detected as: " . $workspaceRoot . ")");
        }

        $jsonContent = file_get_contents($jsonPath);
        $candidateMap = json_decode($jsonContent, true);

        if (!is_array($candidateMap)) {
            throw new Exception("Invalid JSON format in candidate_names.json");
        }

        // Get database connection
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_CANDIDATE_USER,
            DB_PASSWORD
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_THROW);

        // Get current school year from settings
        $schoolYear = isset($GLOBALS['ELECTION_SCHOOL_YEAR']) 
            ? $GLOBALS['ELECTION_SCHOOL_YEAR'] 
            : '2026-2027';

        // Process each candidate from JSON
        foreach ($candidateMap as $studentId => $candidateName) {
            // Check if candidate exists
            $checkStmt = $db->prepare(
                "SELECT id FROM candidates WHERE student_id = ? AND year = ?"
            );
            $checkStmt->execute([$studentId, $schoolYear]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                // Update if name differs
                $updateStmt = $db->prepare(
                    "UPDATE candidates SET full_name = ? WHERE student_id = ? AND year = ?"
                );
                $updateStmt->execute([$candidateName, $studentId, $schoolYear]);
                $result['updated']++;
            } else {
                // Insert new candidate
                $insertStmt = $db->prepare(
                    "INSERT INTO candidates (student_id, full_name, year, status, created_at) 
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $insertStmt->execute([$studentId, $candidateName, $schoolYear, 'active']);
                $result['inserted']++;
            }
        }

        // Count total candidates now in database for this year
        $countStmt = $db->prepare(
            "SELECT COUNT(*) as total FROM candidates WHERE year = ?"
        );
        $countStmt->execute([$schoolYear]);
        $total = $countStmt->fetch()['total'];

        $result['success'] = true;
        $result['summary'] = "Inserted: {$result['inserted']}, Updated: {$result['updated']}. Total candidates in DB for {$schoolYear}: {$total}";

    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sync Candidates</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Presets/admin.css">
    <style>
        /* ── Sync Candidates Page Styles ── */
        .content {
            flex: 1;
            margin-left: 240px;
            padding: 40px 24px;
            background-color: #f0f0f0;
            background-image: radial-gradient(circle, #c0c0c0 1px, transparent 1px);
            background-size: 22px 22px;
            min-height: 100vh;
        }

        .sync-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .page-header-sync {
            margin-bottom: 32px;
        }

        .page-header-sync h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a3a8f;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .page-header-sync p {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.6;
        }

        .card-sync {
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            border-left: 4px solid;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.6;
        }

        .alert.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        .alert.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }

        .alert.info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: #1a3a8f;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f5c400;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }

        .section-title-icon {
            font-size: 18px;
        }

        .section-description {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        button, input[type="submit"] {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            letter-spacing: -0.01em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a3a8f 0%, #0d2a6e 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(26, 58, 143, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 58, 143, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #0f7c8f 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(23, 162, 184, 0.4);
        }

        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #f0f5ff 100%);
            padding: 14px 18px;
            border-radius: 8px;
            border-left: 4px solid #1a3a8f;
            font-size: 13px;
            color: #0c5460;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f5ff 100%);
            padding: 16px;
            border-radius: 10px;
            border: 2px solid #dde4f0;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-box:hover {
            border-color: #1a3a8f;
            box-shadow: 0 4px 12px rgba(26, 58, 143, 0.15);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 900;
            color: #1a3a8f;
            letter-spacing: -0.02em;
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }

        .preview-table th,
        .preview-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .preview-table th {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f5ff 100%);
            font-weight: 800;
            color: #1a3a8f;
        }

        .preview-table td code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #0c5460;
        }

        .preview-table tr:hover {
            background: #f9fafb;
        }

        .info-list {
            list-style: none;
            font-size: 13px;
            color: #555;
            line-height: 1.8;
        }

        .info-list li {
            padding: 8px 0;
            font-weight: 500;
        }

        .info-list li strong {
            color: #1a3a8f;
            font-weight: 700;
        }

        .safety-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-left: 4px solid #28a745;
            padding: 24px;
            border-radius: 10px;
        }

        .safety-card .section-title {
            border-bottom-color: #28a745;
            color: #28a745;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 24px 16px;
            }

            .sync-container {
                max-width: 100%;
            }

            .card-sync {
                padding: 20px;
            }

            .page-header-sync h1 {
                font-size: 22px;
            }

            .button-group {
                flex-direction: column;
            }

            button, input[type="submit"] {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </link>
</head>
<body>

<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="/assets/ssg-logo.png" alt="SSG Logo" onerror="this.style.display='none'">
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
            <a href="/admin/api-accounts.php" class="nav-item">API Accounts</a>
            <a href="/admin/sync-candidates.php" class="nav-item active">⟳ Sync Candidates</a>
        </nav>
        <div class="sidebar-footer">
            <a href="#" onclick="openTeamModal();return false;" class="sidebar-powered">Powered by CCS-Creatives Society</a>
            <a href="/admin/logout.php" class="btn-logout-side">Sign Out</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="content">
        <div class="sync-container">
            <div class="page-header-sync">
                <h1>🔄 Sync Candidates</h1>
                <p>Update your database with the latest candidates from data/candidate_names.json</p>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <!-- Sync Section -->
            <div class="card-sync">
                <div class="section-title">
                    <span class="section-title-icon">📤</span>
                    Sync Candidates to Database
                </div>
                <p class="section-description">
                    This will read all candidates from <code>data/candidate_names.json</code> and sync them to your database.
                    New candidates will be inserted, existing ones will be updated if the name changed.
                </p>

                <form method="POST">
                    <input type="hidden" name="sync_action" value="sync_from_json">
                    
                    <div class="info-box">
                        ✓ This process is safe and won't delete existing data
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-primary" name="sync" value="1">
                            ▶ Sync Candidates Now
                        </button>
                        <button type="submit" class="btn-info" formaction="" name="preview" value="1">
                            👁 Preview JSON First
                        </button>
                    </div>
                </form>

                <?php if ($syncStats && isset($syncStats['success']) && $syncStats['success']): ?>
                <div style="margin-top: 28px;">
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $syncStats['inserted']; ?></div>
                            <div class="stat-label">Inserted</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $syncStats['updated']; ?></div>
                            <div class="stat-label">Updated</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Preview Section -->
            <?php if ($syncStats && isset($syncStats['preview']) && $syncStats['preview']): ?>
            <div class="card-sync">
                <div class="section-title">
                    <span class="section-title-icon">📋</span>
                    Preview: Candidates in JSON
                </div>
                
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Student ID</th>
                            <th>Full Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($syncStats['data'], 0, 20) as $id => $name): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($id); ?></code></td>
                            <td><?php echo htmlspecialchars($name); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (count($syncStats['data']) > 20): ?>
                <p style="font-size: 12px; color: #6b7280; margin-top: 16px; text-align: center; font-weight: 600;">
                    Showing 20 of <?php echo count($syncStats['data']); ?> candidates
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Info Section -->
            <div class="card-sync">
                <div class="section-title">
                    <span class="section-title-icon">ℹ️</span>
                    How This Works
                </div>
                <ul class="info-list">
                    <li><strong>Step 1:</strong> Click "Sync Candidates Now" to push all candidates from your JSON file to the database</li>
                    <li><strong>Step 2:</strong> The system will create new records for any candidates not yet in the database</li>
                    <li><strong>Step 3:</strong> Existing candidate records will have their names updated if they differ</li>
                    <li><strong>Step 4:</strong> Your votes will now count toward the new 2026-2027 candidates</li>
                </ul>
            </div>

            <!-- Safety Info -->
            <div class="card-sync safety-card">
                <div class="section-title">
                    <span class="section-title-icon">✓</span>
                    Safety Notes
                </div>
                <ul class="info-list">
                    <li>No data is deleted during this sync</li>
                    <li>Only new records are inserted and existing names are updated</li>
                    <li>You can run this multiple times safely</li>
                    <li>Old vote records remain in the database for record-keeping</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</body>
</html>
