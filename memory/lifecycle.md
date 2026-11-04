# Life cycle record

_What the plugin uses from each tracked part, and when it was last checked against its source (ADR 0017). The `/lcm` skill updates this file. Add or drop a row when a part is added or dropped._

| Part | In use | Source of truth | Last checked | Latest seen / ticket |
|---|---|---|---|---|
| AI models (Anthropic) | the registry in `postquality_model_registry()`: Claude Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5 (`memory/llm-options-architecture.md`); requests send `anthropic-version: 2023-06-01` | the provider's models and deprecations pages; the `claude-api` skill's model reference | 2026-11-03 | all four still served, none deprecated; current generation not listed (Opus 5.5, Sonnet 5.5, Haiku 5.5, Opus 5, Fable 5.1); Sonnet 5's price label wrong → story 101 |
| Freemius SDK | 2.13.5, vendored unmodified (chore 89) | github.com/Freemius/wordpress-sdk tags | 2026-11-03 | 2.13.5 (2026-10-22): same |
| WordPress | `Tested up to: 7.1`, floor 6.6 | api.wordpress.org core version check | 2026-11-03 | 7.1.4: same |
| PHP | `Requires PHP: 8.0` (security support ended 2023-11-26) | endoflife.date; WordPress requirements ("8.3 or greater") | 2026-11-03 | floor past end of life → spike 104 |
| n8n | template version 8, proven on the owner's 2.42.x (`memory/n8n-provisioning.md`) | github.com/n8n-io/n8n releases | 2026-11-03 | 2.46.4; 2.43.0 blocks publishing workflows with unconnected required inputs → story 105 |
| Build | `@wordpress/scripts` 30.27.0; `typescript` 5.3.3 | npm registry (`npm outdated`) | 2026-11-03 | 35.0.0; 7.0.2 → story 103 |
| CI | `actions/checkout@v7`, `setup-node@v7`, `upload-artifact@v7`, `download-artifact@v8` (from v4 on 2026-11-04, story 102; the release job's `setup-node` has no npm cache, CI's keeps `cache: npm`; setup-node v7 would cache by itself if `package.json` gained a `packageManager` or `devEngines.packageManager` naming npm); `shivammathur/setup-php@v2`; `wordpress/plugin-check-action` v1.1.9 (SHA-pinned); `googleapis/release-please-action@v5`; runner `ubuntu-latest` (Ubuntu 26 since 2026-10-19) | each action's releases; run annotations | 2026-11-03 | checked 2026-11-04 by story 102: checkout v7.0.1, setup-node v7.1.0, upload-artifact v7.0.2, download-artifact v8.0.2, all in use since story 102; download-artifact v8.0.2 still logs `[DEP0005] Buffer() is deprecated` (actions/download-artifact#484, open; no input removes it): recheck at the next `/lcm`; the rest current |
