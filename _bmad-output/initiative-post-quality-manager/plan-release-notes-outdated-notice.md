---
title: 'The release notes describe the outdated-workflow state the page shows today'
type: 'chore'
ticket: ''
created: '2026-10-29'
status: 'built'
baseline_revision: 'd9b81d1690ba5e46ec427bfa279ece86d5ce6ff3'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Five paragraphs of `docs/release-notes/unreleased.md` (L11, L13, L27, L41, L43) tell readers that the n8n Workflow page says "Workflow update available". Story 67 removed that notice on 2026-10-19, and these notes ship with the next release (3.10.0). An administrator who follows them looks for words the page no longer shows.

**Approach:** In each paragraph, replace the quoted notice with what the page shows for an outdated workflow today (`post-quality-manager/admin/class-pqm-admin.php` L1586–1601):
- the sentence naming the workflow's version and the one the plugin needs;
- on a managed site, "The plugin can update it in place: the workflow's nodes are replaced; its credentials, webhook path and name stay.";
- the **Update workflow** or **Re-import the workflow** button.

Each paragraph's other claims are left unchanged, and so is every other paragraph. Close the deferred-work entry that recorded the gap.

</frozen-after-approval>

## Implementation Notes

Oneshot: one docs file plus the deferred-work entry. No code, no route, no template. Run in-session on Opus 5.5 (session rule); `bmad-build` rendered by hand from the workspace root (default route, routed `oneshot` by step 2).

## Plan Change Log

## Review Triage Log

Pass 1, 2026-10-29, Quick lens (Opus): 5 findings: medium 3, low 2; 3 patched, 2 deferred as one entry.
- medium, patch: L13's new trigger ("older than this plugin needs") also fires for v6 and v7 workflows, whose production URL does not change on re-import (`postquality_webhook_path()` is per site host since v6; 3.6.0–3.9.0 shipped v6). It also fires on managed sites, which get Update workflow, not Re-import. Patched: the trigger is now the page's own sentence for that case, "A re-import gives it a new production URL." (`class-pqm-admin.php` L1603, shown only unmanaged and below v6).
- medium, defer: L13 "paste it as the n8n Webhook URL in Settings". Read-only since story 67 (L826–833). Predates this change, and the frozen intent keeps other claims unchanged. Recorded in `deferred-work.md` with the next finding.
- medium, defer: old section names ("Workflow File section", "n8n Instance section"). They appear in L11, L19, L23 and L25, in `readme.txt` L30, in `docs/policies/privacy.md` L19, and in the UI string `class-pqm-n8n.php` L437 (asserted in `tests/harness/lib/n8n-world.php` L248). Same root cause as the finding above (story 67's page rework not swept), and it predates this change. Recorded as one entry: code + harness + readme + policy, so it needs a bug ticket.
- low, patch: L11 named no button, against the Approach's third item, and the `resolved:` line overstated what each paragraph says. Patched: L11 offers Update workflow (managed) or Re-import the workflow, and "Until you update or re-import"; the `resolved:` line now names what each paragraph says.
- low, patch: L41 said "managed" twice, and the condition did not match the code (the sentence also shows for the Free variant, and only where Update workflow is offered: `$state['managed']`, L1600; fix `update`, L1485). Patched: "When the n8n Workflow page offers Update workflow for an older workflow or the Free variant, it now says the same".

## Verification

**Commands:**
- `git grep -n "Workflow update available"` -- expected: only the jsdom fixture `tests/js/setup-connect.test.js:39` (page markup in a test, not shown to anyone) and `_bmad-output/` records.
- every string the rewritten paragraphs quote is found in `post-quality-manager/admin/class-pqm-admin.php`.
