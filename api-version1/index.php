<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Load parties from admin-managed JSON file
$_partiesFile = DATA_DIR . '/parties.json';
$_partiesJson = file_exists($_partiesFile) ? (json_decode(file_get_contents($_partiesFile), true) ?: []) : [];

// Load candidate gallery images
$_galleryFile = DATA_DIR . '/candidate_gallery.json';
$_gallery = file_exists($_galleryFile) ? (json_decode(file_get_contents($_galleryFile), true) ?: []) : [];

// Build $parties array from JSON (each entry becomes a party card)
$parties = [];
foreach ($_partiesJson as $p) {
    $key = $p['name'] ?? 'Unknown';
    $parties[$key] = [
        'name'        => $p['name'] ?? 'Unknown',
        'description' => $p['description'] ?? '',
        'theme'       => $p['theme'] ?? 'theme-blue',
        'tag'         => $p['tag'] ?? 'Party List',
        'cover_photo' => $p['cover_photo'] ?? '',
        'candidates'  => [],
    ];
}

$partyAliasMap = [];
foreach ($parties as $partyName => $partyData) {
    $partyAliasMap[strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $partyName)))] = $partyName;
    $partyAliasMap[strtolower(trim(preg_replace('/\s*(partylist|party list)\s*/i', ' ', $partyName)))] = $partyName;
}

$resolvePartyName = function($rawParty) use ($parties, $partyAliasMap) {
    $value = trim((string)($rawParty ?? ''));
    if ($value === '') return 'Independent';

    $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $value)));
    if (isset($partyAliasMap[$normalized])) return $partyAliasMap[$normalized];

    $cleaned = strtolower(trim(preg_replace('/\s*(partylist|party list)\s*/i', ' ', $value)));
    if (isset($partyAliasMap[$cleaned])) return $partyAliasMap[$cleaned];

    foreach ($parties as $partyName => $partyData) {
        $candidateNorm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $partyName)));
        if ($normalized === $candidateNorm || stripos($candidateNorm, $normalized) !== false || stripos($normalized, $candidateNorm) !== false) {
            return $partyName;
        }
    }

    if (stripos($normalized, 'vertex') !== false) return 'Vertex Partylist';
    if (stripos($normalized, 'independent') !== false) return 'Independent';

    return $value;
};

