# Agent Memory — JRMSU SSG Election Portal

- [DB enum status values](db-enum-status.md) — candidate_position.Application_Status enum is PENDING/APPROVED/DENIED/DISQUALIFIED (all uppercase); passing mixed-case in non-strict MySQL silently stores empty string.
- [election_schedule timestamps](election-schedule-timestamps.md) — Time_Start/Time_End are DOUBLE (Unix timestamps); must pass strtotime() result from PHP, not raw datetime-local strings.
- [Backend hardening session](backend-hardening.md) — comprehensive backend fixes applied; key patterns for auth, rate-limiting, CSRF, and admin guard.
