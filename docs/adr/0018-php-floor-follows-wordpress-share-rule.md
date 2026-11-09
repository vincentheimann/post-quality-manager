# 0018. The PHP floor is 8.1 and follows WordPress core's 5 % share rule

- Status: accepted
- Date: 2026-11-09

## Context

The plugin requires PHP 8.0. Upstream security support ended on 2023-11-26 for 8.0 and on 2025-12-31 for 8.1. It ends on 2026-12-31 for 8.2 and on 2027-12-31 for 8.3. Spike 104 gathered the evidence: `_bmad-output/backlog/spike-php-floor-review-findings.md`, with sources.

- **WordPress:** recommends 8.3 or later. Core requires 7.4 since WordPress 7.0, and drops a PHP version from core when its share falls below 5 % of sites.
- **Reach:** on WordPress.org's share data (2026-11-09), each floor keeps this share of reporting sites:
  - 8.0: 80.2 %
  - 8.1: 76.8 %
  - 8.2: 67.7 %
  - 8.3: 45.0 %

  8.0 on its own is 3.4 %.
- **Testing:** nothing runs the plugin on 8.0. CI only lints it there, the harnesses already need 8.1, and Local has no 8.0 build.
- **Code:** later versions add little. The classes are static-only and data moves as arrays.
- **Users:** no site runs the plugin yet. After the launch, a site below a raised floor keeps its last compatible version: WordPress blocks the update, and auto-update skips it.
- **ADR 0017:** it raises the floor for review whenever the floor is past end of life and WordPress recommends higher. Any floor below 8.3 triggers that every month.

Alternatives:
- keep 8.0;
- 8.2: past end of life before the expected directory launch;
- 8.3: what WordPress recommends, at the cost of 35 points of reach.

## Decision

- `Requires PHP` becomes 8.1, before the first public release (owner, 2026-11-09).
- From then on, the floor is the oldest PHP version that both holds at least 5 % of WordPress.org's PHP share and runs the project's checks: CI's lint and harness sweep, and the pre-production pass.
- `/lcm` reads the share in each run. When the floor version falls below 5 %, it files a ticket that moves the floor by this rule, citing this ADR.
- Moving the floor for any other reason, such as a language feature or a security fault, needs a new ADR.
- This replaces ADR 0017's PHP rule; the rest of ADR 0017 stands.

## Consequences

- **Easier:** the floor is a version the project actually runs, and CI's 8.0-only lint job goes. `never` and enums become available.
- **Harder:**
  - Sites on PHP 8.0, 3.4 % of reporting sites, cannot install the plugin.
  - The floor stays past upstream end of life by design, as WordPress core's own does.
  - Each later move leaves sites below it on an older version.
- **Committed to:**
  - A follow-up story that makes the change: the header and readme, CI, the docs and records, the website, the `/lcm` rule and a release-notes line (findings, section 5).
  - A share reading in each `/lcm` run.
