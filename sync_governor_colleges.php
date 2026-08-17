<?php
/**
 * Sync Governor & Vice-Governor colleges and names
 * Run from command line: php sync_governor_colleges.php
 */
require_once __DIR__ . '/api-version1/includes/bootstrap.php';

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
    
    echo "Found " . count($candidates) . " governor/vice-governor candidates.\n";
    
    if (empty($candidates)) {
        echo "No candidates found. Exiting.\n";
        exit;
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
    
    echo "Found " . count($nameMap) . " student names from voter DB.\n";
    
    // ── 3. Load existing candidate_college.json ───────────────────────────────
    $ccFile = DATA_DIR . '/candidate_college.json';
    $ccMap = file_exists($ccFile) ? (json_decode(file_get_contents($ccFile), true) ?: []) : [];
    
    $cnFile = DATA_DIR . '/candidate_names.json';
    $cnMap = file_exists($cnFile) ? (json_decode(file_get_contents($cnFile), true) ?: []) : [];
    
    // ── 4. Process each governor/vice-governor ────────────────────────────────
    // For now, we need to assign colleges manually or via a program code lookup.
    // Use program code from voter DB if available.
    
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
    
    $updated = 0;
    $missing = [];
    
    foreach ($candidates as $c) {
        $sid = trim($c['Student_ID'] ?? '');
        $pos = trim($c['Position'] ?? '');
        
        // Add name if we have it
        if ($sid !== '' && isset($nameMap[$sid])) {
            if (!isset($cnMap[$sid])) {
                $cnMap[$sid] = $nameMap[$sid];
                echo "  Added name: $sid → " . $nameMap[$sid] . "\n";
            }
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
            echo "  Assigned $pos ($sid) → $college (from program $prog)\n";
            $updated++;
        } else {
            $missing[] = ['sid' => $sid, 'pos' => $pos, 'prog' => $prog];
            echo "  ⚠ MISSING: $pos ($sid, program=$prog) - MANUAL ASSIGNMENT NEEDED\n";
        }
    }
    
    // ── 5. Save updated files ─────────────────────────────────────────────────
    file_put_contents($ccFile, json_encode($ccMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo "\n✓ Saved candidate_college.json with $updated new assignments\n";
    
    file_put_contents($cnFile, json_encode($cnMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo "✓ Saved candidate_names.json with " . count($cnMap) . " entries\n";
    
    // ── 6. Show summary ───────────────────────────────────────────────────────
    if (!empty($missing)) {
        echo "\n⚠ MANUAL ASSIGNMENTS NEEDED (" . count($missing) . " candidates):\n";
        echo "Add these to candidate_college.json:\n\n";
        foreach ($missing as $m) {
            echo '    "' . $m['sid'] . '": "CCS",  // ' . $m['pos'] . "\n";
        }
        echo "\nCollege codes: CAS, CBA, CCJE, CCS, CIT, CME, CNAHS, COE, COL, CTED, CTED_HS, GRAD, HS, SOM\n";
    } else {
        echo "\n✓ All governors and vice-governors now have college assignments!\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
