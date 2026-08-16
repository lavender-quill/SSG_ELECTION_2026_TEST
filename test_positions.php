<?php
require __DIR__ . '/api-version1/includes/bootstrap.php';

$db = \Configuration\Application::$SSG_Candidate_DBase;
$pdo = new PDO(
    'mysql:host=' . $db['Host'] . ';port=' . $db['Port'] . ';dbname=' . $db['DBName'] . ';charset=utf8mb4',
    $db['Username'],
    $db['Password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$year = ELECTION_SCHOOL_YEAR;
$result = $pdo->query('SELECT COUNT(*) as cnt FROM candidate_position WHERE Election_Year = ' . (int)$year)->fetch();
echo 'Total positions for year ' . $year . ': ' . $result['cnt'] . PHP_EOL;

$result2 = $pdo->query('SELECT DISTINCT pp.Position_Name, COUNT(*) as cnt FROM candidate_position cp LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID WHERE cp.Election_Year = ' . (int)$year . ' GROUP BY pp.Position_Name')->fetchAll();
echo "Position breakdown:\n";
foreach ($result2 as $r) {
    echo '  ' . ($r['Position_Name'] ?? 'NULL') . ': ' . $r['cnt'] . PHP_EOL;
}

// Also show a sample of candidates with positions
echo "\nSample candidates with positions:\n";
$samples = $pdo->query('SELECT cp.Student_ID, pp.Position_Name FROM candidate_position cp LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID WHERE cp.Election_Year = ' . (int)$year . ' LIMIT 5')->fetchAll();
foreach ($samples as $s) {
    echo '  Student ' . $s['Student_ID'] . ': ' . ($s['Position_Name'] ?? 'NO_POSITION') . PHP_EOL;
}
