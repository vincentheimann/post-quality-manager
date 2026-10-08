# Translation test fixtures (story 4.6)

Two hand-written files that prove the plugin loads translations from its own `languages/` folder. They are not translations and never ship: the release zip only holds `post-quality-manager/`, and `languages/` is not tracked in git.

| File | Read by | Strings |
|---|---|---|
| `post-quality-manager-de_DE.l10n.php` | PHP admin pages (WordPress 6.5+ reads this format before `.mo`) | Post Reviews → Beitragsprüfungen, Settings → Einstellungen |
| `post-quality-manager-de_DE-pqm-app.json` | the React bundle (handle `pqm-app`, so no md5 file name is needed) | Average score → Durchschnittliche Bewertung |

## Check on a site

1. Copy both files into `wp-content/plugins/post-quality-manager/languages/` (create the folder).
2. Settings → General → Site Language: Deutsch. Install the language when WordPress asks.
3. With `WP_DEBUG` on, open the plugin's menu. The submenu shows **Einstellungen** and **Beitragsprüfungen**, the Post Reviews page heading reads **Beitragsprüfungen**, and the Dashboard tile reads **Durchschnittliche Bewertung**. No notice appears.
4. Delete the `languages/` folder and reload: the same labels are English again, still without a notice.

`tests/harness/release-header-harness.php` fails when a fixture string disappears from the plugin, so the check keeps working after copy changes.
