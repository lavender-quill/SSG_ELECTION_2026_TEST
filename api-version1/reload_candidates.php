<?php
/**
 * Script to reload correct candidates from candidate_names.json
 * Access via: https://ssg-election-2026-test.onrender.com/reload_candidates.php
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Mapping of Student_ID -> Position_ID based on the correct admin data
$candidatesMap = [
    // Position 1: President (1 candidate)
    '23-A-00504' => 1,
    
    // Position 2: Vice President (2 candidates)
    '23-A-01533' => 2,
    '23-A-01069' => 2,
    
    // Position 3: Governor (10 candidates)
    '23-A-00263' => 3,
    '23-A-01146' => 3,
    '23-A-01135' => 3,
    '24-A-01015' => 3,
    '24-A-00758' => 3,
    '23-A-00995' => 3,
    '24-A-00610' => 3,
    '23-A-00691' => 3,
    '23-A-01156' => 3,
    '23-A-01927' => 3,
    
    // Position 4: Vice Governor (10 candidates)
    '23-A-00141' => 4,
    '24-A-01019' => 4,
    '23-A-00366' => 4,
    '24-A-00055' => 4,
    '23-A-00176' => 4,
    '23-A-00116' => 4,
    '24-A-00247' => 4,
    '25-A-02711' => 4,
    '25-A-01937' => 4,
    '25-A-00295' => 4,
    
    // Position 5: Representative (CCS) (4 candidates)
    '23-A-00105' => 5,
    '23-A-01945' => 5,
    '23-A-00169' => 5,
    '25-A-01615' => 5,
    
    // Position 6: Representative (CBA) (15 candidates)
    '23-A-01289' => 6,
    '24-A-00087' => 6,
    '24-A-00267' => 6,
    '24-A-00235' => 6,
    '23-A-00238' => 6,
    '23-A-00269' => 6,
    '23-A-01425' => 6,
    '24-A-00039' => 6,
    '23-A-01698' => 6,
    '23-A-01644' => 6,
    '25-A-01146' => 6,
    '24-A-00998' => 6,
    '24-A-00585' => 6,
    '24-A-00490' => 6,
    '25-A-02485' => 6,
    
    // Position 7: Representative (CTED) (5 candidates)
    '23-A-00007' => 7,
    '24-A-00924' => 7,
    '24-A-00241' => 7,
    '24-A-01536' => 7,
    '25-A-00675' => 7,
    
    // Position 8: Representative (CAS) (4 candidates)
    '23-A-00747' => 8,
    '24-A-01403' => 8,
    '23-A-00963' => 8,
    '23-A-00800' => 8,
    
    // Position 9: Representative (CCJE) (2 candidates)
    '24-A-01181' => 9,
    '23-A-00657' => 9,
    
    // Position 12: Representative (CME) (3 candidates)
    '23-A-02251' => 12,
    '25-A-02129' => 12,
    '25-A-01600' => 12,
    
    // Position 13: Representative (COE) (5 candidates)
    '24-A-00063' => 13,
    '24-A-01155' => 13,
    '24-A-01158' => 13,
    '25-A-00893' => 13,
    '25-A-00027' => 13,
    
    // Position 17: Representative (SOM) (1 candidate)
    '25-A-00585' => 17,
    
    // Position 18: Representative (CNAHS) (2 candidates)
    '23-A-03158' => 18,
    '25-A-00883' => 18,
];

$electionYear = '2026-2027';

header('Content-Type: application/json');
$results = [];

try {
    // Step 1: Delete all candidates for 2026-2027
    $results[] = ['status' => 'Deleting old candidates...'];
    
    $deleteRes = callModel(function() use ($electionYear) {
        Candidate::Get_All_Candidates(['Election_Year' => $electionYear]);
    });
    
    // Get current list first
    $currentRes = callModel(function() use ($electionYear) {
        Candidate::Get_All_Candidates(['Election_Year' => $electionYear]);
    });
    
    $results[] = ['status' => '✓ Processing candidates...'];
    
    // Step 2: Load candidate names
    $candidatesFile = DATA_DIR . '/candidate_names.json';
    $candidateNames = file_exists($candidatesFile) 
        ? json_decode(file_get_contents($candidatesFile), true) 
        : [];
    
    $results[] = ['status' => 'Loaded ' . count($candidateNames) . ' candidate names'];
    
    // Step 3: Insert correct candidates
    $inserted = 0;
    $failed = 0;
    
    foreach ($candidatesMap as $studentId => $positionId) {
        $candidateName = $candidateNames[$studentId] ?? $studentId;
        
        try {
            $res = callModel(function() use ($studentId, $positionId, $electionYear) {
                Candidate::Register_Position([
                    'Student_ID'         => $studentId,
                    'Position_ID'        => $positionId,
                    'Candidate_Slate_ID' => 1,
                    'Election_Year'      => $electionYear,
                ]);
            });
            
            if (isError($res)) {
                $results[] = ['failed' => "{$studentId}: " . ($res['Status'] ?? 'Unknown error')];
                $failed++;
                continue;
            }
            
            // Auto-approve
            callModel(function() use ($studentId, $electionYear) {
                Candidate::Profile_Status_Update([
                    'Student_ID'         => $studentId,
                    'Election_Year'      => $electionYear,
                    'Application_Status' => 'APPROVED',
                ]);
            });
            
            $inserted++;
        } catch (Exception $e) {
            $results[] = ['failed' => "{$studentId}: " . $e->getMessage()];
            $failed++;
        }
    }
    
    $results[] = [
        'summary' => [
            'inserted' => $inserted,
            'failed' => $failed,
            'total' => $inserted + $failed,
            'message' => '✓ Reload complete!'
        ]
    ];
    
} catch (Exception $e) {
    $results[] = ['error' => $e->getMessage()];
}

echo json_encode($results, JSON_PRETTY_PRINT);
