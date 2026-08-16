<?php
require __DIR__ . '/api-version1/includes/bootstrap.php';
$db = \Configuration\Application::$SSG_Candidate_DBase;
$pdo = new PDO(
    "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4",
    $db['Username'],
    $db['Password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

foreach (['candidate_position', 'position_profile', 'candidate_photo'] as $table) {
    echo "=== $table ===\n";
    $rows = $pdo->query('SELECT * FROM ' . $table . ' LIMIT 5')->fetchAll();
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n\n";
}

$sample = $pdo->query("SELECT cp.Student_ID, cp.Position_ID, pp.Position_Name, cp.Election_Year
    FROM candidate_position cp
    LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID
    LIMIT 10")->fetchAll();

echo "=== sample candidate_position + position_profile ===\n";
echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
// help