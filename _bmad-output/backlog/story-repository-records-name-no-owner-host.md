---
id: 66
type: story
title: "The repository's records name the owner's own site, hosts and providers only generically"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# The repository's records name the owner's own site, hosts and providers only generically

## Description

The owner's rule is that the domain of their own site, the hosts behind it and the accounts that reach it never appear in repository artifacts; records say "the owner's production site", "an owner-provided test instance", "the owner's registrar". A scan on 2026-10-14 (while recording entry 6.2's walk) still found 14 mentions in 9 tracked files, all written before the rule was applied everywhere: the owner's site domain (and its n8n subdomain) and the name of the owner's hosting and registrar provider. This story replaces each with the generic wording, so the working tree carries none of them, and settles with the owner whether the provider name stays where it describes the public project domain.

## Acceptance Criteria

1. **No owner site domain in the tree**
   **Given** the tracked files on the branch
   **When** they are searched case-insensitively for the owner's site domain (the pattern lives in the owner's private notes, not in this ticket)
   **Then** there is no match; each former mention reads "the owner's production site", "the owner's n8n instance" or "an owner-provided test instance", and its sentence still says what it said
2. **The provider name only where the owner keeps it**
   **Given** the mentions of the owner's hosting and registrar provider
   **When** this story is done
   **Then** the ones about the owner's own site and hosting read "the owner's host" or "the owner's registrar", and the ones about the public project domain follow the owner's answer to the open question below
3. **Records keep their meaning**
   **Given** a plan, walkthrough or memory line that recorded a finding with a URL (for example entry 4.9's history-scan record)
   **When** the URL is replaced
   **Then** the record still identifies the finding (path shape, commit, rule) with a placeholder such as `https://<owner-n8n>/webhook/…`, and no plan's frozen block is reworded beyond the replacement
4. **Nothing else moves**
   **Given** the diff
   **When** it is reviewed
   **Then** it changes only those mentions; no code, template, copy shown to users, or git history

## Boundaries

- Must not change: git history (entry 4.9 scanned it and the owner accepted what it holds; rewriting published history is a separate decision); the public project domain and its mailbox, which are the product's own and stay as they are.
- Out of scope: the owner's private auto-memory mirror outside the repository (it may hold the domains by design); hosts of other projects the owner runs, which never belong in this repository at all.

## References

- rule — the owner's instruction recorded in the agent's private notes (test instances) and in `memory/lessons.md` 2026-10-14 (another project's host)
- precedent — `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md` (entry 4.9: the history scan and what the owner accepted)
- data — `docs/distribution.md` (the public domain's records)

## Notes

- Filed 2026-10-14 (owner: "add a PBI to clean them up later"), from the scan run before committing entry 6.2's walk record; that record itself names no owner host.
- Found 2026-10-14, by file and line (pattern classes only): `_bmad-output/backlog/story-astro-landing-page.md:63` (provider); `_bmad-output/backlog/story-n8n-connection-secure-and-host-agnostic.md:73` (site domain); `_bmad-output/backlog/story-public-home-site-pricing-docs-plan.md:18` (provider); `_bmad-output/backlog/story-public-home-site-pricing-docs.md:44` (provider); `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md:25` (provider), `:70` and `:80` (n8n subdomain in the history-scan record); `_bmad-output/initiative-post-quality-manager/walkthrough-4-9-ready-to-go-public/walkthrough-4-9-ready-to-go-public.md:14` (provider); `docs/distribution.md:126`, `:129` (provider); `memory/delivery-pipeline.md:39` (provider and site domain); `memory/pivot-agnostic.md:8` (site domain); `memory/release-readiness.md:46` (provider). Line numbers drift; re-run the search at build.
- Open question (owner, at build): the provider name in `docs/distribution.md` and the public-home tickets describes where the public project domain's DNS lives. Keep it there (it is the product's own infrastructure), or write "the registrar" everywhere?
- Option (not a criterion): a CI guard against the owner domain returning, holding the pattern in a repository secret so the check itself does not name it.
- Risk low: wording in records only, no behaviour.
