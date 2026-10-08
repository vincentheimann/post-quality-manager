---
title: 'The repository is ready to go public'
type: 'chore'
ticket: '9'
created: '2026-10-08'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '8b89edc88b216db55b5eccbd21e0eb5e3d52710d'
context: ['{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Entry 4.8 switches the repository to public, but the root has no LICENSE file (GitHub shows no license although the plugin header declares GPL-2.0-or-later), nobody has scanned the 266-commit history for secrets, and `_archives/turbo-drill/` (12 tracked files of an unrelated quiz app) is still on `main`.

**Approach:** Add the verbatim GPL-2.0 text as `LICENSE` at the repository root; delete `_archives/turbo-drill/` and every note that tells agents to ignore it; record the history scan (gitleaks 8.30.1, all refs, run from a scratchpad download, nothing installed) and settle each finding with the owner. The owner switches visibility only in 4.8.

**Scan result (planning, 2026-10-08):** 2 findings, both in n8n exports of the first three commits (`1b3ba98`, `e06713d`, `6e0265c`; files gone from `main` since 2026-07-22): a kChat slash-command verification token (UUID) and, beside it, a kChat incoming-webhook URL (`…<owner-chat>/hooks/019caee5-…`), the owner's own site URLs, kChat team/user ids and request IPs. No API key, no WordPress application password, no GitHub or Anthropic token anywhere in history.

**Decisions (owner, 2026-10-08):** both findings are rotated and accepted, history is not rewritten. The owner deletes or regenerates the old kChat slash command and incoming webhook (or confirms they are gone); the personal data in the same exports (own public blog URLs, kChat ids, username, request IPs) is accepted as is.

## Boundaries & Constraints

**Always:** LICENSE is the unmodified GPL-2.0 text (GitHub's licensee only detects verbatim text); no plugin code change; the scan command is re-runnable and written down; a live secret is rotated before anything else; Conventional Commits, the owner commits; deletions in this plan are approved by the owner approving the plan.

**Never:** no history rewrite (owner decision above: it would change every hash behind tags v3.0.0–v3.2.0 and the release-please bootstrap); no LICENSE copy inside the plugin folder (story 38 handles the WordPress.org packaging); no gitleaks install on the machine, no new CI job (GitHub's free secret scanning covers the public repo from 4.8 on); no hand edit inside the AGENTS.md managed block.

</frozen-after-approval>

## Code Map

- `LICENSE` (new, root) -- GPL-2.0 text from `https://www.gnu.org/licenses/gpl-2.0.txt` (follow the redirect), unchanged. Header `License: GPL-2.0-or-later` at `post-quality-manager/post-quality-manager.php:12-13` already matches.
- `README.md:103-105` -- "## License / GPL-2.0-or-later": add "see `LICENSE`".
- `_archives/turbo-drill/` -- 12 tracked files, the only content of `_archives/`; `git rm -r` removes the folder.
- `memory/workspace-layout.md:13` -- the turbo-drill paragraph goes; `memory/MEMORY.md:7` drops "turbo-drill archives".
- `memory/delivery-pipeline.md` -- one 4.9 line: LICENSE added, scan tool + command + result, owner's decision on the findings.
- `AGENTS.md:28` -- inside the managed block (markers L3/L55): not hand-edited; the owed `bmad-project-context` refresh drops the line once the folder is gone (note in Implementation Notes).
- `C:/Git/Post Quality/CLAUDE.md:18` (workspace, outside the repo) -- the turbo-drill rule goes with the folder.
- Scan command (scratchpad binary, repo root): `gitleaks git --no-banner --redact=60 --log-opts=--all --report-format json --report-path <scratch>/report.json --exit-code 0 .` → 201 commits, 2 findings, rule `generic-api-key`, files `PQ-Post-Score-Reviewer-Tier-4-Enhanced.json` and `_sources/PQ-Post-Score-Reviewer-Tier-3-Final (7).json`. Supplementary grep for webhook URLs: `git log --all -p | grep -o -E 'hooks/[0-9a-f-]{20,}'`.

## Tasks & Acceptance

**Execution:**
- [x] `LICENSE` -- add the GPL-2.0 text -- GitHub shows the license.
- [x] `README.md` -- point the License section at the file -- one source.
- [x] `_archives/turbo-drill/` -- `git rm -r` -- unrelated project off `main`.
- [x] `memory/workspace-layout.md`, `memory/MEMORY.md`, `memory/delivery-pipeline.md`, workspace `CLAUDE.md` -- memory says what is true: no archive, scan recorded.
- [x] Owner (hitl) -- delete or regenerate the old kChat slash command and incoming webhook, then confirm. Done 2026-10-08: slash command and incoming webhook deleted, old n8n workflow deactivated.

**Acceptance Criteria:**
- Given the branch merged, when the owner opens the repository on GitHub, then the sidebar reads "GPL-2.0 license".
- Given `git ls-files` on `main`, when filtered on `turbo-drill`, then nothing is listed and no file under `memory/` or the workspace `CLAUDE.md` mentions it.
- Given the recorded gitleaks command re-run on `main`, when it finishes, then it lists exactly the findings the owner settled, each recorded as rotated and accepted.

## Implementation Notes

- `LICENSE`: `curl -L https://www.gnu.org/licenses/gpl-2.0.txt`, copied byte for byte (338 lines, 17984 bytes, ASCII, LF, sha256 `edaef632…b233f6`).
- `README.md`: besides the License line, the "Repository layout" bullet for `_archives/` went too; the folder no longer exists, so the line would be false.
- `memory/delivery-pipeline.md` names the folder only as `_archives/`, so the grep in Verification stays empty on `memory/`.
- `AGENTS.md:28` still names the deleted folder; it sits inside the managed block, so the owed `bmad-project-context` refresh drops it (not hand-edited).
- Scan re-run on this branch (gitleaks 8.30.1, checksum-verified release zip in the session scratchpad, nothing installed): 201 commits, 2 findings, rule `generic-api-key`, same token in both files at commit `1b3ba98` (`Tier-4-Enhanced.json:1382`, `Tier-3-Final (7).json:1204`), nothing new. The webhook grep finds one URL only (36 hits); `git log -G` shows it also in `c781bbe` (added) and `8afada0` (removed), both 2026-03-17, on top of the three planning commits. Same URL, not a new finding; recorded in memory. Neither value is in `HEAD`.
- Out of plan, not touched: the user's private auto-memory mirror (`~/.claude/projects/c--Git-Post-Quality/memory/workspace-layout.md` and its `MEMORY.md` line) still names the archive.
- Review found a third item: the owner's old n8n trigger `https://<owner-n8n>/webhook/pq-review` (commits `1b3ba98`, `e06713d`, `6e0265c`; no auth on the webhook node, only a static token check, so the leaked token opens it while that workflow is active). The frozen Scan result and Decisions do not name it; the owner's hitl task covers it (deactivate the old n8n workflow or change its path and token) once the owner confirms. Recorded in `memory/delivery-pipeline.md`.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-10-08):** 6 findings; high 1, medium 1, low 2, false 1, deferred 1.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | Old n8n trigger `<owner-n8n>/webhook/pq-review` in history, not in the scan record or the owner's rotation task | high | patch | Verified in `1b3ba98`: webhook node "kChat Trigger" has `options: {}`, "Parse & Route Input" only compares `body.token` to static `webhook_token`. Same rotate-and-accept reading as the owner's decision (one reading, no rewrite). Memory now lists it as item 3; owner confirms the workflow is deactivated or re-keyed. |
| 2 | Memory said "rotated and accepted" while the rotation task is open | medium | patch | `memory/MEMORY.md` and `delivery-pipeline.md` now say rotation pending owner confirmation. |
| 3 | "2 findings" mixed gitleaks' two hits with the token + webhook pair | low | patch | Memory now says gitleaks reports one token once per file and lists the three settled items. |
| 4 | Token path `n8n/post_quality_workflow.json` (renamed `e06713d`, removed `6e0265c`) missing | low | patch | Confirmed with `git log -G`; path added to memory. |
| 5 | `AGENTS.md:28` still names the deleted folder, untracked | medium | defer | Agent-context file inside the managed block; entry appended to `deferred-work.md` for the owed `bmad-project-context` refresh. |
| 6 | Private auto-memory mirror still names the archive | false | reject | The orchestrator updated the mirror's `MEMORY.md` line and `workspace-layout.md` during step 3, before the review returned. |

## Verification

**Commands:**
- `git ls-files | grep -i turbo-drill` -- expected: no output.
- `grep -rn turbo-drill memory/ README.md "C:/Git/Post Quality/CLAUDE.md"` -- expected: no output.
- `head -3 LICENSE` -- expected: "GNU GENERAL PUBLIC LICENSE / Version 2, June 1991".
- gitleaks command from the Code Map, on the branch -- expected: the 2 settled findings, nothing new.
- `gh repo view vincentheimann/post-quality-manager --json licenseInfo` after merge -- expected: `"key": "gpl-2.0"`.

**Manual checks (if no CLI):**
- Owner: the kChat slash command and incoming webhook of the old workflow are deleted or regenerated (owner decision).
