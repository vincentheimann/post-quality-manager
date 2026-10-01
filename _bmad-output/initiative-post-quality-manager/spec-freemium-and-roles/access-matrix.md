# Access matrix — role × feature × tier

Effective access = plugin capability (role column) **and** plan tier (tier column), both read by `PQM_Features::can()`. On an unlicensed site every Paid row refuses regardless of role.

| Feature | Tier | Administrator | Editor | Author | Contributor |
|---|---|---|---|---|---|
| Trigger Review | Free | ✓ | ✓ | ✓ (quota) | — |
| Trigger Key Takeaways | Free | ✓ | ✓ | ✓ (quota) | — |
| Trigger Rewrite / Sources / SEO / Audit | Paid | ✓ | ✓ | — | — |
| Per-agent overrides (settings section) | Paid | ✓ | ✓ | — | — |
| Automated Reviews (cron) settings | Paid | ✓ | ✓ | — | — |
| Global settings, n8n Workflow page | — (admin) | ✓ | — | — | — |
| License, roles and quota management | — (admin) | ✓ | — | — | — |

- Quota: per user, monthly, visible; Admin-configured, shipped default 20/month; cron runs are site-level and never consume it.
- Editor's paid-agent access decided 2026-10-01 (owner): Editors get the four paid agents on a licensed site.
- Presentation (decision 2026-10-01): Paid rows an eligible role cannot use for plan reasons render visible but locked with upgrade copy; rows a role lacks the capability for are hidden.
- Capabilities are registered on activation with `pqm_` names and stay remappable by role-editor plugins; the table shows default mapping only.
