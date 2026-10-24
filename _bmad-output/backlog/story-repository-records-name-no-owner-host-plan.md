---
title: 'The repository records name the owner''s site, hosts and accounts only generically'
type: 'chore'
ticket: '66'
created: '2026-10-24'
status: 'built'
baseline_revision: '7c16646000dcb10f4ef70677a8f1e3ae8b01e4ee'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 3
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** eight lines in six tracked files still name the owner's own site or its accounts: six name the site (its domain, its n8n subdomain in an old webhook URL, the production Local site's name), three give the old chat webhook URL with the chat provider's host (one line does both), and one more names the site by its title. The portfolio's dashboard image shows the owner's site title and post titles, and the portfolio PDF carries it. Entry 4.8 makes the repository public, and the owner's rule is that records name these only generically.

**Approach:** replace each mention with generic wording ("the owner's own blog", "the owner's production copy") or a placeholder that keeps the URL's path (`https://<owner-n8n>/webhook/…`, `https://<owner-chat>/hooks/…`), so every sentence still says what it said and every finding stays identifiable. The portfolio image becomes the showcase capture of the same view, on sample posts, and the PDF is rebuilt with the portfolio kit's builder.

**Decisions (owner delegation 2026-10-24, "make choices based on best practices"):**
- Story 66's open question: the provider name stays where it describes postquality.com's own DNS and its hello@ mailbox (`docs/distribution.md`, `memory/release-readiness.md`, the public-home and site tickets). Those records are the product's own runbook, and anyone can read the same facts from the domain's public NS and MX records. The ticket's Boundaries already keep that domain and its mailbox as they are.
- "Behind Cloudflare" in the n8n records stays. It names the class of proxy that shaped the failure handling (the 1010 ban), not the owner's host or registrar, and it identifies no site.
- No CI guard in this story. GitHub Actions is blocked on credit until 2026-11-01, and a guard needs the pattern in a repository secret. It stays an option in the ticket.
- The local user-profile paths in a few plans' scratchpad references stay: they name no site, host or provider.
- Review loop 1 (owner delegation 2026-10-24): the scope covers the site's title in any spelling and the pixels of tracked images, not only the domain pattern. `assets/portfolio/dashboard.png` becomes a copy of `assets/screenshot-3.png` (story 86's showcase capture of the Dashboard; `PORTFOLIO.md`'s alt text describes it as well), and the PDF is rebuilt (`node .claude/agents/portfolio/build-portfolio-pdf.mjs --strip`). Git history keeps the old image and the old lines; whether to rewrite or accept it before entry 4.8 is the owner's call, asked at the PR.

## Boundaries & Constraints

**Always:** each replaced sentence keeps its meaning. A URL becomes a placeholder that keeps its path shape, commits and grep commands (`/webhook/pq-review`, `/hooks/019caee5-…`). Inside a frozen block the change is the replacement alone.

**Never:** the search pattern itself in any repository file, plan, commit message or PR (it lives in the agent's private notes); a git-history rewrite; changes to code, the workflow template, copy users see, or the records of the public project domain.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Domain in a sentence | `memory/pivot-agnostic.md`: "tailored to <the owner's domain>" | "tailored to the owner's own blog" | No error expected |
| Old n8n webhook URL in a scan record | `https://<the owner's n8n subdomain>/webhook/pq-review` | `https://<owner-n8n>/webhook/pq-review`; commits and the grep command unchanged | No error expected |
| Old chat webhook URL | `…<provider's chat host>/hooks/019caee5-…` | `https://<owner-chat>/hooks/019caee5-…` | No error expected |
| Search instruction naming the domain | "(`grep -ri <domain word>` hits only memory and this ticket)" | "(a case-insensitive search for the development site's domain hits only memory and this ticket)" | No error expected |
| The production Local site's name | "(`<site name>` is the production copy; …)" | "(one of them is the owner's production copy; …)", the rest of the sentence unchanged | No error expected |
| The site's title in a frontmatter description | `memory/pivot-agnostic.md` L3: "from <title>-specific to …" | "from owner-blog-specific to …" | No error expected |
| The portfolio image | `assets/portfolio/dashboard.png` shows the owner's site title and post titles | the showcase capture of the same view; `PORTFOLIO.md` unchanged; the PDF rebuilt and showing the new image | a builder failure leaves the old PDF: the story is not done |
| postquality.com's DNS and mailbox | `docs/distribution.md` Mailbox and DNS rows, the public-home records | unchanged | No error expected |
| Full scan after the change | the tracked tree | no match for the domain pattern, the site's title in any spelling, or the old chat host | a match fails the story |

