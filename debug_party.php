<?php
require __DIR__ . '/api-version1/includes/bootstrap.php';
$response = callModel(function () {
    Candidate::Get_All_Candidates([
        'Election_Year' => ELECTION_SCHOOL_YEAR,
        'Application_Status' => 'APPROVED',
    ]);
});
$rows = $response['Record'] ?? ($response['Result'] ?? $response);
if (!is_array($rows)) {
    echo "NOT_ARRAY\n";
    var_export($response);
    exit;
}
foreach ($rows as $idx => $row) {
    $party = $row['Party_Name'] ?? $row['Party'] ?? $row['Candidate_Slate'] ?? $row['Slate'] ?? 'EMPTY';
    $sid = $row['Student_ID'] ?? $row['student_id'] ?? 'UNKNOWN';
    $name = $row['Candidate_Name'] ?? $row['name'] ?? 'UNKNOWN';
    echo ($idx + 1) . ' | ' . $sid . ' | ' . $name . ' | Party=' . $party . ' | SlateID=' . ($row['Candidate_Slate_ID'] ?? $row['candidate_slate_id'] ?? 'NONE') . PHP_EOL;
}
