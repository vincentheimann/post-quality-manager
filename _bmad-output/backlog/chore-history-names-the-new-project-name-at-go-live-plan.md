---
title: 'The rewritten history goes to a fresh repository under the same name'
type: 'chore'
ticket: 87
created: '2026-11-11'
status: 'built'
baseline_revision: '812737964148f894267814bff363c4a8ca229bdd' # main before the rewrite; its rewritten id is recorded below
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/chore-history-names-the-new-project-name-at-go-live.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The history names the project by its old name in about 40 commits, and GitHub keeps every pull request's commits under `refs/pull/*`, so the repository cannot go public as it is (chore 87, AC1–AC8 and the owner decisions in its Notes).

**Approach:** Rewrite a bare clone of `main` and the 28 tags once with `git filter-repo` and the private recipe of 2026-10-29. Check it (AC5, AC8), then the owner publishes it as a fresh private repository under the same name, with the current one renamed and kept private as the archive. One forward commit, by pull request, fixes the cited commit ids. The rewrite runs once #285 and this plan's PR have merged: entry 8's gates (stories 15, 44, 48, bug 93, entries 7 and 9) are all done, and entry 8's launch PR follows on the new repository (owner order 87 → 4.8 → 38, 2026-11-10/11).

**Decisions (owner, 2026-11-11):** the GitHub archive is named `post-quality-manager-archive`. The local folder `post-quality-manager` is renamed `post-quality-manager-archive`, and its `origin` points at the archive, with its 148 branches kept. The new repository is cloned fresh into `post-quality-manager/`, and `.claude/settings.local.json` is copied into it. The full plan is kept (about 2,300 tokens). At review (owner, 2026-11-11): only the personal address moves to the owner's noreply address, and the bot, GitHub web-flow and Anthropic noreply identities stay as published, which settles the Rewrite row's identity check. Pull-request and issue numbers cited before the go-live refer to the archive: this is recorded, not reworded. The folders are swapped now, and the owner adds the archive clone's pre-push guard.

## Boundaries & Constraints

**Always:** the map, the search pattern, the drop list, the helper scripts and the mirror stay in the session scratchpad and the agent's private notes. None of them goes into the repository, a commit, a PR text or the plan. Every fix to the map reruns from a fresh clone, never on a rewritten mirror. The search prints the commit, path and count, never the matched text, because the dropped exports hold a dead token. Owner-only steps stay the owner's: the rename, the repository creation, the first push and the secret.

**Never:** a force push or any write to the archive; carrying `refs/pull/*` or branches other than `main`; the GitHub Releases 3.x (decided 2026-10-24); branch protection, private vulnerability reporting or Discussions (the free plan refuses them on a private repository, read 2026-11-11: 403/404; entry 8 sets them after the switch); touching the site repository.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Rewrite | bare clone: `main` + 28 tags, after #285 merged | AC5: 0 hits; tip file list = `origin/main`'s; the tip differs only in the four record files story 66 kept; every author and committer is the noreply address; 28 tags on new ids | No error expected |
| New spelling | a hit AC5 finds in the 121 commits since the dry run | reported by path and commit | add a map row in the private notes, rerun from a fresh clone |
| False positive | a hit inside a hash or a binary | listed with its location for the owner | never replaced (no `package-lock.json` blob in history has a replaceable token, read 2026-11-11) |
| Pruned commit | a commit left empty by the drops | `commit-map` gives it an all-zero id | AC6 skips it; a tip citation of it is listed for the owner and left as written |
| Main moves | a merge on the archive after the clone | the mirror is stale | the owner holds merges until the push; otherwise reclone and rerun |
| Old branch | one of the clone's 148 old-history branches pushed to the new repository | must not happen | the old folder becomes the archive's clone; only the fresh clone points at the new repository |

</frozen-after-approval>

## Code Map

