---
id: 110
type: spike
title: "Should Post Quality offer a hosted workflow plan, with free use, for sites that cannot run their own n8n?"
parent: none
covers: [UV4, O9]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# Should Post Quality offer a hosted workflow plan, with free use, for sites that cannot run their own n8n?

## Description

Today every site brings its own n8n: the setup dialog provisions the workflow on the blogger's instance (ADR 0015), or the blogger imports it by hand. Bloggers who cannot or will not run n8n stop at the friction wall of `docs/user-paths.md` A4, before their first review. The owner's idea: run one existing workflow for every site that wants it, from different websites, and open a new paid plan, with free use, to those bloggers. This spike analyses and forecasts what that would bring and cost, what could go wrong, and how it could be built, so the owner can decide go or no-go. A go may lead to a new epic.

## Acceptance Criteria

1. **The options are compared with evidence**
   **Given** at least these options: (a) the existing workflow on an n8n instance the owner hosts, shared by every subscribed site (per-site webhook path and token, as today); (b) the same under a commercial n8n licence for hosting or embedding; (c) no n8n for these sites: the AI calls go through a small hosted service, or straight from the plugin; (d) no new plan, better guidance toward n8n Cloud or self-hosting
   **When** the spike ends
   **Then** a findings file states each option's benefits, costs, risks, effort and what it changes in the plugin (setup dialog, n8n Workflow page, Free/Pro split, release and support), with sources
2. **The forecast is explicit about its assumptions**
   **Given** no real users yet, and the prices and fees on record (ADR 0013, `docs/distribution.md`)
   **When** the forecast is written
   **Then** it gives, for low, base and high adoption over 12 and 24 months: revenue, hosting, licence, AI tokens of the free use and of paid use, payment fees, support time, and the break-even point; every input is marked as sourced or assumed
3. **The risks are named with a mitigation and an owner**
   **Given** the options
   **When** the risks are listed
   **Then** each one (licence, data protection, security, abuse of the free use, availability and support, the directory's rules, brand and promise) has its likelihood, impact, mitigation and who carries it, and the deal-breakers are marked
4. **The owner's decision is recorded**
   **Given** the findings and the forecast
   **When** the owner decides
   **Then** an ADR records go or no-go with the chosen option; on a go, the input for `bmad-spec` and an epic envelope through `bmad-ticket`; on a no-go, the reasons and what would reopen the question

## References

- user paths — docs/user-paths.md (A4 Connect the chain, the friction wall; A5 First review; the activation metric)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (seller, plans, prices, support promise)
- decision — docs/adr/0014-two-builds-from-one-source.md (Free and Pro builds)
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md (the n8n paths today, the per-site webhook path)
- decision — docs/adr/0009-multi-provider-models-and-token-limit.md (model choice and a site-wide token limit)
- source — _bmad-output/backlog/spike-commercial-model-decided.md (spike 43, the commercial model)
- source — docs/distribution.md (prices, fees, checkout); memory/release-readiness.md; memory/n8n-provisioning.md; docs/policies/privacy.md

## Notes

- Filed 2026-11-04 at the owner's request: "analyze and forecast the benefits, cost, risk and other things to analyze to provide an already existing n8n workflow to every user from different website; the goal is to open new paid plan (with free use) to more users; the ones that can't create their own n8n App … It can possibly drive to a new Epic."
- Decision (owner, 2026-11-04): **the full analysis and the plan are done with Fable 5.1** (every subagent of this spike runs with `model: "fable"`), an exception to the 2026-10-26 rule that runs every agent on Opus.
- Numbered 110: id 109 lives on PR #234's branch (story 109, the post editor meta box).
- To check first (agent, unverified): whether n8n's Sustainable Use License allows running a workflow for paying third parties, or whether a commercial licence (n8n's embed offer) is needed; read the licence text and n8n's licensing FAQ at the time of the spike.
- To check (agent): who pays the AI tokens of the free use (the owner's provider key with a quota, or the blogger's own key); the owner then processes bloggers' unpublished posts (processor role, data processing agreement, sub-processors, data location, retention, privacy policy); multi-tenant isolation and the storage of provider keys; the WordPress.org guidelines for a plugin that relies on an external paid service (disclosure, free use); the support promise of a private person (ADR 0013).
- Evidence on 2026-11-04: still no real users or installs; the forecast rests on assumptions until `epic-ebm-telemetry` or the launch gives numbers.
- Not ranked yet: the owner places it.
