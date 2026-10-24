---
name: naming
description: Plugin naming locked 2026-10-08 (story 10) — full name Post Quality Manager, short form Post Quality, slug/text domain post-quality-manager permanent, provider-neutral tagline; rules live in docs/brand.md § Naming; story 41 applied them 2026-10-24
metadata:
  type: project
---

**Naming is decided (owner, 2026-10-08, backlog story 10).** The canonical rules are `docs/brand.md` § Naming; this file only points there and records what is not in the code yet.

- Full name **Post Quality Manager** (header, readme, README, repo); short form **Post Quality** (admin menu label, in-copy cross-references). "Content Quality" is retired as a name; since 2026-10-23 no admin string carries it (menu "Post Quality", headings "Dashboard" and "Settings", the not-connected message names "Post Quality → n8n Workflow"; a slice of story 41 pulled forward for story 86's screenshots).
- Slug and text domain `post-quality-manager` stay: checked free on WordPress.org 2026-10-08 (so is `post-quality`), auto-derived from the Plugin Name header at submission, permanent after approval. This unblocks `epic-delivery-pipeline` entry 4.6 (text domain / `languages/`).
- Tagline (readme short description, 124 chars, provider-neutral — no n8n, no model vendor, "AI" kept as discovery keyword): "Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin."
- Page headings name the screen only; "PQM" is code-only; no third-party trademark in name, slug, tagline or imagery.
- Visuals: the WordPress.org asset set and the lockup artwork exist (2026-10-24, story 41) in `assets/` at the repository root (never in the zip): `banner-772x250.png` and `banner-1544x500.png` rendered from `assets/src/banner.html` by headless Edge, `screenshot-1..3.png` copied from the postquality.com captures, `lockup-b.svg` and `lockup-b-dark.svg` (Inter outlined, no `<text>`) by `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py`; canon and re-render recipes in `docs/brand.md` § Imagery and the WordPress.org assets. The copy sweep ran the same day (one fix: the cron schedule label).

Related: [[delivery-pipeline]], [[user-paths]].
