---
title: 'The setup guide points a site without n8n to self-hosting, not to n8n Cloud'
type: 'bugfix'
ticket: ''
created: '2026-10-29'
baseline_revision: '271ea8e329228f9a17257c5775d490e583c59570'
status: 'done'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
- '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The setup guide's n8n step tells a site without n8n to "Sign up for n8n Cloud at n8n.io". That path is unwalked (`docs/user-paths.md` A4), n8n Cloud's free trial has no public API, which the guide's provisioning needs, and the owner's launch gate is a Cloud walk before Cloud is named; postquality.com already sends visitors to self-hosting only (deferred-work entry from story 86's honesty review, 2026-10-22).

**Approach:** reword that one muted paragraph in `src/setup/steps.js` to the site's /docs/ wording: host n8n on a server this site can reach and that can reach it back, n8n's self-hosting guide named as plain text, the reach-back sentence kept without naming Cloud. No other string, file or behaviour changes.

</frozen-after-approval>

## Implementation Notes

- 2026-10-29, oneshot (session 8346ecaf, Opus 5.5; skill rendered by hand from the workspace root): route oneshot, one string in one file; no `languages/` folder, so no `.pot` to regenerate. README.md L40 names n8n Cloud only as a host that cannot reach a local site, which stays true: not touched. Records that name Cloud (ADR 0015, user-paths A4, the external-services policy, the bug report template) describe decisions or facts: not touched.

## Plan Change Log

## Review Triage Log

Pass 1 (2026-10-29, lens `quick`, Opus): 0 high, 0 medium, 2 low, 1 false, 0 maybe-false; plus one out-of-scope defer.
- low, patch: "so n8n cannot reach a local or password-protected site" read as a limit of n8n itself; an n8n on the same network reaches a local site (the plugin's calls are plain `wp_remote_post`, `docs/local-verification.md` posts to 127.0.0.1). Now "so an n8n on another network cannot reach…".
- low, patch: "docs.n8n.io" is n8n's docs home, not the self-hosting guide the sentence names (postquality.com /docs/ links `https://docs.n8n.io/hosting/`). Now "docs.n8n.io/hosting".
- false, reject: the Implementation Notes miss README L39 "(self-hosted or cloud)"; the fix would edit this build's plan. README L39 itself is outside the frozen intent ("no other string, file"): deferred, entry written in the sprint records PR (`chore/sprint-2026-10-29-records`) so the two open PRs do not both edit the file's last entry.
- Re-verified after the patches: `npm run build` compiles, `npm run lint:js` clean, `npm run test:unit` 174 passed, `grep -rn "n8n Cloud" src/` empty.

## Verification

**Commands:**
- `npm run build`, `npm run lint:js`, `npm run test:unit` -- expected: compiles, clean, 174 passed
- `grep -rn "n8n Cloud" src/` -- expected: no output

**Manual checks (if no CLI):**
- The setup guide's n8n step on the showcase shows the new paragraph.
