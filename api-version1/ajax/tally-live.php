<?php
/**
 * Public live tally endpoint — no admin session required.
 * Students can view this; it returns vote counts per candidate grouped by position.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$schoolYear = ELECTION_SCHOOL_YEAR;
$semester   = ELECTION_SEMESTER;

$opts = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function formatPositionName(string $name): string {
    // Capitalise first letter of each space-separated word, then fully uppercase
    // any college-code suffix that follows an underscore (e.g. Representative_coe → Representative_COE).
    $name = ucwords(strtolower($name));
    return preg_replace_callback('/_([a-zA-Z]+)/', fn($m) => '_' . strtoupper($m[1]), $name);
}

function pdoConnect(array $cfg, array $opts): PDO {
    return new PDO(
        "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
        $cfg['Username'], $cfg['Password'], $opts
    );
}

// ── Position helpers ──────────────────────────────────────────────────────────
$posIdMap = [
    1 => 'PRESIDENT', 2 => 'VICE-PRESIDENT',
    3 => 'GOVERNOR',  4 => 'VICE-GOVERNOR',
    5 => 'REPRESENTATIVE', 6 => 'REPRESENTATIVE', 7 => 'REPRESENTATIVE',
    8 => 'REPRESENTATIVE', 9 => 'REPRESENTATIVE', 10 => 'REPRESENTATIVE',
    11 => 'REPRESENTATIVE', 12 => 'REPRESENTATIVE', 13 => 'REPRESENTATIVE',
    14 => 'REPRESENTATIVE', 15 => 'REPRESENTATIVE', 16 => 'REPRESENTATIVE',
    17 => 'REPRESENTATIVE', 18 => 'REPRESENTATIVE',
];
$posIdToCollege = [
    5 => 'CCS', 6 => 'CBA', 7 => 'CTED', 8 => 'CAS', 9 => 'CCJE',
    10 => 'CIT', 11 => 'CTED_HS', 12 => 'CME', 13 => 'COE',
    14 => 'COL', 15 => 'HS', 16 => 'GRAD', 17 => 'SOM', 18 => 'CNAHS',
];
$collegeLabels = [
    'CAS'     => 'CAS — College of Arts & Sciences',
    'CBA'     => 'CBA — College of Business Administration',
    'CCJE'    => 'CCJE — College of Criminal Justice Education',
    'CCS'     => 'CCS — College of Computer Studies',
    'CIT'     => 'CIT — College of Industrial Technology',
    'CME'     => 'CME — College of Marine Engineering',
    'CNAHS'   => 'CNAHS — College of Nursing, Allied Health Sciences',
    'COE'     => 'COE — College of Engineering',
    'COL'     => 'COL — College of Law',
    'CTED'    => 'CTED — College of Teacher Education',
    'CTED_HS' => 'CTED — Laboratory High School',
    'GRAD'    => 'Graduate School',
    'HS'      => 'High School',
    'SOM'     => 'SOM — School of Medicine',
];
$positionOrder = ['PRESIDENT','VICE-PRESIDENT','GOVERNOR','VICE-GOVERNOR','REPRESENTATIVE'];

try {
    // ── 1. Load parties (color mapping) ───────────────────────────────────────
    $partiesFile = DATA_DIR . '/parties.json';
    $partiesJson = file_exists($partiesFile) ? (json_decode(file_get_contents($partiesFile), true) ?: []) : [];
    $themeColorMap = [
        'theme-blue'   => '#1a3a8f', 'theme-purple' => '#7c3aed',
        'theme-navy'   => '#0d2a6e', 'theme-green'  => '#16a34a',
        'theme-red'    => '#dc2626', 'theme-gold'   => '#f5c400',
    ];
    $partyThemes = [];
    foreach ($partiesJson as $p) {
        $partyThemes[$p['name'] ?? ''] = $themeColorMap[$p['theme'] ?? ''] ?? '#1a3a8f';
    }

    // ── 2. Candidate college overrides (Governors) ────────────────────────────
    $ccFile = DATA_DIR . '/candidate_college.json';
    $ccMap  = file_exists($ccFile) ? (json_decode(file_get_contents($ccFile), true) ?: []) : [];

    // ── 3. Approved candidates from candidate DB ──────────────────────────────
    $cPdo = pdoConnect(\Configuration\Application::$SSG_Candidate_DBase, $opts);
    $cStmt = $cPdo->prepare(
        "SELECT cp.Candidate_ID, cp.Student_ID, cp.Position_ID, cp.Candidate_Slate_ID,
                p.Position, p.Position_Rank, p.Num_Elected_Officer,
                cs.Candidate_Slate,
                ph.Photo
         FROM candidate_position cp
         LEFT JOIN position p           ON cp.Position_ID        = p.Position_ID
         LEFT JOIN candidate_slate cs   ON cp.Candidate_Slate_ID = cs.Candidate_Slate_ID
         LEFT JOIN candidate_photo ph   ON cp.Student_ID         = ph.Candidate_ID
         WHERE cp.Election_Year = ? AND cp.Application_Status = 'APPROVED'
         ORDER BY p.Position_Rank ASC, cp.Candidate_ID ASC"
    );
    $cStmt->execute([$schoolYear]);
    $candidates = $cStmt->fetchAll();

    // ── 4. Student names + total voter count from voter DB ────────────────────
    $vPdo = pdoConnect(\Configuration\Application::$SSG_Voter_DBase, $opts);

    // Auto-detect the most recent school year that has student data
    // (ARMS mirror only goes up to 2024-2025, but synced to current election year)
    $totalStudents = 0;
    $baseYearRow = $vPdo->query(
        'SELECT School_Year FROM student GROUP BY School_Year ORDER BY School_Year DESC LIMIT 1'
    )->fetch();
    $baseYear = $baseYearRow ? $baseYearRow['School_Year'] : null;
    if ($baseYear) {
        $tvStmt = $vPdo->prepare('SELECT COUNT(DISTINCT Student_ID) FROM student WHERE School_Year = ?');
        $tvStmt->execute([$baseYear]);
        $totalStudents = (int) $tvStmt->fetchColumn();
    }

    // Student names
    $nameMap = [];
    if (!empty($candidates)) {
        $sids = array_unique(array_filter(array_map(fn($c) => trim($c['Student_ID'] ?? ''), $candidates)));
        if (!empty($sids)) {
            $ph = implode(',', array_fill(0, count($sids), '?'));
            $nStmt = $vPdo->prepare("SELECT Student_ID, MAX(Student_Name) AS Student_Name FROM student WHERE Student_ID IN ($ph) GROUP BY Student_ID");
            $nStmt->execute(array_values($sids));
            foreach ($nStmt->fetchAll() as $row) {
                $nameMap[trim($row['Student_ID'])] = $row['Student_Name'];
            }
        }
    }

    // Merge manually-entered names as fallback for voter-DB misses (temp/unknown IDs)
    $cnFile = DATA_DIR . '/candidate_names.json';
    if (file_exists($cnFile)) {
        $cnMap = json_decode(file_get_contents($cnFile), true) ?: [];
        foreach ($cnMap as $cnSid => $cnName) {
            if (!isset($nameMap[trim($cnSid)])) {
                $nameMap[trim($cnSid)] = $cnName;
            }
        }
    }

    // ── 5. Vote tally from election DB ────────────────────────────────────────
    $ePdo = pdoConnect(\Configuration\Application::$SSG_Election_DBase, $opts);
    $tStmt = $ePdo->prepare(
        'SELECT Candidate_ID, COUNT(*) AS vote_count FROM votes WHERE School_Year = ? GROUP BY Candidate_ID'
    );
    $tStmt->execute([$schoolYear]);
    $tallyMap = [];
    foreach ($tStmt->fetchAll() as $t) {
        $tallyMap[$t['Candidate_ID']] = (int)$t['vote_count'];
    }

    $totalVotesCast = array_sum($tallyMap);

    // ── 6. Assemble tally grouped by position → college ───────────────────────
    $byPosition = []; // [posKey => [college => [candidates...]]]
    $posRanks   = [];
    $posNames   = [];

    foreach ($candidates as $c) {
        $sid    = trim($c['Student_ID'] ?? '');
        $pid    = (int)($c['Position_ID'] ?? 0);
        $rawPos = trim($c['Position'] ?? ($posIdMap[$pid] ?? 'GENERAL'));
        // Normalize spaces to hyphens: "VICE GOVERNOR" → "VICE-GOVERNOR"
        $posKey = strtoupper(str_replace(' ', '-', $rawPos));

        // College grouping
        // Position names may include a college suffix (e.g. Representative_ccs, Representative_cted)
        // so we use str_starts_with instead of strict equality for REPRESENTATIVE.
        if (in_array($posKey, ['GOVERNOR','VICE-GOVERNOR'])) {
            $college = $ccMap[$sid] ?? '';
        } elseif (str_starts_with($posKey, 'REPRESENTATIVE')) {
            // Try position_ID map first
            $college = $posIdToCollege[$pid] ?? '';
            // Fallback: extract college suffix from name (REPRESENTATIVE_CCS → CCS)
            if ($college === '' && str_contains($posKey, '_')) {
                $college = strtoupper(substr($posKey, strrpos($posKey, '_') + 1));
            }
        } else {
            $college = '';
        }

        $partyKey   = $c['Candidate_Slate'] ?? '';
        $partyColor = $partyThemes[$partyKey] ?? '#1a3a8f';
        $name       = ucwords(strtolower($nameMap[$sid] ?? $c['Student_ID'] ?? '—'));

        if (!isset($byPosition[$posKey])) {
            $byPosition[$posKey] = [];
            $posRanks[$posKey]   = (int)($c['Position_Rank'] ?? 99);
            $posNames[$posKey]   = formatPositionName($c['Position'] ?? $posKey);
        }
        if (!isset($byPosition[$posKey][$college])) {
            $byPosition[$posKey][$college] = [];
        }

        // Photo: serve via dedicated endpoint to keep JSON payload small
        // candidate_photo.Candidate_ID stores Student_ID (not the numeric position ID)
        $rawPhoto   = trim($c['Photo'] ?? '');
        $photoUrl   = $rawPhoto !== '' ? '/ajax/candidate-photo.php?id=' . urlencode($sid) : '';

        $byPosition[$posKey][$college][] = [
            'candidate_id'   => $c['Candidate_ID'],
            'student_id'     => $sid,
            'name'           => $name,
            'slate'          => $partyKey,
            'party_color'    => $partyColor,
            'votes'          => $tallyMap[$c['Candidate_ID']] ?? 0,
            'num_elected'    => (int)($c['Num_Elected_Officer'] ?? 1),
            'photo'          => $photoUrl,
        ];
    }

    // Sort candidates within each college group by votes desc
    foreach ($byPosition as $posKey => &$collegeGroups) {
        ksort($collegeGroups);
        foreach ($collegeGroups as $college => &$cands) {
            usort($cands, fn($a, $b) => $b['votes'] - $a['votes']);
        }
        unset($cands);
    }
    unset($collegeGroups);

    // Sort positions by position order
    uksort($byPosition, function($a, $b) use ($positionOrder, $posRanks) {
        $ai = array_search($a, $positionOrder);
        $bi = array_search($b, $positionOrder);
        $ai = $ai === false ? ($posRanks[$a] ?? 99) : $ai;
        $bi = $bi === false ? ($posRanks[$b] ?? 99) : $bi;
        return $ai - $bi;
    });

    // Build flat cards array for the frontend
    $cards = [];
    foreach ($byPosition as $posKey => $collegeGroups) {
        $hasColleges = !(count($collegeGroups) === 1 && array_key_first($collegeGroups) === '');
        if ($hasColleges) {
            foreach ($collegeGroups as $college => $cands) {
                $cards[] = [
                    'position'      => $posNames[$posKey] ?? formatPositionName($posKey),
                    'college'       => $college,
                    'college_label' => $collegeLabels[$college] ?? $college,
                    'candidates'    => $cands,
                ];
            }
        } else {
            $cands = reset($collegeGroups);
            $cards[] = [
                'position'      => $posNames[$posKey] ?? formatPositionName($posKey),
                'college'       => '',
                'college_label' => '',
                'candidates'    => $cands,
            ];
        }
    }

    // ── 7. College filter — logged-in students only see their own college's positions ──
    // President & Vice-President (college === '') are always visible to everyone.
    // Governor, Vice-Governor, Representative are filtered to the voter's college
    // when the student is authenticated. Public/guest visitors see everything.
    // Pass ?all=1 (projector/slideshow views) to bypass the filter entirely.
    if (!empty($_GET['all'])) {
        echo json_encode([
            'ok'             => true,
            'school_year'    => $schoolYear,
            'total_students' => $totalStudents,
            'total_votes'    => $totalVotesCast,
            'cards'          => $cards,
            'voter_college'  => null,
            'generated'      => date('Y-m-d H:i:s'),
        ]);
        exit;
    }

    $voterCollegeCode = '';
    if (!empty($_SESSION['logged_in'])) {
        // Priority 1: explicit college_code set during login
        $voterCollegeCode = strtoupper(trim($_SESSION['college_code'] ?? ''));

        // Priority 2: derive from program code (e.g. BSCS → CCS)
        if ($voterCollegeCode === '') {
            $prog = strtoupper(trim($_SESSION['program'] ?? ''));
            $programToCollegeMap = [
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
            if (isset($programToCollegeMap[$prog])) {
                $voterCollegeCode = $programToCollegeMap[$prog];
            }
        }

        // Priority 3: ARMS returns College as "CCS COLLEGE OF COMPUTER STUDIES"
        // — the first word/token before any space or hyphen is the college code.
        if ($voterCollegeCode === '') {
            $collegeStr = strtoupper(trim($_SESSION['college'] ?? ''));
            if ($collegeStr !== '') {
                // Extract first token (before space or hyphen)
                $firstToken = preg_split('/[\s\-]+/', $collegeStr)[0] ?? '';
                $knownCodes = ['CCS','CBA','CTED','CAS','CCJE','CIT','CME','CNAHS','COE','COL','GRAD','HS','SOM'];
                if (in_array($firstToken, $knownCodes, true)) {
                    $voterCollegeCode = $firstToken;
                }
            }
        }

        // Priority 4: match full college name substring against known names
        if ($voterCollegeCode === '') {
            $collegeStr = strtoupper(trim($_SESSION['college'] ?? ''));
            $nameToCode = [
                'COMPUTER STUDIES'   => 'CCS',
                'BUSINESS ADMIN'     => 'CBA',
                'TEACHER EDUCATION'  => 'CTED',
                'ARTS'               => 'CAS',
                'CRIMINAL JUSTICE'   => 'CCJE',
                'INDUSTRIAL TECH'    => 'CIT',
                'MARINE ENGINEER'    => 'CME',
                'NURSING'            => 'CNAHS',
                'ENGINEERING'        => 'COE',
                'LAW'                => 'COL',
                'GRADUATE'           => 'GRAD',
                'HIGH SCHOOL'        => 'HS',
                'MEDICINE'           => 'SOM',
            ];
            foreach ($nameToCode as $needle => $code) {
                if (str_contains($collegeStr, $needle)) {
                    $voterCollegeCode = $code;
                    break;
                }
            }
        }
    }

    // Fallback: tally.php embeds the PHP-resolved college code in the JS and passes it
    // as ?college=CCS. This is reliable when the session cookie isn't forwarded to the
    // AJAX endpoint (e.g. in proxied/iframe environments after a session regeneration).
    // Only accepted when session didn't already resolve a code.
    if ($voterCollegeCode === '' && !empty($_GET['college'])) {
        $_allowed = ['CCS','CBA','CTED','CAS','CCJE','CIT','CME','CNAHS','COE','COL','GRAD','HS','SOM'];
        $_qc = strtoupper(trim($_GET['college']));
        if (in_array($_qc, $_allowed, true)) {
            $voterCollegeCode = $_qc;
        }
    }

    if ($voterCollegeCode !== '') {
        $cards = array_values(array_filter($cards, function($card) use ($voterCollegeCode) {
            // college === '' means school-wide (President, Vice-President) — always show
            // otherwise only show cards matching the voter's college
            return $card['college'] === '' || strtoupper($card['college']) === $voterCollegeCode;
        }));
    }

    echo json_encode([
        'ok'              => true,
        'school_year'     => $schoolYear,
        'total_students'  => $totalStudents,
        'total_votes'     => $totalVotesCast,
        'cards'           => $cards,
        'voter_college'   => $voterCollegeCode ?: null,
        'generated'       => date('Y-m-d H:i:s'),
    ]);

} catch (\Throwable $e) {
    error_log('tally-live error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
}
