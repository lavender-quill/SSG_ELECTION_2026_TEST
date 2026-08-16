<?php
/**
 * Admin session guard — include at the top of every admin page/endpoint.
 * Enforces login + 30-minute inactivity timeout.
 * Detects AJAX requests and returns JSON errors instead of HTML redirects.
 * Provides CSRF token helpers for admin POST/AJAX actions.
 */

define('ADMIN_SESSION_TIMEOUT', 1800);

$_adminIsAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (empty($_SESSION['admin_logged_in'])) {
    if ($_adminIsAjax) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Location: /admin/');
    exit;
}

if (isset($_SESSION['admin_last_activity'])
    && (time() - $_SESSION['admin_last_activity']) > ADMIN_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    if ($_adminIsAjax) {
        http_response_code(401);
        echo json_encode(['error' => 'Session expired. Please log in again.']);
        exit;
    }
    header('Location: /admin/?timeout=1');
    exit;
}

$_SESSION['admin_last_activity'] = time();

// ── CSRF token helpers ────────────────────────────────────────────────────────

/**
 * Return (and lazily create) the admin CSRF token stored in the session.
 */
function adminCsrfToken(): string {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

/**
 * Verify the CSRF token submitted by an admin AJAX request.
 * Exits with a 403 JSON error on failure.
 */
function requireAdminCsrf(): void {
    $submitted = trim(
        $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''
    );
    if (!$submitted || !hash_equals(adminCsrfToken(), $submitted)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

/**
 * Render a hidden CSRF input field for use inside HTML forms.
 */
function adminCsrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES) . '">';
}

// Initialise the token so it's always available for rendering into pages.
adminCsrfToken();
