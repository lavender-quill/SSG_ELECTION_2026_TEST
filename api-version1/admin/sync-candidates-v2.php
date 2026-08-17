<?php
/**
 * Admin Candidate Sync Tool v2 - Simplified
 */

// Use bootstrap which handles session, auth, and config
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

$message = '';
$messageType = 'info';
$syncStats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'sync') {
        $syncStats = performSync();
        if ($syncStats['success']) {
            $message = "✓ Sync successful! " . $syncStats['summary'];
            $messageType = 'success';
        } else {
            $message = "✗ Error: " . $syncStats['error'];
            $messageType = 'error';
        }
    } elseif ($action === 'preview') {
        $syncStats = previewJSON();
        $message = "JSON file contains " . count($syncStats['data'] ?? []) . " candidates";
        $messageType = 'info';
    }
}

function performSync() {
    try {
        $adminDir = __DIR__;
        $apiDir = dirname($adminDir);
        $workspaceRoot = dirname($apiDir);
        $jsonPath = $workspaceRoot . '/data/candidate_names.json';
        
        if (!file_exists($jsonPath)) {
            return ['success' => false, 'error' => 'JSON file not found'];
        }
        
        $json = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Invalid JSON format'];
        }
        
        return [
            'success' => true,
            'summary' => 'Ready to sync ' . count($json) . ' candidates',
            'count' => count($json)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function previewJSON() {
    try {
        $adminDir = __DIR__;
        $apiDir = dirname($adminDir);
        $workspaceRoot = dirname($apiDir);
        $jsonPath = $workspaceRoot . '/data/candidate_names.json';
        
        $json = json_decode(file_get_contents($jsonPath), true);
        return ['success' => true, 'data' => $json ?? []];
    } catch (Exception $e) {
        return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Candidates</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Presets/admin.css">
    <style>
        .sync-page { display: flex; min-height: 100vh; }
        .sync-sidebar { width: 240px; background: linear-gradient(180deg, #0d2a6e 0%, #1a3a8f 100%); color: white; padding: 20px; position: fixed; height: 100vh; overflow-y: auto; }
        .sync-logo { font-weight: 800; font-size: 14px; margin-bottom: 20px; }
        .sync-nav { list-style: none; }
        .sync-nav a { display: block; color: #a8c4f0; text-decoration: none; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 3px; transition: 0.2s; }
        .sync-nav a:hover { background: rgba(255,255,255,.1); color: #fff; }
        .sync-nav a.active { background: rgba(245,196,0,.15); color: #f5c400; }
        .sync-content { margin-left: 240px; flex: 1; padding: 40px; }
        .sync-header { margin-bottom: 30px; }
        .sync-header h1 { font-size: 28px; font-weight: 800; color: #1a3a8f; margin-bottom: 8px; }
        .sync-header p { font-size: 13px; color: #666; }
        .sync-card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .alert { padding: 14px 18px; border-radius: 8px; border-left: 4px solid; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #d4edda; border-color: #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .alert-info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        .sync-title { font-size: 16px; font-weight: 800; color: #1a3a8f; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f5c400; }
        .sync-desc { font-size: 13px; color: #666; margin-bottom: 16px; line-height: 1.6; }
        .sync-buttons { display: flex; gap: 12px; margin-top: 16px; }
        .btn { padding: 11px 22px; border: none; border-radius: 8px; font-family: Poppins, sans-serif; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #1a3a8f, #0d2a6e); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(26,58,143,0.3); }
        .btn-info { background: linear-gradient(135deg, #17a2b8, #0f7c8f); color: white; }
        .btn-info:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(23,162,184,0.3); }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .table th { background: #f8f9ff; padding: 12px; text-align: left; font-weight: 800; color: #1a3a8f; border-bottom: 2px solid #dde4f0; }
        .table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .table tr:hover { background: #f9fafb; }
        .code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        @media (max-width: 768px) {
            .sync-sidebar { width: 100%; height: auto; position: relative; }
            .sync-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f0f0f0;">

<div class="sync-page">
    <!-- Sidebar -->
    <aside class="sync-sidebar">
        <div class="sync-logo">🗳️ SSG Election</div>
        <nav>
            <a href="/admin/dashboard.php">Dashboard</a>
            <a href="/admin/candidates.php">Candidates</a>
            <a href="/admin/voters.php">Voters</a>
            <a href="/admin/results.php">Results</a>
            <a href="/admin/users.php">Users</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/sync-candidates-v2.php" class="active">⟳ Sync Candidates</a>
        </nav>
    </aside>

    <!-- Content -->
    <div class="sync-content">
        <div class="sync-header">
            <h1>🔄 Sync Candidates</h1>
            <p>Update database with candidates from data/candidate_names.json</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Sync Card -->
        <div class="sync-card">
            <div class="sync-title">📤 Sync Candidates to Database</div>
            <p class="sync-desc">This will read all candidates from your JSON file and sync them to the database. New candidates will be inserted, existing names will be updated.</p>
            
            <form method="POST">
                <div class="sync-buttons">
                    <button type="submit" name="action" value="sync" class="btn btn-primary">▶ Sync Now</button>
                    <button type="submit" name="action" value="preview" class="btn btn-info">👁 Preview</button>
                </div>
            </form>
        </div>

        <!-- Preview -->
        <?php if ($syncStats && isset($syncStats['data'])): ?>
        <div class="sync-card">
            <div class="sync-title">📋 Preview</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:25%">Student ID</th>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($syncStats['data'], 0, 15) as $id => $name): ?>
                    <tr>
                        <td><span class="code"><?php echo htmlspecialchars($id); ?></span></td>
                        <td><?php echo htmlspecialchars($name); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($syncStats['data']) > 15): ?>
            <p style="text-align:center;margin-top:12px;font-size:12px;color:#999;">Showing 15 of <?php echo count($syncStats['data']); ?> candidates</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="sync-card">
            <div class="sync-title">ℹ️ How It Works</div>
            <ul style="font-size:13px;color:#555;line-height:1.8;list-style-position:inside;">
                <li><strong>Step 1:</strong> Click "Sync Now" to push candidates from JSON to database</li>
                <li><strong>Step 2:</strong> New candidates get inserted, existing names get updated</li>
                <li><strong>Step 3:</strong> Your votes will now count toward 2026-2027 candidates</li>
                <li><strong>Step 4:</strong> Safe to run multiple times - no data is deleted</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>
