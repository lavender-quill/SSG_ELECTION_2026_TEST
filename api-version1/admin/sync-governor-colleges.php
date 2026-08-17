<?php
/**
 * Sync Governor & Vice-Governor colleges and names
 * Access via: /admin/sync-governor-colleges.php?token=YOUR_ADMIN_TOKEN
 * Requires admin authentication or valid token
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-guard.php';

// Optional: Allow bypass with token for Render scheduled tasks
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = $token === getenv('ADMIN_SYNC_TOKEN');

if (!$validToken && !isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized. Admin login required or valid token needed.']));
}

header('Content-Type: application/json; charset=utf-8');

$schoolYear = ELECTION_SCHOOL_YEAR;
$opts = [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // ── 1. Get all GOVERNOR and VICE-GOVERNOR candidates ──────────────────────
    $cDb = \Configuration\Application::$SSG_Candidate_DBase;
    $cPdo = new PDO(
        "mysql:host={$cDb['Host']};port={$cDb['Port']};dbname={$cDb['DBName']};charset=utf8mb4",
        $cDb['Username'], $cDb['Password'], $opts
    );
    
    $stmt = $cPdo->prepare(
        "SELECT cp.Student_ID, p.Position, cp.Candidate_Slate_ID
         FROM candidate_position cp
         LEFT JOIN position p ON cp.Position_ID = p.Position_ID
         WHERE cp.Election_Year = ? AND cp.Application_Status = 'APPROVED'
         AND p.Position IN ('GOVERNOR', 'VICE-GOVERNOR')
         ORDER BY cp.Student_ID ASC"
    );
    $stmt->execute([$schoolYear]);
    $candidates = $stmt->fetchAll();
    
    if (empty($candidates)) {
        http_response_code(404);
        exit(json_encode([
            'error' => 'No governor/vice-governor candidates found',
            'school_year' => $schoolYear,
        ]));
    }
    
    // ── 2. Get student names from voter DB ────────────────────────────────────
    $vDb = \Configuration\Application::$SSG_Voter_DBase;
    $vPdo = new PDO(
        "mysql:host={$vDb['Host']};port={$vDb['Port']};dbname={$vDb['DBName']};charset=utf8mb4",
        $vDb['Username'], $vDb['Password'], $opts
    );
    
    $studentIds = array_map(fn($c) => trim($c['Student_ID'] ?? ''), $candidates);
    $studentIds = array_unique(array_filter($studentIds));
    
    $nameMap = [];
    if (!empty($studentIds)) {
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $nStmt = $vPdo->prepare("SELECT Student_ID, MAX(Student_Name) AS Student_Name FROM student WHERE Student_ID IN ($ph) GROUP BY Student_ID");
        $nStmt->execute(array_values($studentIds));
        foreach ($nStmt->fetchAll() as $row) {
            $nameMap[trim($row['Student_ID'])] = $row['Student_Name'];
        }
    }
    
    // ── 3. Load existing JSON files ───────────────────────────────────────────
    $ccFile = DATA_DIR . '/candidate_college.json';
    $ccMap = file_exists($ccFile) ? (json_decode(file_get_contents($ccFile), true) ?: []) : [];
    
    $cnFile = DATA_DIR . '/candidate_names.json';
    $cnMap = file_exists($cnFile) ? (json_decode(file_get_contents($cnFile), true) ?: []) : [];
    
    // ── 4. Program code to college mapping ─────────────────────────────────────
    $programToCollege = [
        'BSCS'=>'CCS','BSIT'=>'CCS','ACT'=>'CCS',
        'BSA'=>'CBA','BSBA'=>'CBA','BSMA'=>'CBA','BSAIS'=>'CBA',
        'BEED'=>'CTED','BSED'=>'CTED','MAED'=>'CTED',
        'AB'=>'CAS','BSMATH'=>'CAS','BSSTAT'=>'CAS',
        'BSCRIM'=>'CCJE','BSCrim'=>'CCJE',
        'BSMT'=>'CIT','BSET'=>'CIT',
        'BSME'=>'CME','BSMarE'=>'CME',
        'BSN'=>'CNAHS','BSPT'=>'CNAHS',
        'BSCE'=>'COE','BSEE'=>'COE','BSECE'=>'COE','BSCHE'=>'COE',
        'LLB'=>'COL','JD'=>'COL',
        'MD'=>'SOM',
    ];
    
    // ── 5. Process each governor/vice-governor ────────────────────────────────
    $updated = 0;
    $missing = [];
    $names_added = 0;
    
    foreach ($candidates as $c) {
        $sid = trim($c['Student_ID'] ?? '');
        $pos = trim($c['Position'] ?? '');
        
        if ($sid === '') continue;
        
        // Add name if we have it and it's not already there
        if (isset($nameMap[$sid]) && !isset($cnMap[$sid])) {
            $cnMap[$sid] = $nameMap[$sid];
            $names_added++;
        }
        
        // Skip if college already assigned
        if (isset($ccMap[$sid])) {
            continue;
        }
        
        // Try to get college from program code
        $pStmt = $vPdo->prepare("SELECT Program_Code FROM student WHERE Student_ID = ? LIMIT 1");
        $pStmt->execute([$sid]);
        $progRow = $pStmt->fetch();
        $prog = $progRow ? strtoupper(trim($progRow['Program_Code'] ?? '')) : '';
        
        $college = isset($programToCollege[$prog]) ? $programToCollege[$prog] : null;
        
        if ($college) {
            $ccMap[$sid] = $college;
            $updated++;
        } else {
            $missing[] = ['sid' => $sid, 'pos' => $pos, 'prog' => $prog];
        }
    }
    
    // ── 6. Save updated files ─────────────────────────────────────────────────
    file_put_contents($ccFile, json_encode($ccMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    file_put_contents($cnFile, json_encode($cnMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    
    // ── 7. Return result ──────────────────────────────────────────────────────
    http_response_code(200);
    exit(json_encode([
        'success' => true,
        'school_year' => $schoolYear,
        'total_candidates' => count($candidates),
        'colleges_assigned' => $updated,
        'names_added' => $names_added,
        'names_found_in_db' => count($nameMap),
        'total_in_candidate_college_json' => count($ccMap),
        'total_in_candidate_names_json' => count($cnMap),
        'missing_assignments' => count($missing),
        'missing' => array_slice($missing, 0, 10), // Show first 10
        'message' => $updated > 0 
            ? "Successfully assigned colleges to $updated governors/vice-governors"
            : "All governors/vice-governors already have college assignments (or no program codes found)",
    ]));
    
} catch (\Throwable $e) {
    http_response_code(500);
    exit(json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]));
}
