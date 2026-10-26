---
title: 'An agent verifies the plugin on a local WordPress through WP-CLI'
type: 'chore'
ticket: '9'
created: '2026-10-26'
status: 'in-review'
baseline_revision: '810c15547a7d0d8cc25b015cb6a03c4bd43e023a'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/lessons.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Every real-site check is a human pass in the owner's browser; the harnesses prove logic against stubs only. Sessions that walked the Local sites (stories 2, 67, 91) each rebuilt their own WP-CLI wrapper, cookie and backup scripts in a throwaway scratchpad, and two of them hit the same Local quirks (Local's own PHP, the MySQL port, `wp db import` importing nothing).

**Approach:** Tooling and docs only (ticket 9, owner decision 2026-10-26: build the tooling now; the pre-production pass itself still runs last, on the release that goes to WordPress.org). One committed entry point finds a Local site by its name in Local's own site list and runs WP-CLI there; one local listener captures the webhook payloads; one doc names the smoke sequence and the expected output of each step; one memory file lets a fresh session find all of it. Decision (owner, 2026-10-26, replacing the ticket's "symlinked"): each check installs a build (`npm run zip` for Pro, `bin/build-free.php` for Free) with `wp plugin install <zip> --force`, so it runs what ships; the doc names a junction to the working tree as an option for quick loops only. Decision (owner, 2026-10-26): routine checks use the showcase; the pre-production pass runs on a clean WordPress 6.6 site the owner creates in Local when story 38 nears (hitl), found by its name like any site.

## Boundaries & Constraints

