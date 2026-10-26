# Checking the plugin on a local WordPress

_How an agent session checks the plugin on a real WordPress, headlessly, through WP-CLI on a [Local](https://localwp.com) site on the owner's PC. Written by backlog story 9 on 2026-10-26, walked the same day on the showcase site. The harnesses in `tests/harness/` prove the logic against stubs; this walk proves it on WordPress itself: the build installs, options are written, each role gets the right REST answer, a trigger reaches the webhook, the cron runs, `debug.log` stays clean. Look and feel in the browser stays the owner's pass._

## Rules

- **Which site.** Routine checks run on the showcase, the Local site "Post Quality Showcase" (sample posts, a Free build). The pre-production pass runs on a clean WordPress 6.6 site the owner creates in Local when story 38 nears. Never the owner's production site or its Local copy, reads included (`memory/lessons.md` 2026-10-14, 2026-10-24).
- **Nothing site-specific in the repository.** A site is named by its Local name on the command line; its address, folder, Local id, port and database credentials are read from Local at run time and are never written into a repository file, ticket, plan or memory line. Neither are dumps, captures, post titles or user names from a site.
- **Nothing leaves the machine.** The webhook listener binds to 127.0.0.1; the plugin's payload (post content, the site's webhook token) goes to it and to no other host.
- **Export first, restore last.** Every walk starts with the backup below and ends with the restore, then checks the site is as it was. Every file the walk writes lives in the session's scratchpad, outside the repository, and is deleted when the walk ends (owner rule 2026-10-20: nothing an agent creates outlives the work).
- **A defect found becomes its own ticket.** The walk changes no plugin code.
- **Shell.** The commands below are for the agents' shell, Git Bash on Windows, run from the repository root. Shell state does not survive between tool calls: start every call with the two lines of [Variables](#variables).

## One-time setup (owner)

Done for the showcase. For a new site (the pre-production site, story 38):

1. Install Local (localwp.com) on Windows.
2. Create the site in Local with PHP 8.x and MySQL as its database (not MariaDB: the entry point knows Local's MySQL only). Give it a name no other Local site carries; that name is how every command finds it. Local installs the current WordPress release; how the pre-production site gets to 6.6 (WP-CLI's downgrade, `lwp core update --version=6.6 --force`, is one way) is settled when that pass is prepared, it is not exercised yet.
3. Start the site in Local before a walk. A stopped site answers every command with "Error establishing a database connection", and an import with the MySQL client's "Can't connect".

