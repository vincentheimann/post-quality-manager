# UX review — Dashboard & Post Reviews (backlog ticket 7)

_Findings-and-decisions document, 2026-10-02. Status: **approved by the owner, 2026-10-02** (D1–D7 as drafted; see Owner decisions at the end). Companion: [mockup.html](mockup.html) (interactive, self-contained; open in any browser). Sources: `docs/user-paths.md` (B2, A5–A6), `docs/brand.md`, code analysis of `admin/class-pqm-admin.php`, `admin/js/pqm-admin.js`, `admin/css/pqm-admin.css`. Once approved, this feeds `bmad-spec` → epic inception; the framework decision is drafted as ADR 0005._

## The lean-UX frame

One primary job per screen, everything else earns its place:

- **Dashboard** answers **"what should I fix next?"** — today it only answers "how is my blog doing?". user-paths B2 names this exactly: *"the dashboard reports, but doesn't prioritize"*.
- **Post Reviews** answers **"what do I do about this post?"** — today it's close (detail tabs, Open in Editor) but buried under triage friction: two filters, 3-of-7 sortable columns, no score/status filtering despite backend support, one command at a time.

Each decision below carries the hypothesis it tests and the signal that would validate it (user-paths signals; no telemetry yet, so validation is owner-observed).

## Findings

### F1 — The dashboard is a report, not a queue (B2, UV rank 1 adjacency)
Four equal stat cards; three link to the *unfiltered* posts list. No ordering by urgency, no "next action" anywhere. The B3 gap ("insight → action loop is unproven") starts here: the dashboard never proposes an action.

### F2 — Two render sources that already drift
Every badge/pill/status exists twice: PHP (`format_status`, `date_i18n`) and JS string-concatenation (`statusLabel`, ad-hoc date split). Confirmed drift: JS maps `published` where PHP maps `ship`; rows change date format after an AJAX update. ~500 lines of string-built HTML with inline styles (the exact pain backlog story 5 records).

### F3 — i18n split-brain, with a functional bug
PHP copy is translatable; all JS copy is hardcoded English (tabs, toasts, modal, status labels). Worse: the processing auto-resume matches `textContent.indexOf('Processing')` — on a translated site PHP outputs the localized label and the resume silently breaks.

### F4 — Accessibility gaps across the board
Tabs without `tablist`/`aria-selected`, toggles without `aria-expanded`, modal without `role=dialog`/focus trap/Esc, no `aria-live` for toasts or polling updates, `▲▼` text instead of `aria-sort`, emoji-encoded status, tooltip-only help, decorative dashicons unannotated.

### F5 — Dead weight
Legacy GET trigger path (duplicates AJAX flow, nothing emits it), JS `apply_` branch with no buttons, unused CSS blocks, `review_status` filter param no UI emits, `esc()` that drops numeric `0`.

### F6 — Triage friction on Post Reviews
No status or score-range filter, fixed 20/page, no bulk action, confirm-modal + 3 s polling per single command. The agents the owner actually uses (seo/audit/takeaways) are reachable but each run is a ceremony.

## Decisions (proposed)

- **D1 — Framework: `@wordpress/components` + `@wordpress/element`, built with `@wordpress/scripts`, for Dashboard and Post Reviews only.** Settings and n8n Workflow stay on the PHP Settings API. Rationale: it *is* the WP admin design language (brand.md's "stick to native WP admin" by construction), a11y/i18n/focus handling baked in (resolves F3, F4 structurally rather than patch-by-patch), React ships with WP so the bundle stays lean, WordPress.org-compatible (UV7). Cost accepted: a Node build step enters the repo; the jQuery layer retires on these two pages. → ADR 0005 (draft).
- **D2 — One render source (fixes F2):** PHP renders only the page shell + a REST-fed data bootstrap; every badge, pill, row and panel renders client-side from the same components. The REST API already serves the data. Kills the drift class entirely; subsumes backlog story 5.
- **D3 — Dashboard becomes triage-first:** a "Fix next" queue (top 5, ordered by priority) is the page's centerpiece; the stat tiles move above it in compact form and **every tile links to the correspondingly filtered Post Reviews view**. Priority v1 = status severity, then score ascending, then staleness (days since last review). Honest limitation: no traffic data exists, so the user-paths "impact × traffic" formula waits for an analytics integration — out of scope here. *Hypothesis: a visible queue raises the % of dashboard sessions that open a post (B2 signal).*
- **D4 — Post Reviews keeps its shape, loses its friction:** same table + expandable detail, plus status chips and a score-range filter (backend already supports them), all columns sortable, bulk-select with "Review selected" (batch = sequential triggers behind one confirmation; respects gate + quota per post). Detail tabs become real `TabPanel`s.
- **D5 — Status language: no emoji.** WP `Badge`-style pills with dashicon + label + color (status palette below); color never alone (CVD + brand "professional").
- **D6 — Polling stays (KISS)** — 3 s REST polling is fine at this scale; it just moves into one shared hook with an `aria-live` status region instead of three ad-hoc loops.
- **D7 — Dead weight (F5) is deleted in the rebuild**, listed in the spec so the refactor sweep (story 1.7) doesn't double-handle it.

## Visual & data-viz rules for the rebuild

Design system = WP admin. Status palette (WP core): Ready `#00a32a`, Needs Revision `#dba617`, Major Revision `#d63638`, Processing `#646970` (neutral gray by design). Validator run (2026-10-02): as a bare categorical set it **fails** deutan red↔green separation (ΔE 2.9) and the yellow misses 3:1 against the surface — which is why these are status colors, not series colors: **every status renders as icon + label + color, never color alone**, the yellow appears only as a filled badge behind its own dark label (never as text or a thin stroke), and the table always carries the textual status. That satisfies the relief obligation instead of dismissing it. Pillar scores render as single-hue horizontal meters (WP blue `#2271b1`), magnitude only — status color lives in the badge, not the bars. The only chart is the dashboard's average-score sparkline (single series, direct-labeled endpoint, no legend, hover tooltip). Stat tiles are stat tiles, not charts. Light scheme only (WP admin default); dark/alternate admin schemes inherit WP component tokens.

## What the mockup shows ([mockup.html](mockup.html))

Interactive, fake data: (1) Dashboard with compact stat tiles (filter-linked), the Fix-next queue with per-row "Review" / "Open" actions, score sparkline, contextual cron nudge; (2) Post Reviews with chip filters + score range, sortable table, bulk select, an opened detail row with accessible tabs, pillar meters, quota note, a locked (plan-gated) button and the toast/polling status region. What it deliberately does not show: Settings, n8n Workflow (unchanged), empty/error states (listed in the spec instead).

## Owner decisions (2026-10-02)

1. **D1–D7 approved as drafted.** ADR 0005 is accepted.
2. **Fix-next queue length 5; the "Review selected" bulk action is in the first slice.**
3. **Sequencing:** the rebuild becomes its own epic, incepted via `bmad-spec` + `bmad-ticket`, built after epic-freemium-and-roles closes (stories 1.7/1.8). Story 1.7's UI-rendering cleanup items fold into the rebuild epic (the rebuild deletes that code anyway); 1.7 keeps only non-UI cleanup.
