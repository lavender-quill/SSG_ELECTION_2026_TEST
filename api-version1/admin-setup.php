<?php
/**
 * Admin Account Setup Script
 * Creates a test admin account in the database
 * WARNING: Only use this in development/testing environments
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Security: Only allow from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    die('Access denied. This endpoint is only available from localhost.');
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userlevel = trim($_POST['userlevel'] ?? 'admin');
    
    if (empty($username) || empty($password)) {
        die('Username and password are required.');
    }
    
    try {
        $cfg = \Configuration\Application::$SSG_Election_DBase;
        $pdo = new PDO(
            "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
            $cfg['Username'], $cfg['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Try to insert the admin account
        $stmt = $pdo->prepare(
            "INSERT INTO user_account (UserName, Password_Hash, Userlevel, User_Status) 
             VALUES (?, ?, ?, 'active')
             ON DUPLICATE KEY UPDATE Password_Hash = VALUES(Password_Hash), User_Status = 'active'"
        );
        
        $result = $stmt->execute([$username, $passwordHash, $userlevel]);
        
        if ($result) {
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #28a745; border-radius: 4px;'>";
            echo "<h3>✅ Admin account created/updated successfully!</h3>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
            echo "<p><strong>User Level:</strong> " . htmlspecialchars($userlevel) . "</p>";
            echo "<p><a href='/admin/'>Go to Admin Login →</a></p>";
            echo "</div>";
        } else {
            throw new Exception('Failed to create admin account');
        }
    } catch (\Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>";
        echo "<h3>❌ Error creating admin account</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Setup - Development Only</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; max-width: 600px; }
        .warning { background: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px; }
        form { background: #f8f9fa; padding: 20px; border-radius: 4px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input { padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }
        h1 { color: #333; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>⚠️ Admin Account Setup (Development Only)</h1>
    
    <div class="warning">
        <strong>WARNING:</strong> This is a development-only endpoint.
        Do NOT use this in production!
        Delete this file before deploying.
    </div>
    
    <form method="POST" action="?action=create">
        <h2>Create Admin Account</h2>
        
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="admin" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="userlevel">User Level:</label>
            <input type="text" id="userlevel" name="userlevel" value="admin">
            <small>Common values: admin, superuser, moderator</small>
        </div>
        
        <button type="submit">Create Admin Account</button>
    </form>
    
    <hr>
    <p><a href="/admin/">← Back to Admin Login</a></p>
</body>
</html>
