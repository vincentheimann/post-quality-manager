---
id: 63
type: story
title: "An interested visitor who is not ready can leave an email and hears from us again"
parent: none
covers: [UV7, O9]
after: [57, 60, 64]
assignee: ""
refined: false
hitl: true
risk: medium
---

# An interested visitor who is not ready can leave an email and hears from us again

## Description

A visitor who reads postquality.com and is interested but not ready (no n8n yet, no API key, waiting for the WordPress.org listing) has nowhere to leave that interest today: the site has no form, GitHub Pages has no backend, and the plugin only hears from someone once installed. This story adds one place on the site to leave an email, and a short follow-up sequence written with the copywriting skill (story 60): what the plugin is, how to get ready (n8n, a key, the cost), when Free lands on WordPress.org, and one invitation to Pro. The tool is the owner's choice at refinement (hitl), under the site's no-tracking rule and the privacy policy.

## Acceptance Criteria

1. **One place to leave interest**
   **Given** the front page and the pricing page
   **When** a visitor is not ready to install or buy
   **Then** each offers one way to leave an email with one sentence saying what they will receive and how often, and nothing else on the page asks for it
2. **Consent and privacy hold**
   **Given** the email form
   **When** it is submitted
   **Then** the visitor confirms by email before anything is sent (double opt-in), every message carries an unsubscribe that works in one click, the site still loads no analytics or tracking script (ADR 0013 D8), and `docs/policies/privacy.md` names the tool, what it stores and for how long before the form goes live
3. **A sequence, not a blast**
   **Given** a confirmed address
   **When** the follow-up runs
   **Then** it sends the agreed messages in order at the agreed spacing, each with one next step, and stops after the last one; a purchase or an unsubscribe stops it earlier
4. **Readable without the tool**
   **Given** the sequence's texts
   **When** they are reviewed
   **Then** they live in the repository as the source the tool copies from, follow the Copy Rules, and name no product the reader does not have
5. **Owner-verified end to end**
   **Given** the live form
   **When** the owner subscribes with a test address (hitl)
   **Then** the confirmation, the first message and the unsubscribe all arrive and work, recorded in the plan

## Boundaries

- Must not change: the no-tracking rule of ADR 0013 D8; the page URLs of story 45; the Freemius opt-in and its data (the plugin's channel stays Freemius).
- Out of scope: newsletters beyond the sequence; sharing addresses with Freemius; any capture inside the plugin.

## References

- parent — none
- site — _bmad-output/backlog/story-site-content-style-checkout-best-practices.md (57: the pages this adds to)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8)
- policy — docs/policies/privacy.md (the static-site paragraph: no cookies, no analytics)
- measure — _bmad-output/backlog/spike-funnel-measurement-without-tracking.md (64: whether sign-ups are counted, and how)
- copy — the copywriting skill of story 60; docs/brand.md

## Notes

- Filed 2026-10-13 (owner: "nudge those with interest").
- Risk medium: personal data leaves the site to a third party under a policy the owner publishes; a mistake is a privacy and reputation cost, not a revert. Check outside the criteria: the owner reviews the privacy text and the tool's data-processing terms before the form goes live.
- Unknown (gates the start): the tool. Candidates to compare at refinement: a hosted form-and-sequence service with a static-site embed and EU data processing; a mailto-based "write to us" with a manual reply, zero tooling; Freemius's own marketing opt-in (installed users only, so not for visitors). The owner decides.
- Open question: whether the sequence is one for everyone or branches on the reason given (no n8n yet, waiting for WordPress.org, cost).
