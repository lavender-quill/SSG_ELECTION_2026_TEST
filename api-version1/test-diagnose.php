<?php
require_once __DIR__ . '/includes/bootstrap.php';

echo "═══════════════════════════════════════════════════\n";
echo "DIAGNOSTIC TEST\n";
echo "═══════════════════════════════════════════════════\n\n";

// 1. Check configuration loaded
echo "1. Configuration Status:\n";
echo "   School Year: " . ELECTION_SCHOOL_YEAR . "\n";
echo "   Semester: " . ELECTION_SEMESTER . "\n";
echo "   Data Dir: " . DATA_DIR . "\n\n";

// 2. Check candidate_college.json
echo "2. Candidate College File:\n";
$ccFile = DATA_DIR . '/candidate_college.json';
if (file_exists($ccFile)) {
    $ccData = json_decode(file_get_contents($ccFile), true);
    echo "   Exists: YES\n";
    echo "   Entries: " . count($ccData) . "\n";
    $sampleEntries = array_slice($ccData, 0, 3, true);
    foreach ($sampleEntries as $k => $v) {
        echo "     - $k => $v\n";
    }
} else {
    echo "   Exists: NO\n";
}
echo "\n";

// 3. Check database connection
echo "3. Database Connection (Election):\n";
try {
    $opts = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $eCfg = \Configuration\Application::$SSG_Election_DBase;
    $ePdo = new PDO(
        "mysql:host={$eCfg['Host']};port={$eCfg['Port']};dbname={$eCfg['DBName']};charset=utf8mb4",
        $eCfg['Username'], $eCfg['Password'], $opts
    );
    echo "   Status: CONNECTED\n";
    
    // Check if election_schedule table exists and has college schedules
    $stmt = $ePdo->prepare("SELECT COUNT(*) as cnt FROM election_schedule WHERE College IS NOT NULL AND College != ''");
    $stmt->execute();
    $row = $stmt->fetch();
    echo "   College Schedules in DB: " . $row['cnt'] . "\n";
    
} catch (\Throwable $e) {
    echo "   Status: FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Check governors/VPs in candidate database
echo "4. Governor/Vice-Governor Count:\n";
try {
    $opts = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $cCfg = \Configuration\Application::$SSG_Candidate_DBase;
    $cPdo = new PDO(
        "mysql:host={$cCfg['Host']};port={$cCfg['Port']};dbname={$cCfg['DBName']};charset=utf8mb4",
        $cCfg['Username'], $cCfg['Password'], $opts
    );
    
    $stmt = $cPdo->prepare(
        "SELECT COUNT(*) as cnt FROM candidate_position cp
         LEFT JOIN position p ON cp.Position_ID = p.Position_ID
         WHERE cp.Election_Year = ? AND p.Position IN ('GOVERNOR', 'VICE-GOVERNOR')"
    );
    $stmt->execute([ELECTION_SCHOOL_YEAR]);
    $row = $stmt->fetch();
    echo "   Total Candidates: " . $row['cnt'] . "\n";
    
} catch (\Throwable $e) {
    echo "   Status: FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Check tally-live.php execution
echo "5. Tally-Live Endpoint Test:\n";
try {
    $response = file_get_contents('http://localhost:80/api-version1/ajax/tally-live.php?all=1', false, 
        stream_context_create(['http' => ['timeout' => 5]]));
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['ok'] ?? false) {
            echo "   Status: OK\n";
            echo "   School Year: " . $data['school_year'] . "\n";
            $cardCount = count($data['cards'] ?? []);
            echo "   Card Groups: $cardCount\n";
        } else {
            echo "   Status: INVALID JSON\n";
        }
    }
} catch (\Throwable $e) {
    echo "   Status: FAILED (Endpoint unreachable from local)\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════\n";
echo "END DIAGNOSTIC\n";
echo "═══════════════════════════════════════════════════\n";
?>
