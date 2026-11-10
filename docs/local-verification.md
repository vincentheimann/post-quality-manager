# Checking the plugin on a local WordPress

_How an agent session checks the plugin on a real WordPress, headlessly, through WP-CLI on a [Local](https://localwp.com) site on the owner's PC. Written by backlog story 9 on 2026-10-26, walked the same day on the showcase site, and again on 2026-10-27 after its review. The harnesses in `tests/harness/` prove the logic against stubs; this walk proves it on WordPress itself: the build installs, options are written, each role gets the right REST answer, a trigger reaches the webhook, the cron runs, `debug.log` stays clean. Look and feel in the browser stays the owner's pass._

## Rules

- **Which site.** Routine checks run on the showcase, the Local site "Post Quality Showcase" (sample posts, a Free build). The pre-production pass runs on a clean WordPress 6.6 site the owner creates in Local when story 38 nears. Never the owner's production site or its Local copy, reads included (`memory/lessons.md` 2026-10-14, 2026-10-24).
- **Nothing site-specific in the repository.** A site is named by its Local name on the command line; its address, folder, Local id, port and database credentials are read from Local at run time and are never written into a repository file, ticket, plan or memory line. Neither are dumps, captures, post titles or user names from a site.
- **Nothing leaves the machine.** The webhook listener binds to 127.0.0.1; the plugin's payload (post content, the site's webhook token) goes to it and to no other host. One exception, the owner's to allow: a walk with [a disposable n8n](#a-disposable-n8n) sends the commands it runs (a few of the site's sample posts) to the AI provider, with the owner's key.
- **Export first, restore last.** Every walk starts with the backup below and ends with the restore, then checks the site is as it was. Every file the walk writes lives in the session's scratchpad, outside the repository, and is deleted when the walk ends (owner rule 2026-10-20: nothing an agent creates outlives the work).
- **One walk per site at a time.** A busy port 8765, `pq-smoke-*` users already on the site, or the [Add Plugins preview](#add-plugins-preview-story-70) in its mu-plugins folder (`postquality-directory-preview.php` or the `postquality-directory-preview/` folder) mean a walk is running or was left unrestored, maybe another session's: its backup would hold the other walk's half-done state, and step 11 would still print "same". Stop and ask the owner.
- **A defect found becomes its own ticket.** The walk changes no plugin code.
- **Shell.** The commands below are for the agents' shell, Git Bash on Windows, run from the repository root. Shell state, the working folder included, does not survive between tool calls: start every call with the lines of [Variables](#variables).

## One-time setup (owner)

Done for the showcase. For a new site (the pre-production site, story 38):

1. Install Local (localwp.com) on Windows.
2. Create the site in Local with PHP 8.x and MySQL as its database (not MariaDB: the entry point knows Local's MySQL only). Give it a name no other Local site carries; that name is how every command finds it. Local installs the current WordPress release; how the pre-production site gets to 6.6 (WP-CLI's downgrade, `lwp core update --version=6.6 --force`, is one way) is settled when that pass is prepared, it is not exercised yet.

The agent needs nothing else: no address, folder or password is shared. The plugin comes from a build the walk installs ([Installing a build](#installing-a-build)). Each walk needs the site started in Local, which stays the owner's step ([The walk](#the-walk)).

## The entry point: `bin/local-wp.py`

```sh
python -I bin/local-wp.py --site "Post Quality Showcase" plugin list
python -I bin/local-wp.py --site "Post Quality Showcase" import <dump.sql>
```

It finds the one site with that name in Local's site list (`%APPDATA%\Local\sites.json`) and runs the rest of the command line as WP-CLI there, with Local's PHP for the site's version and the site's `php.ini`, the `wp-cli.phar` Local bundles, the site's `app\public` folder, and Local's MySQL client first on `PATH`. Standard library only, Windows only.

- **Exit codes:** WP-CLI's own, or the MySQL client's on `import`; 2 when the name is absent from Local's list or in it twice (it prints the name sought and the count, never another site's name: Local also lists other projects' and clients' sites), when the PHP, MySQL client, MySQL port, `php.ini`, `wp-cli.phar` or site folder the site needs is missing (it prints the path), or for a refused command.
- **No imagick warning:** PHP's ImageMagick folder is put on `PATH`, so `php_imagick.dll` loads instead of printing a startup warning before every output.
- **`db export`** gets the site's MySQL port and `--protocol=tcp` added. It prints `Warning: Failed to get current character set of the posts table. Reason: ERROR 2003 … 'localhost:3306'` (a WP-CLI check that ignores the port); the dump is complete and starts with `SET NAMES utf8mb4`.
- **`db tables`, `size`, `prefix`, `columns` and `search` pass** (WP-CLI answers them through `$wpdb`). **Every other `db` subcommand is refused** (exit 2): `import`, `query`, `reset`, `cli`, `check`, `drop`… run the MySQL client without the site's port and never reach its database. A query goes through `eval` and `$wpdb`; the restore is `import <dump.sql>`.
- **`import <dump.sql>`** drops every table of the site's database, then feeds the dump to Local's MySQL client on 127.0.0.1 and the site's port, and prints `Imported <dump>: <n> tables dropped first, <m> tables now.` Dropping first matters: a build installed during the walk can rename or add tables (since story 91 the first load renames the reviews table), and a plain import would leave them beside the restored ones. It refuses (exit 2, nothing dropped) a file not named `*.sql` and one holding no `CREATE TABLE`; WP-CLI's own `import` (WXR files) is not reachable through the script.
- **Offline checks of the script and the listener:** `python -I tests/harness/local-wp-harness.py` (a fake Local tree in a temporary folder, no site needed; Windows only; the PHP harness sweep does not run it). Run it after touching either script; expected `<n> passed, 0 failed`.
- **WP-CLI writes PHP deprecations of its own** into `debug.log` on PHP 8.5 (`Using null as an array offset is deprecated … wp-cli.phar/vendor/wp-cli/php-cli-tools/lib/cli/Colors.php`), once per output line. They are not the plugin's; step 9 leaves them out.

## Installing a build

Each check installs a build, so it runs what ships: `lwp plugin install <zip> --force` replaces the plugin folder and keeps the plugin active (no activation hook runs; the plugin's first load runs its one-time upgrades).

- **Free build** (what the showcase runs):
  ```sh
  php bin/build-free.php "$S/free"
  python -I -m zipfile -c "$S/free.zip" "$S/free/post-quality-manager"
  lwp plugin install "$S/free.zip" --force
  ```
  `build-free.php` copies the tree, React build included: run `npm run build` first when `post-quality-manager/admin/build/` is missing or older than `src/`. It refuses an output folder that already holds a build: use a new one per walk. Expected: `Free build written to …`, then `Plugin updated successfully.` and `Success: Installed 1 of 1 plugins.`
- **Pro build**: `npm run zip` needs a clean tree (it refuses uncommitted or untracked changes): stash the working changes around it (`git stash push -u`, then `git stash pop` right after), or commit them when the owner has said so. It prints the path of the zip it wrote, `dist/post-quality-manager-<branch>.zip` with every character of the branch name outside `A–Z a–z 0–9 . _ -` turned into `-` (`chore/x` gives `post-quality-manager-chore-x.zip`). Install the path it prints: `lwp plugin install "<that path>" --force`.
- **Which one is installed:** `lwp eval 'echo class_exists( "PostQuality_Cron" ) ? "Pro build" : "Free build", PHP_EOL;'` (the Free build loads no cron class). `plugin list` cannot tell: every build carries the same `Version:`.
- **Quick loops only: a junction to the working tree.** For many small edits in a row, the site's plugin folder can point at `post-quality-manager/` instead: move the installed folder (`lwp plugin path post-quality-manager --dir`) into the scratchpad, then `cmd //c mklink /J "<that folder>" "<repo>\post-quality-manager"`. It runs the Pro source with whatever `admin/build/` holds, never the Free build. Remove the junction with `cmd //c rmdir "<that folder>"` (it removes the link, not the target) and move the folder back **before** any `plugin install --force`, `plugin delete` or `plugin uninstall`: those delete through the junction into the working tree. A check that counts runs on a build. (Not exercised by story 9's walk.)

## The walk

The site must be running in Local. A stopped site answers every command with "Error establishing a database connection" (and an import with the MySQL client's "Can't connect"): ask the owner to start it in Local, then go on. Starting it from the script is not built.

### Variables

```sh
cd "<repository root>"
SITE="Post Quality Showcase"; S="<session scratchpad>/local-walk"; mkdir -p "$S"
lwp() { python -I bin/local-wp.py --site "$SITE" "$@"; }
```

The repository root is the folder holding `bin/`, not the workspace above it; a session's shell, a subagent's above all, can start anywhere.

### 1. Backup

```sh
lwp plugin list > "$S/before-plugins.txt"; cat "$S/before-plugins.txt"
lwp --skip-plugins eval 'global $wpdb; $t = $wpdb->get_col( "SHOW TABLES" ); echo count( $t ), " tables: ", implode( " ", $t ), PHP_EOL; foreach ( $t as $n ) { if ( preg_match( "/reviews$/", $n ) ) { echo $n, ": ", $wpdb->get_var( "SELECT COUNT(*) FROM $n" ), " rows", PHP_EOL; } } echo "users: ", count_users()["total_users"], PHP_EOL; echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? "debug.log exists" : "no debug.log", PHP_EOL;' > "$S/before-state.txt"; cat "$S/before-state.txt"
lwp --skip-plugins eval '$d = WP_PLUGIN_DIR . "/post-quality-manager"; $l = []; foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $d, FilesystemIterator::SKIP_DOTS ) ) as $f ) { $l[] = substr( $f, strlen( $d ) + 1 ) . " " . md5_file( $f ); } sort( $l ); $l[] = "wp-config.php " . md5_file( WP_CLI\Utils\locate_wp_config() ); echo implode( "\n", $l ), "\n";' > "$S/before-files.txt"; wc -l < "$S/before-files.txt"
lwp db export "$S/before.sql"
python -I -m zipfile -c "$S/plugin-before.zip" "$(lwp plugin path post-quality-manager --dir)"
cp "$(lwp config path)" "$S/wp-config-before.php"
```

Expected: the plugin list with `post-quality-manager	active`; the table count and names, the reviews table with its row count (`wp_pqm_reviews` on a build older than story 91, `wp_postquality_reviews` after), the user count and `no debug.log`; the line count of the checksum list (one line per file of the plugin folder, then `wp-config.php`); `Success: Exported to '…/before.sql'.` after the character-set warning above; no output from the zip and the copy. If a `debug.log` exists, move it into `$S` now and put it back at the end. The checksum list is what tells the restored plugin from the walk's build: every build carries the same `Version:`, so `plugin list` reads the same with the Pro build left installed.

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

Run it in the background (an agent: the shell tool's background mode; stop it by its task id at the end; a person: a second terminal, Ctrl+C). Keep port 8765: a walk on another port would not see one already running. Expected: `Capturing POSTs on http://127.0.0.1:8765/ into …/capture.jsonl (Ctrl+C stops).` It answers every POST with 200 and `{}`, appends one JSON line per request (`time`, `path`, `headers`, `body`) and prints each request on stderr. A port another socket already listens on gives exit 2 and `Cannot listen on 127.0.0.1:<port>`: a walk is running or was left unrestored, maybe another session's on this site; stop and ask the owner ([Rules](#rules), one walk per site at a time).

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
lwp --skip-plugins eval 'global $wpdb; $t = $wpdb->get_col( "SHOW TABLES" ); echo count( $t ), " tables: ", implode( " ", $t ), PHP_EOL; foreach ( $t as $n ) { if ( preg_match( "/reviews$/", $n ) ) { echo $n, ": ", $wpdb->get_var( "SELECT COUNT(*) FROM $n" ), " rows", PHP_EOL; } } echo "users: ", count_users()["total_users"], PHP_EOL; echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? "debug.log exists" : "no debug.log", PHP_EOL;' | diff "$S/before-state.txt" - && echo "tables, reviews, users, log: same"
lwp --skip-plugins eval '$d = WP_PLUGIN_DIR . "/post-quality-manager"; $l = []; foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $d, FilesystemIterator::SKIP_DOTS ) ) as $f ) { $l[] = substr( $f, strlen( $d ) + 1 ) . " " . md5_file( $f ); } sort( $l ); $l[] = "wp-config.php " . md5_file( WP_CLI\Utils\locate_wp_config() ); echo implode( "\n", $l ), "\n";' | diff "$S/before-files.txt" - && echo "plugin files, wp-config.php: same"
```

Expected: `plugin list: same`, `tables, reviews, users, log: same` and `plugin files, wp-config.php: same`. A difference prints the lines that differ instead (`diff`'s `<` before, `>` now): restore again, or report it.

What the walk created, and what removes it:

| Created | Where | Removed by |
|---|---|---|
| the build's files | the site's plugin folder | step 10's plugin install |
| the build's upgrades (renamed options, table, capabilities), the webhook URL, the four `pq-smoke-*` users, the `processing` rows, the cron event, WP-Cron's own runs | the site's database | step 10's import |
| `WP_DEBUG` true, `WP_DEBUG_LOG` | `wp-config.php` | step 10's copy |
| `wp-content/debug.log` | the site | step 10's delete |
| the listener | a process of this session | stopping it (step 10) |
| dumps, plugin and `wp-config.php` copies, build folders and zips, the capture (it holds post content and the site's webhook token) | `$S`, in the session's scratchpad | deleting `$S` once step 11 passed |
| the zip `npm run zip` printed (Pro build), `dist/post-quality-manager-<branch with / turned into ->.zip` | the repository, git-ignored | deleting it, or leaving it for the next `npm run zip` of that branch, which replaces it |

## Other checks on the same site

- **Plugin Check:** `(cd "$S" && python -I "$OLDPWD/bin/local-wp.py" --site "$SITE" plugin check post-quality-manager)` (Plugin Check is installed on the showcase). Run it from a folder that holds no `post-quality-manager/` folder, such as `$S`: from the repository root, Plugin Check takes the name as that folder and checks the repository's source instead of the installed build (`memory/local-verification.md`, 2026-11-09). Its `FILE:` lines name the site's plugin folder when it checked the build.
- **Deleting the plugin:** the Freemius SDK registers the plugin's uninstall callback only when an administrator deactivates it and runs it only for a user who can `activate_plugins`, so a WP-CLI walk of deletion passes `--user=<an administrator>` on both `lwp plugin deactivate` and `lwp plugin uninstall`; without it the files go and every option, user meta, capability and the reviews table stay (`memory/freemium-and-roles.md`, real licensing). The restore brings the plugin back.
- **A plan's manual checks:** the steps a story plan's Verification section lists run in the same frame: backup, build, the checks through `lwp` (options, `eval` with `rest_do_request()`, the capture), restore.

### Add Plugins preview (story 70)

_Shows the plugin's card on Plugins → Add New Plugin and its More Details view as the WordPress.org directory would build them, before the plugin is listed. `bin/directory-preview.php`, a development-only must-use plugin, answers WordPress's `plugins_api` for a search for the plugin and for its slug's details with the record the directory's API sends for a new listing, read from the installed build's readme and header and from the directory images in `assets/`; every other request keeps its own answer. The readme goes through Plugin Check's copy of the directory's readme parser, with the directory's own Markdown, which the walk fetches into `$S` and which never enters the repository. Written by story 70; story 60 reruns it for the Description rewrite._

The frame is the walk: [Variables](#variables), Preview 0, walk step 1, walk step 2 with the Free build (the directory hosts it), Preview 1 to 4, then walk steps 9 to 11. Walk steps 3 to 8 are skipped. Plugin Check must be active on the site (it is on the showcase): the preview uses its parser.

#### Preview 0. Check the site is free, before walk step 1

```sh
lwp plugin is-active plugin-check && echo "Plugin Check: active" || { echo "STOP: Plugin Check is not active"; exit 1; }
M="$(lwp --skip-plugins eval 'echo WPMU_PLUGIN_DIR;')"; [ -n "$M" ] || { echo "STOP: no mu-plugins path"; exit 1; }
[ -e "$M/postquality-directory-preview" ] || [ -e "$M/postquality-directory-preview.php" ] && { echo "STOP: a preview is already in mu-plugins"; exit 1; }
echo "no preview in mu-plugins"
```

Expected: `Plugin Check: active`, then `no preview in mu-plugins`. The block only reads the site. A `STOP` line ends it with exit 1 before anything on the site changes, and nothing is restored: Plugin Check inactive (ask the owner), or a preview's file or folder already in mu-plugins, which means a walk is running or was left unrestored, maybe another session's ([Rules](#rules)): the site is that walk's to restore, never write over the preview or delete it; stop and ask the owner.

#### Preview 1. Fetch the Markdown

```sh
C=d0621733b16ef8300d506edc4390bef01e34ba8e
B="https://raw.githubusercontent.com/WordPress/wordpress.org/$C/wordpress.org/public_html/wp-content/plugins/plugin-directory"
L=libs/michelf-php-markdown-1.6.0/Michelf
mkdir -p "$S/md/$L"
curl -fsSo "$S/md/class-markdown.php" "$B/class-markdown.php"
for f in MarkdownExtra.inc.php MarkdownInterface.php Markdown.php MarkdownExtra.php; do curl -fsSo "$S/md/$L/$f" "$B/$L/$f"; done
[ "$(find "$S/md" -type f -size +0 | wc -l)" -eq 5 ] && echo "Markdown: 5 files" || { echo "STOP: the Markdown download is incomplete"; exit 1; }
```

`C` pins one commit of [WordPress/wordpress.org](https://github.com/WordPress/wordpress.org) (trunk on 2026-11-09). `class-markdown.php` is the directory's Markdown class (GPLv2); it includes the Michelf MarkdownExtra 1.6.0 library (BSD-3-Clause) from `libs/` beside itself, so `$S/md` keeps that layout, and the four library files are the ones `MarkdownExtra.inc.php` loads. Expected: `Markdown: 5 files`. A `STOP` line ends the block with exit 1: a download failed (the count is of files that are not empty); delete `$S/md` and run the block again. A later commit may serve a later walk: check first that the five paths still exist there.

#### Preview 2. Copy it into the site

```sh
M="$(lwp --skip-plugins eval 'echo WPMU_PLUGIN_DIR;')"; [ -n "$M" ] || { echo "STOP: no mu-plugins path"; exit 1; }
lwp --skip-plugins eval 'echo is_dir( WPMU_PLUGIN_DIR ) ? implode( " ", array_diff( scandir( WPMU_PLUGIN_DIR ), [ ".", ".." ] ) ) : "no mu-plugins folder", PHP_EOL;' > "$S/before-mu.txt"; cat "$S/before-mu.txt"
[ -d "$M" ] || { mkdir "$M" && touch "$S/mu-created"; }
mkdir "$M/postquality-directory-preview" && touch "$S/preview-created" || { echo "STOP: a preview is already in mu-plugins"; exit 1; }
cp bin/directory-preview.php "$M/postquality-directory-preview.php"
cp assets/icon.svg assets/icon-128x128.png assets/icon-256x256.png assets/banner-772x250.png assets/banner-1544x500.png assets/screenshot-1.png assets/screenshot-2.png assets/screenshot-3.png "$M/postquality-directory-preview/"
cp -r "$S/md/." "$M/postquality-directory-preview/"
lwp plugin list --status=must-use --fields=name,status
lwp eval 'require_once ABSPATH . "wp-admin/includes/plugin-install.php"; $q = plugins_api( "query_plugins", [ "search" => "post%20quality" ] ); $p = $q->plugins[0]; $d = plugins_api( "plugin_information", [ "slug" => "post-quality-manager" ] ); echo count( $q->plugins ), " card: ", $p["name"], " ", $p["version"], ", tested ", $p["tested"], PHP_EOL, "tabs: ", implode( " ", array_keys( $d->sections ) ), ", ", count( $d->screenshots ), " screenshots", PHP_EOL;'
```

Expected: the mu-plugins folder's entries before the copy (`no mu-plugins folder` on the showcase, so the walk creates it and `$S/mu-created` records that); no output from the copies; `postquality-directory-preview	must-use`; then `1 card: Post Quality Manager <version>, tested <the site's version>` and `tabs: description installation faq changelog screenshots, 3 screenshots`. The `mkdir` stop repeats Preview 0's check for a preview that appeared since: it ends the block with exit 1 before anything is copied; never write over that preview or delete it, ask the owner. `$S/preview-created` records that this walk made the preview; Preview 4 removes nothing without it. The eight images are the directory files `assets/` holds for WordPress.org (`docs/brand.md` § Imagery); the card shows `icon.svg`, as the directory does when a plugin has one.

#### Preview 3. Open the screens

The owner opens them in a browser logged into the site (Local's WP Admin button): Plugins → Add New Plugin, search `Post Quality Manager`, then **More Details** on the card. Directly: `<site>/wp-admin/plugin-install.php?s=Post%20Quality%20Manager&tab=search&type=term`, and `<site>/wp-admin/plugin-install.php?tab=plugin-information&plugin=post-quality-manager` for the dialog's content alone. An agent reads the same pages with Playwright (`node_modules/playwright-core` driving Edge, a session cookie minted by WP-CLI for user 1, `TEMP` and `TMP` at the scratchpad: `memory/local-verification.md`), typing the search into the search field once as well, since the screen's live search sends the term percent-encoded. Captures stay in `$S`.

What a new listing shows, and what the preview does too:

- **The card:** the icon, the name, the readme's short description (the tagline), "By postquality" linked to that WordPress.org profile (the directory's author is the submitting account, the readme's Contributors account in `docs/distribution.md`, shown by its profile's display name, the username until one is set; the plugin header's Author and Author URI feed neither the card nor the sidebar), empty stars with `(0)`, "Last Updated: <n> seconds ago" (the preview's time is the request's), "Less Than 10 Active Installations", "Compatible with your version of WordPress", and the button "Active" (the plugin is installed and active on the site; "Install Now" elsewhere).
- **More Details:** the banner, then the tabs Description, Installation, FAQ, Changelog and Screenshots: the parser puts a section it does not expect (External services) at the end of Description under its own heading, and WordPress hides Reviews while a plugin has no rating. Screenshots holds the three images with the readme's captions. The sidebar reads Version (the installed build's), Author (`postquality`, as on the card), Last Updated, Requires WordPress Version, Compatible up to (the site's version), Requires PHP Version, Active Installations "Less Than 10", Plugin Homepage, and Contributors `postquality` linked to its WordPress.org profile.
- **Not like the listing:** WordPress shows no "WordPress.org Plugin Page" link for an answer a filter gave. The directory also runs each section through its own `the_content` (curly quotes among others); the preview leaves the site's content filters out. The contributor's avatar is the site's default avatar; the listing shows the profile's Gravatar (`d=monsterid` when the profile has none).

#### Preview 4. Remove it

Before the restore (walk step 10), with the browser closed:

```sh
[ -f "$S/preview-created" ] || { echo "STOP: this walk did not make the preview"; exit 1; }
M="$(lwp --skip-plugins eval 'echo WPMU_PLUGIN_DIR;')"; [ -n "$M" ] || { echo "STOP: no mu-plugins path"; exit 1; }
rm -r "${M:?}/postquality-directory-preview" "${M:?}/postquality-directory-preview.php" && echo "preview: removed"
[ -f "$S/mu-created" ] && rmdir "${M:?}" && echo "mu-plugins folder: removed"
```

A `STOP` line ends the block with exit 1, nothing removed: without `$S/preview-created` the preview in mu-plugins is not this walk's (Preview 2 stopped, or another session made it); ask the owner.

Then walk step 9 (`debug.log`), step 10 and step 11, and after step 11's three lines:

```sh
lwp --skip-plugins eval 'echo is_dir( WPMU_PLUGIN_DIR ) ? implode( " ", array_diff( scandir( WPMU_PLUGIN_DIR ), [ ".", ".." ] ) ) : "no mu-plugins folder", PHP_EOL;' | diff "$S/before-mu.txt" - && echo "mu-plugins: same, the preview is gone"
```

Expected: `preview: removed`, then `mu-plugins folder: removed` when the walk created it (`rmdir` leaves a folder that is not empty); in step 9 `no debug.log`, or only WP-CLI's own lines (a line `Post Quality directory preview: missing …` names what the preview lacked, and the screens then showed the directory's real answer); step 11's three "same"; `mu-plugins: same, the preview is gone`. Step 10 can print `debug.log deleted` after step 9's `no debug.log`: its `plugin install` and `import` run while the log is still on, with the preview already gone. Step 11's plugin list would show the preview too while its file stays (`must-use`), but not its folder; this last line covers both. The session cookie an agent minted is a session in the database, gone with step 10's import; `$S/md`, the captures and the cookie file go with `$S`.

## A disposable n8n

_Written by chore 105 on 2026-11-04, to prove the workflow template on a new n8n release (ADR 0017's n8n rule, the `/lcm` check) without touching the owner's n8n instance. Installs one n8n release from npm into the session's scratchpad, runs it on this PC beside the Local site, and deletes it after. No Docker, no WSL, and no script in the repository installs it: these steps are the recipe._

- **Which release:** the version the ticket names; by default npm's `latest` that day (`npm view n8n dist-tags`). A `beta` or `next` release waits until it is `latest`.
- **What it holds:** its owner account, its API key, and everything the plugin provisions (the credentials, the owner's provider key inside one of them, the workflow) live in its SQLite database under `$N`, and go when `$N` is deleted. Nothing of it is written into a repository file, ticket, plan or memory line.
- **The site side** is the walk above: backup first (walk step 1), the build installed (walk step 2), restore and check last (walk steps 10 and 11). Skip walk steps 3, 4 and 7: the disposable n8n replaces the listener (step 3), runs the workflow and calls the site back; step 4 would write the listener's address into `n8n_webhook_url` over the URL provisioning saved; step 7 reads the listener's capture, and a command now runs from the plugin and lands through n8n's callback.
- **The provider key** (owner, 2026-11-10): the agent provisions with the owner's Anthropic key, read from its file (`../.secrets/anthropic-key.txt`, beside the repository) by the `wp eval-file` script of step n8n 6. The key is never printed, and nothing the script prints holds `status_html` (the n8n Workflow page's markup, the webhook token in it) or `webhook_url`. The owner's alternative: they type the key into the plugin's setup dialog themselves, and the agent never sees, writes or passes it.

### n8n variables

```sh
cd "<repository root>"
V=<version>; N="$(cygpath -u "<session scratchpad>")/n8n-$V"; mkdir -p "$N"
PATH="$N/node:$PATH"
```

`$N/node` goes first on `PATH`: n8n starts its Code-node task runner as `node` from `PATH`, so the walk's Node.js must be found before the one installed on the PC.

### n8n 1. Node.js 24, portable

n8n 2.46 needs Node.js 24 or later (`engines` in its `package.json`). A portable zip leaves the Node.js installed on the PC alone:

```sh
curl -fso "$N/SHASUMS256.txt" https://nodejs.org/dist/latest-v24.x/SHASUMS256.txt
Z=$(grep -o 'node-v24\.[0-9.]*-win-x64\.zip' "$N/SHASUMS256.txt")
curl -fso "$N/$Z" "https://nodejs.org/dist/latest-v24.x/$Z"
(cd "$N" && grep " $Z\$" SHASUMS256.txt | sha256sum -c)
python -I -m zipfile -e "$N/$Z" "$N" && mv "$N/${Z%.zip}" "$N/node"
node --version
```

Expected: `<zip>: OK`, then `v24.<…>`.

### n8n 2. Install n8n

```sh
npm install "n8n@$V" --prefix "$N/app" --cache "$N/npm-cache" --force --no-audit --no-fund
node "$N/app/node_modules/n8n/bin/n8n" --version
```

`--cache` keeps npm's download cache in `$N` too, instead of `%LocalAppData%\npm-cache`, so n8n 7 deletes it. `--force` is needed since n8n 2.46.0: `n8n-nodes-base` declares musl-only native bindings (`@napi-rs/canvas-linux-*-musl`, `@napi-rs/image-linux-*-musl`) as hard dependencies, and npm refuses them with `EBADPLATFORM` on any host that is not Alpine Linux, Windows included ([n8n#43006](https://github.com/n8n-io/n8n/issues/43006), open on 2026-11-04). The install takes several minutes. Expected: `added <n> packages`, then `$V`.

### n8n 3. Configure it

n8n reads `.env` from the folder it starts in:

```sh
cat > "$N/.env" <<EOF
N8N_USER_FOLDER=$(cygpath -m "$N/data")
N8N_LISTEN_ADDRESS=127.0.0.1
N8N_PORT=5688
N8N_RUNNERS_BROKER_PORT=5689
N8N_SECURE_COOKIE=false
N8N_DIAGNOSTICS_ENABLED=false
N8N_VERSION_NOTIFICATIONS_ENABLED=false
N8N_TEMPLATES_ENABLED=false
EOF
echo "require('fs').writeFileSync(__dirname + '/n8n.pid', String(process.pid));" > "$N/pid.js"
```

- `N8N_USER_FOLDER` keeps the database, the encryption key and the logs in `$N` (written as `C:/…`: Node.js on Windows does not read Git Bash's `/c/…` form inside a file).
- `N8N_LISTEN_ADDRESS=127.0.0.1`: n8n listens on every interface by default (`::`, seen as `0.0.0.0:5688`); this keeps it on the PC. Its address is therefore `http://127.0.0.1:5688` everywhere below, never `localhost`, which can resolve to `::1`.
- No webhook base is set: the plugin builds the webhook URL from the address it is given (`/webhook/` and the per-site path). If one is ever needed, it is `N8N_WEBHOOK_URL=http://127.0.0.1:5688/` (2.46.4 logs `WEBHOOK_URL` as deprecated).
- Ports 5688 and 5689 stay clear of an n8n on the defaults (5678, broker 5679). Keep them: a walk on other ports would not see one already running.
- `N8N_SECURE_COOKIE=false` lets `curl` keep the login cookie over `http://127.0.0.1` (n8n 5).
- Diagnostics, version notifications and the template library are off: nothing leaves the machine but the workflow's own calls.
- `pid.js` writes the Windows PID of n8n's own `node` process, the one n8n 7 stops: `$!` in Git Bash is the shell's own process number, and stopping it can leave the Windows process running.

### n8n 4. Start it

```sh
netstat -ano | grep -E ':(5688|5689) .*LISTENING' && { echo "STOP: a port is taken"; exit 1; }
cd "$N" && node --require ./pid.js app/node_modules/n8n/bin/n8n start > "$N/n8n.log" 2>&1
```

Run the block in the background (an agent: the shell tool's background mode). A taken port ends it with exit 1 before n8n starts: another walk is running, maybe another session's; stop and ask the owner. Then wait until `curl -s http://127.0.0.1:5688/healthz` answers `{"status":"ok"}` (the first start runs the database migrations first), then until `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:5688/rest/settings` answers `200`: `/healthz` passes before the editor's routes are mounted (2.46.6, 2026-11-04: `/healthz` answered while `POST /rest/owner/setup` was still 404), and step 5 needs them; then until `grep -q 'Editor is now accessible' "$N/n8n.log"` succeeds (2.46.6, 2026-11-06: `/rest/settings` answered 200 before start-up ended, and its next three bodies were not JSON). Then read `cat "$N/n8n.pid"`. Expected: `$N/n8n.log` names the editor's address and `n8n.pid` holds one number.

### n8n 5. Owner account and API key

```sh
B=http://127.0.0.1:5688; J="$N/cookies.txt"
python -I -c "import secrets; print('Pq1' + secrets.token_urlsafe(18))" > "$N/owner-password.txt"
PW=$(cat "$N/owner-password.txt")
pending() { curl -s "$B/rest/settings" | python -I -c "import json, sys; print(json.load(sys.stdin)['data']['userManagement']['showSetupOnFirstLoad'])"; }
for i in 1 2 3 4 5 6 7 8 9 10; do
  [ "$(pending)" = False ] && break
  curl -s -o /dev/null -c "$J" -H 'Content-Type: application/json' -d "{\"email\":\"walk@example.com\",\"firstName\":\"Walk\",\"lastName\":\"Owner\",\"password\":\"$PW\"}" "$B/rest/owner/setup"
  sleep 3
done
[ "$(pending)" = False ] || { echo "STOP: n8n still asks for its owner"; exit 1; }
grep -q n8n-auth "$J" 2>/dev/null || curl -s -o /dev/null -c "$J" -H 'Content-Type: application/json' -d "{\"emailOrLdapLoginId\":\"walk@example.com\",\"password\":\"$PW\"}" "$B/rest/login"
grep -q n8n-auth "$J" && echo "owner: set up, logged in" || { echo "STOP: no login cookie"; exit 1; }
SCOPES=$(curl -sf -b "$J" "$B/rest/api-keys/scopes" | python -I -c "import json, sys; print(json.dumps(json.load(sys.stdin)['data']))")
curl -sf -b "$J" -H 'Content-Type: application/json' -d "{\"label\":\"post-quality-walk\",\"scopes\":$SCOPES,\"expiresAt\":null}" "$B/rest/api-keys" | python -I -c "import json, sys; print(json.load(sys.stdin)['data']['rawApiKey'])" > "$N/api-key.txt"
wc -c < "$N/api-key.txt"
```

`POST /rest/owner/setup` is the editor's first-run form (it needs a password of 8 to 64 characters with a digit and a capital) and logs the owner in. Its answer proves nothing: on 2.46.6 (2026-11-04) the first call, right after `/rest/settings` turned 200, answered 2xx yet created no owner and set no cookie, and the key step failed after it. The proof is `showSetupOnFirstLoad` in `GET /rest/settings`, which turns `False` once the owner exists, so the block sends the form until it does (10 tries, 3 s apart). When the cookie jar then lacks n8n's `n8n-auth` cookie (a try that created the owner but whose answer was lost), it logs in through `POST /rest/login` (`emailOrLdapLoginId` and `password`, n8n 2.46.6's `LoginRequestDto`). A `STOP` line ends the block with exit 1: read `$N/n8n.log`, then ask the owner. `GET /rest/api-keys/scopes` lists every scope the owner's role may give a key, and `POST /rest/api-keys` makes a key with all of them, no expiry (it dies with `$N`). Expected: `owner: set up, logged in`, then the key's length. The owner reaches the editor at `http://127.0.0.1:5688` with `walk@example.com` and the password in `$N/owner-password.txt`.

### n8n 6. Connect the site

The agent connects the site with a PHP script in `$N` (it holds no key, and goes with the folder), run as an administrator: `lwp --user=<an administrator> eval-file "$N/connect.php"`. The script reads the n8n key from `$N/api-key.txt` and the provider key from its file (the provider-key bullet above), then sends two requests through `rest_do_request()`, the form of walk step 6: `POST /post-quality/v1/n8n/connection` with `base_url` `http://127.0.0.1:5688` (the plugin takes `http://` on localhost and 127.0.0.1 only) and `api_key`, then, when that answers 200 with a passing probe, `POST /post-quality/v1/n8n/provision` with `anthropic_key`. For each it prints the status and the reply with `status_html` and `webhook_url` removed; it never prints a key, and it stops at the first refusal. The plugin then provisions the workflow; the Local site and n8n run on the same PC, so n8n can call the `.local` site back, which an n8n elsewhere cannot. Expected: `200` for both; the provision reply's `ping_ok` and `message` say whether the test ping after the setup passed.

The owner's alternative (the provider-key bullet): they open the plugin's setup dialog on the Local site, enter the n8n address `http://127.0.0.1:5688` and the key from `$N/api-key.txt`, which the agent hands over, then type their provider key.

### n8n 7. Stop it and delete it

After the walk's restore (walk step 10):

```sh
P=$(cat "$N/n8n.pid")
tasklist //FI "PID eq $P" //NH | grep -q '^node\.exe ' && netstat -ano | tr -d '\r' | grep -qE ":5688 .*LISTENING +$P\$" || { echo "STOP: PID $P is not n8n's node.exe listening on 5688"; exit 1; }
taskkill //PID "$P" //T //F
netstat -ano | grep -E ':(5688|5689) .*LISTENING' || echo "ports 5688 and 5689: free"
cd "<repository root>" && rm -rf "$N" && echo "n8n folder: deleted"
```

The first two lines prove the PID file names n8n's own process before anything is stopped: `node.exe` in `tasklist`, and that PID listening on 5688. A `STOP` line ends the block with exit 1, nothing stopped: report it and ask the owner. `//T` also stops the task runner n8n started. Expected: `SUCCESS: The process with PID <pid> (child process of PID <n8n's>) has been terminated.` lines, `ports 5688 and 5689: free`, `n8n folder: deleted`. A port still taken after the stop is a process this walk cannot prove it started: report it and ask the owner.

## Not covered

- **The browser:** layout, loading and error states, look and feel stay the owner's pass in WP Admin.
- **The n8n round trip:** the listener answers like n8n's webhook but never runs the workflow or calls `POST /save-review` back; a full round trip needs an n8n that can reach the site: [A disposable n8n](#a-disposable-n8n) on the same PC (chore 105), or the owner's instance and a site it can reach (story 2).
- **The pre-production pass:** it runs last, on the release that goes to WordPress.org (story 38), on the clean WordPress 6.6 site: that release's zip installs and activates with `WP_DEBUG` on and no notices (the check of delivery-pipeline entry 4.1), then this walk and the plans' manual checks.
