---
id: 46
type: story
title: "Support, privacy, terms and refund policies exist before the first sale"
parent: none
covers: [UV7, O9]
after: [43]
assignee: ""
refined: false
hitl: true
risk: low
---

# Support, privacy, terms and refund policies exist before the first sale

## Description

Three platforms ask for policy text the project does not have: the WordPress.org readme needs the external-services disclosure and a privacy statement (story 38 AC3), the licensing provider asks for terms, refund and privacy URLs at product setup (story 44), and the public repository needs a security policy (story 48). This story writes them once, in the seller's name, and places each where the platforms read it. The plugin's own data story is simple and must be said plainly: the plugin sends post content to the user's own n8n instance, which calls the AI provider the user configured; the seller receives nothing from the plugin except what the licensing provider's opt-in and license check collect (story 43 D8).

## Acceptance Criteria

Verify: a privacy policy covers the plugin (what is sent, to whom, when; the licensing provider's opt-in and license check; the website), is published on the site (story 45) and summarized in the readme's External services section; terms of service for Pro state that the code is GPL-2.0-or-later and that the license buys updates and support, not the code; the refund policy matches story 43 D6; the support policy names the channels and response time of story 43 D9 (Free: WordPress.org forum; Pro: the stated channel); a security policy names a private reporting address and the supported versions, lives in `SECURITY.md` (story 48 wires the repository side) and is linked from the readme; the text for `wp_add_privacy_policy_content()` (story 38 AC3) is drafted here; every text names the seller of story 43 D2 and is approved by the owner (hitl).

## Boundaries

- Wording only; no plugin code except handing the privacy text to story 38.
- Not legal advice: the drafts follow what the platforms require; the owner validates them with whoever they trust for their jurisdiction.

## References

- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC3: external services and privacy text)
- licensing — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: the provider needs the URLs)
- repository — _bmad-output/backlog/story-public-repository-welcomes-issues-and-security-reports.md (story 48: SECURITY.md)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (guidelines 7 and 9: data collection and disclosure)
- external — https://developer.wordpress.org/plugins/privacy/ (privacy-policy content hooks)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43).
- Decision (owner, 2026-10-09, spike 43, ADR 0013): refund = 14-day money-back guarantee handled by Freemius; support = WordPress.org forum for Free, best effort, and email for Pro; the privacy text discloses the Freemius opt-in and license check as well as the n8n and AI-provider calls. Seller named in every text: Vincent Heimann, private person (D2); Pro response time: 2 business days at hello@postquality.com (D9); the pages live on postquality.com (story 45).
