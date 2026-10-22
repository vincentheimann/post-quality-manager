---
title: 'postquality.com sends no visitor to a dead end and says what is true today'
type: 'feature'
ticket: '85'
created: '2026-10-22'
status: 'done'
baseline_revision: 'ec2d076d84c8751a970301952b414fff3c3e039d'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: ['{project-root}/_bmad-output/backlog/story-site-hygiene-no-dead-ends.md', '{project-root}/_bmad-output/backlog/story-site-content-style-checkout-best-practices-audit.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** postquality.com sends visitors to 404s (the private plugin repository's releases, issues and security pages), carries a stale privacy page and a docs page describing the setup before the n8n provisioning epic, has no policy links outside the header, no favicon, no 404 page of its own, no `sitemap.xml` or `robots.txt`, two `<h1>` on the front page, and link and footer text under 4.5:1 contrast (story 85, from story 57's audit).

**Approach:** On the existing Jekyll 3.10 + `minima` 2.5 site, in two repositories on branch `feat/site-hygiene`: the plugin repository edits `docs/policies/terms.md`'s currency line (USD, EUR and CHF) first; the site repository gets the policy copies, a rewritten docs page, the Free action as a `mailto:`, a footer and head override, a stylesheet override, `404.html`, `robots.txt`, the sitemap plugin and the icon files. Draft PRs in both repositories; the owner merges (hitl).

**Decisions (owner, 2026-10-22):** hygiene slice now; Jekyll + GitHub Pages kept; Free action = `mailto:hello@postquality.com` with a prefilled subject until story 63; story 68 folded in; the agent pushes and opens PRs under the scoped rules; branch `feat/site-hygiene` in both repositories; the site clone lives at the workspace root. Local build before pushing: a `Gemfile` with `github-pages` (and its lock) committed to the site repository, gems installed into its ignored `vendor/`, `bundle exec jekyll build` checked before any push (owner, 2026-10-22). Plan kept whole at ~1,800 tokens (owner, 2026-10-22). Scope widened after review pass 1 (owner, 2026-10-22): until the plugin is listed on WordPress.org, Free questions and bug reports go to hello@postquality.com (best effort), and until the plugin repository is public, security reports go to hello@postquality.com privately; `docs/policies/support.md` says so (edited first, then copied) and the pricing table's Free Support cell matches — prices unchanged.

## Boundaries & Constraints

**Always:** the seven URLs answer as today; policy pages render the text of `docs/policies/` (front matter title replaces the `#` heading, as today); copy per `docs/brand.md` (full name first, then "Post Quality"); no analytics or third-party script; only GitHub Pages' allowed plugins (`jekyll-sitemap`, `jekyll-seo-tag`, `jekyll-feed`).

**Never:** prices or plans changed, or the pricing table beyond its Free Support cell; `docs/policies/` changed beyond the terms' currency line and the support policy's Free and Security sections; screenshots, OG image, structured data, buy buttons or redesign (story 57); a CI workflow in the site repository; a merge.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Visitor follows any link | the seven pages, logged out | 200, or a `mailto:` to hello@postquality.com | — |
| Visitor wants Free | front page, docs page, support page | one sentence: not installable yet, leave your email; a `mailto:` with subject "Tell me when Post Quality Manager is available" | — |
| Any page | load | footer links Privacy, Terms, Refund, Support; favicon from `assets/icon.svg` with a PNG fallback | — |
| Missing URL | `/nope/` | status 404, the site's page with a link home | — |
| Crawler | `/robots.txt`, `/sitemap.xml` | allow all, `Sitemap:` line; the seven pages listed, `404.html` excluded | — |
| Policy drift | site privacy, terms, refund, support vs `docs/policies/` | same body text after the front matter | — |
| Contrast | links, footer text | ≥ 4.5:1 on white | — |
| Front page | headings | exactly one `<h1>` | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/docs/policies/terms.md` L17 -- "in USD and EUR" → "in USD, EUR and CHF"; bump its "Last updated" line to 2026-10-22.
- `post-quality-manager/docs/policies/support.md` -- "Last updated" 2026-10-22; "Free plugin" (L7): questions and bug reports go to hello@postquality.com, best effort, until the plugin is listed on WordPress.org, then its support forum; "Security problems": report privately to hello@postquality.com (drop the GitHub security-policy URL until the repository is public). Pro section unchanged. Then copy the body to `postquality.com/support.md`.
- `postquality.com/pricing.md` L17 -- Free Support cell "WordPress.org forum" → "email, best effort"; nothing else in the file.
- `postquality.com/docs.md` L10 Requirements (review finding 2) -- "an API key for the AI provider you want to use (Anthropic by default)" → an Anthropic API key (the workflow calls Anthropic; the setup guide takes `anthropic_key`). Check the rest of the Requirements and FAQ for the same claim.
- `post-quality-manager/docs/distribution.md` (review finding 5) -- L62 the favicon row: wired by story 85 as `assets/icon.svg` + `icon-128x128.png` in the site's `_includes/head.html`; L121 heading "(story 45, later story 50)" → "(story 45; story 57 improves it)"; L127 "no build step" → built by GitHub Pages ("deploy from a branch", Jekyll 3.10 via `github-pages`), local build per the site README.
- `post-quality-manager/memory/n8n-provisioning.md` L12 (review finding 5) -- the clause "the `postquality.com` privacy page still needs the sync (entries 1 and 6, one site PR, owner's word)" → synced by story 85 (2026-10-22).
- `postquality.com/` (workspace root clone, `main` = `fc5442c` = live) -- Jekyll 3.10, `minima` 2.5.1, `_config.yml` (title, description, author, email, `header_pages`, `exclude: [README.md]`); pages `index.md` (layout home), `pricing.md`, `docs.md`, `privacy.md`, `terms.md`, `refund.md`, `support.md` (layout page, permalinks), `CNAME`, `README.md`.
- Policy copies: `privacy.md` is the 2026-10-09 text — replace the body with `docs/policies/privacy.md` minus its `# ` heading; `terms.md` takes the new currency line; `refund.md` and `support.md` already match (diff shows only the heading). Keep each file's front matter.
- `support.md`: the WordPress.org forum URL is plain text in the policy (not rendered as a link); leave it.
- `index.md`: drop the Markdown `# Post Quality Manager` (layout home already prints `page.title` as `<h1 class="page-heading">`; set `title: Post Quality Manager` so that heading reads right); replace the GitHub releases sentence in "Get it" with the interest `mailto:`.
- `docs.md`: rewrite "Install" for today — not installable yet (interest `mailto:`); once available: activate, open Post Quality → n8n Workflow, the setup guide connects your n8n with an n8n API key and sets the workflow up (three values typed: n8n URL, n8n API key, AI provider key), or "Set up by hand" with the Workflow File section; first review from the guide. Remove every github.com link (releases, issues, security); bug reports and security → hello@postquality.com. Read `post-quality-manager/memory/n8n-provisioning.md` (entry 6 line) and `docs/user-paths.md` A4 for the facts; keep the rest of the page.
- `_includes/footer.html` -- override minima 2.5.1's footer: keep its contents (site title, email, description), add a nav of Privacy, Terms, Refund, Support links. Copy the gem's file first (`gem contents minima -v 2.5.1` or the live HTML) so nothing else changes.
- `_includes/head.html` -- icon links in this order: the 128px PNG first, the SVG last (review finding 6). Override minima 2.5.1's head (`{%- seo -%}`, `main.css`, `{%- feed_meta -%}`; the Google Analytics include removed, ADR 0013 D8) plus `<link rel="icon" type="image/svg+xml" href="/assets/icon.svg">` and `<link rel="icon" type="image/png" sizes="128x128" href="/assets/icon-128x128.png">`.
- `assets/icon.svg`, `assets/icon-128x128.png` -- copies of `post-quality-manager/assets/` (story 51).
- `assets/main.scss` -- front matter `---\n---`, then `$brand-color` and `$grey-color` darkened to reach 4.5:1 on white (e.g. `#1a5fb4`, `#6a6a6a`; compute, do not guess), then `@import "minima";`.
- `404.html` -- layout page, `permalink: /404.html`, `sitemap: false`, one sentence and a link home.
- `robots.txt` -- `User-agent: *`, `Allow: /`, `Sitemap: https://postquality.com/sitemap.xml`.
- `_config.yml` -- `plugins: [jekyll-feed, jekyll-seo-tag, jekyll-sitemap]`; add `Gemfile`, `Gemfile.lock`, `vendor` to `exclude`.
- `Gemfile.lock` platforms (review finding 4) -- after `bundle install`, `bundle lock --add-platform x86_64-linux arm64-darwin x86_64-darwin`, so the lock resolves off Windows.
- `Gemfile` (+ `Gemfile.lock`) -- `source "https://rubygems.org"`, `gem "github-pages", group: :jekyll_plugins`; `.gitignore` with `_site/`, `vendor/`, `.bundle/`, `.jekyll-cache/`; `bundle config set --local path vendor/bundle`. On Windows `wdm`/`tzinfo-data` may be needed for `jekyll serve` only — build only, add nothing else unless the build fails.
- `README.md` (site) -- drop the stale Astro/story 50 sentence; say story 57 owns the site's improvement; the local-build line starts with `bundle config set --local path vendor/bundle` (review finding 3: `.bundle/` is ignored, so a fresh clone needs it) and keeps `PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""` with its reason; on Windows the Ruby with the DevKit is `C:/Ruby32-x64` (run with `MSYSTEM`/`MSYS` unset).
- Workspace `CLAUDE.md` -- Repository row added 2026-10-22 (outside both repositories, done).
- Reuse pass 1 (Plan Change Log, KEEP): the snapshot `C:/Users/vhe/AppData/Local/Temp/claude/c--Git-Post-Quality/8b84c8b5-6bf4-48a7-b945-0eb7b9def8c5/scratchpad/story85-pass1/` holds every pass-1 file (site/ and plugin/ trees) and the pass-1 diff; start from those files and apply only the amendments above. The installed gems (`postquality.com/vendor/`, `.bundle/`) are kept and ignored; no reinstall is needed. The check scripts `check_site.py`, `sync_policies.py`, `contrast.py` are in the scratchpad; extend `check_site.py` so it also fails on any `wordpress.org` or `github.com` URL in the built text of the seven pages, linked or not.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/docs/policies/terms.md` -- currency line + date -- source first.
- [x] `post-quality-manager/docs/policies/support.md` -- Free and Security sections until listed / public -- source first.
- [x] `postquality.com/privacy.md`, `terms.md`, `support.md`, `pricing.md` (Free Support cell) -- copy the policy bodies, align the pricing row -- AC2, AC4, story 68.
- [x] `post-quality-manager/docs/distribution.md`, `memory/n8n-provisioning.md` -- the stale lines -- records.
- [x] `postquality.com/index.md`, `docs.md` -- one `<h1>`, interest `mailto:`, docs install text and Requirements for today, no GitHub links -- AC1, AC2, AC4.
- [x] `postquality.com/_includes/footer.html`, `_includes/head.html`, `assets/*`, `assets/main.scss` -- policy links, favicon, contrast, no analytics include -- AC3, AC5.
- [x] `postquality.com/404.html`, `robots.txt`, `_config.yml`, `README.md` -- AC3.
- [x] Verify per Verification; record the evidence in Implementation Notes.

**Acceptance Criteria:**
- Given the built site, when every `href` on the seven pages and `404.html` is listed, then each internal one resolves to a built file and each external one is a `mailto:` or answers 200 (wordpress.org, freemius.com, n8n.io, anthropic.com and the like are checked live).
- Given the merged site live on GitHub Pages, when the agent re-crawls it, then the matrix holds on https://postquality.com (owner merges first; hitl).

## Spec Change Log

## Implementation Notes

- Baselines: plugin repository `ec2d076d84c8751a970301952b414fff3c3e039d`; site repository (`C:/Git/Post Quality/postquality.com`) `fc5442c376df76206f4196e4e87acf86a74df792`. Both on `feat/site-hygiene`, clean at the start. Rendered by hand from the workspace root (owner rule 2026-10-16); implementer and review lens on Opus 5.5 (session rule).
- Environment: Git Bash on this PC sometimes dies at startup (`msys-2.0.dll … add_item … errno 1`, exit 5) without running anything; rerun the same command. Nothing is committed or pushed by the implementer.
- Implemented 2026-10-22 (implementer, Opus 5.5). Plugin repository: `docs/policies/terms.md` L3 date → 2026-10-22, L17 "in USD, EUR and CHF". Site repository: `privacy.md`, `terms.md` bodies copied from `docs/policies/` (front matter kept); `index.md` title `Post Quality Manager`, Markdown `#` dropped, Free bullet → interest `mailto:` (subject "Tell me when Post Quality Manager is available"); `docs.md` Install rewritten (not installable yet + the same `mailto:`, then the setup guide's two steps with the three values, "Set up by hand instead" → Workflow File section, Settings → AI), bug FAQ → hello@postquality.com, no github.com link left; `_includes/head.html` and `_includes/footer.html` copied from minima v2.5.1 (tag source) — head: analytics block removed, two icon links added; footer: a `<nav aria-label="Policies">` with Privacy, Terms, Refund, Support in footer-col-2; `assets/icon.svg`, `assets/icon-128x128.png` copied from the plugin; `assets/main.scss` (`$brand-color #2470d1`, `$grey-color #737373`, `$grey-color-light`/`-dark` pinned to minima's derived `#e8e8e8`/`#424242` so borders and shading stay); `404.html`, `robots.txt`, `_config.yml` (plugins + excludes), `Gemfile` + `Gemfile.lock` (github-pages 232: jekyll 3.10.0, minima 2.5.1, jekyll-sitemap 1.4.0), `.gitignore`, `README.md`.
- Build environment: the PATH's Ruby 3.1 (`C:/tools/ruby31`) has no MSYS2 devkit, so native gems failed; `C:/Ruby32-x64` (3.2.2, devkit bundled) installed them with `MSYSTEM`/`MSYS` unset (`env -u MSYSTEM -u MSYS bundle install`; ~45 min, native builds of json, eventmachine, commonmarker…). `Gemfile.lock` platform is `x64-mingw-ucrt` only; GitHub Pages' classic build ignores the Gemfile. No `wdm`/`tzinfo-data` needed for `build`. Locally `jekyll-github-metadata` (no token) cannot read the remote and either fails ("No repo name found") or guesses `baseurl: /pages/vincentheimann/postquality.com`; the build therefore runs as `PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""` (written in the site README). `_config.yml` unchanged for that.
- Verification evidence (2026-10-22): `JEKYLL_ENV=production … jekyll build` done, no error; `_site/` has the seven pages, `404.html`, `robots.txt`, `sitemap.xml`, `feed.xml`, `assets/icon.svg`, `assets/icon-128x128.png`, `assets/main.css`. Link/heading script (scratchpad `check_site.py`) over the seven pages + `404.html`: one `<h1>` each, both icon links each, the four policy links in every footer, every internal href resolves to a built file, no `google-analytics`/`gtag`/external script, hrefs outside the site: none except `mailto:hello@postquality.com` (plain and with the subject); sitemap lists exactly the seven URLs, no `404.html`; robots.txt has `Allow: /` and the `Sitemap:` line → NO PROBLEMS. Contrast (WCAG formula): `#2470d1` 4.87:1 on `#fff`, 4.79:1 on minima's `#fdfdfd` body background (was `#2a7ae2` 4.15); `#737373` 4.74 / 4.66 (was `#828282` 3.78); built `main.css` carries neither old value. Policy diff (scratchpad `sync_policies.py --check`): privacy, terms, refund, support bodies identical to `docs/policies/` after the front matter. Front page `<title>` now "Post Quality Manager | <tagline>" (was "Home | Post Quality Manager").
- Plain-text URLs (not links, policy text kept verbatim per the plan): `support.md` still prints https://wordpress.org/support/plugin/post-quality-manager/ (404 today) and https://github.com/vincentheimann/post-quality-manager/security/policy (404 today, private repository); the Freemius privacy and terms URLs in the policies answer 200. Changing them means changing `docs/policies/support.md`, outside this plan's boundary — owner's call. (Superseded by pass 2: none left.)
- Pass 2 implemented 2026-10-22 (implementer, Opus 5.5), from the `story85-pass1/` snapshot (restored byte-for-byte) plus the amendments. Plugin repository: `terms.md` as pass 1; `docs/policies/support.md` dated 2026-10-22, Free section = the interest sentence with the same `mailto:` and subject as the front and docs pages (the frozen matrix lists the support page under "Visitor wants Free" and its drift row needs that page to equal the policy, so the sentence lives in the policy), then questions and bug reports to hello@postquality.com, best effort, until listed, then the forum there; Security = privately to hello@postquality.com, the GitHub URL dropped; `docs/distribution.md` L62/L121/L127 and `memory/n8n-provisioning.md` L12 per the Code Map. Site repository: `_includes/head.html` PNG link first, SVG last; `docs.md` Requirements "an Anthropic API key: the workflow runs the reviews on Anthropic's models", and the FAQ's same claim (cost: "You pay Anthropic per call, on your own API key", "A worked example will be added here", was "per provider"; content: "and, through it, to Anthropic"); `pricing.md` Free Support "email, best effort", nothing else; `support.md` synced; `Gemfile.lock` + `arm64-darwin`, `x86_64-darwin`, `x86_64-linux` (only `ffi` and `nokogiri` platform variants added; github-pages 232, jekyll 3.10.0, minima 2.5.1, jekyll-sitemap 1.4.0 unchanged); README build line starts with `bundle config set --local path vendor/bundle` and names `C:/Ruby32-x64` with `MSYSTEM`/`MSYS` unset.
- Scripts (scratchpad): `check_site.py` now fails on any URL to wordpress.org or github.com (case-insensitive, linked or plain text) and on the lowercase domain string anywhere in a page; "WordPress.org" as the directory's name is reported, not failed; icon order checked exactly; the interest `mailto:` required on `/`, `/docs/`, `/support/`. Negative test on the stale pass-1 `_site/`: it failed on the support page's two plain-text URLs, its missing interest link and the icon order on all eight pages. `sync_policies.py` strips CR: the revert under `core.autocrlf=true` wrote `pricing.md`, `privacy.md`, `terms.md` as CRLF (index LF; the rewritten files are LF, the diffs show only real changes).
- Verification evidence (pass 2, 2026-10-22): `JEKYLL_ENV=production PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""` (`C:/Ruby32-x64`, `MSYSTEM`/`MSYS` unset; build only, no server) done, no error. `check_site.py _site` → NO PROBLEMS: one `<h1>` on each of the seven pages and `404.html`, icons PNG then SVG, the four footer policy links, every internal href resolves, the only hrefs leaving the site are `mailto:hello@postquality.com` (plain and with the subject), no wordpress.org/github.com URL or domain string, "WordPress.org" named once (on `/support/`), sitemap = the seven URLs without `404.html`, robots.txt `Allow: /` + `Sitemap:`. No external `<a href>` exists; the plain-text URLs left (freemius.com/privacy/, freemius.com/terms/, the postquality.com policy and pricing URLs) answer 200 live. Contrast `#2470d1` 4.87 / 4.79, `#737373` 4.74 / 4.66 (on `#fff` / `#fdfdfd`); built `main.css` has neither `#2a7ae2` nor `#828282`. `sync_policies.py --check`: the four bodies identical to `docs/policies/`. Exact rendered strings checked in `_site/` (support Free and Security, docs Requirements and FAQ, the pricing cell, terms CHF, privacy 2026-10-14, the front page's `<h1>` and `<title>` "Post Quality Manager | …"). `git diff --check` clean in both repositories; nothing staged, committed or pushed.
- Left outside the Code Map (owner's call): ADR 0013 D9 ("Free gets the WordPress.org support forum") and `.github/ISSUE_TEMPLATE/config.yml` (security-policy URL, forum wording) describe the post-listing state; the front page still says "the workflow asks the AI provider you chose" and "the AI provider account", a provider choice that does not exist today (the plan scoped the Anthropic fix to the docs page).
- Live check 2026-10-22 (agent, the plan's Manual checks), after the owner merged both PRs (site #1 → `7348fcf`, plugin #174 → `3d2a845`): the GitHub Pages build of `7348fcf` `built` in 59 s. `check_site.py --live https://postquality.com` → NO PROBLEMS: the seven pages answer 200 and `/nope/` answers 404 with the site's 404 page, one `<h1>` each, icons PNG then SVG, the four footer policy links, the interest `mailto:` on `/`, `/docs/`, `/support/`, no wordpress.org or github.com URL; `/robots.txt`, `/sitemap.xml` (the seven URLs), `/assets/icon.svg` and `/assets/icon-128x128.png` answer 200. The four policy bodies on the site's `main` equal `docs/policies/` (story 68 AC1); the live privacy page carries entries 6.1 and 6.6's text, dated 2026-10-14. The front-page clause of the line above was closed in pass 2 (finding 4): `index.md` no longer names a provider choice; "AI provider" stays as the generic term in the policies and the pricing note.

## Plan Change Log

**Loopback 1 (2026-10-22), review pass 1, intent_gap (finding 1) with bad_plan findings 2 and 5:** code reverted in both repositories; a full snapshot of pass 1 is kept in the session scratchpad (`story85-pass1/`). Known-bad state avoided: a site whose support and pricing pages contradict the docs page and point to 404s, a docs page naming a provider choice that does not exist, and stale records in the plugin repository. Amendments wait for the owner's answer on finding 1, then the Code Map gains: `docs.md` Requirements (Anthropic only), `docs/distribution.md` L62/L121/L127, `memory/n8n-provisioning.md` L12, and the three patches (README bundle path, lock platforms, icon order).
KEEP: the policy-body copies, the `index.md` single-`<h1>` change and interest `mailto:` with its subject, the rewritten Install section of `docs.md`, the minima 2.5.1 head/footer overrides (analytics removed, four footer policy links), `assets/main.scss` (`#2470d1`, `#737373`, the two pinned greys), `404.html`, `robots.txt`, `_config.yml` plugins and excludes, the `Gemfile` and `.gitignore`, the README's `PAGES_REPO_NWO … --baseurl ""` build line, and the check scripts in the scratchpad (`check_site.py`, `sync_policies.py`).


## Review Triage Log

**Pass 2 (quick lens, Opus 5.5, 2026-10-22): 8 findings — high 0, medium 0, low 8, false 0, maybe-false 0; all 8 → patch (the step-03 implementer re-engaged). Pass 1's findings 1–6: none repeated.**

1. low → patch — `docs.md` Install step 3 names "the Workflow File section"; since story 67 the manual steps sit under **Set up by hand** (`id="pqm-by-hand"`). Verified: "Workflow File" occurs 0 times in `class-pqm-admin.php`. The plan's Code Map carried the old name from memory.
2. low → patch — `docs.md` step 2: the page's button is **Set up n8n**; "Set up the workflow" is the notice link and the dialog step; the first review is picked and run by the user. Verified at `class-pqm-admin.php` (`'Set up n8n'`).
3. low → patch — `docs.md` steps 3–4 describe Pro (three credentials, Automated Reviews) to a Free reader. Verified: "two credentials, two Claude nodes" in `render_workflow_file()`; `stepsFor()` drops `schedule` on Free.
4. low → patch — `docs.md` step 2 ("your AI provider key") and `index.md` "How it works" still present a provider choice that does not exist; the site now contradicts itself. Verified against the dialog label and the template.
5. low → patch — `docs/brand.md` Icon Files line still names `icon-512x512.png` as the favicon. Verified.
6. low → patch — `memory/workspace-layout.md` says the workspace holds one git repo; the site clone now sits at its root (story 85 decision). Verified.
7. low → patch — `memory/n8n-provisioning.md` L12 and `docs/distribution.md` L62 state as live what is live only after the owner merges the site PR. Verified by reading; fix words them as done by the PR, not live.
8. low → patch — site `README.md` L3 links the private plugin repository (404 for visitors of the public site repo). Verified: repository private (audit).


**Pass 1 (quick lens, Opus 5.5, 2026-10-22): 6 findings — high 0, medium 2, low 4, false 0, maybe-false 0. Routes: 1 intent_gap, 2 bad_plan, 3 patch → loopback (intent_gap outranks; patches carried into the re-derivation).**

1. medium → intent_gap — `postquality.com/support.md` L11/L21 and `pricing.md` L17: the support page still sends Free users to the unlisted WordPress.org forum (404) and security reports to the private repository's policy page (404); the pricing table says Free support is the forum; `docs.md` now says email for both. Ticket AC2 names the support page; the plan's frozen matrix narrowed it to the docs page with no record. Verified on `_site/support/index.html`. The fix edits `docs/policies/support.md` and the pricing table's Support row, both behind the frozen Never → the owner decides.
2. medium → bad_plan — `postquality.com/docs.md` L10 Requirements: "an API key for the AI provider you want to use (Anthropic by default)"; the template calls only `api.anthropic.com` and `POST /n8n/provision` takes `anthropic_key`. Verified by grep. The Code Map's "keep the rest of the page" left it; ticket AC4 wants the docs page true today.
3. low → patch — site `README.md`: `bundle install` lands in the system gem path on a fresh clone (`.bundle/` is ignored, `BUNDLE_PATH` lives only there). Verified with `git check-ignore`. Fix: the `bundle config set --local path vendor/bundle` step in the README.
4. low → patch — `Gemfile.lock` lists only `x64-mingw-ucrt`; a Linux or macOS install rewrites it. Fix: `bundle lock --add-platform x86_64-linux arm64-darwin x86_64-darwin`.
5. low → bad_plan — durable facts left stale: `docs/distribution.md` L62 (favicon "story 57 wires it, the site has no custom head yet"), L121 ("later story 50"), L127 ("no build step"); `memory/n8n-provisioning.md` L12 ("the postquality.com privacy page still needs the sync"). Verified by reading. The ticket references `docs/distribution.md` (Website section); the Code Map omitted it.
6. low → patch — `_includes/head.html` L7–8: the SVG icon link comes first and the sized PNG last, so browsers that take the last or the sized link show the PNG. Plausible per browser; same artwork. Fix: PNG first, SVG last.


## Design Notes

`minima` 2.5.1 (the version GitHub Pages pins) has no `custom-head` hook, so the head is overridden whole; copying the gem's files keeps the override a two-line diff from upstream. Skipped: `favicon.ico` at the root — modern browsers use the `<link rel="icon">`; add when a crawler report asks for it.

## Verification

**Commands (site repository):**
- `bundle exec jekyll build` -- expected: no error, `_site/` has the seven pages, `404.html`, `robots.txt`, `sitemap.xml`, `assets/icon.svg`.
- a link and heading check over `_site/**/*.html` (a short script in the scratchpad: every `href`, every `<h1>` count, no `google-analytics`/`gtag`) -- expected: as the matrix.
- contrast of the two computed colours (WCAG formula) -- expected: ≥ 4.5.
- `diff` of each policy page's body against `docs/policies/` -- expected: only the heading line.
- no `wordpress.org` or `github.com` string in the built text of the seven pages (links or plain text) -- expected: none.

**Manual checks (agent, after the owner merges the site PR):** re-crawl https://postquality.com with the same script; open `/nope/`; record status codes and the favicon request.
