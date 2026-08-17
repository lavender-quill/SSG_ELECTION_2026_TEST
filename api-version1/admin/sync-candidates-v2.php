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
        // Try multiple path strategies
        $paths = [
            dirname(__DIR__) . '/data/candidate_names.json',  // api-version1/data
            dirname(dirname(__DIR__)) . '/data/candidate_names.json',  // workspace/data
            __DIR__ . '/../../data/candidate_names.json',  // relative
        ];
        
        $jsonPath = null;
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $jsonPath = $p;
                break;
            }
        }
        
        if (!$jsonPath) {
            return ['success' => false, 'error' => 'JSON file not found in expected locations'];
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
        // Try multiple path strategies
        $paths = [
            dirname(__DIR__) . '/data/candidate_names.json',
            dirname(dirname(__DIR__)) . '/data/candidate_names.json',
            __DIR__ . '/../../data/candidate_names.json',
        ];
        
        $jsonPath = null;
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $jsonPath = $p;
                break;
            }
        }
        
        if (!$jsonPath) {
            return ['success' => false, 'data' => [], 'error' => 'JSON file not found'];
        }
        
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
        /* Override body styles */
        body, html {
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            background-image: radial-gradient(circle, #c0c0c0 1px, transparent 1px);
            background-size: 22px 22px;
        }

        .sync-page { display: flex; min-height: 100vh; }
        
        .sync-content { 
            margin-left: 240px; 
            flex: 1; 
            padding: 40px; 
            background-color: #f0f0f0;
            background-image: radial-gradient(circle, #c0c0c0 1px, transparent 1px);
            background-size: 22px 22px;
        }
        
        .sync-header { margin-bottom: 30px; }
        .sync-header h1 { 
            font-size: 28px; 
            font-weight: 800; 
            color: #1a3a8f; 
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .sync-header p { 
            font-size: 13px; 
            color: #666; 
            font-weight: 500;
        }
        
        .sync-card { 
            background: white; 
            border-radius: 12px; 
            padding: 28px; 
            margin-bottom: 24px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .alert { 
            padding: 16px 20px; 
            border-radius: 10px; 
            border-left: 4px solid; 
            margin-bottom: 24px; 
            font-weight: 600;
            font-size: 13px;
        }
        .alert-success { background: #d4edda; border-color: #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .alert-info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        
        .sync-title { 
            font-size: 16px; 
            font-weight: 800; 
            color: #1a3a8f; 
            margin-bottom: 16px; 
            padding-bottom: 12px; 
            border-bottom: 2px solid #f5c400;
            letter-spacing: -0.01em;
        }
        
        .sync-desc { 
            font-size: 13px; 
            color: #666; 
            margin-bottom: 16px; 
            line-height: 1.6;
            font-weight: 500;
        }
        
        .sync-buttons { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            font-family: Poppins, sans-serif; 
            font-weight: 700; 
            font-size: 13px; 
            cursor: pointer; 
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, #1a3a8f, #0d2a6e); 
            color: white;
            box-shadow: 0 4px 12px rgba(26,58,143,0.3);
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(26,58,143,0.4); 
        }
        
        .btn-info { 
            background: linear-gradient(135deg, #17a2b8, #0f7c8f); 
            color: white;
            box-shadow: 0 4px 12px rgba(23,162,184,0.3);
        }
        .btn-info:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(23,162,184,0.4); 
        }
        
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 16px; 
            font-size: 13px;
        }
        .table th { 
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f5ff 100%); 
            padding: 12px 14px; 
            text-align: left; 
            font-weight: 800; 
            color: #1a3a8f; 
            border-bottom: 2px solid #dde4f0;
        }
        .table td { 
            padding: 12px 14px; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .table tr:hover { background: #f9fafb; }
        .code { 
            background: #f5f5f5; 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-family: monospace; 
            font-size: 12px;
            color: #0c5460;
        }
        
        @media (max-width: 768px) {
            .sync-content { margin-left: 0; padding: 20px; }
            .sync-header h1 { font-size: 22px; }
            .sync-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f0f0f0;">

<div class="sync-page">
    <!-- Sidebar -->
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
            <a href="/admin/sync-candidates-v2.php" class="nav-item active">⟳ Sync Candidates</a>
        </nav>
        <div class="sidebar-footer">
            <a href="#" onclick="openTeamModal();return false;" class="sidebar-powered">Powered by CCS-Creatives Society</a>
            <a href="/admin/logout.php" class="btn-logout-side">Sign Out</a>
        </div>
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
