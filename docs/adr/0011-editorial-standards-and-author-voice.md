# 0011. Two voice layers: editorial standards per site, a voice per author

- Status: accepted
- Date: 2026-10-07

## Context

The plugin sends one site-wide "Brand Voice" and one "Website Context" with every command, both owned by whoever manages Settings. Story 26's review found the voice had two sources (a preset selected in the UI was resolved from code at send time, in the triggering user's language) and that a Blog Profile click silently replaced typed text. The owner then stated the real need: every post must be checked and rewritten in **its author's own tone and voice**, while **shared editorial rules and standards** hold for every post. The persona spans a solo blogger, a small team (startup) and a company (`docs/user-paths.md`), so the site-wide layer is named for all three — "Editorial Standards", not "Corporate Standards" (owner, 2026-10-07). Real alternatives: keep one site-wide voice and let authors overwrite it (loses the shared layer); one voice per post (no stable identity, repeated work); a voice per author stored in the plugin's Settings page (admin-edited, not the author's own); or a voice per author on their WordPress profile.

## Decision

Two layers, composed in PHP into the existing `brand_voice` payload field, so the n8n template does not change:

- **Editorial Standards** — site-wide, admin-owned, replaces "Brand Voice & Tone" in Settings → AI Prompts & Context; sent to Review and Rewrite for every author. The radio is the setting: a selected tone preset (and, for Website Context, a selected Blog Profile) is resolved when the command runs, from code, in the site language whoever triggers; Custom opens the textarea and sends the stored text (story 26, owner 2026-10-07 — lowest cognitive load; the earlier "presets fill an editable field" rule of 2026-07-23 is replaced).
- **Author voice** — per user, written by the author on their own WordPress profile page (admins see it on *Edit User*), never on the plugin's Settings page; the post's **author's** voice applies, never the triggering user's; no fallback voice when the author wrote nothing; Pro feature; deactivation hides the box and keeps the meta, uninstall deletes it (story 37).
- Website Context stays site-wide and free. The tone presets (Professional, Casual, Direct) stay in Settings as the Editorial Standards choices (the radio is the setting; Custom opens the text) and reappear on the author's profile as choices for their voice, Custom included.

## Consequences

- Easier: an author's posts read like that author; the shared rules reach every post without each author copying them; no re-import for anyone.
- Harder: `brand_voice` now carries two texts under one "TONE & VOICE RULES" heading — the prompt cannot weigh them differently until a template change splits them; a free site keeps one voice for everyone — for a solo blogger the Editorial Standards simply are their voice; a team on a free site writes its house tone into the standards.
- Not done until a site reports a need: per-author website context, a voice per post, prompt-level separation of the two layers.
