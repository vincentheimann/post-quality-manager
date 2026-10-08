---
title: "The first public release's notes cover every silent upgrade behavior"
type: 'chore'
ticket: '15'
created: '2026-10-08'
status: 'built'
baseline_revision: 'f06a8bb9a5a718168bf21ecd4294f6afdb643da3'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['docs/brand.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A site upgrading from 2.x or a 3.x test build meets five behaviors nobody tells it about (author quota, the permission the n8n credential user needs, role rights kept as set, "View as" gone, a workflow to re-import), and no release carries human-readable notes: the release workflow appends `docs/release-notes/<version>.md` when present, but the file has never existed.

**Approach:** Write the notes for the first public release in `docs/release-notes/unreleased.md` (renamed to `<version>.md` by the launch PR), make "add your line to the unreleased notes" part of the Definition of Done, and have `/release` check the notes before a release PR is merged. Docs only — no plugin or workflow change.

## Boundaries & Constraints

**Always:** Plain words a site owner understands; the full name "Post Quality Manager" on first mention, "Post Quality" after (`docs/brand.md` § Naming); every line says what changed and what to do; the file starts at `##` level because the release workflow appends it below release-please's own `###`-level changelog; the facts match the code as of today (quota 20, 0 = unlimited; Settings → Monthly Quota → AI Actions per User; Administrator, Editor, Author hold "View reviews"; Settings → Roles is Pro; the webhook credential is Header Auth `X-PQM-Token`).

**Never:** No change under `post-quality-manager/`, `.github/`, `tests/` or `templates/`; no capability slug, function or file name in the notes; no version number inside the notes file (the file name carries it after the rename); no `docs/release-notes/<version>.md` for the 3.x test builds; no rewrite of the CHANGELOG; the spec and ADRs stay untouched.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pre-release cut (today) | `unreleased.md` exists, no `<version>.md` | Release body carries only release-please's changelog; `/release` reports the unreleased notes exist and are left alone | No error expected |
| Launch PR | `unreleased.md` renamed to `<version>.md` in the PR that drops `"prerelease": true` | Release body = changelog + the notes; `/release` finds the file for the proposed version and an empty or absent `unreleased.md` | No error expected |
| Public release, notes missing | No `<version>.md`, or `unreleased.md` still holds lines | `/release` reports it as a stop before the merge (step 3) | Reported, never auto-fixed |
| A later silent change | A PR adds a default, permission, removed screen or re-import | Its builder adds one line to `unreleased.md` in the same PR (Definition of Done) | Caught in review, not by CI |

</frozen-after-approval>
## Code Map

- `docs/release-notes/` -- does not exist; `release.yml` ("Attach zip, append release notes, publish") appends `docs/release-notes/<version>.md` with `cat` after the body release-please wrote, so a notes file begins with a `##` heading and no title line.
- `.claude/skills/release/SKILL.md` -- step 3 says "story 15 writes the first one"; the launch paragraph (pre-release until launch, `release-as`) is where the rename belongs. Rewrite step 3 only; keep steps 1, 2, 4–6 and the dispatch paragraph as they are.
- `AGENTS.md` line 37 ("Definition of Done: …") -- one clause to add; the same sentence is mirrored verbatim in `_bmad/custom/bmad-build.toml` `persistent_facts` (loaded into every build) — change both or the builds never see it.
- `memory/delivery-pipeline.md` -- line 14 ("story 15 writes the first one") and the "Done after 3.0.0" bullet mention the notes; record the file, the rename rule and the DoD clause in one bullet. `memory/bmad-setup.md` line 12 still says "gates the first release since 3.0.0" — stale wording, fix in passing.
- Facts for the notes, verified 2026-10-08: quota default 20 in `post-quality-manager.php` (`author_quota`), applies to users without "Run paid agents" (`PQM_Features::is_quota_subject()`); `/post-score` is gated by `current_user_can('pqm_view_reviews')` and documented in README for "the workflow and other tools" (the template itself never calls it); `register_capabilities()` grants a capability's defaults only when no `manage_options` role holds it (story 42, ADR 0012); the n8n Workflow page shows an "update available" notice when the reported workflow version is older than the template; README § Installation already explains the pre-v4 trigger staying unauthenticated.
- `README.md` § Installation and `docs/user-paths.md` A3 -- not touched here (entry 4.8).

## Tasks & Acceptance

**Execution:**
- [ ] `docs/release-notes/unreleased.md` -- create: a `## Upgrading from an earlier version` section with five short entries (quota; the user behind the n8n credential; role rights kept as set, Settings → Roles on Pro or a role editor; "View as" removed; re-import the workflow — update notice, token credential) -- the content criterion 1 names, written per `docs/brand.md`.
- [ ] `.claude/skills/release/SKILL.md` -- rewrite step 3 and add one sentence to the launch paragraph: pre-release → `unreleased.md` exists and is left alone; public release → `docs/release-notes/<version>.md` exists for the proposed version and `unreleased.md` is empty or gone, else stop; the launch PR renames the file -- criterion 4.
- [ ] `AGENTS.md` + `_bmad/custom/bmad-build.toml` -- add to the Definition of Done: "a change existing sites meet without being told (a new default, a new permission, a removed screen, a workflow re-import) adds one line to `docs/release-notes/unreleased.md` in the same PR" -- criterion 3, in both copies.
- [ ] `memory/delivery-pipeline.md`, `memory/bmad-setup.md` -- record the notes file, the rename-in-launch-PR rule and the DoD clause; fix the stale "first release since 3.0.0" wording -- Definition of Done (memory updated in the same change).

**Acceptance Criteria:**
- Given `docs/release-notes/unreleased.md`, reading it against story 15 criterion 1, each of the five behaviors is named with what changed and what to do, with no slug, function or file name.
- Given the `/release` skill, reading step 3, a pre-release and a public release are told apart and the public case names the two stops (no `<version>.md`; `unreleased.md` not empty).
- Given AGENTS.md and bmad-build.toml, the Definition of Done sentence is identical in both and carries the release-notes clause.
- Given the working tree, `git status` shows no change under `post-quality-manager/`, `.github/`, `tests/`.

## Design Notes

The notes file deliberately carries no version and no `#` title: release-please owns the release body's head (version link + `### Features` / `### Bug Fixes`), and the workflow concatenates the file beneath it. A `##` heading reads as a section of that body on GitHub. Each entry is two sentences at most: what changed, what to do — the audience is a site owner reading the release page, not a developer. Example entry:

```
**Authors have a monthly limit.** Users who cannot run paid agents get 20 AI actions a month; 0 means no limit. Change it under Settings → Monthly Quota → AI Actions per User.
```

## Verification

**Commands:**
- `git status --porcelain -- post-quality-manager .github tests` -- expected: empty.
- `uv run _bmad/method/scripts/tickets.py --project-root . status _bmad-output/backlog` -- expected: runs clean, story 15 listed.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness green (nothing touched, confirms no stray edit).
- `diff <(grep -o 'Definition of Done.*' AGENTS.md | head -1) <(grep -o 'Definition of Done (AGENTS.md).*' _bmad/custom/bmad-build.toml | sed 's/ (AGENTS.md)//; s/",$//')` -- expected: the two DoD sentences differ only in quoting.

**Manual checks (if no CLI):**
- Read `docs/release-notes/unreleased.md` once as a site owner: every entry answers "what changed" and "what do I do", and the first product mention is "Post Quality Manager".
- Read `/release` step 3 as the agent that will run it at launch: the rename, the file name and the two stop conditions are unambiguous.

## Review Triage Log

Pass 1 (quick lens), 2026-10-08 — high 0, medium 6, low 1, false 1, maybe-false 0; rejected 1 as a plan edit.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | DoD sentence not identical in AGENTS.md and bmad-build.toml | — | rejected | The two sentences differed before this change; the added clause is identical in both. The fix would edit this plan's AC (Code Map said "verbatim" wrongly). |
| 2 | AGENTS.md hand-edited inside the bmad-project-context managed block | medium | defer | AGENTS.md:4 header + memory/bmad-setup.md "never hand-edits inside the markers"; a refresh may drop the clause. Agent-context file → deferred-work.md. |
| 3 | Notes intro false per version ("View as" only in 3.0.0–3.1.1; 3.2.0 ships all five) | medium | patch | `git tag --contains fda18a0` / `c7e510b`; PQM_WORKFLOW_VERSION 5 from v3.0.1. |
| 4 | "View reviews" entry describes the current workflow wrongly | medium | patch | Template stopped calling `/post-score` in 6e0265c (2026-07-22); only older imports and other REST callers need it. |
| 5 | Re-import entry blames the new import's credential for the unauthenticated trigger | medium | patch | README § Installation: the pre-v4 trigger stays unauthenticated until re-imported. |
| 6 | "Workflow update available" only shows after a reported version | medium | patch | `render_workflow_status()` shows "not checked yet" while no report exists. |
| 7 | Role-rights entry omits the documented role-editor ceiling | low | patch | class-pqm-features.php:45-46; direct one-clause correction. |
| 8 | `/release` after launch: no owner PR for the rename, empty-release case, re-creating unreleased.md | medium | patch | release-please owns the release PR; release.yml tolerates a missing file. |
| 9 | Quota path adds "Usage & Limits" vs the AC wording | false | rejected | class-pqm-admin.php:646 labels the tab "Usage & Limits"; the notes are right, the AC wording is shorter. |

Patches applied by the implementer (findings 3–8), then re-verified: harnesses green, `tickets.py status` clean, no change under `post-quality-manager/`, `.github/`, `tests/`. The patch pass reverted an earlier fix (the notes named the "Post Quality menu", which is still labelled "Content Quality" until story 41); restored to "the plugin's admin menu".

Owner wording pass (2026-10-08): the notes were condensed to three entries — "View reviews" folded into the re-import entry, "View as" into the roles entry, the role-editor ceiling left to the README — and the owner approved that text (story 15 criterion 2).
