<?php
require_once __DIR__ . '/includes/bootstrap.php';
$screenMode    = isset($_GET['screen']);
$slideshowMode = isset($_GET['slideshow']);

// Load parties for theme/color mapping
$_partiesFile = DATA_DIR . '/parties.json';
$_partiesJson = file_exists($_partiesFile) ? (json_decode(file_get_contents($_partiesFile), true) ?: []) : [];
$parties = [];
foreach ($_partiesJson as $p) {
    $key = $p['name'] ?? 'Unknown';
    $parties[$key] = ['name' => $p['name'] ?? 'Unknown', 'theme' => $p['theme'] ?? 'theme-blue'];
}

// Load approved candidates
try {
    $response = callModel(function() {
        Candidate::Get_All_Candidates(['Election_Year' => ELECTION_SCHOOL_YEAR, 'Application_Status' => 'APPROVED']);
    });
    if (isset($response['Record']) && is_array($response['Record'])) {
        $candidates = $response['Record'];
    } elseif (is_array($response) && !empty($response) && !isset($response['Status'])) {
        $candidates = $response;
    } else {
        $candidates = [];
    }
} catch (\Throwable $e) {
    $candidates = [];
}

// Get total students + name map
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
    while ($_vStmt->nextRowset()) {}
    $_vStmt->closeCursor();
    if (!empty($candidates)) {
        $_sids = array_unique(array_filter(array_map(fn($c) => trim($c['Student_ID'] ?? $c['student_id'] ?? ''), $candidates)));
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

// Merge manually-entered names as fallback
$_cnFile = DATA_DIR . '/candidate_names.json';
if (file_exists($_cnFile)) {
    $_cnMap = json_decode(file_get_contents($_cnFile), true) ?: [];
    foreach ($_cnMap as $_cnSid => $_cnName) {
        if (!isset($_nameMap[trim($_cnSid)])) {
            $_nameMap[trim($_cnSid)] = $_cnName;
        }
    }
}

// Vote counts
$_voteLookup = [];
try {
    $_tallyRaw  = callModel(function() { Election::election_generate_result(['School_Year' => ELECTION_SCHOOL_YEAR]); });
    $_tallyList = $_tallyRaw['Record'] ?? (is_array($_tallyRaw) && !isset($_tallyRaw['Status']) ? $_tallyRaw : []);
    if (is_array($_tallyList)) {
        foreach ($_tallyList as $_r) {
            $_sid = trim($_r['Student_ID'] ?? $_r['student_id'] ?? '');
            if ($_sid !== '') $_voteLookup[$_sid] = (int)($_r['Vote_Count'] ?? $_r['Votes'] ?? 0);
        }
    }
} catch (\Throwable $e) {}

// Build tally
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
$_themeColorMapTally = [
    'theme-blue' => '#1a3a8f', 'theme-purple' => '#7c3aed',
    'theme-navy' => '#0d2a6e', 'theme-green'  => '#16a34a',
    'theme-red'  => '#dc2626', 'theme-gold'   => '#f5c400',
];

// Election countdown — JSON first, DB fallback
$_sched            = loadElectionSchedule(ELECTION_SCHOOL_YEAR);
$electionTimestamp = $_sched ? (int)($_sched['Time_Start'] ?? 0) : 0;
$_electionEnd      = $_sched ? (int)($_sched['Time_End']   ?? 0) : 0;

$_posIdToCollege = [
    5 => 'CCS', 6 => 'CBA', 7 => 'CTED', 8 => 'CAS', 9 => 'CCJE',
    10 => 'CIT', 11 => 'CTED_HS', 12 => 'CME', 13 => 'COE',
    14 => 'COL', 15 => 'HS', 16 => 'GRAD', 17 => 'SOM', 18 => 'CNAHS',
];
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
$_ccMapTally = [];
$_ccFileTally = DATA_DIR . '/candidate_college.json';
if (file_exists($_ccFileTally)) $_ccMapTally = json_decode(file_get_contents($_ccFileTally), true) ?: [];

// College code detection for the logged-in voter
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

if (!empty($candidates) && is_array($candidates)) {
    foreach ($candidates as $_c) {
        $_sid  = trim($_c['Student_ID'] ?? $_c['student_id'] ?? '');
        $_fn   = $_c['First_Name'] ?? $_c['Firstname'] ?? $_c['first_name'] ?? '';
        $_ln   = $_c['Last_Name']  ?? $_c['Lastname']  ?? $_c['last_name']  ?? '';
        $_rawName = $_c['Candidate_Name'] ?? $_c['Full_Name'] ?? $_c['Name']
                    ?? (trim($_fn . ' ' . $_ln) !== '' ? trim($_fn . ' ' . $_ln) : null);
        $_voterName = $_sid !== '' ? ($_nameMap[$_sid] ?? null) : null;
        $_name = ucwords(strtolower($_voterName ?? $_rawName ?? '—'));
        $_posRaw = $_c['Position_Name'] ?? $_c['Position'] ?? '';
        $_pos    = strtoupper(trim($_posRaw));
        $_pid    = (int)($_c['Position_ID'] ?? 0);
        if ($_pos === '') $_pos = $_pid > 0 ? ($_posIdMap[$_pid] ?? 'GENERAL') : 'GENERAL';
        if (in_array($_pos, ['GOVERNOR', 'VICE-GOVERNOR'])) {
            $_college = $_ccMapTally[$_sid] ?? '';
        } elseif ($_pos === 'REPRESENTATIVE') {
            $_college = $_posIdToCollege[$_pid] ?? '';
        } else {
            $_college = '';
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
        $ai = array_search($a, $positionOrder); $bi = array_search($b, $positionOrder);
        $ai = $ai === false ? 99 : $ai; $bi = $bi === false ? 99 : $bi;
        return $ai - $bi;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $slideshowMode ? 'Slideshow' : ($screenMode ? 'Projector' : 'Tally') ?> &mdash; JRMSU SSG Election Portal</title>
    <link rel="icon" href="/Presets/favicon.png" type="image/x-icon"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Unbounded:wght@800&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --blue:#1a3a8f; --yellow:#f5c400; --light:#f4f4f0; --text:#1a2744; --sub:#555e7a; }
        body { font-family:'Poppins',sans-serif; font-weight:700; letter-spacing:-0.02em; line-height:1.1167; background:var(--light); background-image:radial-gradient(circle,#c8c8c4 1px,transparent 1px); background-size:22px 22px; color:var(--text); min-height:100vh; }

        /* Navbar */
        .navbar { position:sticky; top:0; z-index:200; width:100%; height:58px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.07); display:flex; align-items:center; justify-content:space-between; padding:0 48px; }
        .navbar-brand { font-size:18px; font-weight:800; color:var(--blue); text-decoration:none; }
        .navbar-links { display:flex; gap:32px; list-style:none; }
        .navbar-links a { text-decoration:none; font-size:14px; font-weight:600; color:#444; transition:color .2s; }
        .navbar-links a:hover { color:var(--blue); }
        .navbar-links a.nav-active { color:var(--yellow); font-weight:800; }

        /* ── Hero ── */
        .hero {
            max-width: 1140px; margin: 0 auto;
            padding: 36px 40px 8px;
            display: grid;
            grid-template-columns: 1fr 420px;
            grid-template-rows: auto auto;
            grid-template-areas: "left right" "countdown right";
            column-gap: 56px;
        }
        .hero-left { grid-area: left; min-width: 0; }
        .hero-right { grid-area: right; display: flex; align-items: center; justify-content: center; }
        .hero-countdown { grid-area: countdown; padding-top: 4px; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; }
        .hero-logo-row {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 18px;
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
            color: var(--yellow); margin-bottom: 12px;
        }
        .hero-title span { color: var(--yellow); }
        .hero-desc { font-size: 14.5px; font-weight: 400; color: var(--blue); line-height: 1.7; margin-bottom: 18px; max-width: 480px; }
        .hero-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
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
        @keyframes lbpulse { 0%,100%{opacity:1} 50%{opacity:.3} }
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
            max-width: 100%; max-height: 440px; object-fit: contain;
            filter: drop-shadow(0 12px 40px rgba(0,0,0,.15));
            animation: floatHero 4s ease-in-out infinite;
        }
        @keyframes floatHero { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
        @media (max-width:768px) {
            .navbar { padding:0 16px; height:auto; min-height:56px; flex-wrap:wrap; gap:0; align-content:flex-start; }
            .navbar-brand { order:1; flex-basis:auto; padding:8px 0; align-self:center; }
            .nav-toggle { display:flex; order:2; margin-left:auto; align-self:center; }
            .navbar-links { order:3; display:none; flex-direction:column; gap:0; width:100%; padding:0; margin:0; border-top:1px solid #e5e7eb; }
            .navbar-links.open { display:flex; }
            .navbar-links li { width:100%; }
            .navbar-links li a { display:flex; align-items:center; padding:16px 12px; font-size:14px; font-weight:600; border-bottom:1px solid #f0f0f0; min-height:48px; width:100%; }
            .hero { padding:40px 20px 32px; display:flex; flex-direction:column; align-items:center; justify-content:center; grid:unset; grid-template-columns:unset; grid-template-areas:unset; column-gap:unset; }
            .hero-left { grid-area:unset; width:100%; max-width:600px; }
            .hero-right { grid-area:unset; display:flex; align-items:center; justify-content:center; margin-top:24px; max-height:280px; }
            .hero-right img { max-height:280px; }
            .hero-countdown { grid-area:unset; padding-top:32px; width:100%; }
            .hero-title { font-size:38px; line-height:48px; text-align:center; }
            .hero-logo-row { justify-content:center; margin-bottom:16px; }
            .hero-logo { width:60px; height:60px; }
            .hero-logo img { width:52px; height:52px; }
            .hero-logo-label { font-size:11px; }
            .hero-desc { font-size:13px; margin-bottom:24px; text-align:center; }
            .hero-actions { flex-direction:column; align-items:center; width:100%; gap:12px; margin-bottom:20px; }
            .btn-vote { width:160px; height:42px; font-size:10px; }
            .countdown-label { font-size:18px; margin-bottom:12px; }
            .countdown { gap:8px; flex-direction:row; }
            .cd-box { padding:14px 10px 10px; }
            .cd-num { font-size:28px; }
            .cd-unit { font-size:8px; }
        }
        @media (max-width:480px) {
            .hero { padding:24px 14px 28px; }
            .hero-logo-row { margin-bottom:12px; }
            .hero-logo { width:52px; height:52px; }
            .hero-logo img { width:46px; height:46px; }
            .hero-logo-label { font-size:10px; }
            .hero-title { font-size:32px; line-height:40px; margin-bottom:12px; }
            .hero-desc { font-size:12.5px; margin-bottom:20px; }
            .hero-right img { max-height:240px; }
            .countdown-label { font-size:16px; margin-bottom:10px; }
            .countdown { gap:6px; }
            .cd-box { padding:12px 8px 8px; }
            .cd-num { font-size:24px; }
            .cd-unit { font-size:7px; }
        }

        /* Tally */
        .tally-page { padding:64px 48px; max-width:1200px; margin:0 auto; }
        @media (max-width:768px) {
            .tally-page { padding:40px 20px; }
        }
        @media (max-width:480px) {
            .tally-page { padding:24px 14px; }
        }
        .tally-header { text-align:center; margin-bottom:48px; }
        .tally-label { display:inline-block; font-size:13px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:var(--yellow); margin-bottom:10px; }
        .tally-title { font-size:36px; font-weight:900; color:var(--yellow); line-height:1.1; font-style:italic; }
        .tally-positions-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:24px; }
        .tally-pos-card { background:#f9fafb; border-radius:12px; padding:36px 32px; box-shadow:0 1px 3px rgba(0,0,0,.08); border:1px solid #f0f1f5; }
        .tally-pos-card.full-width { grid-column:1/-1; }
        .tally-pos-title { font-size:18px; font-weight:900; color:#0d1b3e; margin-bottom:24px; letter-spacing:-0.5px; }
        .tally-college-badge { display:inline-block; font-size:10px; font-weight:800; letter-spacing:.8px; text-transform:uppercase; color:#1a3a8f; background:#eef2ff; border-radius:20px; padding:3px 10px; margin-bottom:16px; }
        .tally-pos-divider { border:none; border-top:1.5px solid #e5e7eb; margin-bottom:22px; }
        .tally-candidate-row { display:flex; align-items:flex-start; gap:14px; margin-bottom:26px; }
        .tally-candidate-row:last-child { margin-bottom:0; }
        .tally-cand-photo { width:56px; height:56px; border-radius:50%; object-fit:cover; object-position:top; background:#e5e7eb; flex-shrink:0; border:2px solid #d1d5db; }
        .tally-cand-photo-placeholder { width:56px; height:56px; border-radius:50%; background:#e5e7eb; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:2px solid #d1d5db; }
        .tally-cand-info { flex:1; min-width:0; }
        .tally-cand-name-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px; }
        .tally-cand-name { font-size:15px; font-weight:700; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tally-party-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; display:inline-block; }
        .tally-party-tag { font-size:10px; font-weight:700; color:#6b7280; margin-bottom:5px; }
        .tally-vote-pct { font-size:14px; font-weight:800; color:#1a3a8f; white-space:nowrap; flex-shrink:0; transition: color .2s; }
        .tally-bar-track { height:10px; background:#dbeafe; border-radius:4px; overflow:hidden; }
        .tally-bar-fill { height:100%; border-radius:4px; background:#1a3a8f; transition:width .6s ease; }
        .tally-empty { text-align:center; padding:80px 24px; color:#9ca3af; font-size:14px; font-weight:600; }
        .live-badge-pub { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:800;
                          background:#dcfce7; color:#15803d; padding:4px 12px; border-radius:20px; margin-bottom:6px; }
        .live-dot-pub   { width:8px; height:8px; border-radius:50%; background:#22c55e;
                          animation:lbpulse 1.2s ease-in-out infinite; }
        @keyframes lbpulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        .tally-updated-pub { display:block; font-size:12px; font-weight:600; color:#9ca3af; margin-top:2px; }
        .tally-skeleton-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:24px; }
        .tally-skeleton-card { height:220px; border-radius:16px;
                                background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
                                background-size:400% 100%;
                                animation:skshimmer 1.4s ease infinite; }
        @keyframes skshimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }
        @media(max-width:768px){ .tally-skeleton-grid { grid-template-columns:1fr; } }

        /* ── Vote flash animation ── */
        @keyframes voteFlash {
            0%   { color:inherit; transform:scale(1); }
            15%  { color:#f5c400; transform:scale(1.22); text-shadow:0 0 10px rgba(245,196,0,.9); }
            55%  { color:#f5c400; transform:scale(1.12); text-shadow:0 0 6px rgba(245,196,0,.5); }
            100% { color:inherit; transform:scale(1); text-shadow:none; }
        }
        @keyframes barPop {
            0%   { filter:brightness(1); }
            25%  { filter:brightness(1.6) drop-shadow(0 0 4px rgba(245,196,0,.8)); }
            100% { filter:brightness(1); }
        }
        .vote-flash { animation: voteFlash 1.3s cubic-bezier(.22,1,.36,1) both; }
        .bar-flash  { animation: barPop 1.3s ease both; }

        /* ── College filter pills ── */
        .college-filter-bar {
            display:flex; flex-wrap:wrap; gap:8px; justify-content:center;
            margin-bottom:28px;
        }
        
        /* Mobile: horizontal scroll for better UX */
        @media (max-width: 540px) {
            .college-filter-bar {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding: 0 16px;
                margin-left: -16px;
                margin-right: -16px;
                justify-content: flex-start;
                padding-bottom: 4px;
            }
            
            .college-pill {
                flex-shrink: 0;
                white-space: nowrap;
            }
            
            /* Hide scrollbar but keep scrolling functional */
            .college-filter-bar::-webkit-scrollbar {
                height: 0;
            }
        }
        .college-pill {
            padding:6px 14px; border-radius:20px; font-size:11px; font-weight:800;
            letter-spacing:.4px; border:1.5px solid rgba(255,255,255,.15);
            background:rgba(255,255,255,.07); color:#9ca3af; cursor:pointer;
            transition:background .18s, color .18s, border-color .18s, transform .15s;
            font-family:inherit;
        }
        .college-pill:hover { background:rgba(255,255,255,.13); color:#fff; transform:translateY(-1px); }
        .college-pill.active {
            background:#f5c400; color:#05101f; border-color:#f5c400;
            box-shadow:0 2px 10px rgba(245,196,0,.4);
        }
        /* Light variant for normal tally/projector */
        body:not(.ss-page) .college-pill {
            background:#f1f5f9; color:#555e7a; border-color:#dde4f0;
        }
        body:not(.ss-page) .college-pill:hover { background:#e2e8f0; color:#1a2744; }
        body:not(.ss-page) .college-pill.active {
            background:#1a3a8f; color:#fff; border-color:#1a3a8f;
            box-shadow:0 2px 8px rgba(26,58,143,.3);
        }

        /* Footer */
        .footer { background:#fff; border-top:1px solid #e5e8f0; padding:24px 48px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:64px; }
        .footer-left { display:flex; align-items:center; gap:12px; }
        .footer-left img { width:56px; height:auto; object-fit:contain; }
        .footer-brand { font-size:13px; font-weight:700; color:var(--text); }
        .footer-links { display:flex; gap:20px; flex-wrap:wrap; }
        .footer-links a { font-size:12px; color:var(--sub); text-decoration:none; transition:color .2s; cursor:pointer; }
        .footer-links a:hover { color:var(--blue); }
        .footer-links span { font-size:12px; color:#b0b0b0; cursor:default; }
        .footer-copy { font-size:11.5px; color:#9ca3af; width:100%; padding-top:10px; border-top:1px solid #f0f0f0; margin-top:8px; }

        /* Slideshow / Projector action buttons */
        .btn-slideshow {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border-radius:10px;
            background:#1a3a8f; color:#fff;
            font-size:12px; font-weight:800; letter-spacing:.3px;
            text-decoration:none; border:none; cursor:pointer;
            transition:background .2s, transform .15s;
            margin-top:12px; margin-left:8px;
            font-family:inherit;
        }
        .btn-slideshow:hover { background:#2563eb; transform:translateY(-1px); }
        .btn-projector {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border-radius:10px;
            background:#0d2655; color:#f5c400;
            font-size:12px; font-weight:800; letter-spacing:.3px;
            text-decoration:none; border:none; cursor:pointer;
            transition:background .2s, transform .15s;
            margin-top:12px;
        }
        .btn-projector:hover { background:#1a3a8f; transform:translateY(-1px); }
        .btn-projector svg { flex-shrink:0; }

        /* Screen / Projector Mode */
        body.screen-mode { background:#05101f; }
        body.screen-mode .navbar,
        body.screen-mode .footer { display:none !important; }
        body.screen-mode .tally-page { padding:40px 32px; max-width:100%; }
        body.screen-mode .tally-title { font-size:52px; }
        body.screen-mode .tally-positions-grid { grid-template-columns:repeat(2,1fr); gap:28px; }
        body.screen-mode .tally-pos-card { padding:28px 30px; background:#0d1e36; border:1px solid rgba(255,255,255,.08); }
        body.screen-mode .tally-pos-title { font-size:18px; color:#f1f5f9; }
        body.screen-mode .tally-cand-name { font-size:16px; color:#f1f5f9; }
        body.screen-mode .tally-vote-pct { font-size:15px; color:#93c5fd; }
        body.screen-mode .tally-cand-photo,
        body.screen-mode .tally-cand-photo-placeholder { width:58px; height:58px; }
        body.screen-mode .tally-bar-track { height:12px; background:rgba(255,255,255,.1); }
        body.screen-mode .tally-college-badge { background:rgba(37,99,235,.2); color:#93c5fd; }
        body.screen-mode .tally-pos-divider { border-color:rgba(255,255,255,.08); }
        body.screen-mode .tally-label { color:#f5c400; }
        body.screen-mode .tally-header { color:#fff; }
        .screen-exit-bar {
            position:fixed; bottom:0; left:0; right:0; z-index:999;
            background:rgba(5,16,31,.92); backdrop-filter:blur(8px);
            padding:12px 24px;
            display:flex; align-items:center; justify-content:space-between;
            font-size:12px; font-weight:700; color:#9ca3af;
        }
        body:not(.screen-mode) .screen-exit-bar { display:none !important; }
        .screen-exit-bar a { color:#f5c400; text-decoration:none; font-size:12px; font-weight:800; }

        /* ── Standalone Slideshow Page ── */
        body.ss-page {
            background:#05101f; display:flex; flex-direction:column;
            height:100vh; overflow:hidden;
        }
        .ssp-topbar {
            display:flex; align-items:center; justify-content:space-between;
            padding:12px 20px 8px; flex-shrink:0;
        }
        .ssp-brand {
            font-size:13px; font-weight:800; color:#f5c400; letter-spacing:.5px;
        }
        .ssp-exit {
            display:inline-flex; align-items:center; gap:6px;
            font-size:11px; font-weight:700; color:#6b7280;
            text-decoration:none; padding:5px 10px; border-radius:8px;
            border:1px solid rgba(255,255,255,.1);
            transition:color .15s, border-color .15s;
        }
        .ssp-exit:hover { color:#fff; border-color:rgba(255,255,255,.25); }
        .ssp-filter-wrap {
            padding:0 20px 10px; flex-shrink:0;
        }

        /* ── Shared slideshow stage ── */
        .ss-stage {
            flex:1; display:flex; align-items:stretch; justify-content:center;
            padding:12px 80px 16px; overflow:hidden; position:relative;
        }
        .ss-slide {
            display:none; flex-direction:column; align-items:center;
            width:100%; max-width:900px;
        }
        .ss-slide.active { display:flex; height:100%; animation: ssFadeIn .35s ease; }
        /* Position header — pinned to top of each slide */
        .ss-pos-header { flex-shrink:0; text-align:center; width:100%; padding-bottom:16px; }
        @keyframes ssFadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

        .ss-pos-label {
            font-size:13px; font-weight:800; letter-spacing:2px; text-transform:uppercase;
            color:#f5c400; margin-bottom:8px;
        }
        .ss-pos-title {
            font-size:62px; font-weight:900; color:#fff; margin-bottom:8px; text-align:center;
            line-height:1.05;
        }
        .ss-college-badge {
            font-size:18px; font-weight:700; color:#93c5fd; background:rgba(37,99,235,.2);
            border-radius:20px; padding:4px 20px; margin-bottom:0;
        }
        .ss-page-label {
            display:inline-block; margin-top:10px;
            font-size:13px; font-weight:800; letter-spacing:.6px;
            color:#05101f; background:#f5c400;
            border-radius:999px; padding:4px 16px;
        }
        .ss-candidates { flex:1; display:flex; flex-direction:column; gap:8px; width:100%; justify-content:center; min-height:0; overflow:hidden; }
        .ss-cand {
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
            border-radius:14px; padding:12px 18px;
            display:flex; flex-direction:column; gap:6px;
            transition:border-color .3s; flex-shrink:1; min-height:0;
        }
        .ss-cand.leader { border-color:#f5c400; background:rgba(245,196,0,.06); }
        .ss-cand-top { display:flex; align-items:center; gap:14px; }
        .ss-cand-photo {
            width:88px; height:88px; border-radius:50%; object-fit:cover; object-position:top;
            background:#1e2d4a; border:2px solid rgba(255,255,255,.15); flex-shrink:0;
        }
        .ss-cand-photo-ph {
            width:88px; height:88px; border-radius:50%; background:#1e2d4a;
            border:2px solid rgba(255,255,255,.15); flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
        }
        .ss-cand-info { flex:1; min-width:0; }
        .ss-cand-name {
            font-size:28px; font-weight:800; color:#f1f5f9; line-height:1.2;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .ss-party-row { display:flex; align-items:center; gap:6px; margin-top:4px; }
        .ss-party-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .ss-party-name { font-size:17px; font-weight:600; color:#6b7280; }
        .ss-cand-right { display:flex; flex-direction:column; align-items:flex-end; flex-shrink:0; }
        .ss-votes { font-size:42px; font-weight:900; color:#fff; line-height:1; transition:color .2s; }
        .ss-votes-label { font-size:14px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.8px; margin-top:3px; }
        .ss-bar-track { width:100%; height:12px; background:rgba(255,255,255,.1); border-radius:4px; overflow:hidden; }
        .ss-bar-fill { height:100%; border-radius:4px; transition:width .7s ease; }
        .ss-leader-crown { font-size:20px; margin-right:6px; }

        /* ss vote flash — applied to .ss-votes */
        @keyframes ssVoteFlash {
            0%   { color:#fff; transform:scale(1); }
            20%  { color:#f5c400; transform:scale(1.2); text-shadow:0 0 18px rgba(245,196,0,1); }
            60%  { color:#f5c400; transform:scale(1.1); text-shadow:0 0 10px rgba(245,196,0,.6); }
            100% { color:#fff; transform:scale(1); text-shadow:none; }
        }
        .ss-vote-flash { animation: ssVoteFlash 1.4s cubic-bezier(.22,1,.36,1) both; }

        /* ss ascending / descending direction animations (slideshow only) */
        @keyframes ssVoteUp {
            0%   { color:#fff; transform:scale(1); }
            20%  { color:#4ade80; transform:scale(1.25); text-shadow:0 0 20px rgba(74,222,128,1); }
            60%  { color:#4ade80; transform:scale(1.12); text-shadow:0 0 10px rgba(74,222,128,.6); }
            100% { color:#fff; transform:scale(1); text-shadow:none; }
        }
        @keyframes ssVoteDown {
            0%   { color:#fff; transform:scale(1); }
            20%  { color:#f87171; transform:scale(1.18); text-shadow:0 0 18px rgba(248,113,113,1); }
            60%  { color:#f87171; transform:scale(1.1);  text-shadow:0 0 10px rgba(248,113,113,.5); }
            100% { color:#fff; transform:scale(1); text-shadow:none; }
        }
        .ss-vote-up   { animation: ssVoteUp   1.5s cubic-bezier(.22,1,.36,1) both; }
        .ss-vote-down { animation: ssVoteDown 1.5s cubic-bezier(.22,1,.36,1) both; }
        .ss-cand-right { position:relative; overflow:visible; }
        .ss-vote-dir-badge {
            display:inline-block; font-size:18px; font-weight:900;
            position:absolute; right:-26px; top:0;
            pointer-events:none;
            animation: ssDirFade 2s ease both;
        }
        @keyframes ssDirFade {
            0%   { opacity:0; transform:translateY(4px); }
            15%  { opacity:1; transform:translateY(-2px); }
            65%  { opacity:1; transform:translateY(-8px); }
            100% { opacity:0; transform:translateY(-14px); }
        }

        /* ss nav arrows */
        .ss-nav {
            position:absolute; top:50%; transform:translateY(-50%);
            background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
            color:#fff; font-size:20px; width:44px; height:44px;
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:background .2s; user-select:none;
            font-family:inherit;
        }
        .ss-nav:hover { background:rgba(255,255,255,.18); }
        .ss-nav.prev { left:16px; }
        .ss-nav.next { right:16px; }

        /* ss dots */
        .ss-dots {
            display:flex; gap:7px; justify-content:center;
            padding:12px 0 0; flex-shrink:0; flex-wrap:wrap;
        }
        .ss-dot {
            width:8px; height:8px; border-radius:50%;
            background:rgba(255,255,255,.2); cursor:pointer;
            transition:background .2s, transform .2s;
            border:none; padding:0;
        }
        .ss-dot.active { background:#f5c400; transform:scale(1.3); }

        /* progress bar */
        .ss-progress-wrap { height:3px; background:rgba(255,255,255,.08); flex-shrink:0; }
        .ss-progress-bar { height:100%; background:#f5c400; transition:width linear; }

        /* settings fab */
        .ss-settings-fab {
            position:fixed; bottom:24px; right:24px; z-index:950;
            width:42px; height:42px; border-radius:50%;
            background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15);
            color:#9ca3af; cursor:pointer; align-items:center; justify-content:center;
            transition:background .2s, color .2s; font-family:inherit;
            display:none;
        }
        body.ss-page .ss-settings-fab { display:flex; }
        .ss-settings-fab.visible { display:flex; }
        .ss-settings-fab:hover { background:rgba(255,255,255,.18); color:#fff; }
        .ss-settings-panel {
            position:fixed; bottom:76px; right:24px; z-index:950;
            background:#0f1e36; border:1px solid rgba(255,255,255,.12);
            border-radius:14px; padding:18px 20px; width:230px;
            box-shadow:0 8px 32px rgba(0,0,0,.5);
            display:none; flex-direction:column; gap:14px;
        }
        .ss-settings-panel.open { display:flex; }
        .ss-settings-title { font-size:11px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:#6b7280; }
        .ss-setting-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .ss-setting-label { font-size:12px; font-weight:700; color:#d1d5db; }
        .ss-toggle { position:relative; width:36px; height:20px; flex-shrink:0; }
        .ss-toggle input { opacity:0; width:0; height:0; position:absolute; }
        .ss-toggle-track {
            position:absolute; inset:0; border-radius:20px;
            background:#374151; cursor:pointer; transition:background .2s;
        }
        .ss-toggle input:checked + .ss-toggle-track { background:#f5c400; }
        .ss-toggle-track::after {
            content:''; position:absolute; top:3px; left:3px;
            width:14px; height:14px; border-radius:50%; background:#fff;
            transition:transform .2s;
        }
        .ss-toggle input:checked + .ss-toggle-track::after { transform:translateX(16px); }
        .ss-interval-input {
            width:70px; padding:5px 8px; border-radius:8px;
            background:#1e2d4a; border:1px solid rgba(255,255,255,.12);
            color:#f1f5f9; font-size:13px; font-weight:700; font-family:inherit;
            text-align:right;
        }
        .ss-interval-input:focus { outline:none; border-color:#f5c400; }
        .ss-setting-unit { font-size:11px; color:#6b7280; font-weight:600; }

        /* Overlay slideshow (tally page) */
        .ss-overlay {
            display:none; position:fixed; inset:0; z-index:900;
            background:#05101f;
            flex-direction:column; align-items:stretch;
        }
        .ss-overlay.open { display:flex; }
        .ss-topbar {
            display:flex; align-items:center; justify-content:flex-end;
            padding:12px 20px; flex-shrink:0;
        }
        .ss-close {
            background:none; border:none; cursor:pointer; color:#9ca3af;
            font-size:20px; line-height:1; padding:4px 8px; border-radius:6px;
            font-family:inherit; transition:color .15s;
        }
        .ss-close:hover { color:#fff; }
        .ss-overlay .ss-stage { flex:1; }
        .ss-overlay .ss-dots { padding:12px 0 0; flex-shrink:0; }
        .ss-overlay .ss-progress-wrap { flex-shrink:0; }

        /* Nav toggle (mobile) */
        .nav-toggle { display:none; flex-direction:column; gap:5px; background:none; border:none; cursor:pointer; padding:8px; margin:0; }
        .nav-toggle span { display:block; width:22px; height:2.5px; background:var(--blue); border-radius:2px; transition:transform .3s, opacity .3s; }
        .nav-open .nav-toggle span:nth-child(1) { transform:translateY(8px) rotate(45deg); }
        .nav-open .nav-toggle span:nth-child(2) { opacity:0; }
        .nav-open .nav-toggle span:nth-child(3) { transform:translateY(-8px) rotate(-45deg); }

        @media (max-width:768px) {
            .navbar { padding:0 16px; height:auto; min-height:56px; flex-wrap:wrap; gap:0; align-content:flex-start; }
            .navbar-brand { order:1; flex-basis:auto; padding:8px 0; align-self:center; }
            .nav-toggle { display:flex; order:2; margin-left:auto; align-self:center; }
            .navbar-links { order:3; display:none; flex-direction:column; gap:0; width:100%; padding:0; margin:0; border-top:1px solid #e5e7eb; }
            .navbar-links.open { display:flex; }
            .navbar-links li { width:100%; }
            .navbar-links li a { display:flex; align-items:center; padding:16px 12px; font-size:14px; font-weight:600; border-bottom:1px solid #f0f0f0; min-height:48px; width:100%; }
            .hero { padding:40px 20px 32px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; grid:unset; grid-template-columns:unset; grid-template-areas:unset; column-gap:unset; }
            .hero-left { grid-area:unset; width:100%; max-width:600px; }
            .hero-right { grid-area:unset; display:flex; align-items:center; justify-content:center; margin-top:24px; max-height:280px; }
            .hero-right img { max-height:280px; }
            .hero-countdown { grid-area:unset; padding-top:32px; width:100%; }
            .hero-title { font-size:38px; line-height:48px; text-align:center; }
            .hero-logo-row { justify-content:center; margin-bottom:16px; }
            .hero-logo { width:60px; height:60px; }
            .hero-logo img { width:52px; height:52px; }
            .hero-logo-label { font-size:11px; }
            .hero-desc { font-size:13px; margin-bottom:24px; text-align:center; }
            .countdown-label { font-size:18px; margin-bottom:12px; }
            .countdown { gap:8px; }
            .cd-box { padding:14px 10px 10px; }
            .cd-num { font-size:28px; }
            .cd-unit { font-size:8px; }
            .tally-page { padding:40px 16px; }
            .tally-positions-grid { grid-template-columns:1fr; }
            .tally-pos-card.full-width { grid-column:auto; }
            .footer { flex-direction:column; align-items:flex-start; padding:24px 16px; }
            .ss-stage { padding:16px 52px; }
            .ss-pos-title { font-size:38px; }
            .ss-cand-name { font-size:20px; }
            .ss-votes { font-size:32px; }
        }
        @media (max-width:480px) {
            .hero { padding:24px 14px 28px; }
            .hero-logo-row { margin-bottom:12px; }
            .hero-logo { width:52px; height:52px; }
            .hero-logo img { width:46px; height:46px; }
            .hero-logo-label { font-size:10px; }
            .hero-title { font-size:32px; line-height:40px; margin-bottom:12px; }
            .hero-desc { font-size:12.5px; margin-bottom:20px; }
            .hero-right img { max-height:240px; }
            .countdown-label { font-size:16px; margin-bottom:10px; }
            .countdown { gap:6px; }
            .cd-box { padding:12px 8px 8px; }
            .cd-num { font-size:24px; }
            .cd-unit { font-size:7px; }
            .ss-stage { padding:12px 44px; }
            .ss-pos-title { font-size:28px; }
            .ss-pos-label { font-size:11px; }
            .ss-college-badge { font-size:13px; padding:3px 14px; }
            .ss-cand-photo, .ss-cand-photo-ph { width:56px; height:56px; }
            .ss-cand-name { font-size:15px; }
            .ss-party-name { font-size:13px; }
            .ss-votes { font-size:24px; }
            .ss-votes-label { font-size:11px; }
            .ss-cand { padding:8px 12px; }
            .ss-nav { width:44px; height:44px; font-size:18px; }
            .ss-nav.prev { left:4px; }
            .ss-nav.next { right:4px; }
        }
    </style>
</head>

<?php if ($slideshowMode): ?>
<!-- ═══════════════════════════════════════════════════════
     STANDALONE SLIDESHOW PAGE  (?slideshow=1)
════════════════════════════════════════════════════════ -->
<body class="ss-page">

<div class="ssp-topbar">
    <span class="ssp-brand">&#127897; Slideshow &mdash; JRMSU SSG Election <?= htmlspecialchars(ELECTION_SCHOOL_YEAR) ?></span>
    <a href="/tally.php" class="ssp-exit">&#10005; Exit Slideshow</a>
</div>

<!-- College filter -->
<div class="ssp-filter-wrap">
    <div class="college-filter-bar" id="ssCollegeFilter">
        <button class="college-pill active" onclick="ssSetFilter('ALL',this)">All Colleges</button>
    </div>
</div>

<!-- Stage (full page, not an overlay) -->
<div class="ss-stage" id="ssStage">
    <button class="ss-nav prev" id="ssPrev" onclick="ssNav(-1)">&#8592;</button>
    <div id="ssSlides" style="width:100%;max-width:900px;"></div>
    <button class="ss-nav next" id="ssNext" onclick="ssNav(1)">&#8594;</button>
</div>
<div class="ss-progress-wrap"><div class="ss-progress-bar" id="ssProgressBar" style="width:0%"></div></div>
<div class="ss-dots" id="ssDots"></div>

<!-- Settings FAB -->
<button class="ss-settings-fab" id="ssSettingsFab" onclick="toggleSettingsPanel()" title="Slideshow settings">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
</button>
<div class="ss-settings-panel" id="ssSettingsPanel">
    <div class="ss-settings-title">Slideshow Settings</div>
    <div class="ss-setting-row">
        <span class="ss-setting-label">Auto-scroll</span>
        <label class="ss-toggle">
            <input type="checkbox" id="ssAutoToggle" checked onchange="ssApplySettings()">
            <span class="ss-toggle-track"></span>
        </label>
    </div>
    <div class="ss-setting-row">
        <span class="ss-setting-label">Interval</span>
        <div style="display:flex;align-items:center;gap:5px;">
            <input type="number" class="ss-interval-input" id="ssIntervalInput" value="5" min="1" max="60" onchange="ssApplySettings()">
            <span class="ss-setting-unit">sec</span>
        </div>
    </div>
</div>

<?php else: // ─── REGULAR / PROJECTOR TALLY PAGE ─────────────── ?>
<body<?= $screenMode ? ' class="screen-mode"' : '' ?>>

<?php if ($screenMode): ?>
<div class="screen-exit-bar">
    <span>&#128250; Projector View &mdash; JRMSU SSG Election <?= htmlspecialchars(ELECTION_SCHOOL_YEAR) ?></span>
    <a href="/tally.php">&#10005; Exit Projector View</a>
</div>
<?php endif; ?>

<nav class="navbar" id="navbar">
    <a href="/" class="navbar-brand">E-Ballot</a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <span></span><span></span><span></span>
    </button>
    <ul class="navbar-links" id="navLinks">
        <li><a href="/">Candidates</a></li>
        <li><a href="/contact.php">Contact</a></li>
        <li><a href="/tally.php" class="nav-active">Tally</a></li>
        <li><a href="<?= !empty($_SESSION['logged_in']) ? '/dashboard.php' : '/login.php' ?>">Profile</a></li>
        <?php if (!empty($_SESSION['logged_in'])): ?>
        <li><a href="/logout.php">Sign Out</a></li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Hero -->
<section class="section hero-wrapper" style="padding-top:28px;padding-bottom:12px;">
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
                <a href="/login.php" class="btn-vote">Vote Now &rarr;</a>
            </div>
        </div>
        <div class="hero-right">
            <img src="/Presets/login-hero-real.png" alt="Election Portal Illustration" loading="eager" decoding="async"/>
        </div>
        <div class="hero-countdown">
            <div class="countdown-label" id="countdownLabel">Voting Closed</div>
            <div class="countdown" id="countdown">
                <div class="cd-box" id="cd-box-days"><span class="cd-num" id="cd-days">00</span><div class="cd-unit">Days</div></div>
                <div class="cd-box" id="cd-box-hours"><span class="cd-num" id="cd-hours">00</span><div class="cd-unit">Hours</div></div>
                <div class="cd-box" id="cd-box-mins"><span class="cd-num" id="cd-mins">00</span><div class="cd-unit">Minutes</div></div>
                <div class="cd-box" id="cd-box-secs"><span class="cd-num" id="cd-secs">00</span><div class="cd-unit">Seconds</div></div>
            </div>
        </div>
    </div>
</section>

<div class="tally-page">
    <div class="tally-header">
        <div class="tally-label">Live Results</div>
        <h1 class="tally-title">Live Tally</h1>
        <span class="live-badge-pub"><span class="live-dot-pub"></span>Live</span>
        <span class="tally-updated-pub" id="tallyUpdatedPub">Loading&hellip;</span>
        <?php if (!$screenMode): ?>
        <div style="margin-top:16px;">
            <a href="/tally.php?screen=1" class="btn-projector" target="_blank">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Projector View
            </a>
            <a href="/tally.php?slideshow=1" class="btn-slideshow" target="_blank">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
                Slideshow View
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- College filter bar (tally, projector, & slideshow — populated by JS) -->
    <div class="college-filter-bar" id="collegeFilterBar" style="margin-bottom:28px;display:none;"></div>

    <div id="tallyGridWrap">
        <div class="tally-skeleton-grid">
            <div class="tally-skeleton-card"></div>
            <div class="tally-skeleton-card"></div>
            <div class="tally-skeleton-card"></div>
            <div class="tally-skeleton-card"></div>
        </div>
    </div>
    <p id="tallyFootnote" style="text-align:center;font-size:12px;color:#9ca3af;margin-top:24px;display:none;"></p>
</div>

<!-- ── Slideshow Overlay (from regular tally page only) ── -->
<div class="ss-overlay" id="ssOverlay">
    <div class="ss-topbar">
        <button class="ss-close" onclick="closeSlideshow()" title="Close">&#10005;</button>
    </div>
    <div class="ss-stage" id="ssStage">
        <button class="ss-nav prev" id="ssPrev" onclick="ssNav(-1)">&#8592;</button>
        <div id="ssSlides" style="width:100%;max-width:900px;"></div>
        <button class="ss-nav next" id="ssNext" onclick="ssNav(1)">&#8594;</button>
    </div>
    <div class="ss-progress-wrap"><div class="ss-progress-bar" id="ssProgressBar" style="width:0%"></div></div>
    <div class="ss-dots" id="ssDots"></div>
</div>

<!-- Settings FAB (overlay slideshow) -->
<button class="ss-settings-fab" id="ssSettingsFab" onclick="toggleSettingsPanel()" title="Slideshow settings">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
</button>
<div class="ss-settings-panel" id="ssSettingsPanel">
    <div class="ss-settings-title">Slideshow Settings</div>
    <div class="ss-setting-row">
        <span class="ss-setting-label">Auto-scroll</span>
        <label class="ss-toggle">
            <input type="checkbox" id="ssAutoToggle" checked onchange="ssApplySettings()">
            <span class="ss-toggle-track"></span>
        </label>
    </div>
    <div class="ss-setting-row">
        <span class="ss-setting-label">Interval</span>
        <div style="display:flex;align-items:center;gap:5px;">
            <input type="number" class="ss-interval-input" id="ssIntervalInput" value="5" min="1" max="60" onchange="ssApplySettings()">
            <span class="ss-setting-unit">sec</span>
        </div>
    </div>
</div>

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
var navToggle = document.getElementById('navToggle');
var navLinks  = document.getElementById('navLinks');
var navbar    = document.getElementById('navbar');
if (navToggle && navbar && navLinks) {
    navToggle.addEventListener('click', function() {
        navbar.classList.toggle('nav-open');
        navLinks.classList.toggle('open');
    });
    document.addEventListener('click', function(e) {
        if (navbar.classList.contains('nav-open') && !navbar.contains(e.target)) {
            navbar.classList.remove('nav-open');
            navLinks.classList.remove('open');
        }
    });
}
</script>

<?php endif; // end regular/projector vs slideshow-page ?>

<script>
/* ── Shared constants ── */
const IS_SCREEN    = <?= $screenMode    ? 'true' : 'false' ?>;
const IS_SLIDESHOW = <?= $slideshowMode ? 'true' : 'false' ?>;
const VOTER_COLLEGE = <?= json_encode($voterCollegeCode) ?>;
const POLL_MS = IS_SCREEN || IS_SLIDESHOW ? 5000 : 10000;

/* ── Helpers ── */
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtAgo(d) {
    if (!d) return '';
    const sec = Math.round((Date.now() - d) / 1000);
    if (sec < 5)  return 'just now';
    if (sec < 60) return sec + 's ago';
    return Math.round(sec / 60) + 'm ago';
}
function photoHtml(cand, photo_cls, ph_cls) {
    photo_cls = photo_cls || 'tally-cand-photo';
    ph_cls    = ph_cls    || 'tally-cand-photo-placeholder';
    const sz  = photo_cls === 'ss-cand-photo' ? '34' : '22';
    if (cand.photo) {
        return `<img class="${photo_cls}" src="${esc(cand.photo)}" alt="${esc(cand.name)}"
            loading="lazy" decoding="async"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
            <div class="${ph_cls}" style="display:none">
                <svg viewBox="0 0 24 24" fill="#9ca3af" width="${sz}" height="${sz}"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
            </div>`;
    }
    return `<div class="${ph_cls}">
        <svg viewBox="0 0 24 24" fill="#9ca3af" width="${sz}" height="${sz}"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
    </div>`;
}
</script>

<script>
/* ══════════════════════════════════════════════════
   TALLY GRID  (regular & projector views)
══════════════════════════════════════════════════ */
(function () {
    if (IS_SLIDESHOW) return; // not needed on standalone slideshow page

    let lastFetch    = null;
    let lastData     = null;
    let prevVoteMap  = {};  // candidate_id → votes (for change detection)
    let activeFilter = VOTER_COLLEGE || 'ALL'; // default to voter's college when logged in

    /* ── College filter ── */
    window.setCollegeFilter = function(code, btn) {
        activeFilter = code;
        document.querySelectorAll('#collegeFilterBar .college-pill').forEach(p => p.classList.remove('active'));
        if (btn) btn.classList.add('active');
        if (lastData) renderGrid(lastData);
    };

    function buildFilterBar(cards) {
        const bar = document.getElementById('collegeFilterBar');
        if (!bar) return;
        // Collect unique non-empty colleges
        const seen = new Set();
        const colleges = [];
        cards.forEach(c => {
            if (c.college && !seen.has(c.college)) {
                seen.add(c.college);
                colleges.push({ code: c.college, label: c.college_label || c.college });
            }
        });
        if (colleges.length <= 1) { bar.style.display = 'none'; return; }
        bar.style.display = 'flex';
        bar.innerHTML = `<button class="college-pill${activeFilter==='ALL'?' active':''}" onclick="setCollegeFilter('ALL',this)">All Colleges</button>`;
        colleges.forEach(({ code, label }) => {
            const shortLabel = label.split('—')[0].trim() || code;
            bar.innerHTML += `<button class="college-pill${activeFilter===code?' active':''}" onclick="setCollegeFilter('${esc(code)}',this)">${esc(shortLabel)}</button>`;
        });
    }

    /* Format position title: replace underscores/hyphens with spaces,
       then title-case every word. The last word is uppercased only when
       it looks like a college abbreviation (2–5 all-letter characters). */
    function formatPositionTitle(position) {
        var parts = position.replace(/[_-]/g, ' ').split(/\s+/).filter(function(p) { return p.length > 0; });
        var originalLast = position.replace(/[_-]/g, ' ').trim().split(/\s+/).pop() || '';
        var looksLikeCode = originalLast.length <= 5 && /^[a-zA-Z]+$/.test(originalLast);
        return parts.map(function(p, i) {
            if (i === parts.length - 1 && looksLikeCode && parts.length > 1) {
                return p.toUpperCase();
            }
            return p.charAt(0).toUpperCase() + p.slice(1).toLowerCase();
        }).join(' ');
    }

    function renderGrid(data) {
        const wrap     = document.getElementById('tallyGridWrap');
        const footnote = document.getElementById('tallyFootnote');
        if (!wrap) return;

        if (!data || !data.ok || !data.cards || data.cards.length === 0) {
            wrap.innerHTML = `<div class="tally-empty">No approved candidates yet. Results will appear here once candidates are approved.</div>`;
            if (footnote) footnote.style.display = 'none';
            return;
        }

        // Build filter bar for all tally views (tally, projector, slideshow)
        buildFilterBar(data.cards);

        // Apply college filter
        let cards = data.cards;
        if (activeFilter !== 'ALL') {
            cards = cards.filter(c =>
                c.college === '' || c.college === activeFilter
            );
        }

        if (cards.length === 0) {
            wrap.innerHTML = `<div class="tally-empty">No positions match the selected college filter.</div>`;
            if (footnote) footnote.style.display = 'none';
            return;
        }

        // Enforce descending sort by votes within every card
        cards.forEach(card => card.candidates.sort((a, b) => b.votes - a.votes));

        const totalCards = cards.length;
        let html = '<div class="tally-positions-grid">';
        cards.forEach((card, idx) => {
            const isFullWidth = (totalCards % 2 !== 0 && idx === totalCards - 1);
            const maxVotes = card.candidates.length > 0 ? card.candidates[0].votes : 0;
            html += `<div class="tally-pos-card${isFullWidth ? ' full-width' : ''}">
                <div class="tally-pos-title">${esc(formatPositionTitle(card.position))}</div>`;
            if (card.college_label) {
                html += `<div class="tally-college-badge">${esc(card.college_label)}</div>`;
            }
            html += `<hr class="tally-pos-divider"/>`;
            card.candidates.forEach(cand => {
                const color  = esc(cand.party_color || '#1a3a8f');
                const barPct = maxVotes > 0 ? (cand.votes / maxVotes * 100).toFixed(1) : 0;
                const label  = cand.votes === 1 ? '1 vote' : cand.votes + ' votes';
                const cid    = esc(cand.candidate_id || cand.student_id || '');
                const changed = prevVoteMap[cid] !== undefined && prevVoteMap[cid] !== cand.votes;
                html += `<div class="tally-candidate-row">
                    ${photoHtml(cand)}
                    <div class="tally-cand-info">
                        <div class="tally-cand-name-row">
                            <div style="display:flex;align-items:center;gap:6px;min-width:0;overflow:hidden;">
                                <span class="tally-cand-name">${esc(cand.name)}</span>
                                <span class="tally-party-dot" style="background:${color};"></span>
                            </div>
                            <span class="tally-vote-pct${changed ? ' vote-flash' : ''}" data-cid="${cid}">${esc(label)}</span>
                        </div>
                        <div class="tally-bar-track" style="margin-top:6px;">
                            <div class="tally-bar-fill${changed ? ' bar-flash' : ''}" data-pct="${barPct}" style="width:0%;background:${color};"></div>
                        </div>
                    </div>
                </div>`;
            });
            html += `</div>`;
        });
        html += '</div>';
        wrap.innerHTML = html;

        // Animate bars
        requestAnimationFrame(() => {
            wrap.querySelectorAll('.tally-bar-fill').forEach(el => {
                const target = el.dataset.pct + '%';
                requestAnimationFrame(() => { el.style.width = target; });
            });
        });

        // Update prevVoteMap
        data.cards.forEach(card => {
            card.candidates.forEach(cand => {
                const cid = cand.candidate_id || cand.student_id || '';
                if (cid) prevVoteMap[cid] = cand.votes;
            });
        });

        if (footnote) footnote.style.display = 'none';
    }

    async function fetchTally() {
        // Always fetch all colleges — client-side activeFilter (defaults to VOTER_COLLEGE)
        // handles showing the logged-in student's college first while keeping the filter bar visible.
        const ctrl  = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), 8000);
        try {
            const r = await fetch('/ajax/tally-live.php?_=' + Date.now() + '&all=1',
                { credentials: 'include', signal: ctrl.signal });
            clearTimeout(timer);
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            lastData = data;
            try { renderGrid(data); } catch (re) { console.warn('Tally render error:', re); }
            if (data && data.cards) window.__ssTallyData = data;
            lastFetch = Date.now();
            const el = document.getElementById('tallyUpdatedPub');
            if (el) el.textContent = 'Updated just now';
        } catch (err) {
            clearTimeout(timer);
            if (err.name !== 'AbortError') console.warn('Tally fetch failed:', err);
            const el = document.getElementById('tallyUpdatedPub');
            if (el) el.textContent = 'Update failed — retrying…';
        }
    }

    setInterval(() => {
        const el = document.getElementById('tallyUpdatedPub');
        if (el && lastFetch) el.textContent = 'Updated ' + fmtAgo(lastFetch);
    }, 5000);

    fetchTally();
    setInterval(fetchTally, POLL_MS);
})();
</script>

<script>
/* ══════════════════════════════════════════════════
   SLIDESHOW ENGINE  (overlay + standalone page)
══════════════════════════════════════════════════ */
(function () {
    let allCards  = [];   // full unfiltered card list
    let slides    = [];   // filtered & active slides
    let current   = 0;
    let autoTimer = null;
    let isOpen    = IS_SLIDESHOW; // standalone = always open
    let prevVoteMap = {};         // candidate_id → last known votes

    let autoPlay  = true;
    let intervalS = 5;
    let ssFilter  = VOTER_COLLEGE || 'ALL'; // default to voter's college when logged in

    /* ── Expose globals ── */
    window.openSlideshow       = openSlideshow;
    window.closeSlideshow      = closeSlideshow;
    window.ssNav               = ssNav;
    window.toggleSettingsPanel = toggleSettingsPanel;
    window.ssApplySettings     = ssApplySettings;
    // ssSetFilter is assigned below as window.ssSetFilter = function(...) — do not reference it here

    /* ── Photo helper (slideshow variant) ── */
    function sPhotoHtml(cand) {
        return photoHtml(cand, 'ss-cand-photo', 'ss-cand-photo-ph');
    }

    /* ── Expand cards: split any position with ≥4 candidates into pages of 3 ── */
    function expandCards(cards) {
        const PAGE_SIZE = 3;
        const result = [];
        cards.forEach(card => {
            // Always sort descending by votes before paging so highest is first
            const sorted = card.candidates.slice().sort((a, b) => b.votes - a.votes);
            if (sorted.length >= 4) {
                const total = sorted.length;
                const pages = Math.ceil(total / PAGE_SIZE);
                for (let p = 0; p < pages; p++) {
                    result.push(Object.assign({}, card, {
                        candidates: sorted.slice(p * PAGE_SIZE, (p + 1) * PAGE_SIZE),
                        _page: p + 1,
                        _pages: pages,
                    }));
                }
            } else {
                result.push(Object.assign({}, card, { candidates: sorted }));
            }
        });
        return result;
    }

    function buildSlideHtml(card, idx) {
        // Guarantee descending order — safety net for any path that skips expandCards sort
        const candidates = card.candidates.slice().sort((a, b) => b.votes - a.votes);
        const maxVotes = candidates.length > 0 ? candidates[0].votes : 0;
        const candsHtml = candidates.map((cand, ci) => {
            const color   = esc(cand.party_color || '#1a3a8f');
            const barPct  = maxVotes > 0 ? (cand.votes / maxVotes * 100).toFixed(1) : 0;
            const votes   = String(cand.votes);
            const isLead  = ci === 0 && cand.votes > 0;
            const cid     = esc(cand.candidate_id || cand.student_id || '');
            const changed = prevVoteMap[cid] !== undefined && prevVoteMap[cid] !== cand.votes;
            return `<div class="ss-cand${isLead ? ' leader' : ''}">
                <div class="ss-cand-top">
                    ${sPhotoHtml(cand)}
                    <div class="ss-cand-info">
                        <div class="ss-cand-name">${isLead ? '<span class="ss-leader-crown">&#127881;</span>' : ''}${esc(cand.name)}</div>
                        <div class="ss-party-row">
                            <span class="ss-party-dot" style="background:${color};"></span>
                            <span class="ss-party-name">${esc(cand.party_name || cand.slate || '')}</span>
                        </div>
                    </div>
                    <div class="ss-cand-right">
                        <span class="ss-votes${changed ? ' ss-vote-flash' : ''}" data-cid="${cid}">${esc(votes)}</span>
                        <span class="ss-votes-label">votes</span>
                    </div>
                </div>
                <div class="ss-bar-track">
                    <div class="ss-bar-fill" data-cid="${cid}" style="width:${barPct}%;background:${color};"></div>
                </div>
            </div>`;
        }).join('');

        const posTitle  = formatPositionTitle(card.position);
        const pageLabel = card._pages > 1 ? `<div class="ss-page-label">${card._page} / ${card._pages}</div>` : '';

        return `<div class="ss-slide" data-idx="${idx}">
            <div class="ss-pos-header">
                <div class="ss-pos-label">POSITION</div>
                <div class="ss-pos-title">${esc(posTitle)}</div>
                ${card.college_label ? `<div class="ss-college-badge">${esc(card.college_label)}</div>` : ''}
                ${pageLabel}
            </div>
            <div class="ss-candidates">${candsHtml}</div>
        </div>`;
    }

    function buildFilterBar(cards) {
        const bar = document.getElementById('ssCollegeFilter');
        if (!bar) return;
        const seen = new Set();
        const colleges = [];
        cards.forEach(c => {
            if (c.college && !seen.has(c.college)) {
                seen.add(c.college);
                colleges.push({ code: c.college, label: c.college_label || c.college });
            }
        });
        if (colleges.length <= 1) { bar.style.display = 'none'; return; }
        bar.style.display = 'flex';
        bar.innerHTML = `<button class="college-pill${ssFilter==='ALL'?' active':''}" onclick="ssSetFilter('ALL',this)">All Colleges</button>`;
        colleges.forEach(({ code, label }) => {
            const shortLabel = label.split('—')[0].trim() || code;
            bar.innerHTML += `<button class="college-pill${ssFilter===code?' active':''}" onclick="ssSetFilter('${esc(code)}',this)">${esc(shortLabel)}</button>`;
        });
    }

    window.ssSetFilter = function(code, btn) {
        ssFilter = code;
        if (btn) {
            // find the correct filter bar
            const bar = btn.closest('.college-filter-bar');
            if (bar) bar.querySelectorAll('.college-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
        }
        applyFilter();
    };

    function applyFilter() {
        const filtered = ssFilter === 'ALL'
            ? allCards
            : allCards.filter(c => c.college === '' || c.college === ssFilter);
        slides = expandCards(filtered);
        current = 0;
        rebuildSlides();
    }

    function updatePrevVoteMap(cards) {
        cards.forEach(card => {
            card.candidates.forEach(cand => {
                const cid = cand.candidate_id || cand.student_id || '';
                if (cid) prevVoteMap[cid] = cand.votes;
            });
        });
    }

    /* ── Patch vote counts in-place without rebuilding the DOM ── */
    function patchSlideCounts(newSlides) {
        // Enforce descending sort within each card before patching
        newSlides.forEach(card => card.candidates.sort((a, b) => b.votes - a.votes));
        newSlides.forEach(card => {
            const maxVotes = card.candidates.length > 0 ? card.candidates[0].votes : 0;
            card.candidates.forEach(cand => {
                const cid = cand.candidate_id || cand.student_id || '';
                if (!cid) return;
                const votesEl = document.querySelector(`.ss-votes[data-cid="${CSS.escape(String(cid))}"]`);
                if (votesEl) {
                    const oldVal = parseInt(votesEl.textContent, 10) || 0;
                    if (oldVal !== cand.votes) {
                        const dir = cand.votes > oldVal ? 'up' : 'down';
                        votesEl.textContent = cand.votes;
                        votesEl.classList.remove('ss-vote-flash', 'ss-vote-up', 'ss-vote-down');
                        void votesEl.offsetWidth; // force reflow
                        votesEl.classList.add(dir === 'up' ? 'ss-vote-up' : 'ss-vote-down');
                        // Floating direction badge
                        const wrapper = votesEl.closest('.ss-cand-right');
                        if (wrapper) {
                            // Remove any existing badge first
                            wrapper.querySelectorAll('.ss-vote-dir-badge').forEach(b => b.remove());
                            const badge = document.createElement('span');
                            badge.className = 'ss-vote-dir-badge';
                            badge.textContent = dir === 'up' ? '▲' : '▼';
                            badge.style.color = dir === 'up' ? '#4ade80' : '#f87171';
                            wrapper.appendChild(badge);
                            setTimeout(() => badge.remove(), 2100);
                        }
                    }
                }
                const barEl = document.querySelector(`.ss-bar-fill[data-cid="${CSS.escape(String(cid))}"]`);
                if (barEl) {
                    const pct = maxVotes > 0 ? (cand.votes / maxVotes * 100).toFixed(1) : 0;
                    barEl.style.width = pct + '%';
                }
            });
        });
        updatePrevVoteMap(newSlides);
    }

    /* ── Check if current DOM slide order matches new slides data ── */
    function domOrderMatchesSlides() {
        const domOrder = [...document.querySelectorAll('.ss-slide')].map(slideEl =>
            [...slideEl.querySelectorAll('.ss-votes[data-cid]')].map(el => el.dataset.cid).join(',')
        );
        const newOrder = slides.map(card =>
            card.candidates.map(c => String(c.candidate_id || c.student_id || '')).join(',')
        );
        return domOrder.join('|') === newOrder.join('|');
    }

    function rebuildSlides() {
        const container = document.getElementById('ssSlides');
        const dots      = document.getElementById('ssDots');
        if (!container || !dots) return;
        if (!slides.length) {
            container.innerHTML = `<div style="color:#6b7280;font-size:14px;font-weight:600;text-align:center;padding:40px 0;">No data yet. Waiting for results…</div>`;
            dots.innerHTML = '';
            return;
        }
        container.innerHTML = slides.map((card, i) => buildSlideHtml(card, i)).join('');
        dots.innerHTML = slides.map((_, i) =>
            `<button class="ss-dot${i === current ? ' active' : ''}" onclick="window.ssGoTo(${i})"></button>`
        ).join('');
        window.ssGoTo = ssGoTo;
        showSlide(current);
        // After rendering, update prevVoteMap
        updatePrevVoteMap(slides);
    }

    function showSlide(idx) {
        if (!slides.length) return;
        current = ((idx % slides.length) + slides.length) % slides.length;
        document.querySelectorAll('.ss-slide').forEach((el, i) => el.classList.toggle('active', i === current));
        document.querySelectorAll('.ss-dot').forEach((el, i) => el.classList.toggle('active', i === current));
        const bar = document.getElementById('ssProgressBar');
        if (bar) {
            bar.style.transition = 'none';
            bar.style.width = '0%';
            requestAnimationFrame(() => requestAnimationFrame(() => {
                if (autoPlay) {
                    bar.style.transition = `width ${intervalS}s linear`;
                    bar.style.width = '100%';
                }
            }));
        }
    }

    function ssGoTo(idx) { stopAuto(); showSlide(idx); if (autoPlay) startAuto(); }
    function ssNav(dir)  { stopAuto(); showSlide(current + dir); if (autoPlay) startAuto(); }

    function startAuto() {
        stopAuto();
        if (!autoPlay || !slides.length) return;
        autoTimer = setInterval(() => showSlide(current + 1), intervalS * 1000);
    }
    function stopAuto() {
        if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
        const bar = document.getElementById('ssProgressBar');
        if (bar) { bar.style.transition = 'none'; bar.style.width = '0%'; }
    }

    function openSlideshow() {
        const raw = window.__ssTallyData;
        if (raw && raw.cards) { allCards = raw.cards; applyFilter(); }
        isOpen = true;
        current = 0;
        const overlay = document.getElementById('ssOverlay');
        const fab     = document.getElementById('ssSettingsFab');
        if (overlay) overlay.classList.add('open');
        if (fab)     fab.style.display = 'flex';
        rebuildSlides();
        startAuto();
    }
    function closeSlideshow() {
        if (IS_SLIDESHOW) return; // standalone can't close
        isOpen = false;
        stopAuto();
        const overlay = document.getElementById('ssOverlay');
        const fab     = document.getElementById('ssSettingsFab');
        const panel   = document.getElementById('ssSettingsPanel');
        if (overlay) overlay.classList.remove('open');
        if (fab)     fab.style.display = 'none';
        if (panel)   panel.classList.remove('open');
    }
    function toggleSettingsPanel() {
        const panel = document.getElementById('ssSettingsPanel');
        if (panel) panel.classList.toggle('open');
    }
    function ssApplySettings() {
        const tog = document.getElementById('ssAutoToggle');
        const inp = document.getElementById('ssIntervalInput');
        autoPlay  = tog ? tog.checked : true;
        intervalS = inp ? Math.max(1, parseInt(inp.value) || 5) : 5;
        stopAuto();
        if (autoPlay && isOpen) startAuto();
        if (!autoPlay) {
            const bar = document.getElementById('ssProgressBar');
            if (bar) { bar.style.transition = 'none'; bar.style.width = '0%'; }
        }
    }

    document.addEventListener('keydown', function(e) {
        if (!isOpen) return;
        if (e.key === 'Escape' && !IS_SLIDESHOW) closeSlideshow();
        if (e.key === 'ArrowRight') ssNav(1);
        if (e.key === 'ArrowLeft')  ssNav(-1);
    });

    /* ── Standalone slideshow: fetch + auto-start ── */
    if (IS_SLIDESHOW) {
        let lastFetch   = null;
        let sprevVoteMap = {};

        async function fetchSlideshow() {
            const ctrl  = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 8000);
            try {
                const r = await fetch('/ajax/tally-live.php?_=' + Date.now() + '&all=1',
                    { credentials: 'include', signal: ctrl.signal });
                clearTimeout(timer);
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                if (data && data.ok && data.cards) {
                    buildFilterBar(data.cards);
                    // Detect vote changes vs previous fetch
                    const prevMap = Object.assign({}, prevVoteMap);
                    // Update allCards and refresh
                    const prevAllCards = allCards;
                    allCards = ssFilter === 'ALL' ? data.cards : data.cards;
                    const filteredNew = ssFilter === 'ALL' ? data.cards : data.cards.filter(c => c.college === '' || c.college === ssFilter);
                    // Check for any vote changes
                    let hasChange = false;
                    filteredNew.forEach(card => {
                        card.candidates.forEach(cand => {
                            const cid = cand.candidate_id || cand.student_id || '';
                            if (cid && prevMap[cid] !== undefined && prevMap[cid] !== cand.votes) hasChange = true;
                        });
                    });
                    allCards = data.cards;
                    slides   = expandCards(ssFilter === 'ALL' ? allCards : allCards.filter(c => c.college === '' || c.college === ssFilter));
                    if (lastFetch === null) {
                        // First load — build everything and start
                        current = 0;
                        rebuildSlides();
                        startAuto();
                    } else {
                        // Subsequent poll — patch vote counts in-place to avoid DOM flicker.
                        // Full rebuild if slide count changed OR candidate ranking order changed.
                        const renderedCount = document.querySelectorAll('.ss-slide').length;
                        if (slides.length !== renderedCount || !domOrderMatchesSlides()) {
                            const savedCurrent = current;
                            rebuildSlides();
                            current = Math.min(savedCurrent, slides.length - 1);
                            showSlide(current);
                        } else {
                            patchSlideCounts(slides);
                        }
                    }
                    lastFetch = Date.now();
                }
            } catch(err) {
                clearTimeout(timer);
            }
        }
        fetchSlideshow();
        setInterval(fetchSlideshow, POLL_MS);
        return; // skip the __ssTallyData hook below for standalone
    }

    /* ── For overlay slideshow: hook into tally fetch data ── */
    const _origFetch = window.__ssTallyData;
    Object.defineProperty(window, '__ssTallyData', {
        set: function(val) {
            this._ssTallyDataVal = val;
            if (isOpen && val && val.cards) {
                const prev = Object.assign({}, prevVoteMap);
                allCards = val.cards;
                slides   = expandCards(ssFilter === 'ALL' ? allCards : allCards.filter(c => c.college === '' || c.college === ssFilter));
                // rebuild preserving position
                const savedCurrent = current;
                rebuildSlides();
                current = Math.min(savedCurrent, Math.max(0, slides.length - 1));
                showSlide(current);
            }
        },
        get: function() { return this._ssTallyDataVal; },
        configurable: true,
    });
})();
</script>

<!-- Countdown Timer -->
<script>
(function () {
    var target  = <?= $electionTimestamp ?> * 1000;
    var endTime = <?= $_electionEnd ?>     * 1000;

    function pad(n) { return String(n).padStart(2, '0'); }

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

<?php require_once __DIR__ . '/includes/team-modal.php'; ?>
</body>
</html>
