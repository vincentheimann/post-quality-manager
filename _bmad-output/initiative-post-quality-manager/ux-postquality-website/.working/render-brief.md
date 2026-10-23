# Render brief: postquality.com options (2026-10-22)

Shared by every renderer in this pass. The owner skipped the brain dump ("Go ahead"), so these renders are options to react to, not decisions. Facts come from `.memlog.md` and `sources-extract.md` beside this file; nothing below may be invented beyond them.

## Hard rules

- Self-contained HTML: inline CSS, inline SVG, **no JS, no network request of any kind** (no web fonts, no CDN, no images from URLs). System font stacks only; where a direction would want a self-hosted font later, say so in a `<style>` comment.
- Document every hex value in a `<style>` comment.
- Names: first mention **Post Quality Manager**, then **Post Quality**. Never "PQM", "PQ" or "Content Quality".
- Tagline, verbatim when used: "Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin."
- **No third-party logo or trademark imagery**: no WordPress "W", no n8n or Anthropic or Claude marks, nothing in the WP admin bar that is a logo. Their names may appear in plain text where a fact needs them ("your own n8n", "an Anthropic API key", "for WordPress").
- **No invented evidence:** no testimonials, ratings, user counts, download counts, "trusted by", results figures or AI-cost figures. The cost answer is "you pay Anthropic per review, on your own key; a worked example is coming".
- **No dark pattern:** no countdown, no fake scarcity, no pre-ticked box, no hidden dismiss.
- WCAG AA contrast for all text (4.5:1, 3:1 for large text and UI borders). Visible focus styles. One `<h1>` per page.
- Voice (brand.md): professional, analytical, an encouraging colleague; clear verbs on buttons; concise; no "Success!", no hype words ("revolutionary", "supercharge").

## The brand icon (story 51; never mirror, stretch, recolour or add a third colour)

Square tile, sharp corners. Inline it as-is:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" role="img" aria-label="Post Quality Manager"><rect width="20" height="20" fill="#1b2a41"/><g transform="translate(3 3) scale(0.7)"><path fill="#ffffff" fill-rule="evenodd" d="M 13.1 9.55 A 6.3 6.3 0 1 1 0.5 9.55 A 6.3 6.3 0 1 1 13.1 9.55 Z M 10.85 9.55 A 4.05 4.05 0 1 0 2.75 9.55 A 4.05 4.05 0 1 0 10.85 9.55 Z"/><rect fill="#00a32a" x="0" y="-1.12" width="4.81" height="2.24" transform="translate(9.47 12.22) rotate(45)"/><rect fill="#00a32a" x="0" y="-1.12" width="7.78" height="2.24" transform="translate(12.87 15.62) rotate(-45)"/><circle fill="#00a32a" cx="9.47" cy="12.22" r="1.12"/><circle fill="#00a32a" cx="12.87" cy="15.62" r="1.12"/><circle fill="#00a32a" cx="18.38" cy="10.12" r="1.12"/></g></svg>
```

Brand colours: tile navy `#1b2a41`, mark white `#ffffff`, check green `#00a32a`.

## The site today and its fixed facts

