---
name: voice-layers
description: ADR 0011 (2026-10-07) — Editorial Standards per site + a voice per author on their WP profile, composed into brand_voice in PHP; Editorial Standards built (story 26), author voice pending (story 37)
metadata:
  type: project
---

Decided 2026-10-07 (ADR [0011](../docs/adr/0011-editorial-standards-and-author-voice.md)); the editorial layer is built (story 26, 2026-10-07), the author layer is not yet (story 37). The AI receives two voice layers through the existing `brand_voice` payload field, composed in PHP — the n8n template is unchanged:

- **Editorial Standards** (site-wide, admin, Settings → AI Prompts & Context; formerly "Brand Voice & Tone", stored keys unchanged: `brand_voice_preset`, `brand_voice`): the radio is the setting (owner, 2026-10-07): `postquality_resolved_voice()` / `postquality_resolved_context()` resolve a selected preset / Blog Profile from code at send time in the site locale (`switch_to_locale(get_locale())`), Custom sends the stored text; the page shows a selected preset's text muted and the textarea only under Custom (`bindPresetChoice()` in `pqm-admin.js` toggles, never writes). Two rows: Blog Profile → `website_context`, Editorial Standards → `brand_voice`. The 2026-07-23 "presets stay editable in place" rule is replaced — Custom is the editable path. Each field's description names its agents and the template default an empty field gets. Proof: `tests/harness/voice-harness.php`.
- **Author voice** (per user, on the author's own WordPress profile page / *Edit User* for admins, never on the plugin Settings page; Pro; the post's author's voice, not the triggering user's; no fallback voice; uninstall deletes the meta) — story 37.

Related: [[llm-options-architecture]], [[freemium-and-roles]], [[user-paths]].
