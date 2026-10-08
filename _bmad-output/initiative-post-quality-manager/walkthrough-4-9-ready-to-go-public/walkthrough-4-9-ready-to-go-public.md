# Walkthrough: entry 4.9 — The repository is ready to go public

Target: commit `3c4ca39` on `main` (PR #83, merged 2026-10-08). Plan: [story-the-repository-is-ready-to-go-public-plan.md](../epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md).
Review complete 2026-10-08: all six blocks accepted. Blocks 2 and 4 were trimmed during review (scope rule: the removed archive is named by folder and count only).

## Block 1 — Intent  ☑ done (accepted as pasted, no change)

Pasted verbatim from the plan's frozen `## Intent` section.

**Problem:** Entry 4.8 switches the repository to public, but the root has no LICENSE file (GitHub shows no license although the plugin header declares GPL-2.0-or-later), nobody has scanned the 266-commit history for secrets, and `_archives/turbo-drill/` (12 tracked files of an unrelated quiz app) is still on `main`.

**Approach:** Add the verbatim GPL-2.0 text as `LICENSE` at the repository root; delete `_archives/turbo-drill/` and every note that tells agents to ignore it; record the history scan (gitleaks 8.30.1, all refs, run from a scratchpad download, nothing installed) and settle each finding with the owner. The owner switches visibility only in 4.8.

**Scan result (planning, 2026-10-08):** 2 findings, both in n8n exports of the first three commits (`1b3ba98`, `e06713d`, `6e0265c`; files gone from `main` since 2026-07-22): a kChat slash-command verification token (UUID) and, beside it, a kChat incoming-webhook URL (`…<owner-chat>/hooks/019caee5-…`), the owner's own site URLs, kChat team/user ids and request IPs. No API key, no WordPress application password, no GitHub or Anthropic token anywhere in history.

**Decisions (owner, 2026-10-08):** both findings are rotated and accepted, history is not rewritten. The owner deletes or regenerates the old kChat slash command and incoming webhook (or confirms they are gone); the personal data in the same exports (own public blog URLs, kChat ids, username, request IPs) is accepted as is.

## Block 2 — Broad strokes  ☑ done (item 4 trimmed during review)

A chore commit: 19 files, 444 lines added, 7946 removed, no plugin code. Open these in order:

1. [LICENSE](../../../LICENSE) — the verbatim GPL-2.0 text, new at the root; the file GitHub's license detection reads.
2. [README.md:104](../../../README.md#L104) — the License section now points at that file.
3. [memory/delivery-pipeline.md:39](../../../memory/delivery-pipeline.md#L39) — the one paragraph that records the history scan, its three findings and the owner's rotation.
4. `_archives/` — the unrelated project's folder, 12 files, deleted (`git show --stat 3c4ca39`); nothing replaces it.
5. [Review Triage Log](../epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md#L74) — how the review changed the scan record before the commit.

## Block 3 — License detection  ☑ done

GitHub's licensee only recognises an unmodified license text, so the file is the gnu.org `gpl-2.0.txt` copied byte for byte (338 lines, LF, md5 `570a9b37…`). The plugin header already declared the license; nothing there changes.

- [LICENSE:1-3](../../../LICENSE#L1-L3) — the header licensee matches on.
- [post-quality-manager.php:12-13](../../../post-quality-manager/post-quality-manager.php#L12-L13) — `License: GPL-2.0-or-later`, unchanged.
- [README.md:102-104](../../../README.md#L102-L104) — "GPL-2.0-or-later, see LICENSE".
- No copy inside the plugin folder: story 38 (WordPress.org) packages the zip.

## Block 4 — Archive removal  ☑ done (file list trimmed during review)

`git rm -r _archives/turbo-drill/` and every note that told agents to ignore it, except one line inside a managed block.

- Deleted: the 12 tracked files of `_archives/turbo-drill/`; their contents are not this project's material and are not described here.
- [README.md:87-95](../../../README.md#L87-L95) — the Repository layout list lost its `_archives/` bullet.
- [memory/workspace-layout.md:13](../../../memory/workspace-layout.md#L13) — the turbo-drill paragraph above this line is gone; [memory/MEMORY.md:7](../../../memory/MEMORY.md#L7) no longer says "turbo-drill archives".
- Left alone: [AGENTS.md:28](../../../AGENTS.md#L28) still names the folder. It sits inside the `bmad-project-context` managed block, so the owed refresh drops it ([deferred-work.md:55](../deferred-work.md#L55)).
- Outside the repo: the workspace `CLAUDE.md` lost its turbo-drill rule (local file, not in the commit).

## Block 5 — History secret scan and rotation  ☑ done

gitleaks 8.30.1 (downloaded to the session scratchpad, never installed) scanned all refs: 201 commits, 2 hits that are the same kChat slash-command token in two n8n exports of the first commit. Two more items were found by grep, not by gitleaks: the kChat incoming-webhook URL, and (added by the review) the owner's old n8n trigger, whose webhook node had no authentication beyond that leaked token. All three left `main` on 2026-07-22. The owner chose rotate-and-accept over a history rewrite, which would have changed every hash behind tags v3.0.0–v3.2.0.

- [memory/delivery-pipeline.md:39](../../../memory/delivery-pipeline.md#L39) — the whole record: tool, exact command, the grep for each item, commits and paths, decision, and the owner's confirmation (slash command and webhook deleted, workflow deactivated, 2026-10-08).
- [memory/MEMORY.md:17](../../../memory/MEMORY.md#L17) — the index line.
- [Implementation Notes](../epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md#L62) — the re-run on the branch and the third item the review found.
- Not built: a CI secret-scan job. GitHub's free secret scanning covers the repository once it is public (4.8).

## Block 6 — Periphery  ☑ done

- [Plan file](../epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md) — status `built`, quick review, 6 findings triaged ([L74](../epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md#L74)).
- [deferred-work.md:55](../deferred-work.md#L55) — the AGENTS.md refresh entry.
- Ticket 4.9 in [tickets.toml](../epic-delivery-pipeline/tickets.toml) — not yet marked done; the owner marks it.
