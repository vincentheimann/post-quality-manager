---
id: 48
type: story
title: "The public repository welcomes issues and security reports"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# The public repository welcomes issues and security reports

## Description

Entry 4.9 made the repository publishable (GPL-2.0 LICENSE, history scan, archive removed). When it turns public (entry 4.8), people will open issues and some will find security problems; today the repository has no issue templates, no contribution guide, no security policy, no private reporting channel and no branch protection. This story adds the repository-side files and settings so the first reports arrive in a usable shape and a vulnerability is not filed as a public issue.

## Acceptance Criteria

Verify: `SECURITY.md` carries the security policy of story 46 (private reporting address, supported versions) and GitHub's private vulnerability reporting is enabled; `CONTRIBUTING.md` says how changes arrive (Conventional Commits, `feat/`–`fix/`–`chore/` branches, rebase merge, the harness sweep and `php -l`, the pointer to `AGENTS.md`) and whether pull requests from outside are accepted (owner decision); issue templates exist for a bug (WordPress, PHP, plugin and workflow versions, n8n version, steps, WP_DEBUG output) and for a feature request, and a pull-request template carries the Definition of Done checklist; the repository front shows a description, topics and the website URL (story 45, or the GitHub Pages URL until then); `main` is protected (CI checks required, no force push); the owner decides whether Discussions are on.

## Boundaries

- Repository files and settings only; no plugin code.
- Settings are changed through the GitHub UI or `gh` with `--repo` first (house rule); read the current value before changing it.

## References

- release — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md (entry 4.9: what is already done)
- policies — _bmad-output/backlog/story-support-privacy-terms-refund-policies.md (story 46: the security policy text)
- guidelines — AGENTS.md (Definition of Done, commit conventions)
- external — https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Best before entry 4.8 turns the repository public; `tickets.py` cannot express a backlog → epic prerequisite, so this line carries it.
- `SECURITY.md` can ship with a placeholder address before story 46 settles the wording, so this story does not wait on it.
