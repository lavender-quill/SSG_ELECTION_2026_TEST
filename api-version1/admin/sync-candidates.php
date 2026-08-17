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
        $jsonPath = __DIR__ . '/../../data/candidate_names.json';
        if (file_exists($jsonPath)) {
            $jsonContent = json_decode(file_get_contents($jsonPath), true);
            $message = "JSON file contains " . count($jsonContent) . " candidates.";
            $messageType = 'info';
            $syncStats = ['preview' => true, 'data' => $jsonContent];
        } else {
            $message = "candidate_names.json not found at " . $jsonPath;
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
        // Load JSON file
        $jsonPath = __DIR__ . '/../../data/candidate_names.json';
        
        if (!file_exists($jsonPath)) {
            throw new Exception("File not found: candidate_names.json");
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #666; font-size: 14px; }
        
        .card {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
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
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        
        button, input[type="submit"] {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
        }
        
        .info-box {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
            margin-bottom: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .stat-box {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13px;
        }
        
        .preview-table th,
        .preview-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .preview-table th {
            background: #f0f0f0;
            font-weight: 600;
        }
        
        .preview-table tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔄 Admin Candidate Sync</h1>
        <p class="subtitle">Update your database with the latest candidates from data/candidate_names.json</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Sync Section -->
    <div class="card">
        <div class="section-title">📤 Sync Candidates to Database</div>
        <p style="font-size: 13px; color: #666; margin-bottom: 16px;">
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
        <div style="margin-top: 20px;">
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
    <div class="card">
        <div class="section-title">📋 Preview: Candidates in JSON</div>
        
        <table class="preview-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Student ID</th>
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
        <p style="font-size: 12px; color: #666; margin-top: 12px; text-align: center;">
            Showing 20 of <?php echo count($syncStats['data']); ?> candidates
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Info Section -->
    <div class="card">
        <div class="section-title">ℹ️ How This Works</div>
        <ul style="font-size: 13px; color: #555; line-height: 1.8;">
            <li><strong>Step 1:</strong> Click "Sync Candidates Now" to push all candidates from your JSON file to the database</li>
            <li><strong>Step 2:</strong> The system will create new records for any candidates not yet in the database</li>
            <li><strong>Step 3:</strong> Existing candidate records will have their names updated if they differ</li>
            <li><strong>Step 4:</strong> Your votes will now count toward the new 2026-2027 candidates</li>
        </ul>
    </div>

    <!-- Safety Info -->
    <div class="card" style="border-left: 4px solid #28a745;">
        <div class="section-title" style="border-color: #28a745; color: #28a745;">✓ Safety Notes</div>
        <ul style="font-size: 13px; color: #555; line-height: 1.8;">
            <li>No data is deleted during this sync</li>
            <li>Only new records are inserted and existing names are updated</li>
            <li>You can run this multiple times safely</li>
            <li>Old vote records remain in the database for record-keeping</li>
        </ul>
    </div>

</div>

</body>
</html>