The agent needs nothing else: no address, folder or password is shared. The plugin comes from a build the walk installs ([Installing a build](#installing-a-build)).

## The entry point: `bin/local-wp.py`

```sh
python -I bin/local-wp.py --site "Post Quality Showcase" plugin list
python -I bin/local-wp.py --site "Post Quality Showcase" import <dump.sql>
```

It finds the one site with that name in Local's site list (`%APPDATA%\Local\sites.json`) and runs the rest of the command line as WP-CLI there, with Local's PHP for the site's version and the site's `php.ini`, the `wp-cli.phar` Local bundles, the site's `app\public` folder, and Local's MySQL client first on `PATH`. Standard library only, Windows only.

- **Exit codes:** WP-CLI's own, or the MySQL client's on `import`; 2 when the name is absent from Local's list or in it twice (it prints the names Local knows), when the PHP, MySQL client, `php.ini`, `wp-cli.phar` or site folder the site needs is missing (it prints the path), or for a refused command.
- **No imagick warning:** PHP's ImageMagick folder is put on `PATH`, so `php_imagick.dll` loads instead of printing a startup warning before every output.
- **`db export`** gets the site's MySQL port and `--protocol=tcp` added. It prints `Warning: Failed to get current character set of the posts table. Reason: ERROR 2003 … 'localhost:3306'` (a WP-CLI check that ignores the port); the dump is complete and starts with `SET NAMES utf8mb4`.
- **`db import` and `db query` are refused** (exit 2): they ignore the port and never reach the site's database. `import <dump.sql>` is the restore: it drops every table of the site's database, then feeds the dump to Local's MySQL client on 127.0.0.1 and the site's port, and prints `Imported <dump>: <n> tables dropped first, <m> tables now.` Dropping first matters: a build installed during the walk can rename or add tables (since story 91 the first load renames the reviews table), and a plain import would leave them beside the restored ones. A query goes through `eval` and `$wpdb`.
- **WP-CLI writes PHP deprecations of its own** into `debug.log` on PHP 8.5 (`Using null as an array offset is deprecated … wp-cli.phar/vendor/wp-cli/php-cli-tools/lib/cli/Colors.php`), once per output line. They are not the plugin's; step 8 leaves them out.

## Installing a build

Each check installs a build, so it runs what ships: `lwp plugin install <zip> --force` replaces the plugin folder and keeps the plugin active (no activation hook runs; the plugin's first load runs its one-time upgrades).

- **Free build** (what the showcase runs):
  ```sh
  php bin/build-free.php "$S/free"
  python -I -m zipfile -c "$S/free.zip" "$S/free/post-quality-manager"
  lwp plugin install "$S/free.zip" --force
  ```
  `build-free.php` copies the tree, React build included: run `npm run build` first when `post-quality-manager/admin/build/` is missing or older than `src/`. It refuses an output folder that already holds a build: use a new one per walk. Expected: `Free build written to …`, then `Plugin updated successfully.` and `Success: Installed 1 of 1 plugins.`
- **Pro build**: `npm run zip` writes `dist/post-quality-manager-<branch>.zip` (it refuses a tree with uncommitted or untracked changes: commit first), then `lwp plugin install dist/post-quality-manager-<branch>.zip --force`.
- **Which one is installed:** `lwp eval 'echo class_exists( "PostQuality_Cron" ) ? "Pro build" : "Free build", PHP_EOL;'` (the Free build loads no cron class).
- **Quick loops only: a junction to the working tree.** For many small edits in a row, the site's plugin folder can point at `post-quality-manager/` instead: move the installed folder (`lwp plugin path post-quality-manager --dir`) into the scratchpad, then `cmd //c mklink /J "<that folder>" "<repo>\post-quality-manager"`. It runs the Pro source with whatever `admin/build/` holds, never the Free build. Remove the junction with `cmd //c rmdir "<that folder>"` (it removes the link, not the target) and move the folder back **before** any `plugin install --force`, `plugin delete` or `plugin uninstall`: those delete through the junction into the working tree. A check that counts runs on a build. (Not exercised by story 9's walk.)

## The walk

### Variables

```sh
SITE="Post Quality Showcase"; S="<session scratchpad>/local-walk"; mkdir -p "$S"
lwp() { python -I bin/local-wp.py --site "$SITE" "$@"; }
```

### 1. Backup

```sh
lwp plugin list > "$S/before-plugins.txt"; cat "$S/before-plugins.txt"
lwp --skip-plugins eval 'global $wpdb; $t = $wpdb->get_col( "SHOW TABLES" ); echo count( $t ), " tables", PHP_EOL; foreach ( $t as $n ) { if ( preg_match( "/reviews$/", $n ) ) { echo $n, ": ", $wpdb->get_var( "SELECT COUNT(*) FROM $n" ), " rows", PHP_EOL; } } echo "users: ", count_users()["total_users"], PHP_EOL; echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? "debug.log exists" : "no debug.log", PHP_EOL;' > "$S/before-state.txt"; cat "$S/before-state.txt"
lwp db export "$S/before.sql"
python -I -m zipfile -c "$S/plugin-before.zip" "$(lwp plugin path post-quality-manager --dir)"
cp "$(lwp config path)" "$S/wp-config-before.php"
```

Expected: the plugin list with `post-quality-manager	active`; the table count, the reviews table with its row count (`wp_pqm_reviews` on a build older than story 91, `wp_postquality_reviews` after), the user count and `no debug.log`; `Success: Exported to '…/before.sql'.` after the character-set warning above; no output from the zip and the copy. If a `debug.log` exists, move it into `$S` now and put it back at the end.

### 2. Install the build and turn the debug log on

Install the Free or Pro build ([Installing a build](#installing-a-build)), then:

```sh
lwp config set WP_DEBUG true --raw
lwp config set WP_DEBUG_LOG true --raw
lwp eval 'echo class_exists( "PostQuality_Cron" ) ? "Pro build" : "Free build", PHP_EOL;'
```

Expected: `Success: Updated the constant 'WP_DEBUG' in the 'wp-config.php' file with the raw value 'true'.`, `Success: Added the constant 'WP_DEBUG_LOG' …` (or `Updated` if it was there), then the build you installed. The log is `wp-content/debug.log`.

### 3. Start the webhook listener

```sh
python -I bin/webhook-capture.py --port 8765 --out "$S/capture.jsonl"
```

Run it in the background (an agent: the shell tool's background mode; stop it by its task id at the end; a person: a second terminal, Ctrl+C). Any free port does. Expected: `Capturing POSTs on http://127.0.0.1:8765/ into …/capture.jsonl (Ctrl+C stops).` It answers every POST with 200 and `{}`, appends one JSON line per request (`time`, `path`, `headers`, `body`) and prints each request on stderr.

### 4. Write a setting

```sh
lwp option patch insert postquality_settings n8n_webhook_url http://127.0.0.1:8765/webhook
lwp option pluck postquality_settings n8n_webhook_url
```

Expected: `Success: Updated 'postquality_settings' option.` (or `… is unchanged.` when it already held that value), then `http://127.0.0.1:8765/webhook`. `patch insert` sets the key whether or not it exists; `patch update` fails with `No data exists for key` on a site that never saved one. WP-CLI never loads the Settings API, so the value is stored as given, without the admin sanitizer.

### 5. One test user per role

```sh
for role in administrator editor author contributor; do lwp user create "pq-smoke-$role" "pq-smoke-$role@example.com" --role="$role" --porcelain; done
```

Expected: four new user IDs, one per line.

### 6. One REST call per role

```sh
for role in administrator editor author contributor; do printf '%s: ' "$role"; lwp --user="pq-smoke-$role" eval '$r = rest_do_request( new WP_REST_Request( "GET", "/post-quality/v1/posts" ) ); $d = $r->get_data(); echo $r->get_status(), " ", $r->is_error() ? $d["code"] : $d["total"] . " posts", PHP_EOL;'; done
```

The request runs inside WordPress as that user (`--user` sets the current user; no cookie, no nonce, no HTTP), through the route's `permission_callback` and argument handling. Expected with the default role map (`postquality_view_reviews` for administrator, editor and author):

```
administrator: 200 <n> posts
editor: 200 <n> posts
author: 200 <n> posts
contributor: 403 rest_forbidden
```

The same form reaches any route: `new WP_REST_Request( "<METHOD>", "/post-quality/v1/<route>" )`, `set_param()` for its arguments.

### 7. Trigger a review and read the capture

```sh
lwp --user=pq-smoke-administrator eval '$q = new WP_REST_Request( "POST", "/post-quality/v1/trigger-review" ); $q->set_param( "post_id", get_posts( [ "numberposts" => 1 ] )[0]->ID ); $r = rest_do_request( $q ); echo $r->get_status(), " ", wp_json_encode( $r->get_data() ), PHP_EOL;'
python -I -c "import json, sys
for line in open(sys.argv[1], encoding='utf-8'):
    c = json.loads(line); b = c['body']
    print(c['path'], b['command'], b['post_id'], 'X-PQM-Token' in c['headers'], len(b['content']), 'chars')" "$S/capture.jsonl"
```

Expected: `200 {"success":true,"message":"Review started for \"<post title>\".","n8n_status":200,"quota_remaining":null}`, then one capture line: `/webhook review <post id> True <n> chars` (the token header is there, the body is the payload `postquality_n8n_payload()` builds). The reviews table gains one row with status `processing`: the listener never calls back, so it stays until the restore. Add `$q->set_param( "command", "takeaways" )` for another agent. On a Free build a Pro agent's command (`sources`, `seo`, `audit`, `rewrite`) answers `400 rest_invalid_param`: the Free build's command list holds the free agents only.

### 8. The cron run (Pro build only)

The Free build registers no cron callback; install a Pro build for this step.

```sh
lwp cron event schedule postquality_cron_review_posts '+1 hour'
lwp cron event run postquality_cron_review_posts
```

Expected: `Success: Scheduled event with hook 'postquality_cron_review_posts' for <time>.`, then `Executed the cron event 'postquality_cron_review_posts' in <s>s.` and `Success: Executed a total of 1 cron event.`; the capture gains up to `cron_batch_size` (default 5) lines with `review`, one per post needing a review (the cron's calls do not wait, so a line can land a moment later). Schedule an hour ahead: an event due now is picked up at once by the site's own WP-Cron (WordPress starts it over HTTP while WP-CLI runs), and `cron event run` then answers `Error: Invalid cron event`. The run removes the one-off event.

### 9. Read `debug.log`

```sh
lwp --skip-plugins eval '$f = WP_CONTENT_DIR . "/debug.log"; if ( ! file_exists( $f ) ) { echo "no debug.log", PHP_EOL; return; } $all = file( $f ); $rest = array_filter( $all, fn( $l ) => ! str_contains( $l, "wp-cli.phar" ) ); echo count( $all ), " lines, ", count( $all ) - count( $rest ), " of them from WP-CLI itself", PHP_EOL, implode( "", $rest );'
```

Expected: `<n> lines, <n> of them from WP-CLI itself` and nothing after it. Any other line (a notice, warning or error from the plugin, WordPress or another plugin) is a finding: file it as a bug ticket.

### 10. Restore

Stop the listener first, then:

```sh
lwp plugin install "$S/plugin-before.zip" --force --skip-plugins
lwp import "$S/before.sql"
cp "$S/wp-config-before.php" "$(lwp config path)"
lwp --skip-plugins eval '$f = WP_CONTENT_DIR . "/debug.log"; echo file_exists( $f ) && unlink( $f ) ? "debug.log deleted" : "no debug.log", PHP_EOL;'
```

The plugin files go back first, with `--skip-plugins`, then the database: in the other order the walk's build, still installed, would run its one-time upgrades on the restored database at its next load. Expected: `Plugin updated successfully.` and `Success: Installed 1 of 1 plugins.`; `Imported …/before.sql: <n> tables dropped first, <m> tables now.` with `<m>` the dump's table count; no output from the copy; `debug.log deleted`. Put back a `debug.log` you moved aside in step 1.

### 11. Check the site is as it was, then clean up

```sh
lwp plugin list | diff "$S/before-plugins.txt" - && echo "plugin list: same"
lwp --skip-plugins eval 'global $wpdb; $t = $wpdb->get_col( "SHOW TABLES" ); echo count( $t ), " tables", PHP_EOL; foreach ( $t as $n ) { if ( preg_match( "/reviews$/", $n ) ) { echo $n, ": ", $wpdb->get_var( "SELECT COUNT(*) FROM $n" ), " rows", PHP_EOL; } } echo "users: ", count_users()["total_users"], PHP_EOL; echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? "debug.log exists" : "no debug.log", PHP_EOL;' | diff "$S/before-state.txt" - && echo "tables, reviews, users, log: same"
```

Expected: `plugin list: same` and `tables, reviews, users, log: same`.

What the walk created, and what removes it:

| Created | Where | Removed by |
|---|---|---|
| the build's files | the site's plugin folder | step 10's plugin install |
| the build's upgrades (renamed options, table, capabilities), the webhook URL, the four `pq-smoke-*` users, the `processing` rows, the cron event, WP-Cron's own runs | the site's database | step 10's import |
| `WP_DEBUG` true, `WP_DEBUG_LOG` | `wp-config.php` | step 10's copy |
| `wp-content/debug.log` | the site | step 10's delete |
| the listener | a process of this session | stopping it (step 10) |
| dumps, plugin and `wp-config.php` copies, build folders and zips, the capture (it holds post content and the site's webhook token) | `$S`, in the session's scratchpad | deleting `$S` once step 11 passed |
| `dist/post-quality-manager-<branch>.zip` (Pro build) | the repository, git-ignored | deleting it, or leaving it for the next `npm run zip`, which replaces it |

## Other checks on the same site

- **Plugin Check:** `lwp plugin check post-quality-manager` (Plugin Check is installed on the showcase).
- **Deleting the plugin:** the Freemius SDK registers the plugin's uninstall callback only when an administrator deactivates it and runs it only for a user who can `activate_plugins`, so a WP-CLI walk of deletion passes `--user=<an administrator>` on both `lwp plugin deactivate` and `lwp plugin uninstall`; without it the files go and every option, user meta, capability and the reviews table stay (`memory/freemium-and-roles.md`, real licensing). The restore brings the plugin back.
- **A plan's manual checks:** the steps a story plan's Verification section lists run in the same frame: backup, build, the checks through `lwp` (options, `eval` with `rest_do_request()`, the capture), restore.

## Not covered

- **The browser:** layout, loading and error states, look and feel stay the owner's pass in WP Admin.
- **The n8n round trip:** the listener answers like n8n's webhook but never runs the workflow or calls `POST /save-review` back; a full round trip still needs an n8n instance that can reach the site (story 2).
- **The pre-production pass:** it runs last, on the release that goes to WordPress.org (story 38), on the clean WordPress 6.6 site: that release's zip installs and activates with `WP_DEBUG` on and no notices (the check of delivery-pipeline entry 4.1), then this walk and the plans' manual checks.
