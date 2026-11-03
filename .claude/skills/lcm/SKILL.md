---
name: lcm
description: Life cycle management check (ADR 0017) — compare every part the plugin depends on (AI models, Freemius SDK, WordPress, PHP, n8n, build and CI) with its source of truth, file a ticket for what moved, and update memory/lifecycle.md. Use monthly, before a release PR is merged, or when the user asks about outdated models, dependencies or versions.
---

# Life Cycle Management Check

Run from the repository root. Read `memory/lifecycle.md` first: it holds what is in use and when each part was last checked. The check reads sources and files tickets; it changes no code.

1. **AI models.** Read the provider's current model list and its deprecation schedule. For Anthropic, use the `claude-api` skill's model reference, or the provider's models and deprecations pages. Never answer from memory. Compare with the model registry (`postquality_model_registry()`, `memory/llm-options-architecture.md`), the default model, and any model ID in `templates/n8n-workflow.json` and the Free template.
   - A model in use with a retirement date → a ticket due before that date.
   - A new generally available model → a ticket to add it, and to review the default.
2. **Freemius SDK.** Read the latest tag: `gh api repos/Freemius/wordpress-sdk/releases/latest --jq .tag_name`, or the tags list if releases are empty. Compare it with `$this_sdk_version` in `post-quality-manager/vendor/freemius/start.php`. Newer → a chore ticket, as for chore 89.
3. **WordPress.** Read the current release: `https://api.wordpress.org/core/version-check/1.7/`. Compare its major with the readme's `Tested up to`. A new major → a ticket for the next release, testing included. Note WordPress's recommended PHP on its requirements page.
4. **PHP.** Read the supported versions from php.net or `https://endoflife.date/api/php.json`. If the `Requires PHP` floor is past its end of life and WordPress recommends higher, file a ticket proposing an ADR. Never move the floor without one.
5. **n8n.** Read the latest release: `gh api repos/n8n-io/n8n/releases/latest --jq .tag_name`. Compare it with the versions recorded in `memory/lifecycle.md` and `memory/n8n-provisioning.md`. A new major, or a release note touching the public API, webhooks, credentials or publication → a ticket to check the template against it.
6. **Build.** Run `npm outdated` (and `npm audit --omit=dev` when the plugin ships JS built from them). A new major of `@wordpress/scripts`, or a vulnerability in a shipped dependency → a ticket.
7. **CI.** For each `uses:` in `.github/workflows/*.yml`, read the action's latest release and its deprecation notices. Read the annotations of the latest CI and Release runs (`gh run view <id> --repo vincentheimann/post-quality-manager`) for runner-image migrations and deprecations. A major behind, or a dated migration → a ticket.
8. **Record.** Update `memory/lifecycle.md`: each part's version in use, the latest seen, the check date, and the ticket filed. File tickets through `bmad-ticket` (backlog; a bug when something is already broken). Commit the record and the tickets on a `chore/` branch, open a draft PR, and report one line per part: same, or what moved and its ticket.

A part added to or dropped from the plugin is added to or dropped from `memory/lifecycle.md` and this list in the same change (ADR 0017).
