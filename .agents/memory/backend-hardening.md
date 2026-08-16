---
name: Backend hardening
description: Comprehensive backend fixes applied to the election portal — auth, rate-limiting, CSRF, session security, input validation, and error sanitization.
---

## Key decisions and patterns

### Rate limiting (login.php + admin/index.php)
- Helper: `includes/rate-limit.php` — file-based per-IP tracker in `/tmp/rl_*.json`
- Voter: 5 attempts / 15-minute window; admin: 10 attempts / 15-minute window.
- Both call `rateLimitIncrement(key, ip, ttl)` on failure, `rateLimitReset(key, ip)` on success.
- **Why:** No Redis/APCu available; file-based works in single-server PHP env.

### Session regeneration on login
- Voter (login.php) and admin (admin/index.php) both call `session_regenerate_id(true)` after successful auth, before setting session vars.
- **Why:** Prevents session fixation attacks.

### Session cookie security (bootstrap.php)
- `session_set_cookie_params()` AND `session_start()` are both called inside bootstrap.php within the `if (session_status() === PHP_SESSION_NONE)` guard.
- All page files (login, ballot, profile, admin/*, admin/ajax/*) include bootstrap.php FIRST — no individual `session_start()` calls exist except in logout files (which only destroy sessions, never create them).
- **Why:** PHP only applies cookie params to the Set-Cookie header during session creation. If `session_start()` runs before `session_set_cookie_params()`, the HttpOnly/SameSite/Secure flags are silently dropped — this was a real bug in the original code where all files called session_start() on line 2 before including bootstrap.php on line 3.
- Confirmed: live curl shows `Set-Cookie: PHPSESSID=...; path=/; HttpOnly; SameSite=Strict` on both /login.php and /admin/.

### Security response headers (bootstrap.php)
- `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`.

### ARMS API credentials (bootstrap.php + login.php)
- Defined as constants `ARMS_API_KEY` / `ARMS_API_SECRET` in `bootstrap.php`.
- Read from env vars (`ARMS_API_KEY`, `ARMS_API_SECRET`) if set, otherwise fall back to hardcoded values.
- `login.php` `armsGetToken()` uses the constants — never accesses the literal strings directly.
- **Why:** Centralizes credentials; env var override means no source change needed in prod.

### Admin session guard (includes/admin-guard.php)
- Centralises auth check + 30-min inactivity timeout for all admin pages/AJAX.
- Detects AJAX via `HTTP_X_REQUESTED_WITH` and returns JSON 401/403 instead of HTML redirect.
- Include AFTER `header('Content-Type: application/json')` in AJAX files.

### Admin CSRF helpers (includes/admin-guard.php)
- `adminCsrfToken()` — returns/generates `$_SESSION['admin_csrf']` with `bin2hex(random_bytes(32))`.
- `requireAdminCsrf()` — validates `$_POST['_csrf']` or `$_SERVER['HTTP_X_CSRF_TOKEN']` with `hash_equals()`. Exits with 403 JSON on failure.
- `adminCsrfField()` — returns HTML hidden input string; use `<?= adminCsrfField() ?>` inside every admin form.
- Applied to AJAX endpoints: `candidate-delete.php`, `candidate-status.php`, `register-candidate.php`.
- Applied via inline block check to page-level POSTs: `candidates.php`, `users.php`, `settings.php`, `api-accounts.php`.

### Admin JS CSRF wiring (admin/candidates.php)
- `var _adminCsrf = '<?= htmlspecialchars(adminCsrfToken(), ENT_QUOTES) ?>';` at top of `<script>` block.
- `fd.append('_csrf', _adminCsrf)` added to every `FormData` before each `fetch()` call.

### Profile CSRF (profile.php)
- Token: `$_SESSION['profile_csrf']` generated once per session.
- Hidden field `_csrf` in form; verified with `hash_equals()` on POST.
- **Why:** Prevents CSRF forcing a voter to confirm their profile silently.

### Ballot CSRF (ballot.php)
- Token: `$_SESSION['ballot_csrf']`; hidden field `ballot_csrf` in submit form; verified on POST.

### Ballot server-side candidate validation (ballot.php)
- On every POST, fetches all APPROVED Candidate_IDs for the election year.
- Any submitted ID not in that whitelist → rejected.
- If candidate DB unreachable, whitelist is empty and validation is skipped gracefully.

### Input validation (admin pages)
- `admin/users.php`: `userlevel` whitelisted to `['Admin','Voter','Moderator','Viewer']`; `status` whitelisted to `['Active','Inactive']`.
- `admin/api-accounts.php`: `email` validated with `filter_var(FILTER_VALIDATE_EMAIL)`; `status` whitelisted to `['Active','Inactive']`.
- `admin/candidates.php` (via `candidate-status.php`): `Application_Status` whitelisted to `['PENDING','APPROVED','DENIED','DISQUALIFIED']`.

### Error sanitization
- `admin/ajax/search-voter.php`: raw `$e->getMessage()` replaced with generic 'Search unavailable' message.
- `admin/ajax/candidate-delete.php`: raw DB exception sanitized.
- All admin AJAX responses use generic messages; no DB internals exposed.

### XSS prevention
- `login.php` error output uses `htmlspecialchars($error)` — prevents XSS from API response messages.
- All admin/voter pages consistently use `htmlspecialchars()` on any user-controlled data echoed to HTML.

### Voter.Model.php — password hint removed
- Removed `"Hint" => "Expected Last Name: " . $LastName` from incorrect-password error response.

### Position table NULL fix
- Positions 10 (CIT), 11 (CTED_HS), 14 (COL), 16 (GRAD) had NULL Num_Elected_Officer; set to 1.
- position table column is `Position` (not `Position_Name`).

### Voting stats JSON parsing (admin/ajax/voting-stats.php)
- 4-attempt cascade: direct decode → stripslashes → double-encoded unwrap → manual cleanup.
- `while ($stmt->nextRowset()) {}` drains extra MySQL stored-proc result sets.
- `raw_debug` removed from JSON response.

### profile.php — functional
- `requireLogin()` guard; name parsed from ARMS "LASTNAME, FIRSTNAME MIDDLENAME" format.
- Confirm POST sets `$_SESSION['profile_confirmed']`.