</frozen-after-approval>

## Code Map

- `_bmad-output/backlog/story-n8n-connection-secure-and-host-agnostic.md` L73 -- a grep instruction that spells the domain word.
- `_bmad-output/backlog/story-site-navy-and-check-plan-3.md` L78 (Tasks, outside the frozen block) -- names the production Local site.
- `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md` L25 (inside the frozen block: replacement only) -- old chat webhook URL; L70 and L80 -- old n8n webhook URL.
- `_bmad-output/initiative-post-quality-manager/walkthrough-4-9-ready-to-go-public/walkthrough-4-9-ready-to-go-public.md` L14 -- old chat webhook URL.
- `memory/delivery-pipeline.md` L39 -- old chat webhook URL and old n8n webhook URL in one line.
- `memory/pivot-agnostic.md` L8 -- the domain; L3 (`description:` frontmatter) -- the site's title, hyphenated.
- `assets/portfolio/dashboard.png` (1246×756, the owner's site) -- replaced by a copy of `assets/screenshot-3.png` (2048×1488, the showcase site, the same Dashboard view), downscaled to 1600 px wide as the portfolio kit asks (`.claude/agents/portfolio/README.md`, "Downscale to ≤ 1600 px wide before committing"). `PORTFOLIO.md` L7 embeds it; its alt text stays true.
- `memory/lessons.md` -- a new entry (the file's format, newest at the end): the portfolio's screenshot was taken on the owner's production site and showed its title and posts; the owner's rule covers the site's title in any spelling and the pixels of tracked images, so captures for the repository come from the showcase site. Without the site's name or the search pattern.
- `memory/delivery-pipeline.md` -- beside entry 4.9's history acceptance (the line this story already edits): an open owner question, stated in full. Entry 4.9 accepted the personal data in the old n8n exports only; history also names the owner's site, by domain or title, in records from March to 2026-10-23 (the March workflow exports, `tasks/todo.md` until its removal on 2026-10-01, `memory/pivot-agnostic.md` since 2026-07-22, the n8n-connection ticket since 2026-10-06, entry 4.9's own scan record, story 86's plan 3), and the portfolio image in `bfe298b` with the PDFs of `bfe298b` and `419543b`. Before entry 4.8 the owner accepts all of it or publishes a rewritten history. No commit id beyond these, no pattern.
- `memory/MEMORY.md` L17 (the Delivery pipeline index line) -- where it summarises the 4.9 history scan as accepted, add that the history question is open before 4.8 (story 66).
- `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/epic-delivery-pipeline.md` Notes -- an `Open (owner, before entry 8)` line beside the other entry-8 gates (L51, L59): the history question, pointing at `memory/delivery-pipeline.md`; `tickets.py` cannot express it, as L51 says for story 15.
- `.claude/agents/portfolio.tokens.json` -- `heroHeight` (default 240, README L114): raised so the page-1 banner crop of the taller image starts above the Fix-next card's top border (about 320 expected; check the rebuilt page 1).
- `Portfolio - Post Quality.pdf` -- rebuilt from `PORTFOLIO.md` by `.claude/agents/portfolio/build-portfolio-pdf.mjs --strip` (config `.claude/agents/portfolio.tokens.json`: `assetsDir`, `pdfFile`; README beside the builder). Needs Node 18+ and, on a first build, the network for the Mermaid runtime.
- Keep as they are: the provider name in `docs/distribution.md` L127 and L130, `memory/release-readiness.md` L46, `story-astro-landing-page.md` L63, `story-public-home-site-pricing-docs.md` L44 and its plan L18, and the site audit L134 (decision 1); every Cloudflare mention (decision 2); the old short form and the path `pq-review`, an abbreviation that does not spell the domain.
- Line numbers drift; the private search pattern (agent notes, test instances) finds them.

## Tasks & Acceptance

**Execution:**
- [x] each file in the Code Map -- the replacement its matrix row names -- the owner's rule before the repository goes public
- [x] `_bmad-output/backlog/story-repository-records-name-no-owner-host.md` -- `refined: true`; Notes: the refinement, the four decisions and the review-loop decision; AC1's Then reworded to "reads generic wording (such as …) or a placeholder that keeps the URL's path"; no Done line, which goes into the post-merge records PR as for 59, 41 and 79 -- the ticket carries its refinement (by hand: the build normally writes no ticket; recorded below)
- [x] `memory/pivot-agnostic.md` L3 -- the matrix row for the title -- the description is the line agents load first
- [x] `assets/portfolio/dashboard.png`, `Portfolio - Post Quality.pdf` -- copy the showcase capture over the image, rebuild the PDF, look at the PDF's first page -- the portfolio is public with the repository
- [x] `assets/portfolio/dashboard.png`, `Portfolio - Post Quality.pdf` -- downscale the image to 1600 px wide (Pillow through `uv run --no-cache --with pillow`, Lanczos, PNG), rebuild the PDF, look at page 1 again -- the kit's figure rule
- [x] `_bmad-output/backlog/story-repository-records-name-no-owner-host.md` -- AC2's Then reworded like AC1: the mentions about the owner's own site, hosting and accounts read generic wording or a placeholder that keeps the URL's path; the public project domain's part unchanged -- the delivered chat-URL lines carry a placeholder
- [x] `memory/lessons.md`, `memory/delivery-pipeline.md` -- the two records the Code Map names -- durable facts (Definition of Done)
- [x] `memory/delivery-pipeline.md`, `memory/MEMORY.md`, `epic-delivery-pipeline.md` -- the history question stated in full (Code Map) and visible where an entry 4.8 session looks -- the owner decides on the whole premise
- [x] `_bmad-output/backlog/story-repository-records-name-no-owner-host.md` -- AC4's Then: "it changes only those mentions and the records of the rule and its open question (`memory/`, the epic's gate line)"; the loop-1 note says "a 1600-px downscale of" instead of "a copy of"; the loop-1 note's history sentence matches the full question -- the ticket says what was delivered
- [x] `.claude/agents/portfolio.tokens.json`, `Portfolio - Post Quality.pdf` -- raise `heroHeight`, rebuild (temp files in the session scratchpad, as loop 2 did), look at page 1: the "Fix next" heading whole -- the figure the caption points to

**Acceptance Criteria:**
- Given the branch, when the tracked files are searched case-insensitively for the domain pattern and for the old chat host, then nothing matches; the same search for the site's title in any spelling (letters with or without a separator) matches nothing either.
- Given the image and the rebuilt PDF, when they are viewed, then neither shows the owner's site title or post titles.
- Given the diff against the baseline, when it is read, then it changes only the Code Map lines and files, the ticket and this plan, and each changed line reads as the same finding.

## Implementation Notes

- Run by hand from the workspace root (owner rule 2026-10-16): bmad-build rendered with `render_skill.py` and followed step by step. `tickets.py find` refuses the tree while tickets 2 and 86 keep several plans, so the plan path follows the backlog's `<ticket>-plan.md` convention. Ticket 66 refined by hand under the owner's delegation, as for 59, 41 and 79.
- Implemented 2026-10-24 (implementer subagent, Opus 5.5): literal replacements through a script in the session scratchpad that aborts unless each old string occurs exactly once, so nothing else in the six files moved. Eight lines changed: the n8n-connection ticket L73, plan 3 of story 86 L78, entry 4.9's plan L25 (frozen: the URL alone), L70 and L80, the 4.9 walkthrough L14, `memory/delivery-pipeline.md` L39 (both URLs; its two `git log … | grep` commands untouched), `memory/pivot-agnostic.md` L8. The chat URL follows the matrix row exactly: the leading ellipsis becomes `https://` before the placeholder. Entry 4.9's plan L80 wrote the n8n URL without a scheme, so only its host became `<owner-n8n>`; the shape stays as it was. The 4.9 walkthrough's Block 1, a verbatim paste of the plan's frozen Intent, still matches it after the same replacement.
- Seen outside the Code Map, left as it is (the diff changes only the Code Map lines): the `description:` frontmatter of `memory/pivot-agnostic.md` (L3) names the owner's site by its title in hyphenated form. The domain pattern does not match it, so the acceptance search stays clean, but it names the owner's site in the sense of the Intent. Raised for the orchestrator: a one-word change in the same spirit ("from owner-blog-specific to …") if the owner wants it covered here.
- Loop 1 implemented 2026-10-24 (implementer subagent, Opus 5.5), the KEEP lines untouched. `memory/pivot-agnostic.md` L3 reads "from owner-blog-specific to …" (a guarded one-occurrence replacement). `assets/portfolio/dashboard.png` is a byte copy of `assets/screenshot-3.png` (2048×1488; the kit README's "≤ 1600 px" advice set aside, the Code Map names a copy). The PDF was rebuilt with `node .claude/agents/portfolio/build-portfolio-pdf.mjs --strip` (Node 22.14, pandoc, headless Edge; 1 pdf-exclude block stripped, 7 diagrams, 1082 KB); its first page shows the banner-cropped Fix-next queue of the showcase posts, and its extracted text has no match for the private pattern. The builder's work folder in `%TEMP%` (exact path from a before/after listing) was removed; its shared Mermaid cache was left. The ticket: AC1's Then reworded, the Done line removed, the review-loop decision added as the last note; the refinement note and the four decisions unchanged. Spot check beyond the Code Map: the other tracked screenshots (`assets/screenshot-1.png`, `-2.png`, the four website-direction captures under `ux-postquality-website/.working/compare/`) show sample content only.
- Loop 2 implemented 2026-10-24 (implementer subagent, Opus 5.5), loop 1's delivery kept. `assets/portfolio/dashboard.png` is `assets/screenshot-3.png` resized to 1600×1162 (Pillow through `uv run --no-cache --with pillow`, Lanczos, PNG with `optimize`; looked at: the showcase site's Dashboard on sample posts). The file is larger than the 2048 px copy (330 KB against 211 KB), because resampling adds colours to a flat capture; still within the kit's rule, which caps width, not bytes. The PDF was rebuilt with `node .claude/agents/portfolio/build-portfolio-pdf.mjs --strip` (Node 22.14, pandoc 3.8.3; 1 pdf-exclude block stripped, 7 diagrams, 1196 KB) with `TEMP`/`TMP` pointed at the session scratchpad, the Mermaid runtime copied in from the shared cache (read only), so the builder's work folder and the browser's profile landed in the scratchpad and nothing new reached `%TEMP%`. Page 1 looked at: the banner-cropped Fix-next queue of the showcase posts. Its extracted text matches the baseline PDF's on all 16 pages, and neither the text nor the raw bytes match the private pattern. The ticket's AC2 Then reads "the ones about the owner's own site, hosting and accounts read generic wording or a placeholder that keeps the URL's path (such as `https://<owner-chat>/hooks/…`)", its public-domain half unchanged. `memory/lessons.md` gained the newest entry (captures for the repository come from the showcase site "Post Quality Showcase"; look at each image; the hygiene search covers the title with and without a separator; no site name, no pattern). `memory/delivery-pipeline.md` L39 ends with the open owner question on the history after 2026-10-08 (`bfe298b`'s image, the old wording of the replaced lines), to settle before entry 4.8 and asked at the PR. Checks: the private-pattern `git grep -i -c -E` over the tracked tree and a grep of this untracked plan, both empty; a CR byte count of 0 in every touched Markdown file. `memory/MEMORY.md` untouched: its `delivery-pipeline` and lessons pointers still describe the files.
- Loop 3 implemented 2026-10-24 (implementer subagent, Opus 5.5), loops 1 and 2 kept. `memory/delivery-pipeline.md` L39's open question restated in full: the 2026-10-08 acceptance covers the token, the webhook URLs and the personal data in the old n8n exports only; the site is named by domain or title in records from March to 2026-10-23 (listed as the Code Map gives them, entry 4.9's scan record with the old n8n and chat webhook hosts, plan 3 with the production Local site's name); the old image in `bfe298b` with the PDFs of `bfe298b` and `419543b`; the owner accepts all of it or publishes a rewritten history before entry 4.8. Checked against `git log --all -i -G` with the private pattern: every commit it lists falls in those records (the 2026-07-22 hit on the template is the March v2 export renamed into it). `memory/MEMORY.md` L17 ends with the open question; the epic's Notes gained an `Open (owner, before entry 8; story 66, 2026-10-24)` line right after the story 44 gate (L61), pointing at `memory/delivery-pipeline.md`. The ticket: AC4's Then reads "it changes only those mentions and the records of the rule and its open question (`memory/`, the epic's gate line)", its "no code, template, copy shown to users, or git history" tail kept; the loop-1 note says "a 1600-px downscale of" and its history sentence restates the full question. `.claude/agents/portfolio.tokens.json` gained `"heroHeight": 290`, not the expected 320: the 1600×1162 image fills the 680-px content width (scale 0.425), so a centred 290-px crop starts at image row 240, in the gap between the stat tiles (ending at 227) and the Fix-next card (starting at 253); 320 would start at 205 and show a sliver of the tiles. The PDF was rebuilt as in loop 2 (`TEMP`/`TMP` in the session scratchpad, the Mermaid runtime copied from loop 2's scratchpad copy; 1 pdf-exclude block stripped, 7 diagrams, 1216 KB); nothing new reached `%TEMP%`. Page 1 looked at (rendered with pdftoppm): the banner opens on the Fix-next card's top border with the heading whole and ends on the "Average score — last 8 weeks" heading. The taller banner moves the At a glance table's Timeline row to page 2 (looked at); still 16 pages, and the extracted text's word sequence equals the baseline PDF's. Checks: the private pattern over the tracked tree, this plan, and the PDF's text and raw bytes, all empty; CR count 0 in every touched text file; `tickets.py status` reads the tree (its problems list predates this story). The Verification line for `git diff --stat` predates loops 1 to 3: the diff now also holds the image, the PDF, `memory/lessons.md`, `memory/MEMORY.md`, the epic file and the tokens file, all named in the Code Map.
- After review pass 4 (2026-10-24), three patches applied: the ticket's Boundaries say entry 4.9 accepted only the old n8n exports' token, webhook URLs and personal data and point the rest of history to the open question in `memory/delivery-pipeline.md` (settled before entry 4.8), keeping "rewriting published history is a separate decision"; AC4's Then also names the portfolio kit's banner height for the replaced image (`heroHeight`); the open question in `memory/delivery-pipeline.md` L39 and the ticket's loop-1 note add entry 4.9's walkthrough record (the old chat webhook host, checked with `git log --all -i -G`) and speak of the owner's site or accounts.