- private notes (agent memory "history rewrite map") -- pattern, map, drop list, mailmap address, blob ids, the filter-repo command; scripts rebuilt in `<scratchpad>/chore87/` (the 2026-10-29 copies are gone).
- `git filter-repo` a40bce548d2c, installed -- `--invert-paths --paths-from-file`, `--mailmap`, `--replace-text`, `--replace-message` (it also rewrites hash references inside messages), `--blob-callback`; writes `.git/filter-repo/commit-map`.
- `CHANGELOG.md` -- 77 commit links and 27 compare links (tags, which survive); with the plans, memory and release notes it holds the AC6 citations (307–308 counted 2026-10-29).
- `.github/workflows/release.yml` -- reads one secret, `FREEMIUS_API_TOKEN`; release-please needs "Allow GitHub Actions to create … pull requests" (on now: `can_approve_pull_request_reviews: true`).
- `memory/delivery-pipeline.md` L53 -- with no Release objects, release-please stops its walk at the tag `v<manifest version>`, so the 28 tags must arrive.
- Repository settings to copy (read 2026-11-11): description, homepage `https://postquality.com`, topics `ai, content-quality, n8n, wordpress-plugin`, rebase merge only, issues and projects on, wiki and Discussions off, default workflow permissions `read`.
- Local clone: ignored `.claude/settings.local.json` (copy it; the rest regenerates); 148 local branches; another session's prunable worktree (left alone).

## Tasks & Acceptance

**Execution:**
- [x] preconditions -- #285 and this plan's PR merged, no open PR, the clone on `main` and clean, the owner holds merges until the push -- the mirror must be the last history
- [x] `<scratchpad>/chore87/` -- `git clone --bare --single-branch --branch main` (the tags pointing into `main` come along; `refs/pull/*` and other branches do not); helper files from the private notes; the filter-repo command -- AC1–AC3
- [x] `<scratchpad>/chore87/` -- the checks in the matrix's Rewrite row, plus a look at the swapped image and PDF -- AC5
- [x] AC8 delta -- scan what the 121 commits since the 2026-10-29 review add outside the plugin code (`memory/`, `_bmad-output/`, `.claude/`, the portfolio, `docs/distribution.md`); flags go to the owner in chat; answers become map rows, then rerun -- AC8 (the 2026-10-29 answers stand)
- [x] owner commands -- written out for the owner: rename the repository to the archive name, create the private one, copy the settings above, add the secret, push `main` and the tags from the mirror -- AC4 (owner only)
- [ ] local folders -- rename `post-quality-manager` to `post-quality-manager-archive` (no session may hold a file open in it), set its `origin` to the archive, clone the new repository into `post-quality-manager/`, copy `.claude/settings.local.json`, run `npm ci` -- no old branch can reach the new repository
- [ ] AC7 -- read the new repository's settings beside the archive's through the API; the first push's CI and Release runs pass (`gh run list`) -- AC7
- [x] `chore/87-cited-commit-ids` on the new repository -- cited ids at the tip rewritten from `commit-map`, pruned ones skipped, tags checked; then AC5 again on a fresh clone; draft PR -- AC6
- [ ] `memory/delivery-pipeline.md`, `memory/workspace-layout.md`, this plan, agent notes -- the switch, the archive's name, the cleanup -- durable facts; mirror deleted after the owner confirms

**Acceptance Criteria:**
- Given the published repository, when every commit's files, messages and paths are searched with the private pattern, then nothing matches beyond the triaged false positives.
- Given the new repository, when the archive is compared with it, then the archive is unchanged and private, its pull requests included.

## Implementation Notes

- Planning 2026-11-11: rendered with `render_skill.py` (workspace-root session), plan path from `tickets.py find`. Slip: one investigation command wrote a list of `package-lock.json` blob ids to `%TEMP%` instead of the scratchpad; the same command deleted it (checked).
- Deviations (2026-11-11): the plan's own PR (#286) was pushed and merged at approval, because the plan has to be in the rewritten history. Step 03 runs by the agent directly instead of an implementer subagent: the owner's steps interleave with it, and the recipe lives in the agent's private notes. Plan edits after the baseline stay out of the archive and reach the new repository with the AC6 PR.
- Preconditions (2026-11-11 08:24Z): #285 and #286 merged; no open PR in either repository; the clone on `main` 8127379, clean; the owner holds merges until the push.
- Rewrite (2026-11-11, `git filter-repo` a40bce548d2c, 15 s), on a bare single-branch clone with `main` (550 commits) and 28 tags, no other refs:
  - Map: two changes to the 2026-10-29 rows, under the owner's delegation for awkward phrasings. The separator-free row now runs before the general rows, as "specific first" asks; before, it never fired, and 17 grep terms and a site name would have read "Post Quality" with a space. One Local site's name in a sentence becomes a placeholder (2 matches).
  - Identities: of the five author and committer addresses, only the one personal address moves to the owner's noreply address. The GitHub web-flow, github-actions bot and `noreply@anthropic.com` identities stay as they were, so nothing is credited to the wrong author.
