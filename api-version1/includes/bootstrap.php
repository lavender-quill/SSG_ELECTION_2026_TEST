<?php
require_once dirname(__DIR__) . '/services/autoloader.php';
date_default_timezone_set('Asia/Manila');

// ── Load .env file for local development ──────────────────────────────────────
$envFile = dirname(dirname(__DIR__)) . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue; // Skip invalid lines
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) { // Don't override existing environment variables
            putenv("$key=$value");
        }
    }
}

\Configuration\Application::init();

// ── Never expose PHP errors to the browser in any environment ─────────────────
ini_set('display_errors', '0');
ini_set('log_errors',     '1');

// ── Detect HTTPS once — reused for session cookie and HSTS ───────────────────
$_isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// ── Secure session cookie settings + session start ───────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $_isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Security response headers ─────────────────────────────────────────────────
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy
    // script/style need 'unsafe-inline' — all JS and CSS are inline in this app.
    // img-src includes data: for base64 candidate photos.
    // frame-ancestors 'none' enforces no iframe embedding (supersedes X-Frame-Options).
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://replit-cdn.com; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "object-src 'none';"
    );

    // HSTS — only sent over HTTPS; tells browsers to always use HTTPS for 1 year
    if ($_isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── ARMS API credentials ──────────────────────────────────────────────────────
// Override via environment variables (ARMS_API_KEY / ARMS_API_SECRET) in production.
define('ARMS_API_KEY',    getenv('ARMS_API_KEY')    ?: 'asaguin.jr@gmail.com');
define('ARMS_API_SECRET', getenv('ARMS_API_SECRET') ?: 'D43m0nCh41N');

// ── Data directory — lives outside the web root, never browser-accessible ─────
define('DATA_DIR', dirname(dirname(__DIR__)) . '/data');

// Load runtime settings (editable from admin panel)
$_runtimeSettingsFile = DATA_DIR . '/settings.json';
$_runtimeSettings = [];
if (file_exists($_runtimeSettingsFile)) {
    $_runtimeSettings = json_decode(file_get_contents($_runtimeSettingsFile), true) ?: [];
}

define('ELECTION_SCHOOL_YEAR', $_runtimeSettings['school_year'] ?? '2026-2027');
define('ELECTION_SEMESTER',    $_runtimeSettings['semester']    ?? '2nd');

/**
 * Normalize a college identifier to a canonical code.
 * Accepts short codes like CCS and long labels like "CCS COLLEGE OF COMPUTER STUDIES".
 */
function normalizeCollegeCode($value): string {
    $raw = strtoupper(trim((string)($value ?? '')));
    if ($raw === '') {
        return '';
    }

    $knownCodes = ['CCS', 'CBA', 'CTED', 'CAS', 'CCJE', 'CIT', 'CME', 'CNAHS', 'COE', 'COL', 'GRAD', 'HS', 'SOM'];
    foreach ($knownCodes as $code) {
        if ($raw === $code) {
            return $code;
        }
    }

    $tokens = preg_split('/[\s\-_]+/', $raw) ?: [];
    foreach ($tokens as $token) {
        if (in_array($token, $knownCodes, true)) {
            return $token;
        }
    }

    $phraseMap = [
        'COMPUTER STUDIES' => 'CCS',
        'BUSINESS ADMIN' => 'CBA',
        'TEACHER EDUCATION' => 'CTED',
        'ARTS' => 'CAS',
        'CRIMINAL JUSTICE' => 'CCJE',
        'INDUSTRIAL TECHNOLOGY' => 'CIT',
        'MARINE ENGINEERING' => 'CME',
        'NURSING' => 'CNAHS',
        'ENGINEERING' => 'COE',
        'LAW' => 'COL',
        'GRADUATE SCHOOL' => 'GRAD',
        'HIGH SCHOOL' => 'HS',
        'MEDICINE' => 'SOM',
    ];

    foreach ($phraseMap as $phrase => $code) {
        if (str_contains($raw, $phrase)) {
            return $code;
        }
    }

    return $raw;
}

/**
 * Save runtime settings to the settings file.
 */
function saveRuntimeSettings(array $data): bool {
    $file = DATA_DIR . '/settings.json';
    $existing = [];
    if (file_exists($file)) {
        $existing = json_decode(file_get_contents($file), true) ?: [];
    }
    $merged = array_merge($existing, $data);
    return file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Capture the echo output of a model static call and decode it.
 */
function callModel(callable $fn): array {
    try {
        ob_start();
        $fn();
        $raw     = ob_get_clean();
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['Status' => 'Error: Could not parse API response'];
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) ob_end_clean();
        return ['Status' => 'Error: ' . $e->getMessage()];
    }
}

function unwrap(array $response): array {
    if (isset($response['Result']) && is_array($response['Result'])) {
        return $response['Result'];
    }
    return $response;
}

function isError(array $r): bool {
    $status = $r['Status'] ?? '';
    return stripos($status, 'Error:') !== false || stripos($status, 'Error ') !== false;
}

function applyCandidateJsonNameOverrides(array $candidates): array {
    $file = DATA_DIR . '/candidate_names.json';
    if (!file_exists($file)) {
        return $candidates;
    }

    $names = json_decode(file_get_contents($file), true) ?: [];
    if (empty($names)) {
        return $candidates;
    }

    foreach ($candidates as &$candidate) {
        $sid = trim((string)($candidate['Student_ID'] ?? $candidate['student_id'] ?? ''));
        if ($sid !== '' && isset($names[$sid])) {
            $name = (string)$names[$sid];
            $candidate['Candidate_Name'] = $name;
            $candidate['Student_Name']   = $name;
            $candidate['Name']           = $name;
            $candidate['Full_Name']      = $name;
            $candidate['First_Name']     = $name;
            $candidate['Last_Name']      = '';
        }
    }
    unset($candidate);

    return $candidates;
}

function filterCandidatesToJsonSet(array $candidates): array {
    $file = DATA_DIR . '/candidate_names.json';
    if (!file_exists($file)) {
        return $candidates;
    }

    $names = json_decode(file_get_contents($file), true) ?: [];
    if (empty($names)) {
        return $candidates;
    }

    $allowed = array_fill_keys(array_keys($names), true);
    $filtered = [];

    foreach ($candidates as $candidate) {
        $sid = trim((string)($candidate['Student_ID'] ?? $candidate['student_id'] ?? ''));
        if ($sid !== '' && isset($allowed[$sid])) {
            $candidate['Candidate_Name'] = (string)($names[$sid] ?? $candidate['Candidate_Name'] ?? $candidate['Student_Name'] ?? $sid);
            $candidate['Student_Name']   = (string)($names[$sid] ?? $candidate['Student_Name'] ?? $candidate['Candidate_Name'] ?? $sid);
            $candidate['Name']           = (string)($names[$sid] ?? $candidate['Name'] ?? $candidate['Student_Name'] ?? $sid);
            $candidate['Full_Name']      = (string)($names[$sid] ?? $candidate['Full_Name'] ?? $candidate['Student_Name'] ?? $sid);
            $filtered[] = $candidate;
        }
    }

    return $filtered;
}

function requireLogin(): void {
    if (empty($_SESSION['logged_in'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Return the path to the profile-confirmations store.
 */
function _confirmedProfilesFile(): string {
    return DATA_DIR . '/confirmed_profiles.json';
}

/**
 * Check whether a student has already confirmed their profile for a given school year.
 */
function isProfileConfirmed(string $studentId, string $schoolYear): bool {
    $file = _confirmedProfilesFile();
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true) ?: [];
    $key  = $schoolYear . '::' . strtoupper(trim($studentId));
    return isset($data[$key]);
}

/**
 * Persist that a student has confirmed their profile for a given school year.
 */
function markProfileConfirmed(string $studentId, string $schoolYear): void {
    $file = _confirmedProfilesFile();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    $key        = $schoolYear . '::' . strtoupper(trim($studentId));
    $data[$key] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Check whether a student has already cast their vote for a given school year.
 * Primary source: election DB cast_votes table (survives redeploys).
 * Fallback: local JSON cache (used when DB is unreachable).
 */
function isVoteCast(string $studentId, string $schoolYear): bool {
    $sid = strtoupper(trim($studentId));
    // Primary: DB
    try {
        $cfg  = \Configuration\Application::$SSG_Election_DBase;
        $pdo  = new PDO(
            "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
            $cfg['Username'], $cfg['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cast_votes WHERE Student_ID = ? AND School_Year = ?');
        $stmt->execute([$sid, $schoolYear]);
        if ((int)$stmt->fetchColumn() > 0) return true;
    } catch (\Throwable $e) {
        // DB unreachable — fall through to JSON cache
    }
    // Fallback: JSON cache
    $file = DATA_DIR . '/cast_votes.json';
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true) ?: [];
    return isset($data[$schoolYear . '::' . $sid]);
}

/**
 * Clear the session's voted flag if an admin vote-reset happened after the vote was cast.
 * Call this before checking $_SESSION['voted'] in ballot.php and success.php.
 */
function clearStaleVoteSession(string $schoolYear): void {
    if (empty($_SESSION['voted'])) return;
    $resetFile = DATA_DIR . '/vote_reset.json';
    if (!file_exists($resetFile)) return;
    $resets  = json_decode(file_get_contents($resetFile), true) ?: [];
    $resetAt = (int)($resets[$schoolYear] ?? 0);
    if ($resetAt <= 0) return;
    $votedAt = (int)($_SESSION['voted_at'] ?? 0);
    // If voted_at is absent or older than the reset, this vote was wiped — clear it
    if ($votedAt <= $resetAt) {
        unset($_SESSION['voted'], $_SESSION['voted_at']);
    }
}

/**
 * Persist that a student has cast their vote for a given school year.
 * Writes to DB (primary) and JSON file (backup — survives DB outage).
 */
function markVoteCast(string $studentId, string $schoolYear): void {
    $sid = strtoupper(trim($studentId));
    // Primary: DB
    try {
        $cfg = \Configuration\Application::$SSG_Election_DBase;
        $pdo = new PDO(
            "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
            $cfg['Username'], $cfg['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->prepare('INSERT IGNORE INTO cast_votes (Student_ID, School_Year) VALUES (?, ?)')
            ->execute([$sid, $schoolYear]);
    } catch (\Throwable $e) {
        error_log('markVoteCast DB write failed: ' . $e->getMessage());
    }
    // Backup: JSON cache
    $file = DATA_DIR . '/cast_votes.json';
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $data[$schoolYear . '::' . $sid] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Load the global election schedule for a given school year.
 * Reads JSON first; falls back to DB and self-heals the JSON file.
 */
function loadElectionSchedule(string $schoolYear): array {
    $file = DATA_DIR . '/election_schedule.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
        if (!empty($data[$schoolYear])) return $data[$schoolYear];
    }
    // Fallback: read from DB
    try {
        $cfg  = \Configuration\Application::$SSG_Election_DBase;
        $pdo  = new PDO(
            "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
            $cfg['Username'], $cfg['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare(
            "SELECT Time_Start, Time_End, School_Year FROM election_schedule
             WHERE School_Year = ? AND (College IS NULL OR College = '')
             ORDER BY Record_ID DESC LIMIT 1"
        );
        $stmt->execute([$schoolYear]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['Time_Start'] && $row['Time_End']) {
            $sched = [
                'School_Year' => $row['School_Year'],
                'Time_Start'  => (int)$row['Time_Start'],
                'Time_End'    => (int)$row['Time_End'],
            ];
            // Self-heal: write back to JSON
            $existing = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
            $existing[$schoolYear] = $sched;
            @file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT), LOCK_EX);
            return $sched;
        }
    } catch (\Throwable $e) { /* DB unreachable */ }
    return [];
}

/**
 * Load all per-college schedules.
 * Reads JSON first; falls back to DB and self-heals the JSON file.
 */
function loadCollegeSchedules(): array {
    $file = DATA_DIR . '/college_schedules.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
        if (!empty($data)) return $data;
    }
    // Fallback: read from DB
    try {
        $cfg  = \Configuration\Application::$SSG_Election_DBase;
        $pdo  = new PDO(
            "mysql:host={$cfg['Host']};port={$cfg['Port']};dbname={$cfg['DBName']};charset=utf8mb4",
            $cfg['Username'], $cfg['Password'],
            [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $rows = $pdo->query(
            "SELECT College, Time_Start, Time_End, School_Year FROM election_schedule
             WHERE College IS NOT NULL AND College != ''
             ORDER BY Record_ID DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $schedules = [];
            foreach ($rows as $r) {
                $col = $r['College'];
                if (!isset($schedules[$col])) {
                    $schedules[$col] = [
                        'College'     => $col,
                        'Time_Start'  => (int)$r['Time_Start'],
                        'Time_End'    => (int)$r['Time_End'],
                        'School_Year' => $r['School_Year'],
                    ];
                }
            }
            // Self-heal: write back to JSON
            @file_put_contents($file, json_encode($schedules, JSON_PRETTY_PRINT), LOCK_EX);
            return $schedules;
        }
    } catch (\Throwable $e) { /* DB unreachable */ }
    return [];
}

/**
 * Append one line to the vote audit log for every successfully cast ballot.
 * The log file lives outside the web root and is never browser-accessible.
 */
function writeVoteAuditLog(string $voterId, string $schoolYear, string $ip): void {
    $logDir  = dirname(dirname(__DIR__)) . '/logs';
    $logFile = $logDir . '/vote_audit.log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    $entry = sprintf(
        "[%s] VOTE_CAST voter=%s year=%s ip=%s\n",
        date('Y-m-d H:i:s T'),
        preg_replace('/[^A-Za-z0-9\-_]/', '', $voterId),
        preg_replace('/[^A-Za-z0-9\-_]/', '', $schoolYear),
        filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'invalid'
    );
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
