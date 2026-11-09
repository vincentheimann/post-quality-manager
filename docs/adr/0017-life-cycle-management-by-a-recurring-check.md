# 0017. Life cycle management: tracked components, a monthly and pre-release check, a ticket for what moved

- Status: accepted; the PHP rule superseded by [0018](0018-php-floor-follows-wordpress-share-rule.md), the rest stands
- Date: 2026-11-03

## Context

The plugin depends on parts that change on their own schedule. Each change has so far been found by accident:
- **AI models:** Anthropic releases and retires models. The model registry (`memory/llm-options-architecture.md`) still offers the generation current when it was built, after a newer one shipped. A retired model answers with an error on every site that selected it.
- **Freemius SDK:** updated by hand when someone looked (chore 89, 2.13.4 → 2.13.5).
- **WordPress:** "Tested up to" must follow the current major for Plugin Check and the directory. The 6.6 floor is a product decision.
- **PHP:** the 8.0 floor is past its upstream end of life.
- **n8n:** the workflow template meets each n8n release on the owner's instance first (findings in `memory/n8n-provisioning.md`).
- **Build and CI:** `@wordpress/scripts`, the GitHub Actions in both workflows, and the runner image (`ubuntu-latest` moved to Ubuntu 26 from 2026-10-19, seen only as a run annotation).

Alternatives:
- Stay ad hoc.
- Dependabot or Renovate: they cover npm and Actions, but not the models, the vendored SDK, WordPress or n8n.
- A recurring check that reads each part's source and files a ticket for whatever moved.

## Decision

Life cycle management is a recurring check, run with the project skill `/lcm` monthly and before each release PR is merged.
- **One record:** `memory/lifecycle.md` lists each tracked part, the version or state in use, its source of truth and the date of the last check.
- **The check** reads each source, compares it with the record, files a ticket through `bmad-ticket` for each change that needs work, and updates the record. It changes no code itself.
- **Rules per part:**
  - **AI models:** the registry offers only models the provider still serves. A deprecation notice becomes a ticket due before the retirement date. A new generally available model is added by a ticket, and the default model is reviewed at each check.
  - **WordPress:** "Tested up to" follows a new major in the next release. The floor moves only by an ADR.
  - **PHP:** the floor moves only by an ADR. It is raised for review when the floor is past its upstream end of life and WordPress recommends higher.
  - **Freemius SDK:** follow its tagged releases, updated by a chore and vendored unmodified (chore 89).
  - **n8n:** a new major, or a change the template relies on, becomes a ticket, and a template version bump when the workflow changes.
  - **Build and CI:** Actions stay on supported majors (pinned by SHA where the CI already pins); runner-image migrations and deprecation annotations become tickets.

## Consequences

- Easier: a retirement or a breaking release is a ticket weeks ahead, not a failure at release time. `/release` gains a line pointing at the check.
- Harder: about an hour a month, and the skill's source list needs upkeep as parts are added or dropped.
- Committed to: the record and the skill stay in step with this list. Adding automated tools (Dependabot) for npm and Actions later needs no new ADR; dropping the check does.
