# Translation test fixtures (story 4.6)

Two hand-written files that prove the plugin reads its translations from WordPress's language-pack folder, `wp-content/languages/plugins/`, where translate.wordpress.org's language packs land (story 95: the plugin bundles no `languages/` folder and loads no text domain itself). They are not translations and never ship: the release zip only holds `post-quality-manager/`.

| File | Read by | Strings |
|---|---|---|
| `post-quality-manager-de_DE.l10n.php` | PHP admin pages (WordPress 6.5+ reads this format before `.mo`; it loads just in time) | Post Reviews → Beitragsprüfungen, Settings → Einstellungen |
| `post-quality-manager-de_DE-pqm-app.json` | the React bundle. A language pack names this file after the md5 of the script's path inside the plugin: `md5('admin/build/index.js')` = `aa00a6c9d8ddb82857c6ba4f44b9853b`, so copy the fixture as `post-quality-manager-de_DE-aa00a6c9d8ddb82857c6ba4f44b9853b.json` | Average score → Durchschnittliche Bewertung |

## Check on a site

1. Copy both files into `wp-content/languages/plugins/` (create the folder if it is missing); name the JSON copy `post-quality-manager-de_DE-aa00a6c9d8ddb82857c6ba4f44b9853b.json`.
2. Settings → General → Site Language: Deutsch. Install the language when WordPress asks.
3. With `WP_DEBUG` on, open the plugin's menu. The submenu shows **Einstellungen** and **Beitragsprüfungen**, the Post Reviews page heading reads **Beitragsprüfungen**, and the Dashboard tile reads **Durchschnittliche Bewertung**. No notice appears.
4. Delete the two copies from `wp-content/languages/plugins/` and reload: the same labels are English again, still without a notice.

`tests/harness/release-header-harness.php` fails when a fixture string disappears from the plugin, so the check keeps working after copy changes.
