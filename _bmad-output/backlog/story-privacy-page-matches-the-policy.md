---
id: 68
type: story
title: "The published privacy page says what the plugin does with the n8n and Anthropic keys"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# The published privacy page says what the plugin does with the n8n and Anthropic keys

## Description

`docs/policies/privacy.md` is the plugin's privacy policy; the page at postquality.com/privacy is a copy of it kept in the `postquality.com` repository (entry 6.1 plan, D3). The policy changed twice in the n8n provisioning epic and the published copy did not follow:

- entry 6.1 (2026-10-13): the n8n API key is stored on the site, a dedicated key with an expiration date is advised, **Forget key** removes it, and the plugin calls the n8n API when the owner connects or checks the instance;
- entry 6.6 (2026-10-15, shipped in 3.9.0): the Anthropic API key typed in the setup guide is forwarded once (one request from the site to api.anthropic.com checks it, then it goes into the n8n credential) and never stored; WordPress itself calls the n8n API to create, activate and, on a failed setup, remove the workflow and credentials it made; the stored IDs of those objects.

Until the copy is synced, the public page says the plugin never sees the provider key and creates nothing on n8n, which the 3.9.0 build contradicts. Every release is a pre-release until the public launch, so the gap is bounded, but it closes before 3.9.0 (or any later build) leaves pre-release and before the Freemius plans go live.

## Acceptance Criteria

1. **Verbatim copy**
   **Given** `docs/policies/privacy.md` on `main`
   **When** the `postquality.com` repository's privacy page is compared with it
   **Then** the page carries the same text, section for section, including the "Last updated" date.
2. **The external-services text, if published**
   **Given** `docs/policies/readme-external-services.md`
   **When** the site shows an external-services or data-flow section
   **Then** it matches that file too; if the site has no such section, nothing is added.
3. **One site PR, owner-merged**
   **Given** both changes
   **When** the sync is made
   **Then** it is one pull request to the `postquality.com` repository, opened only on the owner's word and merged by the owner.
4. **Recorded**
   **Given** the merge
   **When** the epic's records are read
   **Then** `memory/n8n-provisioning.md` or `memory/release-readiness.md` says the published page is in step with the policy as of that date.

## Boundaries

- Must not change: `docs/policies/*` themselves (the plugin repository is the source), the site's design or other pages (story 57), the readme.txt of the plugin (story 38).
- Out of scope: translating the policy; a changelog of policy versions on the site.

## References

- policy — `docs/policies/privacy.md`, `docs/policies/readme-external-services.md`
- decision — entry 6.1 plan D3 (`_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md`): the published page is a copy in the site repository; entry 6 syncs it in one site PR
- epic — `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning.md`, Touch point `docs/policies/`
- release readiness — `memory/release-readiness.md` (postquality.com on GitHub Pages, story 45)

## Notes

- Filed 2026-10-15 at the owner's request, after #127 merged and 3.9.0 shipped with entry 6.6. Unrefined; ranking is `/prioritize`'s job, with the urgency stated above.
- The site repository's write access and URL follow the GitHub write rules (explicit repository URL on push, `--repo` on `gh`); the owner's domain never appears in this repository's artifacts beyond the policy's own published address.