- Checks, all green:
  - AC5: 0 hits in 3,343 blobs, 534 commit messages and every path.
  - Pruning: 16 commits left empty were pruned (550 → 534, as in the dry run).
  - Tags: the 28 tag names are identical and all are reachable from `main`; `v3.13.3` matches the manifest.
  - Tip: the file list is identical to `main`'s; content differs only in the four record files story 66 kept.
  - Swap: the three old image and PDF blobs are absent.
  - Wording: "the Post Quality" occurs 160 times before and after, so nothing awkward was introduced.
- New `main`: `fda54f034ecbcf26fe0bfab1380f3c710fda3860` (was 8127379).
- AC8 delta (an Opus agent, read only): it scanned the 125 commits since 2026-10-29 (`1c98ae2..8127379`), their 120 record files and their messages. Owner answers, 2026-11-11:
  - The 8 n8n object ids in bug 97's plan (a test on the owner's instance, objects deleted) are mapped to `<n8n-id>`, as the old exports' ids were on 2026-10-29.
  - The records of the 2026-11-03 exposure (story 98's walk) stay as written. The owner rotates the production site's database password and salts (and the license key if wished) before entry 8 switches the repository public, so the public records describe a closed exposure. This gates entry 8, beside chore 81's list.
  - Kept: the Freemius deployment ids and the product id (public by design); the 8-character prefix of the deleted chat webhook id (in history reviewed on 2026-10-29); story 98's profile of the unnamed production site; a Ruby install path; the story 122 images.
  - No personal email, IP, phone, postal address, foreign host or third party's data was found.
- Rerun from a fresh clone (`main` unchanged at 8127379, no open PR) with the 8 rows added. All the checks above were green again, and the 8 ids occur nowhere. The tip now differs in five record files (the four plus bug 97's plan). New `main`: `f90eefe5144811906b91611c6e7b9001f72ea043`; this replaces fda54f0, which was never published.
- Owner steps (2026-11-11, ~09:06–09:37Z): the agent's own attempt to run the rename after the owner's "go ahead" was refused by the permission settings, so the owner ran the steps, as the ticket's Boundaries say.
  - The owner renamed the repository to `post-quality-manager-archive`, created the private `post-quality-manager` with the settings, and set `FREEMIUS_API_TOKEN` (found under the product's Settings → API & Keys, "API Bearer Authorization Token"; copied, not regenerated).
  - The owner pushed `main` and the 28 tags atomically. GitHub holds exactly the mirror: `main` f90eefe, 28 identical tags, no other ref.
  - The agent repointed the old clone's `origin` at the archive right after the rename.
  - The swapped image and PDF are the tip's own files from story 66, which the owner reviewed then; the owner did not look at them again before the push.
- AC7 (read 2026-11-11):
  - Settings match the archive: private, description, homepage, topics, issues and projects on, wiki and Discussions off, rebase merge only, default workflow permissions `read` with `can_approve_pull_request_reviews` true, the secret present.
  - Branch protection and private vulnerability reporting wait for the switch (403/404 on a private repository).
  - The first push's Release run 42996858118 succeeded. release-please found no Release objects, fell back to the tag `v3.13.3` (6938f4c) and opened no PR. CI did not start: that push's last commit touches only ignored paths. This PR gives CI its run.
- AC6, on a fresh clone of the new repository (`post-quality-manager-new`, `.claude/settings.local.json` copied), branch `chore/87-cited-commit-ids`:
  - A token counts as a citation only when the archive resolves it to exactly one commit in `commit-map`. That gave 342 distinct tokens (216 distinct commits) and 520 replacements in 172 files, each at the same length (none ambiguous). A second pass after review mapped the citations of PR-branch commits through their twin on `main` (the one commit with the same author date and subject, as a rebase merge leaves it): 35 tokens, 58 replacements in 26 files. A third pass found nothing left.
  - Changed: `CHANGELOG.md`'s commit links, the records, and three comments (`AGENTS.md`'s verified-against id, two harness headers: `php -l` clean, 42/42 and 22/22).
  - Left as written: two commits the drops pruned (`8afada0`, `c781bbe`, in entry 4.9's plan and `memory/delivery-pipeline.md`), and three superseded branch commits with no twin on `main` (`0aa2cc4`, `61fde4e`, `d187aee`, bug 90's earlier branch versions); they resolve only in the archive.
  - This plan was copied in after the mapping, so its old ids (the baseline, 8127379) stay as written.
- Records in the same PR: `memory/delivery-pipeline.md` (the published history and the PR numbers), `memory/workspace-layout.md` (the two folders), `memory/MEMORY.md`, and the epic's entry-8 gate (the rotation).
- Slip: an investigation command wrote a list of plan baselines to `/tmp` instead of the scratchpad; the same command deleted it.
- Open at hand-off (2026-11-11), so these tasks stay unticked:
  - AC7's CI run is this PR's own.
  - The folder swap, and the owner's pre-push guard in the archive clone.
  - The mirror and the private files in the scratchpad are deleted after the merge.
  - The done flip is the owner's word.

## Plan Change Log

## Review Triage Log

Pass 1 (Quick, Opus, 2026-11-11; diff from f90eefe, the baseline's rewritten id, because 8127379 exists only in the archive): 10 findings. Verdicts: 1 high, 2 medium, 4 low, 1 false (rejected), 2 intent_gap.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | AC6 left citations of PR-branch commits on old ids | medium | patch | Real: 38 tokens cite branch commits that the rebase merges replaced on `main`. The second pass maps each through its unique twin (same author date and subject): 35 tokens, 58 replacements in 26 files. Three superseded bug 90 branch commits have no twin and stay as written, resolving in the archive. |
| 2 | `baseline_revision` missing in the new repository (11 plans) | low / false | patch / reject | Ten plans had branch-commit baselines, and the twin pass fixed them all; 104 of 105 baselines now resolve. The one left is this plan's (8127379): its fix would edit this build's plan, and step 03 never overwrites a captured baseline. Rejected. Its rewritten id, f90eefe, is in the notes and was used for this review. |
| 3 | `workspace-layout.md` states the folder swap as done; the old folder can push old branches to the new repository by AGENTS.md's explicit URL | high | patch + owner | Real: a `git push --dry-run` from the old clone was accepted by the new repository (nothing was sent; afterwards it still holds only `main` and the tags). The wording no longer claims the swap. A local `pre-push` guard in the old clone was refused by the permission classifier, so the guard goes to the owner. The swap stays unchecked until done. |
| 4 | `workspace-layout.md` "two git repos" and MEMORY.md L7 stale | low | patch | Description, L8 and the index line now name the archive clone. |
| 5 | Identities: the frozen Rewrite row expects every address to be the owner's noreply | — | intent_gap | Resolved (owner, 2026-11-11): accepted as published, and the frozen decisions now say so. Six Claude-authored commits and 62 committed by GitHub's web-flow keep their noreply identities. |
| 6 | AC7 ticked before CI ran | low | patch | Unticked until this PR's CI passes. |
| 7 | Cleanup ticked while the mirror exists | low | patch | Unticked; the mirror goes after the merge. |
| 8 | The look at the swapped image and PDF did not happen in this run | medium | patch | The agent looked at both (the dashboard capture and the PDF's page 1): showcase site and sample posts only. They are the files the owner reviewed at story 66; the owner did not look again before the push. |
| 9 | "342 cited commits" is inaccurate | low | patch | Now reads: 342 distinct tokens, 216 distinct commits. |
| 10 | PR numbers in records and messages will point at the new repository's unrelated PRs | — | intent_gap | Real: 146 "PR #n" mentions in 76 files and 147 `#n` in messages (counted 2026-11-11). Resolved (owner): recorded in `memory/delivery-pipeline.md`, not reworded; message autolinks stay. |

## Design Notes

The forward commit goes by pull request rather than into the mirror before the push: nothing reaches `main` without a PR, and its merge gives AC7 a CI run on a real change. The citations stay unresolved only while the repository is private.

## Verification

**Commands:**
- the AC5 search script over the rewritten mirror, then over a fresh clone of the new repository -- expected: 0 hits
- `git -C <mirror> ls-tree -r --name-only main` against `origin/main` -- expected: identical
- `gh pr checks <n> --repo vincentheimann/post-quality-manager` on the AC6 PR -- expected: 4 checks green

**Manual checks (if no CLI):**
- the owner looks at the AC8 flags and the swapped image and PDF before the push.
