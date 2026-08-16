---
name: election_schedule timestamps
description: election_schedule.Time_Start/Time_End are DOUBLE columns storing Unix timestamps; must convert before passing
---

`election_schedule.Time_Start` and `Time_End` are `DOUBLE` (Unix timestamps). The SP `election_schedule_create` receives them as JSON numbers and inserts them directly. The check SP `election_schedule_check` uses `UNIX_TIMESTAMP(CONVERT_TZ(NOW(),'+00:00','+08:00'))` to get Manila-time now and compares numerically.

**Why:** HTML `datetime-local` inputs send strings like `2026-05-29T08:00`. MySQL casts that string to DOUBLE by extracting the leading integer, storing just `2026` — which is a timestamp from Jan 1970, always "closed".

**How to apply:**
In settings.php (and anywhere else schedules are created), convert before passing to the SP:
```php
$ts = strtotime(str_replace('T', ' ', $tsRaw));
$te = strtotime(str_replace('T', ' ', $teRaw));
```
bootstrap.php sets `date_default_timezone_set('Asia/Manila')` so strtotime() produces Manila-timezone Unix timestamps, matching the SP's comparison.
