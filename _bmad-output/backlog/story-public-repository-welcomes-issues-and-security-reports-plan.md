---
title: "The public repository welcomes issues and security reports"
ticket: 48
status: built
---

Plan approved by the owner 2026-10-09 ("go"), six steps; five built the same day, the sixth deferred to entry 4.8 because the GitHub API refuses branch protection, rulesets and private vulnerability reporting on a private repository on the free plan (read 2026-10-09, see the ticket Notes).

Built:

1. `SECURITY.md`: latest release supported; private report through GitHub's advisory form or hello@postquality.com; answer within 2 business days (ADR 0013 D9); scope excludes the user's n8n, AI provider and hosting.
2. `CONTRIBUTING.md`: issues welcome; outside pull requests by prior agreement in an issue (owner default); Conventional Commits, branch names, never bump the version, the CI commands, the AGENTS.md rules, the release-notes line, the template mirror rule, rebase merges, GPL-2.0-or-later for contributions.
3. `.github/ISSUE_TEMPLATE/bug_report.yml` and `feature_request.yml` (issue forms: versions, steps, WP_DEBUG output, a secrets-removed checkbox; problem, proposal, role) and `config.yml` (blank issues off; links to the security policy, the WordPress.org forum and Pro email).
4. `.github/PULL_REQUEST_TEMPLATE.md`: the AGENTS.md Definition of Done as a checklist.
5. Repository front set through `gh repo edit`: description = the tagline, website = https://postquality.com, topics `wordpress-plugin`, `content-quality`, `n8n`, `ai` (read back, all set).

Deferred to the public switch in entry 4.8 (recorded in the epic Notes and `docs/distribution.md`): branch protection on `main` requiring the four CI checks ("Build admin app", "PHP 8.0 lint and harness sweep", "PHP 8.3 lint and harness sweep", "Plugin Check", names read from the last merged PR's checks) with force pushes blocked; private vulnerability reporting on; Discussions as the owner decides.

Verification: the three issue-form files parse as YAML; `gh repo view` returns the description, website and four topics; no plugin file changed. Status `built`; done is the owner's mark, best after entry 4.8 applies the settings.
