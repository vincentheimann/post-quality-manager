# Post Quality Manager — Brand Guidelines

_This file is the single source of truth for brand, copy, and tone decisions in the WordPress admin panel. The agent must read this file before writing any UI text, labels, or copy._

---

## Brand Identity & Tone

**Name:** Post Quality Manager (full name) — short form **Post Quality**; see [Naming](#naming) for which surface uses which
**Slug / text domain:** `post-quality-manager` (permanent)
**Tagline (readme short description):** Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin.
**Category:** WordPress Content Quality Dashboard
**Target audience:** Professional Bloggers, Content Editors, SEO Specialists

### Voice & Tone
- **Professional & Analytical:** Users are here to work and optimize content. Be precise and clear.
- **Casual & Encouraging:** Don't be robotic or overly corporate. Use a friendly "colleague" tone.
- **Action-Oriented:** Focus on what they can do next to improve quality.

---

## Value Proposition

> Streamline your content quality control. Spot issues instantly, automate the review process, and maintain high standards effortlessly.

Promotional wording and the KPI figures behind it: story 53 (needs refinement with the owner's real KPIs) writes them to `docs/messaging.md`, which then replaces this line as the canon for public surfaces.

---

## Copy Rules (Agent Instructions)

### Do
- Use clear, actionable verbs for CTAs (e.g., "Review Post", "Rewrite Content", "Apply Changes").
- Frame everything as **optimization and enhancement**.
- Be concise. Real estate in the WP admin panel is valuable. Focus on the actual data (scores, issues, takeaways).
- Explain errors gently with actionable solutions (e.g., "Could not connect to n8n. Check your Webhook URL in Settings."). The Settings step is for administrators; everyone else is told to ask an administrator.

### Don't
- Never use accusatory or purely negative framing (e.g., "Your post is terrible", "Failed completely"). Replace with constructive feedback (e.g., "Requires revision").
- Do not use overly complex jargon unless standard for SEO/blogging.
- Avoid patronizing praise. They are professionals.

### UX Writing Examples

| Context | Good Copy | Bad Copy |
|---|---|---|
| No reviews yet | "No reviews found. Trigger a review to get started." | "You haven't done anything yet!" |
| Successful review | "Review complete. View takeaways." | "Success! Your post was successfully reviewed!" |
| Action button | "Review Post" | "Click here to run n8n" |

---

## Visual Design (Admin Panel)

- Stick closely to the native **WordPress Admin UI** design language.
- Use standard WordPress CSS classes (`.button`, `.button-primary`, `.notice`, `.notice-success`, `.dashicons`).
- Avoid reinventing the wheel with custom complex CSS unless necessary to organize large datasets (like tabs for review details).
- Visual hierarchy should prioritize:
  1. The overall score or status
  2. Actionable takeaways/issues
  3. Action buttons (Review/Rewrite)
- Logo, menu icon, WordPress.org icons/banners/screenshots and imagery direction: not decided yet — story 51 (icon) and story 41 (the rest) extend this section (owner-approved) before story 38 submits to the directory.

---

## Naming

_Owner decision 2026-10-08 (backlog story 10). The WordPress.org slug is a one-way door: it is derived from the Plugin Name header at submission, it becomes the plugin folder, the text domain and the directory URL, and it can never change once the listing is approved. The display name can change later; the slug cannot. These rules are applied by story 41 (copy sweep); until that lands, the "Content Quality" menu label and page headings in the code are known drift, not a decision._

| Surface | Use | Example |
|---|---|---|
| Plugin Name header, `readme.txt` name, README title, GitHub repo, release notes, first mention in any text | Full name | **Post Quality Manager** |
| WP admin top-level menu label; in-copy cross-references; anywhere space is tight | Short form | **Post Quality**, "Post Quality → n8n Workflow" |
| Submenu labels | Screen name, unchanged | Dashboard, Post Reviews, n8n Workflow, Settings |
| Page headings (`<h1>`, React app shells) | Screen name only, never the product | "Dashboard", not "Content Quality Dashboard" |
| Slug, plugin folder, text domain, zip root, release asset | `post-quality-manager` | `post-quality-manager-3.1.0.zip` |
| `readme.txt` short description | The tagline above, verbatim (124 chars, limit 150) | — |
| Prefixes, constants, option keys, capabilities, CSS/JS handles, the `X-PQM-Token` header | `pqm` / `PQM` | code-only |
| Domain, support mailbox, `Plugin URI` / `Author URI` | `postquality.com` (owner, 2026-10-09, ADR 0013) | https://postquality.com, hello@postquality.com |

### Rules

- **Submit WordPress.org with the full name in the Plugin Name header**, so the directory derives `post-quality-manager` by itself and no slug edit is needed during review. Checked 2026-10-08: the slug is free and no listed plugin carries "post quality" or "content quality" in its name.
- **"Content Quality" is retired as a name.** Lowercase "content quality" stays as the generic concept in prose ("your blog's content quality"). Never capitalise it as a product or menu name.
- **"PQM" never reaches a user.** Not in admin copy, `readme.txt`, release notes or user docs. "PQ" is not a short form either.
- **The tagline is provider-neutral.** It names no workflow engine and no model vendor; "AI" stays as the discovery keyword. The long description may mention n8n and the AI providers as integrations ("through your own n8n workflow"), never as part of the name.
- **No third-party trademark in the name, slug, tagline or imagery** — not "WordPress", "Claude", "n8n", "OpenAI", "Google" (story 38 AC5). The pattern "X for Y" is acceptable in a description; "Y – X" is not.
- **One name per sentence.** Do not pair full name and short form in the same sentence; introduce with the full name, continue with the short form.
- **The domain is the short form.** `postquality.com` is a URL, not a third spelling of the name: pages on it still introduce the product as Post Quality Manager.

