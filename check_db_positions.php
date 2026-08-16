<?php
require __DIR__ . '/api-version1/includes/bootstrap.php';

try {
    $db = \Configuration\Application::$SSG_Candidate_DBase;
    $pdo = new PDO(
        'mysql:host=' . $db['Host'] . ';port=' . $db['Port'] . ';dbname=' . $db['DBName'] . ';charset=utf8mb4',
        $db['Username'],
        $db['Password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $year = ELECTION_SCHOOL_YEAR;
    
    // Check if any position records exist at all
    $count = $pdo->query('SELECT COUNT(*) as cnt FROM candidate_position WHERE Election_Year = ' . (int)$year)->fetch();
    echo "Total position records for year " . $year . ": " . $count['cnt'] . "\n";
    
    // Show distinct positions
    $positions = $pdo->query('SELECT DISTINCT Position_ID, Election_Year FROM candidate_position WHERE Election_Year = ' . (int)$year . ' LIMIT 10')->fetchAll();
    echo "Sample position IDs: " . json_encode($positions) . "\n";
    
    // Check position_profile table
    $posProf = $pdo->query('SELECT * FROM position_profile LIMIT 5')->fetchAll();
    echo "Position profiles: " . json_encode($posProf) . "\n";
    
    // Try to find any candidate with a position
    $sample = $pdo->query('SELECT Student_ID, Position_ID FROM candidate_position WHERE Election_Year = ' . (int)$year . ' LIMIT 3')->fetchAll();
    echo "Sample records: " . json_encode($sample) . "\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
