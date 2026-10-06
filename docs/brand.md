# Post Quality Manager — Brand Guidelines

_This file is the single source of truth for brand, copy, and tone decisions in the WordPress admin panel. The agent must read this file before writing any UI text, labels, or copy._

---

## Brand Identity & Tone

**Name:** Post Quality Manager
**Category:** WordPress Content Quality Dashboard
**Target audience:** Professional Bloggers, Content Editors, SEO Specialists

### Voice & Tone
- **Professional & Analytical:** Users are here to work and optimize content. Be precise and clear.
- **Casual & Encouraging:** Don't be robotic or overly corporate. Use a friendly "colleague" tone.
- **Action-Oriented:** Focus on what they can do next to improve quality.

---

## Value Proposition

> Streamline your content quality control. Spot issues instantly, automate the review process, and maintain high standards effortlessly.

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
