---
title: "Support, privacy, terms and refund policies exist before the first sale"
ticket: 46
status: done
---

Wording only, no plugin code. Drafted 2026-10-09 in `docs/policies/` from ADR 0013 and a read of what the plugin actually sends and stores (`pqm_webhook_payload()` in the main file: post id, command, site and callback URLs, token, `brand_voice`, `website_context`, the `llm` map; `GET /post-score`: title, slug, link, excerpt, content and the five Yoast fields; the reviews table; `pqm_monthly_usage` user meta; `uninstall.php`):

- `privacy.md`: plugin (what leaves the site, to whom, what is stored, nothing from visitors), Freemius (purchase data, license check, opt-in usage data off by default), the website (static, no cookies), rights, changes.
- `terms.md`: GPL code vs what a Pro license buys (unlock, updates, support for one year per pack), Freemius as seller of record, sites and renewal at the bought price, lapse to Free keeping data, refund, acceptable use, requirements, as-is warranty with liability capped at twelve months of fees, consumer rights untouched.
- `refund.md`: 14 days no questions, how to ask, renewals, after 14 days.
- `support.md`: Free = WordPress.org forum best effort; Pro = hello@postquality.com within 2 business days, scope in and out; security pointer.
- `readme-external-services.md`: the `== External services ==` block and the `wp_add_privacy_policy_content()` text for story 38.
- The security policy already lives in `SECURITY.md` (story 48).

Hitl: the owner approves the wording before story 45 publishes the pages and story 44 enters their URLs in Freemius. The drafts follow what the platforms require and are not legal advice (ticket Boundaries). Third-party legal URLs (n8n, Anthropic, OpenAI, Google, Freemius) are to be opened and confirmed by story 38 at readme time; marked `CHECK` in `docs/distribution.md`.

Verification: every fact in `privacy.md` traced to a line of code or `uninstall.php`; no seller, price or response-time value other than ADR 0013's. Status `built`; done is the owner's mark after reading the texts.
