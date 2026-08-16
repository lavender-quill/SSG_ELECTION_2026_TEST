<?php
// Development/Test Login - Bypass ARMS
// Only use this during development/testing
// WARNING: Do NOT use in production!

require_once __DIR__ . '/includes/bootstrap.php';

// Security: Only allow from localhost in development
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'localhost', '::1', '::ffff:127.0.0.1']);
if (!$isLocalhost) {
    http_response_code(403);
    die('Access denied. This endpoint is only for localhost development.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testId = $_POST['test_id'] ?? '';
    
    if ($testId !== 'dev-bypass-25-A-00321') {
        http_response_code(400);
        die('Invalid test ID');
    }
    
    session_regenerate_id(true);
    
    $_SESSION['logged_in'] = true;
    $_SESSION['enrollment_verified'] = true;
    $_SESSION['student_id'] = '25-A-00321';
    $_SESSION['student_name'] = 'Guido (Test User)';
    $_SESSION['college'] = 'CCS';
    $_SESSION['college_code'] = 'CCS';
    $_SESSION['year_level'] = '4';
    $_SESSION['program'] = 'BSCS';
    $_SESSION['semester'] = ELECTION_SEMESTER;
    $_SESSION['school_year'] = ELECTION_SCHOOL_YEAR;
    $_SESSION['voter'] = [
        'Student_ID' => '25-A-00321',
        'Student_Name' => 'Guido (Test User)',
        'College' => 'CCS',
        'College_Code' => 'CCS',
        'Year_Level' => '4',
        'Program_Enrolled' => 'BSCS',
        'Enrollment_Status' => 'ENROLLED',
    ];
    
    header('Location: /dashboard.php');
    exit;
}

// Show test login form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login - Development Only</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        .warning { background: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px; }
        form { background: #f8f9fa; padding: 20px; border-radius: 4px; max-width: 400px; }
        input { padding: 10px; width: 100%; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>⚠️ Development Test Login</h1>
    
    <div class="warning">
        <strong>WARNING:</strong> This is a development-only endpoint. 
        It bypasses ARMS authentication for testing purposes.
        Do NOT use this in production!
    </div>
    
    <form method="POST">
        <h3>Login as Test User</h3>
        <p><strong>Student ID:</strong> 25-A-00321</p>
        <p><strong>Name:</strong> Guido (Test User)</p>
        <p><strong>College:</strong> CCS (College of Computer Studies)</p>
        
        <input type="hidden" name="test_id" value="dev-bypass-25-A-00321">
        <button type="submit">Login as Test User</button>
    </form>
    
    <p><a href="/login.php">← Back to Regular Login</a></p>
</body>
</html>