// Enrich with live candidates from DB if available
try {
    $response = callModel(function() {
        Candidate::Get_All_Candidates([
            'Election_Year'      => ELECTION_SCHOOL_YEAR,
            'Application_Status' => 'APPROVED',
        ]);
    });
    if (isset($response['Record']) && is_array($response['Record'])) {
        $candidates = $response['Record'];
    } elseif (is_array($response) && !empty($response) && !isset($response['Status'])) {
        $candidates = $response;
    } else {
        $candidates = [];
    }
    $candidates = applyCandidateJsonNameOverrides($candidates);

    $slateMap = [];
    try {
        $candidateDb = \Configuration\Application::$SSG_Candidate_DBase;
        $candidatePdo = new PDO(
            "mysql:host={$candidateDb['Host']};port={$candidateDb['Port']};dbname={$candidateDb['DBName']};charset=utf8mb4",
            $candidateDb['Username'], $candidateDb['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        foreach ($candidatePdo->query("SELECT Candidate_Slate_ID, Candidate_Slate FROM candidate_slate ORDER BY Candidate_Slate_ID")->fetchAll() as $row) {
            $slateMap[(int)($row['Candidate_Slate_ID'] ?? 0)] = trim((string)($row['Candidate_Slate'] ?? ''));
        }
    } catch (\Throwable $e) {
        // fall back to raw Party_Name values if the slate table is unavailable
    }

    if (!empty($candidates)) {
        foreach ($candidates as $c) {
            $rawParty = $c['Party_Name'] ?? $c['Party'] ?? $c['Candidate_Slate'] ?? $c['Slate'] ?? 'Independent';
            $slateId = (int)($c['Candidate_Slate_ID'] ?? $c['candidate_slate_id'] ?? 0);
            if ($slateId && isset($slateMap[$slateId]) && trim($slateMap[$slateId]) !== '') {
                $rawParty = $slateMap[$slateId];
            }
            $party = $resolvePartyName($rawParty);
            if (!isset($parties[$party])) {
                $parties[$party] = ['name' => $party, 'description' => '', 'theme' => 'theme-blue', 'tag' => 'Party List', 'candidates' => []];
            }
            $parties[$party]['candidates'][] = $c;
        }
    }
} catch (\Throwable $e) {
    // DB unavailable — show parties from JSON only
}

// Explicitly order parties: Vertex Partylist first, Independent last
$orderedParties = [];
if (isset($parties['Vertex Partylist'])) {
    $orderedParties['Vertex Partylist'] = $parties['Vertex Partylist'];
    unset($parties['Vertex Partylist']);
}
// Add any other parties in the middle
foreach ($parties as $name => $party) {
    if ($name !== 'Independent') {
        $orderedParties[$name] = $party;
    }
}
// Add Independent last
if (isset($parties['Independent'])) {
    $orderedParties['Independent'] = $parties['Independent'];
}
$parties = $orderedParties;

$photoMap = [];
$positionMap = [];
$allSids = [];

// Position mapping like tally.php
$posIdMap = [
    1 => 'PRESIDENT', 2 => 'VICE-PRESIDENT',
    3 => 'GOVERNOR', 4 => 'VICE-GOVERNOR',
    5 => 'REPRESENTATIVE', 6 => 'REPRESENTATIVE', 7 => 'REPRESENTATIVE',
    8 => 'REPRESENTATIVE', 9 => 'REPRESENTATIVE', 10 => 'REPRESENTATIVE',
    11 => 'REPRESENTATIVE', 12 => 'REPRESENTATIVE', 13 => 'REPRESENTATIVE',
    14 => 'REPRESENTATIVE', 15 => 'REPRESENTATIVE', 16 => 'REPRESENTATIVE',
    17 => 'REPRESENTATIVE', 18 => 'REPRESENTATIVE',
];

if (isset($candidates) && is_array($candidates) && !isError($candidates)) {
    foreach ($candidates as $c) {
        $sid = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
        if ($sid !== '') {
            $candidateLookup[$sid] = $c;
            $allSids[] = $sid;
        }
    }
    if (!empty($allSids)) {
        try {
            $candidateDb = \Configuration\Application::$SSG_Candidate_DBase;
            $candidatePdo = new PDO(
                "mysql:host={$candidateDb['Host']};port={$candidateDb['Port']};dbname={$candidateDb['DBName']};charset=utf8mb4",
                $candidateDb['Username'], $candidateDb['Password'],
                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            $pidPlaceholders = implode(',', array_fill(0, count(array_unique($allSids)), '?'));
            $photoStmt = $candidatePdo->prepare("SELECT Candidate_ID, Photo FROM candidate_photo WHERE Candidate_ID IN ({$pidPlaceholders})");
            $photoStmt->execute(array_values(array_unique($allSids)));
            foreach ($photoStmt->fetchAll() as $row) {
                if (!empty($row['Photo'])) {
                    $photoMap[(string)($row['Candidate_ID'] ?? '')] = $row['Photo'];
                }
            }

            $posStmt = $candidatePdo->prepare(
                "SELECT cp.Student_ID, cp.Position_ID, pp.Position_Name
                 FROM candidate_position cp
                 LEFT JOIN position_profile pp ON pp.Position_ID = cp.Position_ID
                 WHERE cp.Student_ID IN ({$pidPlaceholders}) AND cp.Election_Year = ?
                 ORDER BY cp.Record_ID DESC"
            );
            $posParams = array_values(array_unique($allSids));
            $posParams[] = ELECTION_SCHOOL_YEAR;
            $posStmt->execute($posParams);
            foreach ($posStmt->fetchAll() as $row) {
                $sid = trim((string)($row['Student_ID'] ?? ''));
                if ($sid !== '') {
                    $posName = trim((string)($row['Position_Name'] ?? ''));
                    $posId = (int)($row['Position_ID'] ?? 0);
                    if (!empty($posName)) {
                        $positionMap[$sid] = $posName;
                    } elseif ($posId > 0 && isset($posIdMap[$posId])) {
                        $positionMap[$sid] = $posIdMap[$posId];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore photo/position map failures; UI still renders without them
        }
    }
}

// Election countdown — JSON first, DB fallback (self-healing on redeploy)
$_sched            = loadElectionSchedule(ELECTION_SCHOOL_YEAR);
$electionTimestamp = $_sched ? (int)($_sched['Time_Start'] ?? 0) : 0;
$_electionEnd      = $_sched ? (int)($_sched['Time_End']   ?? 0) : 0;
$_now              = time();
$electionIsLive    = $electionTimestamp && $_electionEnd && $_now >= $electionTimestamp && $_now <= $_electionEnd;
$electionEnded     = $_electionEnd && $_now > $_electionEnd;

// Get total student count + candidate names from voter DB
$totalStudents = 0;
$_nameMap = [];
try {
    $_vDb   = \Configuration\Application::$SSG_Voter_DBase;
    $_vOpts = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    $_vPdo  = new PDO("mysql:host={$_vDb['Host']};port={$_vDb['Port']};dbname={$_vDb['DBName']};charset=utf8mb4", $_vDb['Username'], $_vDb['Password'], $_vOpts);
    $_vStmt = $_vPdo->prepare("CALL CODERSTATION_DisPatcher(?, ?)");
    $_vStmt->execute(['voter_count_total', json_encode(['School_Year' => ELECTION_SCHOOL_YEAR, 'Semester' => ELECTION_SEMESTER])]);
    $_vRows = $_vStmt->fetchAll();
    if (!empty($_vRows) && isset($_vRows[0]['Result'])) {
        $_vRaw = $_vRows[0]['Result'];
        $_vRaw = str_replace(['\\\"','"{','}"','"[',']"','\\'], ['"','{','}','[',']',''], $_vRaw);
        $_vDec = json_decode($_vRaw, true);
        $totalStudents = (int)($_vDec['Total'] ?? $_vDec['Count'] ?? $_vDec['count'] ?? 0);
    }
    // Drain any remaining result sets from the stored proc before running SELECT
    while ($_vStmt->nextRowset()) {}
    $_vStmt->closeCursor();
    // Fetch student names for all approved candidates
    if (!empty($candidates)) {
        $_sids = array_unique(array_filter(array_map(
            fn($c) => trim($c['Student_ID'] ?? $c['student_id'] ?? ''), $candidates
        )));
        if (!empty($_sids)) {
            $_ph   = implode(',', array_fill(0, count($_sids), '?'));
            $_nStmt = $_vPdo->prepare("SELECT Student_ID, Student_Name FROM student WHERE Student_ID IN ($_ph)");
            $_nStmt->execute(array_values($_sids));
            foreach ($_nStmt->fetchAll() as $_nRow) {
                $_nameMap[trim($_nRow['Student_ID'])] = $_nRow['Student_Name'];
            }
        }
    }
} catch (\Throwable $e) {}

// Merge manually-entered names as fallback for voter-DB misses (temp/unknown IDs)
$_cnFile = DATA_DIR . '/candidate_names.json';
if (file_exists($_cnFile)) {
    $_cnMap = json_decode(file_get_contents($_cnFile), true) ?: [];
    foreach ($_cnMap as $_cnSid => $_cnName) {
        if (!isset($_nameMap[trim($_cnSid)])) {
            $_nameMap[trim($_cnSid)] = $_cnName;
        }
    }
}

// Build vote-count lookup: Student_ID => votes cast
$_voteLookup = [];
try {
    $_tallyRaw  = callModel(function() {
        Election::election_generate_result(['School_Year' => ELECTION_SCHOOL_YEAR]);
    });
    $_tallyList = $_tallyRaw['Record'] ?? (is_array($_tallyRaw) && !isset($_tallyRaw['Status']) ? $_tallyRaw : []);
    if (is_array($_tallyList)) {
        foreach ($_tallyList as $_r) {
            $_sid = trim($_r['Student_ID'] ?? $_r['student_id'] ?? '');
            if ($_sid !== '') {
                $_voteLookup[$_sid] = (int)($_r['Vote_Count'] ?? $_r['Votes'] ?? $_r['votes'] ?? 0);
            }
        }
    }
} catch (\Throwable $e) {}

// Build tally from approved candidates (all appear, even with 0 votes)
// Structure: $tallyByPosition[posName][collegeCode][] = candidate
// President/VP use '' as collegeCode (no college sub-grouping)
$tallyByPosition = [];
$positionOrder   = ['PRESIDENT','VICE-PRESIDENT','GOVERNOR','VICE-GOVERNOR','REPRESENTATIVE'];
$_posIdMap = [
    1 => 'PRESIDENT', 2 => 'VICE-PRESIDENT',
    3 => 'GOVERNOR',  4 => 'VICE-GOVERNOR',
    5 => 'REPRESENTATIVE', 6 => 'REPRESENTATIVE', 7 => 'REPRESENTATIVE',
    8 => 'REPRESENTATIVE', 9 => 'REPRESENTATIVE', 10 => 'REPRESENTATIVE',
    11 => 'REPRESENTATIVE', 12 => 'REPRESENTATIVE', 13 => 'REPRESENTATIVE',
    14 => 'REPRESENTATIVE', 15 => 'REPRESENTATIVE', 16 => 'REPRESENTATIVE',
    17 => 'REPRESENTATIVE', 18 => 'REPRESENTATIVE',
];
// Theme → hex color (for tally bar fills)
$_themeColorMapTally = [
    'theme-blue'   => '#1a3a8f',
    'theme-purple' => '#7c3aed',
    'theme-navy'   => '#0d2a6e',
    'theme-green'  => '#16a34a',
    'theme-red'    => '#dc2626',
    'theme-gold'   => '#f5c400',
];

// Position_ID → College Code (for Representatives)
$_posIdToCollege = [
    5 => 'CCS', 6 => 'CBA', 7 => 'CTED', 8 => 'CAS', 9 => 'CCJE',
    10 => 'CIT', 11 => 'CTED_HS', 12 => 'CME', 13 => 'COE',
    14 => 'COL', 15 => 'HS', 16 => 'GRAD', 17 => 'SOM', 18 => 'CNAHS',
];
// College code → full label for display
$_collegeLabels = [
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
// Load governor/vice-gov college map (Student_ID → college_code)
$_ccFileTally = DATA_DIR . '/candidate_college.json';
$_ccMapTally  = file_exists($_ccFileTally) ? (json_decode(file_get_contents($_ccFileTally), true) ?: []) : [];

if (isset($candidates) && is_array($candidates) && !isError($candidates)) {
    foreach ($candidates as $_c) {
        $_sid  = trim($_c['Student_ID'] ?? $_c['student_id'] ?? '');
        $_fn   = $_c['First_Name'] ?? $_c['Firstname'] ?? $_c['first_name'] ?? '';
        $_ln   = $_c['Last_Name']  ?? $_c['Lastname']  ?? $_c['last_name']  ?? '';
        $_rawName = $_c['Candidate_Name'] ?? $_c['Full_Name'] ?? $_c['Name']
                    ?? (trim($_fn . ' ' . $_ln) !== '' ? trim($_fn . ' ' . $_ln) : null);
        // Prefer voter-DB name (Student_Name); fall back to candidate-record fields; never show bare ID
        $_voterName = $_sid !== '' ? ($_nameMap[$_sid] ?? null) : null;
        $_name = $_voterName ?? $_rawName ?? '—';
        $_name = ucwords(strtolower($_name));

        $_posRaw = $_c['Position_Name'] ?? $_c['Position'] ?? '';
        $_pos    = strtoupper(trim($_posRaw));
        $_pid    = (int)($_c['Position_ID'] ?? 0);
        if ($_pos === '') {
            $_pos = $_pid > 0 ? ($_posIdMap[$_pid] ?? 'GENERAL') : 'GENERAL';
        }

        // Determine college sub-group key
        if (in_array($_pos, ['GOVERNOR', 'VICE-GOVERNOR'])) {
            $_college = $_ccMapTally[$_sid] ?? '';
        } elseif ($_pos === 'REPRESENTATIVE') {
            $_college = $_posIdToCollege[$_pid] ?? '';
        } else {
            $_college = ''; // President, Vice-President — no college split
        }

        $_partyKey   = $_c['Party_Name'] ?? $_c['Party'] ?? '';
        $_partyTheme = $parties[$_partyKey]['theme'] ?? 'theme-blue';
        $_partyColor = $_themeColorMapTally[$_partyTheme] ?? '#1a3a8f';

        $tallyByPosition[$_pos][$_college][] = [
            '_resolved_name'  => $_name,
            '_resolved_photo' => $_c['Photo'] ?? $_c['Photo_Url'] ?? '',
            '_party_name'     => $_partyKey,
            '_party_color'    => $_partyColor,
            'Vote_Count'      => $_sid !== '' ? ($_voteLookup[$_sid] ?? 0) : 0,
        ];
    }
    uksort($tallyByPosition, function($a, $b) use ($positionOrder) {
        $ai = array_search($a, $positionOrder);
        $bi = array_search($b, $positionOrder);
        $ai = $ai === false ? 99 : $ai;
        $bi = $bi === false ? 99 : $bi;
        return $ai - $bi;
    });
}

// ── College code detection for the logged-in voter ───────────────────────
$voterCollegeCode  = '';
$voterCollegeLabel = '';
if (!empty($_SESSION['logged_in'])) {
    $_knownCodes = ['CCS','CBA','CTED','CAS','CCJE','CIT','CME','CNAHS','COE','COL','GRAD','HS','SOM'];
    $voterCollegeCode = strtoupper(trim($_SESSION['college_code'] ?? ''));
    if ($voterCollegeCode === '' || !in_array($voterCollegeCode, $_knownCodes, true)) {
        $_cs = strtoupper(trim($_SESSION['college'] ?? ''));
        $_ft = preg_split('/[\s\-]+/', $_cs)[0] ?? '';
        if (in_array($_ft, $_knownCodes, true)) $voterCollegeCode = $_ft;
    }
    if ($voterCollegeCode === '') {
        $_prog = strtoupper(trim($_SESSION['program'] ?? ''));
        $_pm = ['BSCS'=>'CCS','BSIT'=>'CCS','ACT'=>'CCS','BSA'=>'CBA','BSBA'=>'CBA','BSMA'=>'CBA',
                'BEED'=>'CTED','BSED'=>'CTED','AB'=>'CAS','BSMATH'=>'CAS','BSCRIM'=>'CCJE',
                'BSMT'=>'CIT','BSET'=>'CIT','BSME'=>'CME','BSMARE'=>'CME',
                'BSN'=>'CNAHS','BSPT'=>'CNAHS','BSCE'=>'COE','BSEE'=>'COE',
                'BSECE'=>'COE','BSCHE'=>'COE','LLB'=>'COL','JD'=>'COL','MD'=>'SOM'];
        if (isset($_pm[$_prog])) $voterCollegeCode = $_pm[$_prog];
    }
    if ($voterCollegeCode === '') {
        $_cs = strtoupper(trim($_SESSION['college'] ?? ''));
        $_nm = ['COMPUTER STUDIES'=>'CCS','BUSINESS ADMIN'=>'CBA','TEACHER EDUCATION'=>'CTED',
                'ARTS'=>'CAS','CRIMINAL JUSTICE'=>'CCJE','INDUSTRIAL TECH'=>'CIT',
                'MARINE ENGINEER'=>'CME','NURSING'=>'CNAHS','ENGINEERING'=>'COE',
                'LAW'=>'COL','GRADUATE'=>'GRAD','HIGH SCHOOL'=>'HS','MEDICINE'=>'SOM'];
        foreach ($_nm as $_n => $_c) { if (str_contains($_cs, $_n)) { $voterCollegeCode = $_c; break; } }
    }
    $voterCollegeLabel = $_collegeLabels[$voterCollegeCode] ?? '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>E-Ballot &mdash; JRMSU SSG Election Portal</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&family=Unbounded:wght@800&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body, html, * {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.1167;
        }

        :root {
            --blue:   #1a3a8f;
            --yellow: #f5c400;
            --light:  #f4f4f0;
            --dot-bg: #e8e8e4;
            --text:   #1a2744;
            --sub:    #555e7a;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            background-image: radial-gradient(circle, #c8c8c4 1px, transparent 1px);
            background-size: 22px 22px;
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
            padding-bottom: 60px;
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky; top: 0; z-index: 200;
            width: 100%; height: 58px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px;
            animation: navIn .4s ease both;
        }
        @keyframes navIn { from{opacity:0;transform:translateY(-100%)} to{opacity:1;transform:translateY(0)} }
        .navbar-brand {
            font-size: 18px; font-weight: 800; color: var(--blue);
            letter-spacing: .5px; text-decoration: none;
        }
        .navbar-links { display: flex; gap: 32px; list-style: none; }
        .navbar-links a.nav-active { color: var(--yellow); font-weight: 800; }
        .navbar-links a {
            text-decoration: none; font-size: 14px; font-weight: 600;
            color: #444; transition: color .2s;
        }
        .navbar-links a:hover { color: var(--blue); }

        /* ── Hero ── */
        .hero {
            max-width: 1140px; margin: 0 auto;
            padding: 72px 40px 56px;
            display: grid;
            grid-template-columns: 1fr 420px;
            grid-template-rows: auto auto;
            grid-template-areas: "left right" "countdown right";
            column-gap: 56px;
        }
        .hero-left { grid-area: left; min-width: 0; }
        .hero-right { grid-area: right; display: flex; align-items: center; justify-content: center; }
        .hero-countdown { grid-area: countdown; padding-top: 8px; }
        .hero-logo-row {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 22px;
        }
        .hero-logo {
            width: 72px; height: 72px; border-radius: 50%;
            background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .hero-logo img { width: 62px; height: 62px; object-fit: contain; }
        .hero-logo-label { font-size: 12px; font-weight: 700; color: var(--sub); letter-spacing: .5px; text-transform: uppercase; }
        .hero-title {
            font-size: 60px;
            font-weight: 700; line-height: 67px;
            letter-spacing: -0.02em;
            color: var(--yellow); margin-bottom: 16px;
        }
        .hero-title span { color: var(--yellow); }
        .hero-desc { font-size: 14.5px; font-weight: 400; color: var(--blue); line-height: 1.7; margin-bottom: 30px; max-width: 480px; }
        .hero-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 40px; }
        .btn-vote {
            background: transparent; color: var(--blue);
            border: 0.3px solid #6A7FC0;
            width: 124px; height: 35px; border-radius: 23px;
            font-family: 'Poppins', sans-serif;
            font-weight: 800; font-size: 10px;
            line-height: 100%; letter-spacing: 0;
            text-decoration: none; text-transform: uppercase; text-align: center;
            transition: background .2s, transform .15s, box-shadow .2s;
            display: inline-flex; align-items: center; justify-content: center;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
            flex-shrink: 0;
        }
        .btn-vote:hover { background: var(--blue); color: #fff; border-color: var(--blue); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(13,42,110,.22); }
        .btn-vote:active { transform: scale(.97); }
        .btn-results {
            background: transparent; color: var(--yellow); border: 2px solid var(--yellow);
            width: 124px; height: 35px; border-radius: 23px;
            font-family: 'Poppins', sans-serif;
            font-weight: 800; font-size: 10px;
            line-height: 100%; letter-spacing: 0;
            text-decoration: none; text-transform: uppercase; text-align: center;
            transition: background .2s, transform .15s, box-shadow .2s;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
            flex-shrink: 0;
        }
        .btn-results:hover { background: var(--yellow); color: var(--blue); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(245,196,0,.22); }
        .btn-results:active { transform: scale(.97); }
        .live-indicator {
            width: 7px; height: 7px; border-radius: 50%;
            background: #22c55e; display: inline-block;
            animation: lbpulse 1.2s ease-in-out infinite;
        }
        .btn-learn {
            font-size: 14px; font-weight: 600; color: var(--blue);
            text-decoration: none; display: flex; align-items: center; gap: 5px;
            transition: gap .2s; min-height: 44px;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
        }
        .btn-learn:hover { gap: 8px; }

        /* Countdown */
        .countdown-label {
            font-size: 22px; font-weight: 900; color: var(--blue);
            margin-bottom: 16px;
        }
        .countdown { display: flex; gap: 14px; }
        .cd-box {
            background: linear-gradient(to right, #b0b0b0 0%, #f5c400 100%);
            border-radius: 16px;
            padding: 18px 10px 14px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(245,196,0,.35);
            min-width: 76px;
            transition: background .5s ease, box-shadow .5s ease;
        }
        .cd-box.live {
            background: linear-gradient(to right, #1a3a8f 0%, #2563eb 100%);
            box-shadow: 0 6px 20px rgba(37,99,235,.4);
        }
        .cd-box.live .cd-unit { color: #bfdbfe; }
        .cd-num {
            font-size: 42px; font-weight: 900; color: #fff;
            display: block; line-height: 1;
            text-shadow: 0 2px 6px rgba(0,0,0,.2);
        }
        .cd-unit { font-size: 11px; font-weight: 800; color: var(--blue); letter-spacing: 1px; text-transform: uppercase; margin-top: 8px; background: none; }

        .hero-right img {
            max-width: 100%; max-height: 480px; object-fit: contain;
            filter: drop-shadow(0 12px 40px rgba(0,0,0,.15));
            animation: floatHero 4s ease-in-out infinite;
        }
        @keyframes floatHero { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }

        /* ── Section common ── */
        .section { padding: 72px 40px; }
        .section-inner { max-width: 1140px; margin: 0 auto; }
        .section-tag {
            display: inline-block; font-size: 11px; font-weight: 800;
            letter-spacing: 2px; text-transform: uppercase; color: var(--yellow);
            background: rgba(245,196,0,.12); border-radius: 20px;
            padding: 4px 14px; margin-bottom: 12px;
        }
        .section-title { font-size: clamp(34px, 4vw, 52px); font-weight: 900; color: var(--text); margin-bottom: 10px; font-family: 'Poppins', sans-serif; }
        #leaders .section-title { color: #f5c400; }
        .section-sub { font-size: 14px; color: var(--sub); line-height: 1.7; max-width: 620px; }

        /* ── Leaders / Parties ── */
        .leaders-header { text-align: center; margin-bottom: 52px; }
        .leaders-header .section-sub { margin: 0 auto; }

        .party-section { margin-bottom: 32px; max-width: 1670px; margin-left: auto; margin-right: auto; }
        .party-card {
            border-radius: 20px;
            display: flex; align-items: stretch;
            height: 260px;
        }
        .party-card.reverse { flex-direction: row-reverse; }
        .party-photo-col {
            flex: 0 0 480px; width: 480px; height: 260px;
            position: relative; overflow: hidden;
            border-radius: 10px;
            box-shadow: 0px 4px 4px 0px #00000040;
        }
        .party-photo-col img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .4s;
        }
        .party-card:hover .party-photo-col img { transform: scale(1.04); }
        .party-info-col {
            flex: 1; padding: 40px 52px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .party-tags { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .party-tag {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 700;
            border: 1.5px solid #1a2744; border-radius: 999px;
            padding: 6px 16px; color: #1a2744; background: transparent;
            letter-spacing: .5px;
        }
        .party-tag .dot { width: 10px; height: 10px; border-radius: 50%; background: #1a2744; flex-shrink: 0; }
        .party-name { font-size: clamp(32px, 3.8vw, 52px); font-weight: 900; line-height: 1.3; margin-bottom: 18px; color: var(--yellow); letter-spacing: .4px; }
        .party-desc { font-size: 15px; font-weight: 400; line-height: 2; margin-bottom: 32px; color: #1a2744; letter-spacing: .35px; }
        .party-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 10px; font-weight: 800; text-decoration: none; color: #1a2744;
            padding: 13px 28px; border-radius: 999px; border: 2px solid #1a2744;
            background: transparent; transition: background .2s, color .2s, transform .15s; width: fit-content;
            min-height: 48px; touch-action: manipulation; -webkit-tap-highlight-color: transparent;
            letter-spacing: 0.5px; text-transform: uppercase;
        }
        .party-link:hover { background: #1a2744; color: #fff; transform: translateY(-1px); }
        .party-link:active { transform: scale(.97); }

        /* All themes — no background */
        .theme-blue .party-info-col,
        .theme-purple .party-info-col,
        .theme-navy .party-info-col,
        .theme-yellow .party-info-col { background: none; }


        /* No-party fallback */
        .no-parties {
            text-align: center; padding: 48px 24px;
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .no-parties p { color: var(--sub); font-size: 14px; margin-top: 8px; }

        /* ── Live Tally Section ── */
        .live-tally-section {
            background: #f2f3f5;
            padding: 64px 48px;
        }
        .live-tally-header { text-align: center; margin-bottom: 40px; }
        .live-tally-label {
            display: inline-block; font-size: 13px; font-weight: 800;
            letter-spacing: 2px; text-transform: uppercase; color: var(--yellow);
            margin-bottom: 10px;
        }
        .live-tally-title {
            font-size: 32px; font-weight: 900; color: var(--yellow); line-height: 1.1;
            font-style: italic;
        }
        .tally-positions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .tally-pos-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
        }
        .tally-pos-card.full-width { grid-column: 1 / -1; }
        .tally-pos-title {
            font-size: 15px; font-weight: 900; color: #0d1b3e;
            margin-bottom: 6px; padding-bottom: 0;
        }
        .tally-college-badge {
            display: inline-block;
            font-size: 10px; font-weight: 800; letter-spacing: .8px;
            text-transform: uppercase; color: #1a3a8f;
            background: #eef2ff; border-radius: 20px;
            padding: 3px 10px; margin-bottom: 16px;
            max-width: 100%; word-break: break-word; white-space: normal;
        }
        .tally-pos-divider { border: none; border-top: 2px solid #f0f1f5; margin-bottom: 16px; }
        .tally-candidate-row {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 18px;
        }
        .tally-candidate-row:last-child { margin-bottom: 0; }
        .tally-cand-photo {
            width: 46px; height: 46px; border-radius: 50%;
            object-fit: cover; object-position: top;
            background: #e5e7eb; flex-shrink: 0;
            border: 2px solid #e5e7eb;
        }
        .tally-cand-photo-placeholder {
            width: 46px; height: 46px; border-radius: 50%;
            background: #e5e7eb; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #9ca3af;
        }
        .tally-cand-info { flex: 1; min-width: 0; }
        .tally-cand-name-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2px; gap: 8px;
        }
        .tally-cand-name {
            font-size: 13px; font-weight: 800; color: #111827;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tally-party-dot {
            width: 10px; height: 10px; border-radius: 50%;
            flex-shrink: 0; display: inline-block;
        }
        .tally-party-tag {
            font-size: 10px; font-weight: 700; color: #6b7280;
            margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tally-vote-pct {
            font-size: 12px; font-weight: 700; color: #1a3a8f;
            white-space: nowrap; flex-shrink: 0;
        }
        .tally-bar-track {
            height: 9px; background: #e5e7eb; border-radius: 4px; overflow: hidden;
        }
        .tally-bar-fill {
            height: 100%; border-radius: 4px;
            background: #1a3a8f;
            transition: width .6s ease;
        }
        .tally-empty {
            text-align: center; padding: 40px 20px;
            color: #9ca3af; font-size: 14px; font-weight: 600;
        }
        .tally-empty-icon { font-size: 36px; margin-bottom: 10px; opacity: .4; }
        @media (max-width: 768px) {
            .live-tally-section { padding: 48px 20px; }
            .tally-positions-grid { grid-template-columns: 1fr; }
            .tally-pos-card.full-width { grid-column: auto; }
        }

        /* ── Footer ── */
        .footer {
            background: #fff; border-top: 1px solid #e5e8f0;
            padding: 24px 48px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .footer-left { display: flex; align-items: center; gap: 12px; }
        .footer-left img { width: 56px; height: auto; object-fit: contain; }
        .footer-brand { font-size: 13px; font-weight: 700; color: var(--text); }
        .footer-brand span { color: var(--yellow); }
        .footer-links { display: flex; gap: 20px; flex-wrap: wrap; }
        .footer-links a { font-size: 12px; color: var(--sub); text-decoration: none; transition: color .2s; cursor: pointer; }
        .footer-links a:hover { color: var(--blue); }
        .footer-links span { font-size: 12px; color: #b0b0b0; cursor: default; }
        .footer-copy { font-size: 11.5px; color: #9ca3af; width: 100%; padding-top: 10px; border-top: 1px solid #f0f0f0; margin-top: 8px; }

        /* ── Responsive ── */
        /* ── Tablet ≤ 1024px ── */
        @media (max-width: 1024px) {
            .hero {
                grid-template-columns: 1fr 320px;
                gap: 32px; padding: 48px 32px;
            }
            .hero-title { font-size: 46px; line-height: 1.15; }
            .hero-right img { max-height: 380px; }
            .party-section { max-width: 100%; }
            .party-photo-col { flex: 0 0 420px; width: 420px; }
        }

        /* ── Mobile navbar hamburger ── */
        .nav-toggle {
            display: none; flex-direction: column; gap: 5px;
            background: none; border: none; cursor: pointer; padding: 6px;
        }
        .nav-toggle span {
            display: block; width: 24px; height: 2px;
            background: var(--blue); border-radius: 2px;
            transition: transform .3s, opacity .3s;
        }
        .nav-open .nav-toggle span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .nav-open .nav-toggle span:nth-child(2) { opacity: 0; }
        .nav-open .nav-toggle span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* ── Scroll-reveal ── */
        .reveal {
            opacity: 0; transform: translateY(28px);
            transition: opacity .55s ease, transform .55s ease;
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ── Section fade ── */
        .section-fadein {
            animation: sectionFade .35s ease both;
        }
        @keyframes sectionFade { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

        /* ── Touch & interaction polish ── */
        a, button { touch-action: manipulation; -webkit-tap-highlight-color: transparent; }
        :focus-visible { outline: 2px solid var(--yellow); outline-offset: 3px; border-radius: 4px; }

        /* ── Floating Vote Button (mobile only) ── */
        .fab-vote {
            display: none;
        }
        @media (max-width: 768px) {
            .fab-vote {
                display: flex;
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(90px);
                z-index: 999;
                align-items: center;
                gap: 8px;
                background: var(--blue);
                color: #fff;
                font-family: 'Poppins', sans-serif;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                text-decoration: none;
                padding: 0 28px;
                height: 48px;
                border-radius: 999px;
                box-shadow: 0 8px 28px rgba(26,58,143,.38), 0 2px 8px rgba(0,0,0,.12);
                opacity: 0;
                transition: transform .38s cubic-bezier(.34,1.56,.64,1), opacity .28s ease, background .2s;
                white-space: nowrap;
                will-change: transform, opacity;
            }
            .fab-vote.fab-visible {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            .fab-vote:active {
                background: #0d2a6e;
                transform: translateX(-50%) translateY(0) scale(.96);
            }
            .fab-vote svg {
                flex-shrink: 0;
            }
        }

        /* ── Mobile ≤ 768px ── */
        @media (max-width: 768px) {
            /* Navbar */
            .navbar {
                padding: 0 20px; position: sticky; height: auto;
                min-height: 60px; flex-wrap: wrap;
            }
            .nav-toggle {
                display: flex; padding: 10px 8px; margin: -10px -8px;
            }
            .navbar-links {
                display: none; flex-direction: column; gap: 0;
                width: 100%; padding: 4px 0 12px;
                border-top: 1px solid #eee;
            }
            .navbar-links.open { display: flex; }
            .navbar-links li a {
                display: flex; align-items: center;
                padding: 14px 8px; font-size: 15px; font-weight: 700;
                border-bottom: 1px solid #f5f5f5; min-height: 52px;
            }
            .navbar-links li:last-child a { border-bottom: none; }

            /* Hero wrapper — override inline padding */
            section.hero-wrapper {
                padding-top: 32px !important;
                padding-bottom: 24px !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            /* Hero grid → flex column */
            .hero {
                display: flex; flex-direction: column;
                padding: 20px 20px 8px; gap: 20px; text-align: center;
            }
            .hero-left { order: 1; display: flex; flex-direction: column; align-items: center; }
            .hero-right { order: 2; flex: none; width: 100%; display: flex; justify-content: center; }
            .hero-countdown { order: 3; padding-top: 0; width: 100%; }
            .hero-logo-row { justify-content: center; margin-bottom: 14px; }
            .hero-logo { width: 58px; height: 58px; }
            .hero-logo img { width: 50px; height: 50px; }
            .hero-logo-label { font-size: 11px; }
            .hero-right img { max-height: 200px; width: auto; }
            .hero-title { font-size: clamp(26px, 7.5vw, 36px); line-height: 1.15; margin-bottom: 12px; }
            .hero-desc { font-size: 13.5px; text-align: center; max-width: 100%; line-height: 1.6; margin-bottom: 20px; }
            .hero-actions { flex-direction: column; align-items: center; width: 100%; gap: 12px; margin-bottom: 0; }
            .btn-vote { width: 160px; height: 42px; font-size: 10px; }
            .btn-results { width: 160px; height: 42px; font-size: 10px; }

            /* Countdown */
            .countdown-label { text-align: center; font-size: 17px; margin-bottom: 12px; }
            .countdown { justify-content: center; gap: 8px; flex-wrap: nowrap; }
            .cd-box { min-width: 0; flex: 1; max-width: 72px; padding: 12px 6px 10px; border-radius: 12px; }
            .cd-num { font-size: 26px; }
            .cd-unit { font-size: 9px; margin-top: 6px; }

            /* Sections */
            .section { padding: 48px 20px; }
            .section-title { font-size: clamp(22px, 6vw, 30px); }
            .section-sub { font-size: 14px; width: auto !important; }

            /* Leaders header */
            .leaders-header { margin-bottom: 32px; text-align: center; }

            /* Party cards */
            .party-section { max-width: 100%; margin-bottom: 24px; }
            .party-card,
            .party-card.reverse { flex-direction: column; border-radius: 16px; height: auto; }
            .party-photo-col {
                flex: none; width: 100%; aspect-ratio: 16/9; height: auto;
                border-radius: 16px 16px 0 0;
            }
            .party-info-col { padding: 26px 20px 28px; }
            .party-name { font-size: clamp(24px, 7vw, 36px); margin-bottom: 10px; }
            .party-desc { font-size: 14px; line-height: 1.7; margin-bottom: 22px; }
            .party-link { width: 100%; justify-content: center; }
            .party-tags { margin-bottom: 12px; }

            /* Live tally */
            .live-tally-section { padding: 48px 16px; box-sizing: border-box; width: 100%; }
            .live-tally-title { font-size: 24px; }
            .tally-positions-grid { grid-template-columns: 1fr; gap: 14px; width: 100%; }
            .tally-pos-card.full-width { grid-column: auto; }
            .tally-pos-card { padding: 18px 16px; border-radius: 14px; box-sizing: border-box; width: 100%; overflow: hidden; }
            .tally-candidate-row { gap: 10px; }
            .tally-cand-photo,
            .tally-cand-photo-placeholder { width: 38px; height: 38px; flex-shrink: 0; }
            .tally-cand-info { min-width: 0; overflow: hidden; }
            .tally-cand-name { font-size: 12px; }
            .tally-vote-pct { font-size: 11px; }
            .tally-college-badge { font-size: 9px; letter-spacing: .4px; padding: 3px 8px; }
            .tally-cand-name-row { gap: 4px; }
            .live-tally-header { padding: 0 4px; }

            /* Contact section */
            #contact { padding: 56px 20px !important; }
            #contact > div { padding: 0; width: 100%; box-sizing: border-box; }
            #contact a {
                display: flex !important;
                width: 100% !important;
                box-sizing: border-box !important;
                padding: 18px 20px !important;
                justify-content: center;
            }
            #contact a span:last-child { text-align: left; min-width: 0; overflow: hidden; }
            #contact a span:last-child span:last-child {
                font-size: 13px !important;
                word-break: break-all;
                white-space: normal !important;
            }

            /* Footer */
            .footer { flex-direction: column; align-items: flex-start; padding: 24px 16px; gap: 16px; width: 100%; box-sizing: border-box; }
            .footer-left { max-width: 100%; }
            .footer-brand { font-size: 12px; word-break: break-word; }
            .footer-links { gap: 12px 16px; flex-wrap: wrap; max-width: 100%; }
            .footer-links a { font-size: 13px; min-height: 36px; display: inline-flex; align-items: center; }
            .footer-copy { font-size: 10.5px; word-break: break-word; }
        }

        /* ── Small phones ≤ 480px ── */
        @media (max-width: 480px) {
            .hero { padding: 16px 16px 8px; gap: 18px; }
            .hero-title { font-size: clamp(24px, 8vw, 30px); }
            .hero-right img { max-height: 180px; }
            .countdown { gap: 6px; }
            .cd-box { padding: 10px 4px 8px; }
            .cd-num { font-size: 22px; }
            .party-info-col { padding: 22px 16px 24px; }
            .party-name { font-size: clamp(22px, 7.5vw, 30px); }
        }

        /* ── Very small phones ≤ 380px ── */
        @media (max-width: 380px) {
            .navbar { padding: 0 14px; }
            .navbar-brand { font-size: 15px; }
            .hero { padding: 12px 14px 4px; }
            .hero-title { font-size: clamp(22px, 8.5vw, 28px); }
            .cd-box { max-width: 60px; }
            .cd-num { font-size: 20px; }
            .cd-unit { font-size: 8px; letter-spacing: 0; }
            .section { padding: 40px 14px; }
            .live-tally-section { padding: 40px 12px; }
            .tally-pos-card { padding: 16px 13px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar" id="navbar">
    <a href="/" class="navbar-brand">E-Ballot</a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <span></span><span></span><span></span>
    </button>
    <ul class="navbar-links" id="navLinks">
        <li><a href="/" class="nav-active">Candidates</a></li>
        <li><a href="/contact.php">Contact</a></li>
        <li><a href="/tally.php" <?= $electionIsLive ? 'style="color:var(--yellow);display:inline-flex;align-items:center;gap:5px;"' : '' ?>>
            <?php if ($electionIsLive): ?><span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;animation:lbpulse 1.2s ease-in-out infinite;flex-shrink:0;"></span><?php endif; ?>
            Tally</a></li>
        <li><a href="<?= !empty($_SESSION['logged_in']) ? '/dashboard.php' : '/login.php' ?>">Profile</a></li>
        <?php if (!empty($_SESSION['logged_in'])): ?>
        <li><a href="/logout.php">Sign Out</a></li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Hero -->
<section class="section hero-wrapper" style="padding-top:64px;padding-bottom:48px;">
    <div class="hero" style="padding:0;">
        <div class="hero-left">
            <div class="hero-logo-row">
                <div class="hero-logo">
                    <img src="/Presets/jrmsu-logo.png" alt="JRMSU Logo"/>
                </div>
                <span class="hero-logo-label">JRMSU &middot; SSG Election Portal</span>
            </div>
            <h1 class="hero-title">
                Jose Rizal Memorial<br>
                State University<br>
                <span>E-Ballot Portal</span>
            </h1>
            <p class="hero-desc">
                The Jose Rizal Memorial State University E-Ballot Portal is a secure, streamlined
                digital platform designed to modernize the university's student government elections.
            </p>
            <div class="hero-actions">
                <?php if ($electionIsLive): ?>
                <a href="/login.php" class="btn-vote">Vote Now &rarr;</a>
                <?php endif; ?>
                <?php if ($electionIsLive || $electionEnded): ?>
                <a href="/tally.php" class="btn-results">
                    <?php if ($electionIsLive): ?><span class="live-indicator"></span><?php endif; ?>
                    View Results &rarr;
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-right">
            <img src="/Presets/login-hero-real.png" alt="Election Portal Illustration" loading="eager" decoding="async"/>
        </div>
        <div class="hero-countdown">
            <div class="countdown-label" id="countdownLabel">Coming Soon</div>
            <div class="countdown" id="countdown">
                <div class="cd-box" id="cd-box-days"><span class="cd-num" id="cd-days">00</span><div class="cd-unit">Days</div></div>
                <div class="cd-box" id="cd-box-hours"><span class="cd-num" id="cd-hours">00</span><div class="cd-unit">Hours</div></div>
                <div class="cd-box" id="cd-box-mins"><span class="cd-num" id="cd-mins">00</span><div class="cd-unit">Minutes</div></div>
                <div class="cd-box" id="cd-box-secs"><span class="cd-num" id="cd-secs">00</span><div class="cd-unit">Seconds</div></div>
            </div>
        </div>
    </div>
</section>


<!-- Leaders / Parties -->
<section class="section" id="leaders">
    <div class="section-inner">
        <div class="leaders-header reveal">
            <h2 class="section-title">Meet Your<br>Future Leaders!</h2>
            <p class="section-sub" style="max-width:100%;font-family:'Poppins',sans-serif;font-weight:400;font-size:15px;line-height:29px;letter-spacing:-0.02em;text-align:center;color:#000000;">
                Don't vote in the dark. Dive into candidate profiles, explore their core advocacies,
                and map out their track records. The<br>future of JRMSU starts with the choices you make today.
            </p>
        </div>

        <?php
        $themeColorMap = [
            'theme-blue'   => '#1a3a8f',
            'theme-purple' => '#7c3aed',
            'theme-navy'   => '#0d2a6e',
            'theme-green'  => '#16a34a',
            'theme-red'    => '#dc2626',
            'theme-gold'   => '#f5c400',
        ];
        $ti = 0;
        $galleryData = []; // Store gallery data for JS
        if (!empty($parties)):
            foreach ($parties as $party):
                $pTheme    = $party['theme'] ?? 'theme-blue';
                $pTag      = $party['tag']   ?? 'Party List';
                $pDesc     = $party['description'] ?? '';
                $isReverse = ($ti % 2 === 1) ? 'reverse' : '';
                $dotColor  = $themeColorMap[$pTheme] ?? '#1a3a8f';
                $photoSrc  = '';
                $partyId   = preg_replace('/[^a-z0-9]/i', '_', $party['name']);
                
                // Collect gallery images from candidates
                $galleryImages = [];
                if (!empty($party['candidates'])) {
                    foreach ($party['candidates'] as $cand) {
                        $candId = $cand['Student_ID'] ?? $cand['Candidate_ID'] ?? '';
                        if ($candId && isset($_gallery[$candId])) {
                            $galleryImages = array_merge($galleryImages, $_gallery[$candId]);
                        }
                    }
                }
                
                // Fallback to cover photo or first candidate photo
                if (!empty($party['cover_photo'])) {
                    $photoSrc = 'data:image/jpeg;base64,' . $party['cover_photo'];
                } elseif (!empty($galleryImages)) {
                    $firstImage = $galleryImages[0];
                    $photoSrc = 'data:' . ($firstImage['mime'] ?? 'image/jpeg') . ';base64,' . $firstImage['data'];
                } elseif (!empty($party['candidates'])) {
                    $fc = $party['candidates'][0];
                    $firstPhoto = $fc['Photo'] ?? $fc['photo'] ?? $fc['Photo_Url'] ?? $fc['photo_url'] ?? '';
                    if ($firstPhoto) $photoSrc = $firstPhoto;
                }
                
                // Store gallery data for this party
                if (!empty($galleryImages)) {
                    $galleryData[$partyId] = array_map(function($img) {
                        return 'data:' . ($img['mime'] ?? 'image/jpeg') . ';base64,' . $img['data'];
                    }, $galleryImages);
                }
        ?>
        <div class="party-section reveal">
            <div class="party-card <?= htmlspecialchars($pTheme) ?> <?= $isReverse ?>">
                <div class="party-photo-col" style="background:#c8c8c8;">
                    <?php if ($photoSrc): ?>
                    <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Party Photo" class="party-img" loading="lazy" decoding="async"
                         id="party-img-<?= htmlspecialchars($partyId) ?>"<?php if (!empty($galleryData[$partyId])): ?> data-gallery-id="<?= htmlspecialchars($partyId) ?>"<?php endif; ?>/>
                    <?php endif; ?>
                </div>
                <div class="party-info-col">
                    <div class="party-tags">
                        <span class="party-tag"><span class="dot" style="background:<?= htmlspecialchars($dotColor) ?>;"></span> Color</span>
                        <span class="party-tag"><?= htmlspecialchars($pTag) ?></span>
                    </div>
                    <div class="party-name"><?= htmlspecialchars($party['name']) ?></div>
                    <div class="party-desc"><?= htmlspecialchars($pDesc) ?: "Don't vote in the dark! Tap into candidate platforms, track records, and concrete goals to shape our university's future." ?></div>
                    <a href="#" onclick="openPartyModal(<?= $ti ?>);return false;" class="party-link">Meet the team</a>
                </div>
            </div>
        </div>
        <?php $ti++; endforeach;
        else: ?>
        <!-- No parties configured yet -->
        <div style="text-align:center;color:#9ca3af;padding:40px 0;font-size:15px;">
            Party lists will appear here once the admin configures them.
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- Store party data for modal -->
<script>
window.partiesData = [
<?php
$partyIndex = 0;
foreach ($parties as $party) {
    $candidates = $party['candidates'] ?? [];
    // Only store necessary fields to keep JSON small
    $partialCandidates = array_map(function($c) use ($_nameMap, $photoMap, $positionMap, $posIdMap) {
        $cid = trim($c['Student_ID'] ?? $c['student_id'] ?? '');
        $name = $_nameMap[$cid] ?? ($c['Candidate_Name'] ?? $c['name'] ?? 'Unknown');
        $posId = (int)($c['Position_ID'] ?? 0);
        $position = $positionMap[$cid] ?? (
            !empty($c['Position']) ? $c['Position'] :
            (!empty($c['Position_Name']) ? $c['Position_Name'] :
            (!empty($c['position_name']) ? $c['position_name'] :
            (!empty($c['position']) ? $c['position'] :
            ($posId > 0 && isset($posIdMap[$posId]) ? $posIdMap[$posId] : 'Candidate'))))
        );
        return [
            'Candidate_Name' => $name,
            'Student_ID' => $cid,
            'Candidate_ID' => $c['Candidate_ID'] ?? $c['id'] ?? '',
            'Photo' => $photoMap[$cid] ?? ($c['Photo'] ?? $c['photo'] ?? $c['Photo_Url'] ?? $c['photo_url'] ?? ''),
            'Program' => $c['Program'] ?? $c['program'] ?? '',
            'Candidate_Nickname' => $c['Candidate_Nickname'] ?? $c['nickname'] ?? '',
            'Position' => $position,
        ];
    }, $candidates);
    ?>
    {
        name: <?php echo json_encode($party['name'] ?? 'Unknown'); ?>,
        tag: <?php echo json_encode($party['tag'] ?? 'Party List'); ?>,
        description: <?php echo json_encode($party['description'] ?? ''); ?>,
        candidates: <?php echo json_encode($partialCandidates); ?>
    }<?php echo ($partyIndex < count($parties) - 1) ? ',' : ''; ?>
<?php
    $partyIndex++;
}
?>
];
</script>


<!-- Tally moved to tally.php | Contact moved to contact.php -->

<!-- Footer -->
<footer class="footer">
    <div class="footer-left">
        <img src="/Presets/ccs-logo.png" alt="CCS-Creatives Logo"/>
        <div>
            <div class="footer-brand"><a href="#" onclick="openTeamModal();return false;" style="color:#f5c400;text-decoration:none;border-bottom:1px solid #f5c400;">CCS-Creatives Society</a></div>
        </div>
    </div>
    <div class="footer-links">
        <span>Security Policy</span>
        <span>Terms of Service</span>
        <span>Accessibility</span>
        <a href="/contact.php">Contact Support</a>
    </div>
    <div class="footer-copy">&copy; <?= date('Y') ?> CCS-Creatives Society - All rights reserved</div>
</footer>

<script>
// Hamburger nav toggle
var navToggle = document.getElementById('navToggle');
var navLinks  = document.getElementById('navLinks');
var navbar    = document.getElementById('navbar');
navToggle.addEventListener('click', function() {
    navbar.classList.toggle('nav-open');
    navLinks.classList.toggle('open');
});
function closeNav() {
    navbar.classList.remove('nav-open');
    navLinks.classList.remove('open');
}
document.addEventListener('click', function(e) {
    if (navbar.classList.contains('nav-open') && !navbar.contains(e.target)) {
        closeNav();
    }
});

// ── Scroll-reveal ──────────────────────────────────────────────────────────
(function() {
    function revealAll() {
        document.querySelectorAll('.reveal').forEach(function(el) {
            el.classList.add('visible');
        });
    }
    if (!('IntersectionObserver' in window)) { revealAll(); return; }
    var io = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0, rootMargin: '0px 0px 0px 0px' });
    document.querySelectorAll('.reveal').forEach(function(el) { io.observe(el); });
    // Fallback: ensure all reveal elements are visible after 800ms
    setTimeout(revealAll, 800);
})();


// Countdown — target is Unix timestamp (seconds) from admin election schedule
(function () {
    var target  = <?= $electionTimestamp ?> * 1000;
    var endTime = <?= $_electionEnd ?>     * 1000;

    function pad(n) { return String(n).padStart(2, '0'); }

    function fmtTime(ms) {
        if (!ms) return '';
        var d = new Date(ms);
        var h = d.getHours(), mn = d.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return h + (mn ? ':' + pad(mn) : '') + ' ' + ampm + ', ' + months[d.getMonth()] + ' ' + d.getDate();
    }

    var boxes    = ['cd-box-days','cd-box-hours','cd-box-mins','cd-box-secs'].map(function(id){ return document.getElementById(id); });
    var label    = document.getElementById('countdownLabel');
    var curState = '';  // 'before' | 'live' | 'closed'

    function applyState(state) {
        if (curState === state) return;
        curState = state;
        if (state === 'live') {
            if (label) label.textContent = 'Voting is Open!';
            boxes.forEach(function(b){ if (b) { b.classList.add('live'); b.classList.remove('closed'); }});
        } else if (state === 'closed') {
            if (label) label.textContent = 'Voting Closed';
            boxes.forEach(function(b){ if (b) { b.classList.remove('live'); b.classList.remove('closed'); }});
        } else {
            if (label) label.textContent = 'Coming Soon';
            boxes.forEach(function(b){ if (b) { b.classList.remove('live'); b.classList.remove('closed'); }});
        }
    }

    function setNums(diff) {
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000)  / 60000);
        var s = Math.floor((diff % 60000)    / 1000);
        document.getElementById('cd-days').textContent  = pad(d);
        document.getElementById('cd-hours').textContent = pad(h);
        document.getElementById('cd-mins').textContent  = pad(m);
        document.getElementById('cd-secs').textContent  = pad(s);
    }

    function setZeros() {
        ['cd-days','cd-hours','cd-mins','cd-secs'].forEach(function(id){
            document.getElementById(id).textContent = '00';
        });
    }

    function tick() {
        var now    = Date.now();
        var live   = target && endTime && now >= target && now <= endTime;
        var closed = endTime && now > endTime;

        if (closed) {
            applyState('closed');
            setZeros();
            return;
        }
        if (live) {
            applyState('live');
            setNums(endTime - now);   // count down to close
            return;
        }
        // Before — count down to open
        applyState('before');
        var diff = target - now;
        if (diff <= 0) { setZeros(); return; }
        setNums(diff);
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<!-- Party Modal -->
<style>
.party-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.party-modal-overlay.open { 
    display: flex; 
    animation: modalFade .2s ease; 
}
@keyframes modalFade { 
    from { opacity: 0 } 
    to { opacity: 1 } 
}
.party-modal {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 720px;
    max-height: 88vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 32px 80px rgba(0,0,0,.28);
    animation: modalSlide .22s ease;
}
@keyframes modalSlide { 
    from { opacity: 0; transform: translateY(20px) } 
    to { opacity: 1; transform: translateY(0) } 
}
.party-modal-header {
    background: linear-gradient(135deg, #0d1b3e 0%, #1a3a8f 100%);
    padding: 28px 32px 22px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
}
.party-modal-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 900;
    color: #fff;
    letter-spacing: -.3px;
}
.party-modal-header p {
    margin: 0;
    font-size: 13px;
    color: rgba(255,255,255,.65);
    font-weight: 500;
}
.party-modal-close {
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
}
.party-modal-close:hover { background: rgba(255,255,255,.28); }
.party-modal-body {
    overflow-y: auto;
    padding: 28px 32px 32px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.party-modal-desc {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}
.party-candidates-label {
    font-size: 11px;
    font-weight: 800;
    color: #1a3a8f;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-top: 12px;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}
.party-candidates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.party-candidate-card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px;
    text-align: center;
    background: #fff;
    transition: all .2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 280px;
}
.party-candidate-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    border-color: #d1d5db;
}
.party-candidate-photo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    margin: 0 auto 12px;
    background: linear-gradient(135deg, #f0f0f0 0%, #e5e5e5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px solid #e5e7eb;
    flex-shrink: 0;
}
.party-candidate-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.party-candidate-photo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: #9ca3af;
}
.party-candidate-photo-placeholder svg {
    width: 48px;
    height: 48px;
    margin-bottom: 2px;
    opacity: .6;
}
.party-candidate-name {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1.3;
}
.party-candidate-info {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
    margin-bottom: 2px;
}
.party-candidate-position {
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    letter-spacing: .08em;
    text-transform: uppercase;
    background: #1a3a8f;
    margin-left: -14px;
    margin-right: -14px;
    margin-bottom: -14px;
    padding-left: 14px;
    padding-right: 14px;
    padding-bottom: 10px;
    border-radius: 0 0 14px 14px;
}
@media (max-width: 520px) {
    .party-modal-header { padding: 20px 20px 16px; }
    .party-modal-body { padding: 20px 20px 24px; gap: 14px; }
    .party-candidates-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}
</style>

<div class="party-modal-overlay" id="partyModal" onclick="if(event.target===this)closePartyModal()">
    <div class="party-modal">
        <div class="party-modal-header">
            <div>
                <h2 id="partyModalTitle">Party Name</h2>
                <p id="partyModalAbout">About the party</p>
            </div>
            <button class="party-modal-close" onclick="closePartyModal()" aria-label="Close">&times;</button>
        </div>
        <div class="party-modal-body">
            <p class="party-modal-desc" id="partyModalDesc"></p>
            <div class="party-candidates-label">Candidates</div>
            <div class="party-candidates-grid" id="partyCandidatesGrid"></div>
        </div>
    </div>
</div>

<script>
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

const positionOrder = {
    'PRESIDENT': 0,
    'VICE-PRESIDENT': 1,
    'VICE PRESIDENT': 1,
    'GOVERNOR': 2,
    'VICE-GOVERNOR': 3,
    'VICE GOVERNOR': 3,
    'REPRESENTATIVE': 4,
};

function getPositionRank(posText) {
    if (!posText) return 999;
    const normalized = posText.trim().toUpperCase();
    return positionOrder[normalized] !== undefined ? positionOrder[normalized] : 999;
}

function openPartyModal(partyIndex) {
    const partyData = window.partiesData[partyIndex];
    if (!partyData) return;
    
    const modal = document.getElementById('partyModal');
    const title = document.getElementById('partyModalTitle');
    const about = document.getElementById('partyModalAbout');
    const desc = document.getElementById('partyModalDesc');
    const grid = document.getElementById('partyCandidatesGrid');
    
    title.textContent = partyData.name || 'Party List';
    about.textContent = partyData.tag || 'Party Candidates';
    desc.textContent = partyData.description || 'Meet the candidates from this party list.';
    
    grid.innerHTML = '';
    let candidates = partyData.candidates || [];
    
    if (candidates.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; padding: 20px; text-align: center; color: #9ca3af;">No candidates yet</div>';
    } else {
        candidates = candidates.sort((a, b) => {
            const posA = a.Position || a.position || a.Position_Name || a.position_name || '';
            const posB = b.Position || b.position || b.Position_Name || b.position_name || '';
            return getPositionRank(posA) - getPositionRank(posB);
        });
        
        candidates.forEach(cand => {
            const candName = cand.Candidate_Name || cand.name || 'Unknown';
            const candId = cand.Student_ID || cand.Candidate_ID || '';
            const candPhoto = cand.Photo || cand.photo || cand.Photo_Url || cand.photo_url || '';
            const candProgram = cand.Program || cand.program || '';
            const candNickname = cand.Candidate_Nickname || cand.nickname || '';
            const candPosition = cand.Position || cand.position || cand.Position_Name || cand.position_name || '';
            
            let photoHtml = '';
            if (candPhoto) {
                if (candPhoto.startsWith('data:') || candPhoto.startsWith('/') || candPhoto.startsWith('http')) {
                    photoHtml = `<img src="${candPhoto}" alt="${candName}" />`;
                } else if (candPhoto.startsWith('iVBOR') || candPhoto.length > 100) {
                    photoHtml = `<img src="data:image/png;base64,${candPhoto}" alt="${candName}" />`;
                } else {
                    photoHtml = `<img src="data:image/jpeg;base64,${candPhoto}" alt="${candName}" />`;
                }
            } else {
                photoHtml = `
                    <div class="party-candidate-photo-placeholder">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                `;
            }
            
            const card = document.createElement('div');
            card.className = 'party-candidate-card';
            const positionDisplay = candPosition || 'Candidate';
            card.innerHTML = `
                <div class="party-candidate-photo">${photoHtml}</div>
                <div class="party-candidate-name">${escHtml(candName)}</div>
                ${candNickname ? `<div class="party-candidate-info">"${escHtml(candNickname)}"</div>` : ''}
                ${candProgram ? `<div class="party-candidate-info">${escHtml(candProgram)}</div>` : ''}
                <div class="party-candidate-position">${escHtml(positionDisplay)}</div>
            `;
            grid.appendChild(card);
        });
    }
    
    modal.classList.add('open');
}

function closePartyModal() {
    document.getElementById('partyModal').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePartyModal();
});
</script>

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>

<!-- Floating Vote Button (mobile only, election open only) -->
<?php if ($electionIsLive): ?>
<a href="/login.php" class="fab-vote" id="fabVote" aria-label="Vote Now">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 12l2 2 4-4"/><rect x="3" y="3" width="18" height="18" rx="3"/>
    </svg>
    Vote Now
</a>
<?php endif; ?>

<script>
// ── Floating Vote Button — show after scrolling past hero, hide near footer ─
(function () {
    var fab         = document.getElementById('fabVote');
    var heroSection = document.querySelector('.hero-wrapper');
    var footer      = document.querySelector('.footer');
    if (!fab || !heroSection) return;

    var heroGone   = false;
    var footerNear = false;

    function update() {
        if (heroGone && !footerNear) {
            fab.classList.add('fab-visible');
        } else {
            fab.classList.remove('fab-visible');
        }
    }

    // Watch hero bottom leaving the viewport
    var heroObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            heroGone = !entry.isIntersecting;
            update();
        });
    }, { threshold: 0 });
    heroObs.observe(heroSection);

    // Watch footer entering the viewport — hide FAB 80px before it appears
    if (footer) {
        var footerObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                footerNear = entry.isIntersecting;
                update();
            });
        }, { threshold: 0, rootMargin: '0px 0px -0px 0px' });
        footerObs.observe(footer);
    }
})();

// ── Auto-rotating Gallery for Party Cards ──────────────────────────────────
(function() {
    var galleryData = <?= json_encode($galleryData) ?>;
    var galleries = {};
    
    // Initialize gallery rotators for each party
    Object.keys(galleryData).forEach(function(partyId) {
        var images = galleryData[partyId];
        if (images.length <= 1) return;
        
        galleries[partyId] = {
            images: images,
            currentIndex: 0,
            img: document.getElementById('party-img-' + partyId)
        };
    });
    
    // Rotate gallery images every 5 seconds
    function rotateGalleries() {
        Object.keys(galleries).forEach(function(partyId) {
            var gallery = galleries[partyId];
            if (!gallery.img) return;
            
            gallery.currentIndex = (gallery.currentIndex + 1) % gallery.images.length;
            gallery.img.src = gallery.images[gallery.currentIndex];
        });
    }
    
    // Start rotation if there are galleries
    if (Object.keys(galleries).length > 0) {
        setInterval(rotateGalleries, 5000);
    }
})();
</script>
</body>
</html>
