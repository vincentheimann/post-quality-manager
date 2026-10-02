---
id: 9
type: story
title: "An agent verifies the plugin on a local WordPress through WP-CLI"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# An agent verifies the plugin on a local WordPress through WP-CLI

## Description

Owner request (2026-10-02). Every real-site check is currently a human manual pass — the CLI harnesses prove logic against WP stubs, but "does it behave on a real WordPress" waits for the owner's browser. The owner mounts a WordPress on localhost with Local (localwp.com), symlinks the plugin into `wp-content/plugins`, and exposes the site's WP-CLI so agent sessions can run real verification headlessly: activate the plugin, set options, create one user per role, run the cron event, call the REST routes, read `debug.log`. This automates much of the standing manual-pass load (backlog tickets 1, 6 and 8, and parts of epic story 1.8); browser look-and-feel stays human. HITL: the owner installs Local, creates the site, symlinks the plugin and shares the site path — the agent then builds and documents the harness around it.

## Acceptance Criteria

1. **Reachable environment**
   **Given** the owner's Local site with the plugin symlinked, **when** an agent session follows the documented entry point from its own shell, **then** `wp plugin list` answers and shows `post-quality-manager` active — no browser, no human step.
2. **Smoke sequence**
   **Given** the documented setup, **when** the agent runs the documented smoke sequence (write a setting via `wp option`, create Administrator/Editor/Author/Contributor test users, `wp cron event run pqm_cron_review_posts`, call a REST route per role, tail `debug.log`), **then** every command completes headlessly and the doc names the expected output of each.
3. **Webhook capture**
   **Given** `n8n_webhook_url` pointed at the documented local capture endpoint, **when** a command triggers, **then** the agent can read the captured payload — full round-trips no longer need a live n8n.
4. **Survives a fresh session**
   **Given** a brand-new agent session with no conversation history, **when** it reads the repo memory, **then** it finds the environment fact and can run checks 1–3 from the docs alone.

## Boundaries

- Must not change: plugin code — this ticket builds tooling and docs only; a defect it surfaces becomes its own bug ticket.
- Browser UX verification (layout, loading states, look and feel) stays human; this reduces the manual passes, it does not replace them.
- The test site runs `WP_DEBUG` + `WP_DEBUG_LOG`; nothing from this setup ships in the plugin zip.

## References

- rules — AGENTS.md "Running and verifying" (the manual-verification duty this automates)
- served — backlog tickets 1, 6, 8 and epic story 1.8 (the passes this shrinks)

## Notes

- Assumption: Local (localwp.com) is the tool — the owner named it. If its per-site shell environment proves un-scriptable from the agent's shell, fall back to `wp-env` (Docker); decide only if hit.
- Open question: webhook capture mechanism — a tiny local HTTP listener is preferred over a public request bin so no post content leaves the machine; settle when the ticket starts.
- HITL steps (owner): install Local, create a PHP 8.x site, symlink `post-quality-manager/post-quality-manager` into `wp-content/plugins`, enable `WP_DEBUG_LOG`, share the site path with the agent.
