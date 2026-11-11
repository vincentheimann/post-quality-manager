---
title: 'Bug 120 — step 1 places the n8n workflow menu right after the workflow''s name, at the top left'
type: 'bugfix'
ticket: '120'
created: '2026-11-10'
status: done
baseline_revision: '189a62344e6887bf1279e1f41aba792e7071a5f3'
route: 'oneshot'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/bug-manual-step-1-puts-the-workflow-menu-top-right.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The n8n Workflow page's Set up by hand step 1 tells the user to open the workflow menu "(the three dots at the top right)"; n8n 2.36+ and n8n 3 show those dots right after the workflow's name, at the top left, and Publish at the top right (bug 120, source read at `n8n@2.46.7`, `n8n@3.1.0`, `n8n@3.2.0`).

**Approach:** Replace the parenthesis with the owner-approved words "(the three dots right after the workflow's name, at the top left)" in `PostQuality_Admin`'s step 1 sentence, the rest of the sentence unchanged; update the three harness rows that quote it (`render-matrix-harness.php` with and without a known n8n address, `free-build-harness.php` on the Free build) and make them fail if "at the top right" returns to the page; update the `memory/n8n-provisioning.md` line that names this bug.

</frozen-after-approval>

## Implementation Notes

- 2026-11-10, coordinator: oneshot pinned by the owner at `/prioritize` ("refine, validate, build it as a oneshot"); one translatable string in `post-quality-manager/admin/class-pqm-admin.php` (L1955), three harness rows, one memory line; no schema, REST, template or JS change, so no `unreleased.md` line (a wording fix sites meet without needing to be told).
- 2026-11-10, coordinator (built directly, the oneshot route): `class-pqm-admin.php` L1955, the parenthesis now "(the three dots right after the workflow\'s name, at the top left)" (`\'` as L1600 and L1998 write it; real WordPress escapes it to `&#039;`, the harness stubs pass it through). `render-matrix-harness.php`: `$step1` and the no-address row carry the new words; a new row, "bug 120: no step sends the user to the top right, with or without a known n8n address", checks both renders with `/top[ -]right/i`. `free-build-harness.php`: AC4's row carries the new words; a new row checks the Free build's page and its two sources the same way. `memory/n8n-provisioning.md` L36: the source reading of the three tags replaces "(the page's "at the top right" is bug 120)", and its opening says the 2026-10-14 check missed the position. Verified: `php -l` on the three PHP files; every harness green (25 files; render-matrix 463, free-build 101); the shipped-source grep empty; `git diff --check` clean. Mutation, run from a script file after a shell-escaped first try was stopped by its own assertion before any write (lessons 2026-10-24): the old words back → render-matrix 4 rows fail, free-build 1; "At the top right" added to the re-import note → both bug 120 rows fail under their own label; the file restored byte for byte each time. No JS touched, so no Jest run.
- 2026-11-10, after #282 merged (2b4a5f2; its CI 4/4 green): done on the owner's word, marked with `tickets.py mark`. The fix ships in release 3.13.3 (release-please #283).
- 2026-11-10, release 3.13.3 (release commit 6938f4c, a pre-release), `/release` check green:
  - Release run 42863897102; the zip's sha256 `7e11a79a…670d` equals the release asset's digest and the Freemius upload.
  - The zip has one root folder, `admin/build` inside, header and `POSTQUALITY_VERSION` 3.13.3, and both templates. The new step 1 words appear once and "at the top right" nowhere.
  - Freemius deployment 172721, pending. "Freemius records Requires PHP 8.1 for 3.13.3, as readme.txt says." That is the same floor as 3.13.2, so no ticket.
  - "PHP identical: 13 files."
  - `/lcm` not due: `memory/lifecycle.md` shows checks on 2026-11-03.
  - Not run: the install on a clean WordPress 6.6, whose site comes with story 38.

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-11-10): 4 findings, all low, all patched by the coordinator (no reject, no defer).**

- low, patch: the Free build's guard sat inside story 111's AC4 row, so a returning "at the top right" failed under story 111's label (the lens proved it on a scratch copy). It is now its own `check()`, labelled bug 120, as in the render-matrix harness.
- low, patch: § Verification's `grep -rn "top right" post-quality-manager tests` could never be empty: it hits the guards themselves and minified vendor JS. It is narrowed to the shipped sources (`admin`, `includes`, `src`), which are empty.
- low, patch: AC2 says "anywhere on the page", but both guards were an exact, case-sensitive `str_contains`, so "At the top right" or "top-right" would pass. Both now use `/top[ -]right/i`; the Free row also scans `pqm-admin.js` and `class-pqm-admin.php`, which the row above it already reads. Rejected part: scanning the React sources (`src/setup/`, `src/n8n-connection/`). That is more than a direct correction, today's grep of them is empty, and the manual steps live in PHP only.
- low, patch: `memory/n8n-provisioning.md` L36 still opened by saying the 2026-10-14 check verified "where they sit". It now says that check confirmed the labels but missed the menu's position (bug 120).

## Verification

**Commands:**
- `php -l post-quality-manager/admin/class-pqm-admin.php` -- no syntax errors.
- every harness in `tests/harness/`, with `-d sys_temp_dir=<scratchpad>` -- all green.
- `grep -rniE "top[ -]right" post-quality-manager/admin post-quality-manager/includes src` -- nothing (the shipped sources; the harness guards, memory and vendor JS name it on purpose or by chance).
- `git diff --check` -- clean.
