---
title: 'The readme has a broken n8n sentence and a caption that misses its screenshot'
type: 'bugfix'
ticket: '140'
created: '2026-11-19'
status: 'built'
baseline_revision: '7ad6510'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** In `post-quality-manager/readme.txt`, the External services n8n entry ends "You run your own n8n instance; n8n's [terms] and [privacy policy]." (no verb after the semicolon), and Screenshots caption 1 says "Click Review on a post to send it to your workflow." while `assets/screenshot-1.png` shows Details buttons and the Review selected bar.

**Approach:** Two copy edits in `readme.txt`, nothing else: the n8n line becomes "You run your own n8n instance; see n8n's [terms](…) and [privacy policy](…)." (links unchanged); caption 1 becomes "Post Reviews: every post with its latest score. Select posts to review them in one go, or open Details on a post. Demo posts, fictional scores." No image, no other entry, no code.

</frozen-after-approval>

## Implementation Notes

- Oneshot route: two lines of one file plus this plan; the ticket and story 38's `after` line go in their own `chore(tickets)` commit, as bugs 138 and 139 did. No code, schema, REST or template change.
- `bmad-build` applied by hand from the workspace root (CLAUDE.md, owner rule 2026-10-16).
- The Anthropic and Freemius entries end with the same kind of reference line ("Anthropic's [terms] and [privacy policy].") after a full stop; they read as references and stay. Only the n8n line follows a semicolon clause, which made it read as a broken sentence.
- No release-notes line: nothing a site owner meets in WP Admin changes. 4.0.7 still needs `docs/release-notes/4.0.7.md` saying there are no upgrade steps (`/release` step 3).

## Review Triage Log

**Pass 1 (quick lens, 2026-11-19):** high 0, medium 0, low 1, false 0, maybe-false 0.

- The plan counted 3 files while the change touched 4 (the story 38 `after` line too), past the oneshot route's 3-file limit — low, patched: the ticket filing (ticket 140 and story 38's `after`) is its own `chore(tickets)` commit, the fix commit carries `readme.txt` and this plan; the note is corrected.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed (no code changed)

**Manual checks (if no CLI):**
- Story 38's directory preview on 4.0.7: More Details → Description shows the n8n line as a sentence; Screenshots caption 1 matches the image.
