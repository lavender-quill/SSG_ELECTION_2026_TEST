---
name: DB enum status values
description: candidate_position.Application_Status enum must use exact uppercase values to avoid MySQL silent truncation
---

The `candidate_position.Application_Status` column is `enum('PENDING','APPROVED','DENIED','DISQUALIFIED')`.

**Why:** MySQL in non-strict mode silently stores `''` (empty string, ordinal 0) when an UPDATE or INSERT provides a value that doesn't match any enum entry (e.g. `'Rejected'`, `'Approved'`, `'Removed'`). The SP `Candidate_Position_Status_Update` takes the value as VARCHAR and passes it directly to the UPDATE — so case matters for INSERT/UPDATE even though SELECT comparison is case-insensitive.

**How to apply:**
- PHP query calls: always pass `'PENDING'`, `'APPROVED'`, `'DENIED'`, `'DISQUALIFIED'`
- JavaScript setStatus() calls and all `Application_Status` assignments: same uppercase values
- `'Rejected'` does NOT exist in the enum — use `'DENIED'`
- `'Removed'` does NOT exist in the enum — use `'DISQUALIFIED'`
- Badge display logic must check `stripos($sc,'deni')` and `stripos($sc,'disqual')` to colour DENIED/DISQUALIFIED as red (badge-rejected)
