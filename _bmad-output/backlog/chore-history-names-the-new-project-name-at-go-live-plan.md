---
title: 'The rewritten history goes to a fresh repository under the same name'
type: 'chore'
ticket: 87
created: '2026-11-11'
status: 'ready-for-dev'
route: 'full'
route_source: 'pinned'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/chore-history-names-the-new-project-name-at-go-live.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The history names the project by its old name in about 40 commits, and GitHub keeps every pull request's commits under `refs/pull/*`, so the repository cannot go public as it is (chore 87, AC1–AC8 and the owner decisions in its Notes).

**Approach:** Rewrite a bare clone of `main` and the 28 tags once with `git filter-repo` and the private recipe of 2026-10-29. Check it (AC5, AC8), then the owner publishes it as a fresh private repository under the same name, with the current one renamed and kept private as the archive. One forward commit, by pull request, fixes the cited commit ids. The rewrite runs once #285 and this plan's PR have merged: entry 8's gates (stories 15, 44, 48, bug 93, entries 7 and 9) are all done, and entry 8's launch PR follows on the new repository (owner order 87 → 4.8 → 38, 2026-11-10/11).

**Decisions (owner, 2026-11-11):** the GitHub archive is named `post-quality-manager-archive`. The local folder `post-quality-manager` is renamed `post-quality-manager-archive`, and its `origin` points at the archive, with its 148 branches kept. The new repository is cloned fresh into `post-quality-manager/`, and `.claude/settings.local.json` is copied into it. The full plan is kept (about 2,300 tokens).

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
- [ ] preconditions -- #285 and this plan's PR merged, no open PR, the clone on `main` and clean, the owner holds merges until the push -- the mirror must be the last history
- [ ] `<scratchpad>/chore87/` -- `git clone --bare --single-branch --branch main` (the tags pointing into `main` come along; `refs/pull/*` and other branches do not); helper files from the private notes; the filter-repo command -- AC1–AC3
- [ ] `<scratchpad>/chore87/` -- the checks in the matrix's Rewrite row, plus a look at the swapped image and PDF -- AC5
- [ ] AC8 delta -- scan what the 121 commits since the 2026-10-29 review add outside the plugin code (`memory/`, `_bmad-output/`, `.claude/`, the portfolio, `docs/distribution.md`); flags go to the owner in chat; answers become map rows, then rerun -- AC8 (the 2026-10-29 answers stand)
- [ ] owner commands -- written out for the owner: rename the repository to the archive name, create the private one, copy the settings above, add the secret, push `main` and the tags from the mirror -- AC4 (owner only)
- [ ] local folders -- rename `post-quality-manager` to `post-quality-manager-archive` (no session may hold a file open in it), set its `origin` to the archive, clone the new repository into `post-quality-manager/`, copy `.claude/settings.local.json`, run `npm ci` -- no old branch can reach the new repository
- [ ] AC7 -- read the new repository's settings beside the archive's through the API; the first push's CI and Release runs pass (`gh run list`) -- AC7
- [ ] `chore/87-cited-commit-ids` on the new repository -- cited ids at the tip rewritten from `commit-map`, pruned ones skipped, tags checked; then AC5 again on a fresh clone; draft PR -- AC6
- [ ] `memory/delivery-pipeline.md`, `memory/workspace-layout.md`, this plan, agent notes -- the switch, the archive's name, the cleanup -- durable facts; mirror deleted after the owner confirms

**Acceptance Criteria:**
- Given the published repository, when every commit's files, messages and paths are searched with the private pattern, then nothing matches beyond the triaged false positives.
- Given the new repository, when the archive is compared with it, then the archive is unchanged and private, its pull requests included.

## Implementation Notes

- Planning 2026-11-11: rendered with `render_skill.py` (workspace-root session), plan path from `tickets.py find`. Slip: one investigation command wrote a list of `package-lock.json` blob ids to `%TEMP%` instead of the scratchpad; the same command deleted it (checked).

## Plan Change Log

## Review Triage Log

## Design Notes

The forward commit goes by pull request rather than into the mirror before the push: nothing reaches `main` without a PR, and its merge gives AC7 a CI run on a real change. The citations stay unresolved only while the repository is private.

## Verification

**Commands:**
- the AC5 search script over the rewritten mirror, then over a fresh clone of the new repository -- expected: 0 hits
- `git -C <mirror> ls-tree -r --name-only main` against `origin/main` -- expected: identical
- `gh pr checks <n> --repo vincentheimann/post-quality-manager` on the AC6 PR -- expected: 4 checks green

**Manual checks (if no CLI):**
- the owner looks at the AC8 flags and the swapped image and PDF before the push.
