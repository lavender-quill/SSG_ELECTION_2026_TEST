<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

$name = trim($_GET['name'] ?? '');
if (strlen($name) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

use Configuration\Application;

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * Build a word-split WHERE clause: every token must appear in Student_Name.
 * Also matches Student_ID as a whole.
 *
 * Returns [$whereSql, $params]
 */
function buildWordSearch(array $words, string $idLike, bool $withYearFilter): array {
    $conditions = [];
    $params     = [];

    foreach ($words as $w) {
        $like = '%' . mb_strtoupper($w) . '%';
        $conditions[] = 'Student_Name LIKE ?';
        $params[]     = $like;
    }

    $namePart = '(' . implode(' AND ', $conditions) . ')';
    $sql      = "($namePart OR Student_ID LIKE ?)";
    $params[] = $idLike;

    if ($withYearFilter) {
        $sql .= ' AND School_Year = ? AND Semester = ?';
        $params[] = ELECTION_SCHOOL_YEAR;
        $params[] = ELECTION_SEMESTER;
    }

    return [$sql, $params];
}

/**
 * Run a shell curl POST and return decoded JSON array, or null on failure.
 * Must use the system curl binary — PHP libcurl is blocked by Cloudflare.
 */
function armsCurl(string $url, array $headers, string $body = ''): ?array {
    $hFlags = '';
    foreach ($headers as $h) {
        $hFlags .= ' -H ' . escapeshellarg($h);
    }
    $bFlag = $body !== '' ? ' -d ' . escapeshellarg($body) : '';
    $cmd   = 'curl -s -X POST --max-time 10 -k'
           . $hFlags . $bFlag
           . ' -w ' . escapeshellarg("\n__CODE__%{http_code}")
           . ' ' . escapeshellarg($url)
           . ' 2>/dev/null';

    $output = shell_exec($cmd) ?? '';
    $parts  = explode("\n__CODE__", $output);
    $raw    = trim($parts[0] ?? '');
    $code   = intval($parts[1] ?? 0);

    if ($raw === '' || $code >= 400) return null;
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

/**
 * Derive the academic school year string from the two-digit year prefix in a Student_ID.
 * e.g. "26-A-00011" → "2026-2027", "25-A-00012" → "2025-2026"
 */
function schoolYearFromId(string $sid): string {
    if (preg_match('/^(\d{2})-/', $sid, $m)) {
        $yr = (int)$m[1] + 2000;
        return $yr . '-' . ($yr + 1);
    }
    return ELECTION_SCHOOL_YEAR;
}

/**
 * Query ARMS enrollment search for a Student_ID.
 * ARMS requires Student_ID + Semester + School_Year — name-only search is not supported.
 * Tries both semesters so a student enrolled in either term is found.
 * Returns array of ['Student_ID' => ..., 'Student_Name' => ...] rows, or [].
 */
function searchArms(string $searchInput): array {
    // ARMS only supports Student_ID lookups — skip if input isn't an ID
    $cleanId = strtoupper(preg_replace('/\s+/', '', $searchInput));
    if (!preg_match('/^\d{2}-[A-Z]+-\d+$/', $cleanId)) {
        return [];
    }

    $apiKey    = getenv('ARMS_API_KEY')    ?: '';
    $apiSecret = getenv('ARMS_API_SECRET') ?: '';
    if (!$apiKey || !$apiSecret) return [];

    $baseHeaders = [
        'User-Agent: Coderstation-Protocol',
        'Referer: https://jrmsu-election-system.vercel.app/',
        'Origin: https://jrmsu-election-system.vercel.app',
    ];

    // Step 1 — request token
    $tokenResp = armsCurl(
        'https://jrmsu-arms.online/api/version-2/services/credential/token/request',
        array_merge($baseHeaders, [
            'Api-Key: '    . $apiKey,
            'Api-Secret: ' . $apiSecret,
        ])
    );
    if (!$tokenResp) return [];

    $secretKey = $tokenResp['Secret_Key'] ?? $tokenResp['SecretKey'] ?? $tokenResp['secretKey'] ?? '';
    $jwToken   = $tokenResp['JWToken']    ?? $tokenResp['Token']     ?? $tokenResp['jwToken']   ?? '';
    if (!$secretKey || !$jwToken) return [];

    $searchHeaders = array_merge($baseHeaders, [
        'Secret-Key: '           . $secretKey,
        'Token: '                . $jwToken,
        'Authorization: Bearer ' . $jwToken,
        'Content-Type: application/json',
    ]);

    $schoolYear = schoolYearFromId($cleanId);

    // Try current election semester first, then the other one
    $semesters = [ELECTION_SEMESTER];
    if (ELECTION_SEMESTER === '1st') $semesters[] = '2nd';
    else                              $semesters[] = '1st';

    foreach ($semesters as $sem) {
        $body       = json_encode(['Student_ID' => $cleanId, 'Semester' => $sem, 'School_Year' => $schoolYear]);
        $searchResp = armsCurl(
            'https://jrmsu-arms.online/api/version-2/services/student/enrollment/search',
            $searchHeaders,
            $body
        );

        if (!$searchResp) continue;
        if (isset($searchResp['Status']) && stripos($searchResp['Status'], 'Error') !== false) continue;

        // ARMS returns a single Record object
        $r = $searchResp['Record'] ?? null;
        if (is_array($r) && isset($r['Student_ID'])) {
            $sid = trim($r['Student_ID']   ?? $r['student_id']   ?? '');
            $sn  = trim($r['Student_Name'] ?? $r['student_name'] ?? '');
            if ($sid !== '' && $sn !== '') {
                return [['Student_ID' => $sid, 'Student_Name' => $sn]];
            }
        }
    }

    return [];
}

// ── Main search logic ─────────────────────────────────────────────────────────

$db = Application::$SSG_Voter_DBase;
try {
    $pdo = new PDO(
        "mysql:host={$db['Host']};port={$db['Port']};dbname={$db['DBName']};charset=utf8mb4",
        $db['Username'],
        $db['Password'],
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Split input into non-empty tokens (handles commas and spaces)
    $words  = array_values(array_filter(preg_split('/[\s,]+/', $name), fn($w) => strlen($w) >= 1));
    $idLike = '%' . $name . '%';

    // Pass 1: current election school year + semester
    [$where, $params] = buildWordSearch($words, $idLike, true);
    $stmt = $pdo->prepare(
        "SELECT Student_ID, Student_Name
         FROM student
         WHERE $where
         ORDER BY Student_Name
         LIMIT 20"
    );
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Pass 2: all years (catches students not enrolled in current year)
    if (empty($results)) {
        [$where2, $params2] = buildWordSearch($words, $idLike, false);
        $stmt2 = $pdo->prepare(
            "SELECT Student_ID, Student_Name
             FROM student
             WHERE $where2
             ORDER BY Student_Name
             LIMIT 20"
        );
        $stmt2->execute($params2);
        $results = $stmt2->fetchAll();
    }

    // Pass 3: ARMS API — for students not yet in the local voter DB (e.g. 25-A-*, 26-A-*)
    if (empty($results)) {
        $results = searchArms($name);
    }

    echo json_encode(['success' => true, 'results' => $results]);

} catch (Exception $e) {
    // DB unavailable — still try ARMS
    $armsResults = searchArms($name);
    echo json_encode(['success' => true, 'results' => $armsResults]);
}
