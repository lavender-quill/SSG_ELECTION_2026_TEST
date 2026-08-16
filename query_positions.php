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
    $count = $pdo->query('SELECT COUNT(*) as cnt FROM candidate_position')->fetch();
    echo "Total position records (all years): " . $count['cnt'] . "\n";
    
    $countYear = $pdo->query('SELECT COUNT(*) as cnt FROM candidate_position WHERE Election_Year = ' . (int)$year)->fetch();
    echo "Total position records for year " . $year . ": " . $countYear['cnt'] . "\n";
    
    // Show sample if any exist
    if ($countYear['cnt'] > 0) {
        $sample = $pdo->query('SELECT cp.Student_ID, cp.Position_ID, pp.Position_Name FROM candidate_position cp LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID WHERE cp.Election_Year = ' . (int)$year . ' LIMIT 5')->fetchAll();
        echo "Sample records:\n" . json_encode($sample, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
