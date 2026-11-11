---
id: 66
type: story
title: "The repository's records name the owner's own site, hosts and providers only generically"
parent: none
covers: [O9]
after: []
assignee: ""
refined: true
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
   **Then** there is no match; each former mention reads generic wording (such as "the owner's own blog" or "the owner's production copy") or a placeholder that keeps the URL's path (such as `https://<owner-n8n>/webhook/…`), and its sentence still says what it said
2. **The provider name only where the owner keeps it**
   **Given** the mentions of the owner's hosting and registrar provider
   **When** this story is done
   **Then** the ones about the owner's own site, hosting and accounts read generic wording or a placeholder that keeps the URL's path (such as `https://<owner-chat>/hooks/…`), and the ones about the public project domain follow the owner's answer to the open question below
3. **Records keep their meaning**
   **Given** a plan, walkthrough or memory line that recorded a finding with a URL (for example entry 4.9's history-scan record)
   **When** the URL is replaced
   **Then** the record still identifies the finding (path shape, commit, rule) with a placeholder such as `https://<owner-n8n>/webhook/…`, and no plan's frozen block is reworded beyond the replacement
4. **Nothing else moves**
   **Given** the diff
   **When** it is reviewed
   **Then** it changes only those mentions and the records of the rule and its open question (`memory/`, the epic's gate line), and the portfolio kit's banner height for the replaced image (`heroHeight`); no code, template, copy shown to users, or git history

## Boundaries

- Must not change: git history (entry 4.9 scanned it, and the owner accepted only the old n8n exports' token, webhook URLs and personal data; the rest of history is the open owner question in `memory/delivery-pipeline.md`, settled before entry 4.8; rewriting published history is a separate decision); the public project domain and its mailbox, which are the product's own and stay as they are.
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
- Refined 2026-10-24 (Opus 5.5, by hand from the workspace root; the owner delegated the choices: "make choices based on best practices"). The search re-run at build finds eight lines in six tracked files: six name the owner's own site (its domain, its n8n subdomain in the old webhook URL of entry 4.9's history-scan record, the production Local site's name in story 86's plan 3, written 2026-10-23), and three give the old chat webhook URL with the chat provider's host (one line does both). The other provider mentions of the 2026-10-14 scan describe postquality.com's own DNS and mailbox. Each mention becomes generic wording ("the owner's own blog", "the owner's production copy") or a placeholder that keeps the URL's path (`https://<owner-n8n>/webhook/…`, `https://<owner-chat>/hooks/…`), so commits, grep commands and findings stay identifiable; the wording follows the plan's matrix, and the phrases quoted in AC1 are examples of it.
- Decision (owner delegation, 2026-10-24): the open question above is settled by keeping the provider name where it describes postquality.com's own DNS and its hello@ mailbox (`docs/distribution.md`, `memory/release-readiness.md`, the public-home and site tickets). Those records are the product's own runbook, anyone can read the same facts from the domain's public NS and MX records, and the Boundaries already keep that domain and its mailbox as they are.
- Decision (owner delegation, 2026-10-24): "behind Cloudflare" in the n8n records stays. It names the class of proxy that shaped the failure handling (the 1010 ban), not the owner's host or registrar, and it identifies no site.
- Decision (owner delegation, 2026-10-24): no CI guard in this story. GitHub Actions is blocked on credit until 2026-11-01, and a guard needs the pattern in a repository secret; the option above stays open.
- Decision (owner delegation, 2026-10-24): the local user-profile paths in a few plans' scratchpad references stay; they name no site, host or provider.
- Decision (owner delegation, 2026-10-24, review loop 1): the scope covers the owner's site title in any spelling (letters with or without a separator) and the pixels of tracked images, not only the domain pattern. The `description:` frontmatter of `memory/pivot-agnostic.md` reads "owner-blog-specific". `assets/portfolio/dashboard.png`, which showed the owner's site title in the admin bar and the owner's post titles, becomes a 1600-px downscale of `assets/screenshot-3.png` (story 86's showcase capture of the same Dashboard view, on sample posts; `PORTFOLIO.md`'s alt text describes it as well), and `Portfolio - Post Quality.pdf` is rebuilt with the portfolio kit's builder. Git history keeps the old image (`7d91589`, and the PDFs of `7d91589` and `02deadd`) and names the owner's site or accounts (the site by domain or title, the chat account by its webhook host) in records from March to 2026-10-23 (the March workflow exports, `tasks/todo.md` until 2026-10-01, `memory/pivot-agnostic.md` since 2026-07-22, the n8n-connection ticket since 2026-10-06, entry 4.9's own scan record and its walkthrough record, story 86's plan 3); entry 4.9's acceptance covers the token, the webhook URLs and the personal data in the old n8n exports only, so before entry 4.8 the owner accepts all of it or publishes a rewritten history, asked at the PR (the question in full: `memory/delivery-pipeline.md`, entry 4.9's line; the gate: the epic's Notes). The Done line goes into the post-merge records PR, as for 59, 41 and 79.
- Done 2026-10-24: PR #189 merged (363ed91). The owner answered the history question at the PR: "Update with the new project name, replace every old mention of the old name. Wait until a public go live of the repo." History is rewritten once, right before entry 8 switches the repository public: chore 87 (`chore-history-names-the-new-project-name-at-go-live.md`), which gates entry 8.
