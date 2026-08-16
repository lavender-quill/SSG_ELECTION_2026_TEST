# JRMSU SSG E-Ballot Portal

A secure, PHP-based online election portal for Jose Rizal Memorial State University's Student Supreme Government elections.

## Stack

- **Language:** PHP 8.2 (built-in dev server)
- **Databases:** Remote MySQL at `153.92.15.54` (4 databases: Manage, Voter, Candidate, Election)
- **Frontend:** Vanilla HTML/CSS/JS (no build step)
- **Document root:** `api-version1/`

## How to run

The workflow `Start application` runs:
```
php -S 0.0.0.0:5000 -t api-version1
```

## Environment / Secrets

| Key | Type | Purpose |
|-----|------|---------|
| `DB_PASSWORD` | Secret | Shared password for all four remote MySQL databases |
| `SESSION_SECRET` | Secret | PHP session signing key |

Optional overrides (default to values in `Application.Config.php`):
- `DB_HOST`, `DB_PORT`
- `DB_MANAGE_USER`, `DB_MANAGE_NAME`
- `DB_VOTER_USER`, `DB_VOTER_NAME`
- `DB_CANDIDATE_USER`, `DB_CANDIDATE_NAME`
- `DB_ELECTION_USER`, `DB_ELECTION_NAME`
- `ARMS_API_KEY`, `ARMS_API_SECRET`

## Key URLs

| Path | Description |
|------|-------------|
| `/` | Public voter portal homepage |
| `/admin/` | Admin panel login |
| `/tally.php` | Live vote tally page |
| `/contact.php` | Contact page |

## Project structure

```
api-version1/
  Configuration/   — DB config, routes, messages
  Libraries/       — PDO, JWT, crypto, PDF, email
  Presets/         — Static assets (CSS, images, JS)
  Templates/       — Email templates
  admin/           — Admin panel pages + AJAX handlers
  ajax/            — Public AJAX endpoints
  services/        — Autoloader and service classes
  includes/        — bootstrap.php (session, headers, helpers)
data/              — File-based storage (parties, schedules, votes, settings)
logs/              — Vote audit log (outside web root)
```

## Notes

- The `data/` directory is file-based fallback storage; the app syncs from DB and writes back to JSON.
- DB connection errors are caught silently — the app degrades gracefully when the DB is unreachable.
- All DB passwords are loaded at runtime from the `DB_PASSWORD` secret via `Application::init()`.

## User preferences

- Keep existing PHP/vanilla JS stack — do not migrate to a different framework.
