---
title: "The plugin has a public home: static pages on postquality.com for pricing, docs and policies"
ticket: 45
status: done
---

Built 2026-10-09 after the owner asked for help setting the site up ("I'll need your help on how to set it up").

- **Repository:** `github.com/vincentheimann/postquality.com`, public, created with `gh repo create`; a Jekyll site on the built-in `minima` theme, no build step, no analytics, no CMS. Files: `index.md` (front: tagline, pillars, how it works, Free vs Pro, links), `pricing.md` (feature table, the three site packs in USD and EUR, 14-day guarantee, "Pro goes on sale with the first public release"; the checkout link is story 44's last step), `docs.md` (requirements, the seven-step install from the README, using it, FAQ with a cost placeholder for story 49), the four policies copied from `docs/policies/` with front matter and `permalink:` so the URLs are `/privacy/`, `/terms/`, `/refund/`, `/support/` as entered in Freemius, `CNAME`, `_config.yml`, `README.md` (says `docs/policies/` is the source of truth).
- **GitHub Pages:** enabled on `main` / root through the API; custom domain `postquality.com` set; `https_enforced` left false until the certificate is issued, which needs the DNS below.
- **Plugin side (this branch):** `Plugin URI` and `Author URI` in the plugin header now read https://postquality.com; `docs/distribution.md` website rows updated. `php -l` clean. The README installation text keeps its GitHub link (the developer entry point).
- **DNS, owner:** at the registrar, apex `A` 185.199.108.153 / .109.153 / .110.153 / .111.153, `AAAA` 2606:50c0:8000::153 / 8001 / 8002 / 8003, `CNAME www` → `vincentheimann.github.io` (verified against GitHub's docs 2026-10-09). On 2026-10-09 the apex still resolved to the registrar's address, so the site is not reachable yet.

Left for later, on purpose: the Astro landing page (story 50), the demo and cost figures (story 49), the "Get Pro" checkout link (story 44), the `www` → apex redirect check and `https_enforced=true` once DNS resolves (this story's done mark).

Verification so far: repository pushed, Pages API reports `building` with the cname set, header lint clean. Remaining for done: DNS in place, https://postquality.com and its six pages served over HTTPS, owner has read the pages (hitl).

Done 2026-10-12 (owner decision: keep GitHub Pages as the MVP, story 50 dropped, story 57 filed for the improvement). Verified the same day: DNS at Infomaniak carries the four `A`, four `AAAA` and `CNAME www` (mail records untouched); the Pages API reports the certificate approved for postquality.com and www.postquality.com (expires 2027-01-10) and `https_enforced` true; the seven pages answer 200 over HTTPS; `www` and plain HTTP redirect to https://postquality.com/. Mail at hello@postquality.com confirmed by the owner.
