---
id: 87
type: chore
title: "At the public go-live, the repository's history names the project only by its new name"
parent: none
covers: [O9]
after: [66]
assignee: ""
refined: true
hitl: true
risk: high
---

# At the public go-live, the repository's history names the project only by its new name

## Description

Owner decision (2026-10-24, at story 66's PR #189): the git history is rewritten before the repository goes public, so every mention of the project's old name reads the new project name instead. The old name is the name of the blog the prototype was built for, in every spelling, plus its three-letter short form. The rewrite waits for the go-live. It runs once, as the last step before entry 8 switches the repository public. Commit ids stay stable while development continues, and no later commit can bring back what was removed. Story 66 cleaned the working tree; this chore cleans the history.

## Acceptance Criteria

1. **The rewrite runs once, at the go-live**
   **Given** entry 8 ready to switch the repository public, every other gate met
   **When** the owner gives the word
   **Then** the history is rewritten in a fresh mirror clone with `git filter-repo`, covering file contents, commit messages and file paths. The replacement map lives in the agent's private notes, never in the repository.
2. **Names become the new name; addresses become placeholders**
   **Given** the map
   **When** it is applied
   **Then**:
   - the old name in every spelling (with or without separators, the name part of its domain, the short form in names and paths) reads "Post Quality", or the matching short form;
   - host names and URLs read the placeholders the tree has used since story 66 (`<owner-n8n>`, `<owner-chat>`, and `<owner-site>` for the blog's own address), and each URL keeps its path.
3. **The images change too**
   **Given** the portfolio image and PDFs committed in `bfe298b` and `419543b`
   **When** the rewrite runs
   **Then** their blobs are replaced by the ones story 66 committed (`assets/portfolio/dashboard.png`, `Portfolio - Post Quality.pdf`), so no commit shows the old site
4. **Nothing old stays reachable once public**
   **Given** that GitHub keeps every pull request's commits under read-only `refs/pull/*`, which a force push does not remove
   **When** the rewritten history is published
   **Then** either it goes to a fresh public repository under the same name, with the current one renamed and kept private as the archive (pull requests included), or, if the owner keeps the current repository, GitHub Support has purged the pull-request references and cached views before the switch to public
5. **The search comes back empty**
   **Given** the published repository
   **When** every commit's files, message and paths are searched case-insensitively with the private pattern, and the replaced image and PDF blobs are looked at
   **Then** nothing matches
6. **Citations still resolve**
   **Given** records that cite commit ids (plans, memory, release notes)
   **When** the rewrite has changed the ids
   **Then** one forward commit rewrites the cited ids at the tip from filter-repo's commit map (`.git/filter-repo/commit-map`), and the tags are carried to the new ids

## Boundaries

- Must not change: what each commit did, apart from the map (dates, authors, order, content); the public project domain postquality.com and its mailbox; the retired admin label "Content Quality". That label is the product's own former name, and `docs/brand.md` and the release notes record its retirement on purpose.
- Out of scope: the postquality.com site repository, where neither its files nor its history carry the old name (checked 2026-10-24).
- Owner only: the force push or the new repository. The agent's rules deny a force push; the agent prepares the mirror, the map, the checks and the commands.

## References

- decision — `memory/delivery-pipeline.md` (entry 4.9's line: the history question and the owner's answer); story 66, `_bmad-output/backlog/story-repository-records-name-no-owner-host.md`
- gate — `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/epic-delivery-pipeline.md` (entry 8, the switch to public; this chore gates it)
- precedent — `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md` (entry 4.9, the history scan)
- tool — `git filter-repo` (`--replace-text`, `--replace-message`, `--path-rename`, a blob callback for the two binaries); GitHub Docs, "Removing sensitive data from a repository" (pull-request references, GitHub Support)
- private — the replacement map and the search pattern, in the agent's notes (test instances, history rewrite map)

## Notes

- Filed 2026-10-24 from the owner's answer at story 66's PR: "Update with the new project name, replace every old mention of the old name. Wait until a public go live of the repo."
- Reading (agent; for the owner to correct): "the old name" is the blog the prototype was built for (its title, domain and short form), which named the project's files and workflows before the pivot. "Content Quality" is out of scope (Boundaries).
- Decision (owner delegation, best practice): addresses become placeholders, not the new name. A postquality.com host that never existed would mislead anyone reading the public history. Names in prose and in file names become "Post Quality".
- Decision (owner delegation, best practice): the old chat account's webhook host goes into the same map. Story 66's history question listed it, and the rewrite runs only once.
- Recommendation: a fresh public repository (the first option of AC4). It needs no Support ticket, the pull-request texts and their old diffs stay in the private archive, and the public history starts clean.
  - Cost: the GitHub Releases 3.0.0–3.9.x, all pre-releases, are not carried over. The first public release (4.0.0) is created on the new repository anyway.
  - To redo: the repository settings, the secrets (Freemius deployment, story 58) and branch protection (entry 8's step from story 48).
  - The site's links keep working if the new repository takes the same name.
- Scale on 2026-10-24: 22 commits name the blog by its title or domain, 15 use the short form, 2 commit messages and 2 file paths carry it.
