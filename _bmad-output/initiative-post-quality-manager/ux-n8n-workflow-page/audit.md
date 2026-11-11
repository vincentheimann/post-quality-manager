# Audit — the n8n Workflow page as it ships (main 4b56564, 2026-10-14)

Story 67, AC1. Read from the code (`admin/class-pqm-admin.php` `render_workflow()` and helpers, `src/setup/`, `src/n8n-connection/`, `admin/js/pqm-admin.js`); spot-checked. The page needs `pqm_manage_settings`. Every section below renders in every state (code comment: "The sections below always show"); only lines inside them change.

## The page, in render order

| # | Element | Job | Renders / changes when |
|---|---------|-----|------------------------|
| 1 | H1 "n8n Workflow" | title | always |
| 2 | Intro line "New to n8n?…" | send novices to the dialog | setup not complete |
| 3 | Button "Set up the workflow" (primary) / "Setup guide" (secondary) | open the setup dialog | label by `complete`; opens on load with `?pqm_setup=1` |
| 4 | Status line "Template version: 6 · Your n8n workflow: …" | which version answered, when, how | always; 3 forms: not checked yet / older / checked via test or command |
| 4a | Warning "Workflow update available" | ask for a re-import | reported version < 6 |
| 4b | Warning "Free variant" | ask a Pro site to re-import | Pro build and the Free template answered |
| 5 | H2 "n8n Instance" + island | store n8n address + API key, probe | always. Inside: intro, "Connected… Checked {date}" notice, "API key: set / not set", address field, key field, Connect, Check again + Forget key (key stored), error notice |
| 6 | H2 "Workflow File" | the manual path | always |
| 6a | "New to n8n? Use 'Set up the workflow'…" | point back to the dialog | setup not complete |
| 6b | Info "On {date}, the n8n Instance check did not pass: …" | why the reader is on the manual path | last probe failed with a manual-path cause, no passing key |
| 6c | Info "You chose to set up n8n by hand" | same, by choice | `manual` flag (nothing writes it yet; entry 6.6) |
| 7 | Step 1 Import | import URL copy row (carries the token) + privacy note, "your n8n" link, Download button | always |
| 8 | Step 2 Credentials | webhook credential (3 copy rows), Claude credential (2 rows + Anthropic link), Pro: WordPress credential (name, user, Application Passwords link or "off" text) | always; Free says "two Claude nodes", Pro "six" |
| 9 | Step 3 Check each node | node list | always; Free/Pro lists |
| 10 | Step 4 Publish and connect | publish, unpublish-old note, expected `/webhook/<path>`, "not the Test URL", link to Settings → Connection to paste the URL | always |
| 11 | H2 "What Each Agent Sends to Claude" + table (Agent/Model/Effort/Temperature/Max tokens) | reference | always; 2 rows Free, 6 Pro |
| 12 | H2 "Connection Test" + "Send test ping" + result | ping the saved URL | always; without a URL the click answers "Enter your n8n webhook URL first." |
| 13 | Setup dialog (Modal) | guided manual setup, then profile, standards, schedule (Pro) | on the button. n8n steps: get an instance, import, credentials, link, publish, connect (URL field + "Save and test"), first review. No API-key or provisioning step yet |

## n8n parts of the Settings page (Connection tab)

- "New here?…" + link to the n8n Workflow page (no webhook URL saved).
- H2 "n8n Connection" + link to the n8n Workflow page.
- **"n8n Webhook URL" field + "Send test ping"** (tests the unsaved value).
- "Webhook Token", read-only (no rotation yet: entry 6.10).
- "Notification URL".
- AI tab: the models and limits the page's agent table repeats.

## Duplications

| What | Where |
|------|-------|
| Webhook URL (edit) | Settings field; dialog connect step. Page step 4 links to Settings |
| Test ping | Settings; page Connection Test; dialog "Save and test" (one `ping_webhook()`) |
| Webhook token | Settings (read-only); page copy row; dialog copy row; inside the import URL (page + dialog) |
| Import URL + Download | page step 1; dialog Import |
| Credential names, header rows, WP user, node linking, publish | page steps 2–4; dialog Credentials / Link / Publish (dialog wording stale since entry 6.2) |
| "Set up the workflow" entry point | page button; page line 6a; welcome notice on other plugin pages; Dashboard connect card; Post Reviews notice |
| Outdated / Free-variant warning | status block 4a/4b; ping reply messages |
| Model / limits per agent | Settings AI tab; page agent table |

## States the code distinguishes

- Webhook URL saved or not (`connected`).
- Setup complete (`complete`, or a URL + a stored result).
- Workflow report: none (not checked), version 0, older than 6, current; Free variant on Pro.
- n8n API key stored or not; last passing probe; last manual-path failure; base URL.
- Manual reason: probe failure, choice, none.
- Build: Free or Pro; Application Passwords available or not.
- Provisioned (`pqm_n8n_setup`) and leftovers (`pqm_n8n_leftovers`): stored since entries 6.4/6.5, read by no screen yet.

## What the epic adds to this page

6.6 the wizard (connect by key → provision → first review; "Set up by hand instead"), 6.7 mis-wiring diagnosis (one sentence in the handshake), 6.8 adopt an existing manual setup, 6.9 one-click update (replaces the re-import warning), 6.10 token rotation.