**Always:** Python standard library only, run as `python -I bin/<script>.py` (no new dependency). A site is named by its Local name on the command line; no site address, folder, Local id or database credential is written into the repository (the name "Post Quality Showcase" is already public in `memory/lessons.md`). Every documented walk starts with a database export and ends with the restore, and lists what it created so the cleanup can remove it (owner rule 2026-10-20, `memory/` secrets-cleanup). Windows paths only (Local on the owner's PC); other platforms fail with a clear message.

**Never:** change plugin code (a defect found becomes its own ticket); run against the owner's production site or its Local copy (lessons 2026-10-24); send post content off the machine (the listener binds to 127.0.0.1); ship anything of this in the plugin zip.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Known site | `--site "Post Quality Showcase" plugin list` | WP-CLI's output, the imagick warning filtered | No error expected |
| Unknown or ambiguous name | a name absent from, or twice in, Local's list | exit 2, the names Local knows | No WP-CLI call |
| Local's PHP or MySQL missing | the version folder the site names is absent | exit 2, the missing path | No WP-CLI call |
| DB import | `--site … import <dump.sql>` | imported through Local's MySQL client on the site's port; table count printed | non-zero exit on a client error |
| Webhook captured | listener running, plugin pointed at it, review triggered | one JSON line per request in the capture file; the plugin sees 200 | No error expected |

</frozen-after-approval>

## Code Map

- Local's site list: `%APPDATA%\Local\sites.json`, a dict keyed by Local id; each entry has `name`, `path`, `domain`, `mysql{database,user,password}` and `services{php{version}, mysql{version, ports}}` (the MySQL port is `services.mysql.ports.MYSQL`, read on the owner's PC 2026-10-26; check its type, list or number, before using it). PHP: `%APPDATA%\Local\lightning-services\php-<version>+<build>\bin\win64\php.exe` (the folder carries a `+N` build suffix sites.json lacks: match `php-<version>+*`), ini `%APPDATA%\Local\run\<id>\conf\php\php.ini`; WP-CLI `%LOCALAPPDATA%\Programs\Local\resources\extraResources\bin\wp-cli\wp-cli.phar`; `--path=<site path>\app\public`; MySQL client `lightning-services\mysql-<version>\bin\win64\bin\mysql.exe` put first on PATH (WP-CLI's `db export` needs `--port=<port> --protocol=tcp`; `db import` and `db query` ignore the port, so `import` goes through `mysql.exe -h 127.0.0.1 -P <port>`). Source: `test-instances` notes of sessions 5e744831 and 4a97be98 (agent-local), re-verified 2026-10-26.
- `bin/` -- home of the repo's helper scripts (`build-free.php`, `zip.js`, `count-cost-example.py`); new `bin/local-wp.py` and `bin/webhook-capture.py`.
- Builds to install: `npm run zip` → `dist/post-quality-manager-<branch>.zip` (Pro build of the tree); `php bin/build-free.php <out>` then a zip of `<out>` (Free build). The showcase runs a Free build; the Free build loads no cron class.
- Plugin facts the smoke sequence uses: REST namespace `post-quality/v1` (`post-quality-manager.php` L89); `POST /trigger-review` (`post_id` required, `command` optional; `class-pqm-rest-api.php` L70, L640–655) needs `n8n_webhook_url` in `postquality_settings` and calls it with `wp_remote_post` (L720 area), which allows 127.0.0.1 (story 2's 2026-10-22 walk used a listener there); `GET /posts` for a per-role read; cron hook `postquality_cron_review_posts` (Pro build only). The settings sanitizer is registered on the Settings API (`admin/class-pqm-admin.php` L314–350), which WP-CLI never loads, so `wp option patch update postquality_settings …` writes the value as given. A REST call per role runs headless as `wp eval` with `wp_set_current_user()` and `rest_do_request()` (no cookie, no nonce).
- Deleting the plugin from WP-CLI removes its data only with `--user=<an administrator>` on both `wp plugin deactivate` and `wp plugin uninstall` (`memory/freemium-and-roles.md`, real licensing bullet).
- `WP_DEBUG_LOG` is not defined on the showcase today; `wp config set WP_DEBUG true --raw` and `WP_DEBUG_LOG true --raw` write it, the log is `wp-content/debug.log`.
- `AGENTS.md` "Running and verifying" -- the bullet that says real WP Admin behaviour is verified manually: one sentence pointing to the new doc.
- `memory/agent-conventions.md` L21 -- the real-site verification rule names story 9 as the future run; point it to the doc and memory file.

## Tasks & Acceptance

**Execution:**
- [x] `bin/local-wp.py` -- `--site <name>` resolves the site from Local's list and runs the rest of the command line as WP-CLI with Local's PHP and MySQL; `import <dump.sql>` imports through Local's MySQL client; exit 2 with a message for the matrix's error rows -- AC1, the one entry point.
- [x] `bin/webhook-capture.py` -- `--port`, `--out <file.jsonl>`: answers every POST with 200 `{}`, appends `{time, path, headers, body}` as one JSON line, binds 127.0.0.1 only, stops on Ctrl+C -- AC3.
- [x] `docs/local-verification.md` -- the owner's one-time setup (the HITL steps, already done for the showcase), the entry point, installing a Free or Pro build, the smoke sequence with the expected output of each step (option write, four role users, one REST call per role, the trigger with capture, the cron run on a Pro build, `debug.log`), backup and restore, the cleanup list, the rules (showcase only, nothing site-specific committed) -- AC2, AC3.
- [x] `memory/local-verification.md` + `memory/MEMORY.md` index line; `memory/agent-conventions.md` L21; `AGENTS.md` one sentence -- AC4.

**Acceptance Criteria:**
- Given the showcase site, when an agent runs `python -I bin/local-wp.py --site "Post Quality Showcase" plugin list`, then `post-quality-manager` is listed active, with no browser and no human step.
- Given the doc's smoke sequence run on the showcase after an export, when each step runs, then each completes headlessly with the output the doc names, the capture file holds the trigger's payload, and the restore leaves the site as it was.
- Given a fresh session that reads `memory/MEMORY.md`, when it looks for how to check the plugin on a real WordPress, then the index leads it to the memory file and the doc, and it can run the three checks above from them alone.

## Implementation Notes

- Deviation (2026-10-26): the session runs from the workspace root and renders `bmad-build` by hand (workspace rule 2026-10-16). Commits, push and draft PRs are allowed for this sprint by the owner (2026-10-26), overriding the house "user commits" rule. Ticket 9's rank (owner 2026-10-08: last, pre-production) holds for the pass; the owner moved the tooling ahead on 2026-10-26.
- Implementer (2026-10-26): first dispatched on Fable 5.1 under the 2026-10-16 rule; the owner then set "every agent on Opus until I change it", so that run was stopped before it changed any file (tree and showcase checked: nothing written) and the implementer was relaunched on Opus 5.5 (`model: "opus"`).
- Built (2026-10-26, implementer on Opus 5.5): `bin/local-wp.py`, `bin/webhook-capture.py`, `docs/local-verification.md`, `memory/local-verification.md` + index line, `memory/agent-conventions.md` L21, `AGENTS.md` one sentence (plus a hand-edit note in its managed block's header comment, as stories 88 and 91 did). No plugin file touched.
- The imagick warning is removed at its cause, not filtered: `php_imagick.dll` loads once PHP's own `ImageMagick` folder (beside `php.exe`) is on `PATH`, so WP-CLI's output passes through unpiped (prompts, `wp shell` and `db export -` keep working). Every PHP folder on the owner's PC that ships `php_imagick.dll` also ships that folder (checked 2026-10-26).
- `import` drops every base table of the site's database before feeding the dump: the walk's build renames the reviews table on its first load (story 91), and a plain import would leave the renamed table beside the restored one. `db import` and `db query` are refused with exit 2 (they never reach the site's MySQL); `db export` gets `--port --protocol=tcp` added and still prints WP-CLI's harmless character-set warning, named in the doc.
- The Code Map's `wp option patch update` fails with "No data exists for key" on the showcase (its settings held no `n8n_webhook_url`); the doc uses `patch insert`, which sets the key either way (overwrite checked on the showcase).
- Cron: a `cron event schedule` due now is run at once by the site's own WP-Cron (HTTP spawn), and `cron event run` then answers "Invalid cron event"; the doc schedules `'+1 hour'` and runs it.
- On a Free build a Pro command answers 400 `rest_invalid_param` (the enum is the Free registry), not 403 `pqm_feature_gated`; the doc says so.
- The Pro build of the walk came from `npm run zip` with the four untracked story files stashed around it (`git stash push -u`, popped right after; the zip refuses an unclean tree).
- Walked twice on the showcase (the second run copy-pasting the doc's blocks), both restored and checked; the junction option and the 6.6 downgrade are documented as not exercised.

## Plan Change Log

## Review Triage Log

## Verification

**Commands:**
- `python -I bin/local-wp.py --site "Post Quality Showcase" plugin list` -- expected: the plugin listed active
- `python -I bin/local-wp.py --site "No Such Site" plugin list` -- expected: exit 2, the known names
- the doc's smoke sequence on the showcase, export first and restore last -- expected: every step's documented output; the site restored (same plugin list, same review count)
- the hygiene search from the agent's private notes (site titles with and without a separator, domains, Local ids; never written into the repository) over the new files -- expected: no match

**Manual checks (if no CLI):**
- None: this story's checks are the CLI runs above.
