---
title: 'Fix: score band filter returns 500 on Post Reviews'
type: 'bugfix'
ticket: ''
created: '2026-10-05'
status: 'built'
baseline_revision: '68fd86c2b2964399492355519154399046004580'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Choosing any score band on Post Reviews shows "Could not load your posts": `GET /posts` declares `'sanitize_callback' => 'intval'` for `min_score`/`max_score`, WP calls it with three arguments, and PHP 8 throws `ArgumentCountError` → 500 on every band request (owner report, 2026-10-05; introduced by the story 5.3 review patch).

**Approach:** Drop the two `sanitize_callback` entries (WP's `type: integer` schema sanitizing casts to int; `score_bound()` already clamps 0–100), add a harness check that calls every registered route arg's `sanitize_callback` with WP's real `(value, request, param)` shape, and record the pattern in `memory/lessons.md`.

</frozen-after-approval>

## Implementation Notes

Oneshot: one root cause, ~30 lines, 3 files, no schema/route/template change. Touched `includes/class-pqm-rest-api.php` (two arg entries removed, docblock on `score_bound()` says why), `tests/harness/rest-posts-harness.php` (3-arg sanitize check over all routes + `sanitize_title` stub at WP's real arity — without it the check false-failed on `/post-score` `slug`), `memory/lessons.md`. Proved the check: against the baseline file it fails on `min_score` and `max_score`; with the fix all 8 harnesses pass (rest-posts 88/88).

## Review Triage Log

Quick lens (Sonnet, economy): 5 items — 1 patched, 4 rejected.
- **false** — "3-arg check only catches PHP internals / relies on stub arity": that is the stated goal; the lesson records the stub-arity rule.
- **low / patch** — the why-note sat only on `score_bound()`; added a two-line comment on the args array where `intval` would be re-added.
- **false** — empty `min_score=` → 400: the UI never sends one (`activeParams()` drops null defaults from the request); pre-existing schema validation, unchanged.
- **false** — manual real-site check missing from the diff: it is the owner's step (plan § Verification), not a defect in the change.
- **false** — rules: no violations reported.

## Verification

**Commands:**
- `php -l` on the touched PHP files -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

**Manual checks (if no CLI):**
- Post Reviews on the real site: each score band (Below 41 / 41–70 / 71+) filters without the error notice; a band combined with a status chip works.