## Plan Change Log

- Loop 3 (2026-10-24), from review pass 3 finding 2 (patches 1, 3, 4 and 5 folded in). Amended: the Code Map (the full history question; `memory/MEMORY.md`; the epic's entry-8 gate line; the portfolio kit's `heroHeight`) and the tasks. Known-bad state avoided: the owner deciding rewrite-or-accept on a premise that leaves out the records since March; an entry 4.8 session that never sees the question; a ticket whose AC4 and note contradict the delivery; a page-1 banner that cuts the heading its caption names. KEEP: everything loops 1 and 2 delivered (the nine lines, the 1600-px image, the lessons entry, AC1 and AC2); not reverted.
- Loop 2 (2026-10-24), from review pass 2 finding 6 (with patches 4 and 7 folded in). Amended: the Code Map (`memory/lessons.md`, the history question in `memory/delivery-pipeline.md`, the image downscaled) and the tasks. Known-bad state avoided: a widened rule nobody after this session learns, which is how the owner's screenshot entered the repository; an open history question kept only in a PR; an image over the kit's width; an AC2 the delivery does not meet as written. KEEP: everything loop 1 delivered (the nine changed lines, the image's source, the rebuilt PDF's content, the ticket's AC1 and notes); not reverted, for the same reason as loop 1.
- Loop 1 (2026-10-24), from review pass 1 findings 1, 2, 4 and 5. Amended: the frozen Problem, Approach, Decisions and matrix under the owner's delegation (the intent gap: images and the title's spelling); the Code Map (pivot L3, the portfolio image and PDF); the ticket task (no Done line before merge, AC1 reworded); the acceptance criteria. Known-bad state avoided: a public repository whose portfolio image and a memory description still name the owner's site after a story that says none is left; a Done line on an unmerged story. KEEP: the eight replacements exactly as made (the lines, the placeholders `<owner-n8n>` and `<owner-chat>`, the frozen-block edit limited to the URL), and the ticket's refinement note and four decisions. Not reverted: re-deriving the KEEP lines would reproduce them byte for byte.

