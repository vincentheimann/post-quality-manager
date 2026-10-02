---
id: 8
type: story
title: "Manual pass of the epic's Done when"
parent: epic-freemium-and-roles
covers: [CAP-1, CAP-2, CAP-3, CAP-4, CAP-5]
after: [7]
hitl: true
risk: low
---

# Manual pass of the epic's Done when

## Description

The owner walks Done when 1–4 on a real site, free then licensed, and records the result.

## Acceptance Criteria

Verify: Each Done when line is checked off, or a bug ticket exists for the failure.

## Walkthrough checklist (2026-10-02)

**Preconditions:** latest `main` on the real site; the `pqm_is_pro` mu-plugin file **removed** (the License key drives plan state since story 3); ideally `WP_DEBUG_LOG` on.

### Done when 1 — free site refuses the paid four before the webhook (UI, REST, AJAX)
- [ ] License field empty → status line reads "Current plan: Free"; Review and Key Takeaways both reach n8n.
- [ ] UI: Rewrite/Sources/SEO/Audit buttons render locked with upgrade copy; clicking fires no request.
- [ ] REST: `POST /wp-json/post-quality/v1/trigger-review` with `command=seo` → 403 `pqm_feature_gated`, and n8n shows no execution.
- [ ] AJAX: copy a trigger request as fetch in the browser console, swap the command to `rewrite` → 403 with the upgrade copy.

### Done when 2 — same zip, key entered, all six commands run; template/handshake unchanged
- [ ] Enter any key, save → "Current plan: Pro", Overrides + Automated Reviews unlock.
- [ ] Each of the six commands runs once and stores a result (SEO/Audit via their tabs).
- [ ] n8n Workflow page: version handshake quiet, no re-import ever prompted.

### Done when 3 — role behavior (stay keyed)
- [ ] Author: quota counter visible next to the buttons and decrements on a successful trigger; paid buttons absent (not locked); no Settings menu.
- [ ] Editor: Settings shows exactly Agent-Specific Overrides + Automated Reviews, editable; after an Editor save, webhook URL/token, models, context, quota and the license key are unchanged.
- [ ] Contributor: no PQM menu; direct REST call with their application password → 403.

### Done when 4 — capabilities remappable without code
- [ ] Role editor grants Author `pqm_run_paid_agents` → paid buttons appear, quota counter gone; removing the grant restores the free set + counter.

**Recording:** tick the boxes here; any failure becomes a bug ticket in `_bmad-output/backlog/` instead of a tick.

## References

- parent — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles.md