- Pages (URLs fixed): `/` Home, `/pricing/`, `/docs/`, `/support/`, `/privacy/`, `/terms/`, `/refund/`. Footer carries the four policy links. Header nav today: Pricing, Documentation, Support policy.
- **Today's state (pre-launch):** the plugin is not installable yet and Pro is not on sale. The only action is to leave an email: a link `mailto:hello@postquality.com?subject=Tell%20me%20when%20Post%20Quality%20Manager%20is%20available`. Button wording candidates: "Tell me when it's available", "Get notified at launch".
- **After launch (show as a labelled state variant):** "Install the free plugin" (primary) and "See Pro pricing" / "Buy Pro" (secondary).
- What it does (readme): reviews your posts with AI and keeps the results in WP Admin: a score for every post, what to fix first, and the trend across your blog.
- Four pillars: **Structure, Readability, Engagement, Credibility.** Tailored to any niche through a Blog Profile and Editorial Standards, never code.
- How it works: your site → your own n8n workflow → Anthropic's models → the result back in WP Admin (score, issues, suggested edits, takeaways). A setup guide asks three values (n8n address, n8n API key, Anthropic key), sets the workflow up for you and tests the connection both ways.
- Free: Review, Key Takeaways, Dashboard (average, trend, Fix next), Post Reviews, Insert into Post. "The free plugin is the trial."
- Pro: Rewrite, Sources, SEO and Audit agents; per-agent model and instructions; Automated Reviews on a schedule; Roles; email support within 2 business days. One yearly plan: 49 / 99 / 199 for 1 / 5 / unlimited sites (same figures in USD, EUR, CHF). 14-day money-back guarantee. If Pro lapses, the site returns to Free and **nothing is deleted**.
- What you need (the evaluator's biggest hesitation, user path A2 — must be visible before any install action): your own n8n (self-hosted or n8n Cloud), an Anthropic API key, and you pay Anthropic per review on your own key. WordPress 6.6+, PHP 8.0+.
- Trust facts on record: your content goes only to your own n8n and, through it, to Anthropic; the author receives nothing from your site. The website sets no cookies and runs no analytics. The Anthropic key is checked once and never stored by the plugin.
- Honest nudges available (candidates, label them as such in the rationale): the guarantee, "the free plugin is the trial", "nothing deleted if Pro lapses", "you hold the keys, your content goes nowhere else", the prerequisites stated up front.

## The "inside the plugin" capture (real screenshots do not exist yet)

Render the plugin's **Dashboard** as an HTML/CSS mock in the native WP admin look, inside a browser-window frame, with fictional demo content. Real labels, verbatim:

- Page heading: "Post Quality" (wp-admin H1 23px/400).
- Five stat tiles: "Average score" **71**; status badge tiles "Major revision" **3**, "Needs revision" **9**, "Not analyzed" **14**, "Ready to ship" **22**.
- Section "Fix next": five rows, lowest score first, each with title, score, status badge, "Last reviewed 3 days ago" style meta, and two buttons "Review" and "Open in Editor":
  1. "Five backup mistakes I made so you don't have to" — 42 — Major revision
  2. "A calm guide to home network VLANs" — 48 — Major revision
  3. "Why my static site builds got slow" — 57 — Needs revision
  4. "Self-hosting a photo library: one year later" — 61 — Needs revision
  5. "Choosing a password manager for the whole family" — 64 — Needs revision
- Section "Average score — last 8 weeks": a sparkline rising from 63 to 71.
- Admin tokens: primary `#2271b1` (hover `#135e96`), ink `#1d2327`, muted `#50575e`, surface `#ffffff`, canvas `#f0f0f1`, line `#c3c4c7`. Status: Ready to ship `#00a32a`, Needs revision `#dba617` (only as a filled badge behind a dark label), Major revision `#d63638`, Processing `#646970`. Badges always icon + label + colour, no emoji. System font stack, 13px body.
- A dark admin sidebar is fine but carries no logo; the menu item reads "Post Quality".

Optionally a second capture: a post's review detail with the four pillar meters (Structure 68, Readability 74, Engagement 59, Credibility 81) and "Top edits" (three short, concrete edit suggestions in the brand voice).

## Self-check before returning

Render your file with Playwright in headless Edge (module `C:/Git/Post Quality/post-quality-manager/node_modules/playwright`, `chromium.launch({ channel: 'msedge', headless: true })`, `page.goto('file:///…')`) at 1280 px and 390 px wide, full page, screenshots into `C:/Users/vhe/AppData/Local/Temp/claude/c--Git-Post-Quality/8b84c8b5-6bf4-48a7-b945-0eb7b9def8c5/scratchpad/ux-shots/`. Look at them (Read the PNGs) and fix overflow, clipped text, horizontal scroll at 390 px and contrast before returning. Close the browser in a `finally`. Start no server; stop no process you did not start; never select processes by name or pattern.