## Review Triage Log

**Pass 4 (quick lens, Opus 5.5, 2026-10-24, on loop 3): 5 findings — low 5; route: patch 3 (applied by the loop-3 implementer, re-engaged), rejected 2. No loopback.**

1. low, patch -- the ticket's Boundaries still said the owner accepted what history holds, against the full question in memory; reworded to the exports-only acceptance and the open question.
2. low, patch -- the ticket's AC4 did not name the portfolio kit's `heroHeight`; added.
3. low, patch -- the history question missed entry 4.9's walkthrough record, which carries the old chat host in history, and called the list "the owner's site" though the chat host names the owner's chat account; both fixed in memory and in the ticket note.
4. low, rejected (its fix edits this plan's frozen block) -- the frozen loop-1 decision says "a copy"; the image is the 1600-px downscale the Code Map and Implementation Notes describe.
5. low, rejected (its fix edits this plan) -- the Verification line for `git diff --stat` predates loops 1–3; the diff holds 13 tracked files, all named in the Code Map, and this plan.

**Pass 3 (quick lens, Opus 5.5, 2026-10-24, on loop 2): 5 findings — medium 2, low 3; route: bad_plan 1 → loop 3; patches 4 folded in.**

1. medium, patch -- the open history question in `memory/delivery-pipeline.md` (and the ticket's loop-1 note) dates the gap from 2026-10-08 and lists only the replaced lines and the image; `git log --all -i -G` shows the site named in records since March (the old exports, `tasks/todo.md`, `memory/pivot-agnostic.md` from 2026-07-22, the n8n-connection ticket from 2026-10-06) and the PDFs carry the old image. Restated in full in loop 3.
2. medium, bad_plan -- the question is invisible where an entry 4.8 session looks: `memory/MEMORY.md` L17 still says the 4.9 scan was accepted, and the epic's entry-8 gate lines (L51, L59) do not name it; entry 4.9's own review fixed the same gap in both places. Loop 3.
3. low, patch -- the ticket's AC4 ("changes only those mentions") does not hold with the lessons entry and the open question; reworded in loop 3.
4. low, patch -- the PDF's page-1 banner (`object-fit: cover`, `heroHeight` 240) crops the taller image mid-way through the "Fix next" heading; the kit's `heroHeight` token raised in loop 3.
5. low, patch -- the ticket's loop-1 note calls the image a copy; it is a 1600-px downscale. Loop 3.

**Pass 2 (quick lens, Opus 5.5, 2026-10-24, on loop 1): 7 findings — medium 1, low 5, false 1; route: bad_plan 1 → loop 2; patches 2, rejected 3.**

1. false -- "the staged diff leaves out the PDF": the staging file, not the change; the reviewer checked the working-tree PDF (text identical to the baseline, page 1 the showcase crop).
2. low, rejected (its fix edits this plan's frozen block) -- the Problem's count reads eight where its own breakdown gives nine lines; the diff changes nine.
3. low, rejected (its fix edits this plan) -- the Verification line for `git diff --stat` predates loop 1's two binary files.
4. low, patch -- the ticket's AC2 Then still quotes "the owner's host" / "the owner's registrar" for the chat-URL lines that now carry a placeholder; reworded like AC1 in loop 2.
5. low, rejected -- the old short form, the title's initials, stays: it names the history-scan findings (`PQ-…json`, the old workflow and slash command) that entry 4.9's records must keep identifying (AC3 of the ticket), and initials do not lead a reader to the site.
6. medium, bad_plan -- the widened rule (the title in any spelling, the pixels of tracked images) and the open history question had no `memory/` record (Definition of Done; each recent story commit updated memory); the plan's diff boundary ruled them out. Loop 2.
7. low, patch -- the new image is 2048 px wide; the portfolio kit asks for at most 1600 px. Downscaled in loop 2.

**Pass 1 (quick lens, Opus 5.5, 2026-10-24): 6 findings — high 1, medium 1, low 4 (two rejected), false 0; route: intent_gap 1 (resolved by the owner's delegation), bad_plan 1 → loop 1; patches folded into the loop.**

1. medium, bad_plan -- `memory/pivot-agnostic.md` L3 names the owner's site by its hyphenated title; verified by a search for the title with an optional separator. The Code Map listed the domain pattern only.
2. high, intent_gap -- `assets/portfolio/dashboard.png` shows the owner's site title in the admin bar and the owner's post titles (looked at the image); the PDF's first page carries the same image. The frozen Intent named text lines only; resolved under the owner's delegation (Decisions, review loop 1).
3. low, rejected (its fix edits this plan's frozen block) -- the Problem's counts were wrong (eight lines in six files, not six in six); corrected anyway inside the delegated intent change of finding 2.
4. low, patch -- the ticket's Done line came before review and merge; the repository's practice puts it in the post-merge records PR (29f8104; story 79's 7c16646 carries none). Folded into loop 1's ticket task.
5. low, patch -- the ticket's AC1 Then quotes three phrases the delivered lines do not use; AC1 reworded to generic wording or a path-keeping placeholder. Folded into loop 1.
6. low, rejected (its fix edits this plan) -- `git diff --stat` skips the untracked plan; the plan shows once committed.

## Verification

**Commands:**
- `git grep -i -c -E "<pattern from the agent's private notes>"` -- expected: no output
- `git diff --stat 7c16646000dcb10f4ef70677a8f1e3ae8b01e4ee` -- expected: the six Code Map files, the ticket, this plan
- a byte count of CR in each touched file -- expected: 0

**Manual checks (if no CLI):**
- Read each changed line in its paragraph: it states the same fact as before.
