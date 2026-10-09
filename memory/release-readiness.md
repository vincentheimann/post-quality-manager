---
name: release-readiness
description: What the first public release still needs beyond the code — commercial model, real licensing, a public home, policies, platform data, repository hygiene, launch content — filed 2026-10-09 as backlog 43–49 under owner requirement O9; nothing commercial is decided yet
metadata:
  type: project
---

**State on 2026-10-09:** the plugin side of the public release is covered by `epic-delivery-pipeline` (entry 4.8 = 4.0.0), story 38 (WordPress.org listing), 41 (visual identity), 15 (release notes, done), 9 (pre-production pass) and 4.9 (repository publishable, done). Nothing covered *how a customer pays* or what the platforms ask for beyond the readme. The owner asked for that to be defined and ticketed; the answer is the **release-readiness pack** in `_bmad-output/backlog/`, under the new initiative requirement **O9**:

| Ticket | What it settles | Runs after |
|---|---|---|
| spike 43 | Commercial model → ADR: launch posture (D1), seller identity (D2), provider (D3, Freemius per ADR 0003 unless overridden), plans (D4), price/currencies (D5), trial/refund (D6), lapse (D7), opt-in telemetry vs `epic-ebm-telemetry` (D8), support promise (D9), plan names (D10). Each has a recommended default. | — |
| story 44 | Real licensing: provider product + SDK behind `PQM_License` (ADR 0006 seam), "Get Pro" link, lapse → Free, harness, Plugin Check, release-notes line. Replaces the deferred-work Freemius item. | 43 |
| story 45 | Public home: domain (+ trademark search), landing page, pricing, docs, policy pages; `Plugin URI` / `Author URI` leave GitHub. | 43, 41 |
| story 46 | Policies: privacy (plugin + provider opt-in + website), Pro terms, refund, support, security (`SECURITY.md`), `wp_add_privacy_policy_content()` text for story 38. | 43 |
| story 47 | `docs/distribution.md`: every field per platform (WordPress.org account + readme header, provider product, GitHub front, website, plugin header) with a value or an `OWNER:` marker. First to run. | — |
| story 48 | Public repository hygiene: `SECURITY.md`, `CONTRIBUTING.md`, issue/PR templates, About, branch protection, private vulnerability reporting. Best before 4.8 turns the repository public. | — |
| story 49 | Launch content: demo, AI-cost guidance (A2), announcement channels incl. the n8n template library (A1). Last. | 41, 45 |

**Risk recorded in the epic Notes:** as built, 4.0.0 ships with the stub license (any key unlocks Pro) and a License section asking for a key nobody can buy.

**Ranked by `/prioritize` 2026-10-09 (owner):** spike 43 next, then story 48, then entry 4.8; story 47 beside them. Real licensing (44) gates story 38 (now `after: [10, 15, 41, 9, 44, 46, 47]`), not entry 4.8: a GitHub-only release reaches almost nobody (A1), the directory is the exposure. Spike 43's D1 can still pull 44 ahead of 4.8. Commercial lane 44 → 46 → 45 is owner-paced and runs beside the agent lane (22 + 40, telemetry epic, 37 → 39, 25 → 29 → 30 → 28, 4, Yoast); 49 last.

**All inputs given 2026-10-09 (ADR 0013):** seller = Vincent Heimann as a private person; prices 49 / 99 / 199 a year for 1 / 5 / unlimited sites, USD and EUR; support hello@postquality.com within 2 business days; domain postquality.com (bought; brand.md § Naming has the row). Home: static pages on GitHub Pages at launch (story 45), Astro landing page after real buyers (story 50). Still open: the wordpress.org username (story 47 marker).

Related: [[freemium-and-roles]] (the gate and the stub), [[delivery-pipeline]] (entry 4.8, release notes), [[naming]] (brand canon the data sheet points at).

**Spike 43 built 2026-10-09 (owner took every default) → ADR [0013](../docs/adr/0013-pro-sold-through-freemius-before-public-release.md):** D1 = real licensing before the first public release, so story 44 now gates entry 4.8 as well as story 38; order 43 → 48 and 47 → 44 → 4.8. Freemius fees checked that day: 3.8% + 50¢ plus gateway fees, plus 2.3% for WordPress products. The inputs with no default (D2, D5, D9) were given the same day; see above.

**Story 46 built 2026-10-09 (owner wording approval open):** `docs/policies/` holds privacy, terms, refund, support and the readme External services + `wp_add_privacy_policy_content()` block; facts traced to `pqm_webhook_payload()`, `GET /post-score`, the reviews table, the usage meta and `uninstall.php`. Story 45 publishes them at /privacy, /terms, /refund, /support; story 44 enters those URLs in Freemius; story 38 pastes the readme block after checking the third-party links.

**Story 48 built 2026-10-09:** `SECURITY.md`, `CONTRIBUTING.md` (outside PRs by prior agreement in an issue), issue forms + config, PR template with the Definition of Done; repository description, website and topics set. Branch protection, private vulnerability reporting and Discussions wait for the public switch (free plan refuses them on a private repo), as a named step of entry 4.8.

**Story 47 built 2026-10-09:** `docs/distribution.md` is the one place listing every platform field with its value or owner; stories 38, 44, 45, 46 and 48 read it and write back. Open owner markers there: wordpress.org username, story 41 assets, Freemius account, mail forwarding, Discussions.
