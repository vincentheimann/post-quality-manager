# Agent Lessons Log

_Updated by the agent after any user correction. Reviewed at the start of each session and loaded into every `bmad-build` run as a persistent fact (`_bmad/custom/bmad-build.toml`). Lived in `tasks/` until 2026-10-01 (ADR 0004)._

---

<!-- The agent appends entries here as the project progresses. Format:

## [YYYY-MM-DD] — Short description of the mistake
**What happened:** ...
**Root cause:** ...
**Rule going forward:** ...

-->

## [2026-10-13] — Deferring a file without checking who else needs it
**What happened:** Story 54's plan left `readme.txt` to story 38 (the WordPress.org listing) and kept CI's `no_plugin_readme` ignore. At the story's HITL step Freemius refused the release zip: its deployment needs `readme.txt` on every upload. The owner's test stopped until a fix and a new release.
**Root cause:** The deferral was decided from the directory's point of view only; the plan never read what the other consumer of the same zip (Freemius) requires from it.
**Rule going forward:** Before deferring an artifact to a later story, list every system that consumes the deliverable it belongs to (directory, Freemius, CI, release workflow) and check each one's requirements in its docs; a requirement of any of them makes the artifact part of the current story.

## [2026-10-12] — Building on a plan's claim about code nobody had read
**What happened:** Story 54's plan (decision D1) stated that the React pages take their command list "from the server", so the Free build would render no paid button. The build ran four slices on that premise. Code review then read `src/kit/copy.js`: `COMMAND_ORDER` hard-codes all six commands, so the Free build would have shown Sources, SEO, Audit and Rewrite buttons that answer 400. The same plan also missed a memory line assigning Freemius's dual-activation pattern to this story.
**Root cause:** The plan's Code Map named the files to change and traced those; the claim about *other* code (the JS it chose not to touch) was asserted, never grepped. Reading memory for the story's own facts stopped at the ticket file.
**Rule going forward:** A plan statement of the form "X already does Y, so this change need not touch it" is a claim to verify with a grep or a read before the checkpoint, and again before building on it; it is listed under the plan's unverified assumptions until then. Before planning a story, grep `memory/` for the story's number and read every line that names it.

## [2026-10-12] — Assuming where the owner tested
**What happened:** After story 55 the owner said "no bugs found"; the agent called it "your Local pass" and offered to record it as such in the plan, memory and user paths. The owner had tested online on production (risk assumed), not on Local.
**Root cause:** Taking the plan's "Manual checks — On Local" line as the place the test happened, instead of what the owner said.
**Rule going forward:** Record a manual test with the environment the owner names. When they don't name it, ask before writing it into a plan, memory or `docs/user-paths.md`.

## [2026-10-08] — Reporting CI green from workflow runs instead of the PR's checks
**What happened:** Story 4.4 made Plugin Check non-blocking with `continue-on-error: true` on the job. The agent read `gh run list`, saw every workflow run conclude `success`, and told the owner PR #77 was all green. The owner saw both Plugin Check checks failing on the PR.
**Root cause:** A job-level `continue-on-error` only stops the workflow run from failing; the job's check run still concludes `failure`, and that is what the PR shows. The agent checked a different layer from the one the owner looks at.
**Rule going forward:** Report a PR's CI state from `gh pr checks <n> --repo …` (the check runs the owner sees), never from workflow-run conclusions alone. To make a CI finding non-blocking, put `continue-on-error` on the step that reports it, not on the job.

## [2026-10-08] — Proposing a repo-settings change before verifying it
**What happened:** To stop release-please counting each merged PR twice, the agent recommended a GitHub setting ("Default message") that was already the cause. It then asked to PATCH the repo's merge-commit settings to a combination the UI does not offer and the API docs neither allow nor forbid, without having read how release-please parses commits. The owner asked whether it had thought the command through. Reading release-please's source confirmed the mechanism, and showed that an option already enabled (rebase merging) solved the problem without any experiment.
**Root cause:** Answering from a remembered mental model of GitHub and release-please instead of checking the current setting and the tool's source. Also narrowing the options to the two first framed (squash or merge commit) instead of listing every merge method the repo already allows.
**Rule going forward:** Before proposing a change to an external system's settings (GitHub repo, Actions, n8n, hosting), read the current value through its API, confirm the mechanism in the tool's docs or source, and list every option that is already available. Never ask to apply a setting combination the documentation does not state as valid; say it is unverified and offer the documented alternative first.

## [2026-10-07] — Treating presets as text fillers instead of as the setting
**What happened:** Story 26 built the AI Prompts & Context presets twice as mechanics around an always-visible textarea — first "fill, then Undo", then "fill when empty, otherwise offer Replace / Keep". The owner rejected both: a user picking a profile should not have to reason about text boxes, drafts or undo. The model they want is progressive disclosure — the radio *is* the setting, a selected preset shows its text read-only and muted, and the textarea appears only under Custom.
**Root cause:** Optimizing the overwrite problem inside the existing control layout instead of asking which control layout removes the problem. Also over-reading the 2026-07-23 rule ("every preset stays editable") as "presets must fill an editable field".
**Rule going forward:** For preset-driven settings here: a preset is a choice, not a fill — resolved from code when used, shown read-only when selected; **Custom is the one editable path and stays visible**, starting from the stored custom text, never pre-filled. The 2026-07-23 rule is replaced by this one; its spirit — never remove the Custom escape hatch — stays. When a UX fix needs an undo, an offer or a confirmation, first ask whether a different control removes the action that needs them.

## [2026-10-07] — Naming a feature after the requester's context instead of the persona
**What happened:** Story 26 renamed the site-wide voice field "Corporate Standards" because the owner described the need as a corporate company. The owner corrected it: the plugin also serves solo bloggers and small teams (startups), for whom "Corporate" says the field is not for them. Renamed to "Editorial Standards" before commit.
**Root cause:** Taking the framing of one request ("as a corporate company…") as the product's audience, without checking the persona in `docs/user-paths.md` (solo professional blogger, any niche).
**Rule going forward:** Name features, labels and copy for the whole persona in `docs/user-paths.md`, never for the context of the person asking. When a request names an audience narrower than the persona, check whether the name and copy still read right for a solo blogger before writing them.

## [2026-10-05] — Recommending `window.confirm()` over a WordPress `Modal`
**What happened:** The story 5.5 plan offered the bulk confirmation as native `window.confirm()` or a `@wordpress/components` `Modal`, and recommended the native one because the minimal-code ladder's rung 4 lists the browser next to WordPress core. The owner chose `Modal` and said they prefer WordPress components by default.
**Root cause:** Reading rung 4 as "the browser is the cheaper of two equals". The React pages exist to render from one WordPress component source (ADR 0005), so a browser-native dialog is an off-system exception there, not the minimal option.
**Rule going forward:** In the React admin pages, dialogs, confirmations and similar UI use `@wordpress/components` by default. Offer a browser native only with a named reason, and ask first. The durable fact lives in `memory/admin-ui-rebuild.md`.

## [2026-10-05] — `intval` as a REST sanitize_callback fatals every request using the arg
**What happened:** A story 5.3 review patch swapped `absint` for `'intval'` on the `min_score`/`max_score` args of `GET /posts`. WP calls every `sanitize_callback` as `(value, request, param)`; PHP 8 internals with fixed arity throw `ArgumentCountError` on the third argument, so any score-band request returned 500 ("Could not load your posts"). The owner found it on the real site; the harnesses were green because they call route callbacks directly, bypassing WP's arg sanitizing.
**Root cause:** Treating a PHP internal like a WP userland helper (WP functions such as `absint`/`sanitize_key` silently ignore extra args; internals do not), and trusting harness coverage for a code path the harness never exercises.
**Rule going forward:** Never put a PHP internal (`intval`, `trim`, `strtolower`…) in a REST `sanitize_callback` — use a WP helper, a closure, or omit it and let the `type` schema sanitize. `rest-posts-harness.php` now calls every registered arg's `sanitize_callback` with WP's three arguments; keep stubs at WP's real arity so that check stays honest.

## [2026-07-24] — Dismissing ADR/skill as vehicles because the practice "didn't exist yet"
**What happened:** When placing the user-paths doc, the agent reasoned "the repo has no ADR practice, skills are workflows" and wired references only into AGENTS.md + memory. The user corrected: ADRs, skills, and living memory are practices that *should* be applied in this repo — the absence of a practice was the gap to fix, not a constraint to design around.
**Root cause:** Treating the current state of the workspace as fixed. The agentic workspace is itself a product the user actively invests in; "we don't do X here" is an invitation to propose adopting X, not a reason to route around it.
**Rule going forward:** When a decision reveals a missing practice (decision records, telemetry, a recurring workflow without a skill), surface it as an adoption proposal instead of silently choosing the existing structure. For knowledge placement use the triad: ADR = why, memory = what is true now, skill = recurring how.

## [2026-07-23] — Removing a user-facing "Custom" option during a UX redesign
**What happened:** A settings-UX plan proposed replacing the Blog Profile radios (which include "Custom") with preset-only one-shot buttons, dropping the explicit Custom option. The user rejected it: presets are useful, but the custom possibility is *more* important than the presets.
**Root cause:** Optimizing for control-model purity (action vs. state) over the user's actual priority — full ownership of the AI prompt inputs. Presets are onboarding aids; custom input is the product.
**Rule going forward:** In any preset-driven UI here, every preset must remain fully editable AND an explicit "Custom" path must stay visible. Never remove an escape hatch to simplify a control; simplify around it. *(Replaced 2026-10-07, see the entry above: presets are read-only choices, Custom is the one editable path — the escape hatch stays.)*

## [2026-07-23] — confirm() dialog on a pre-fill that only takes effect on Save
**What happened:** The profile/voice preset pre-fill asked "Apply this profile?" via confirm() before overwriting the textareas. The user flagged it as too intrusive: the settings form is not persisted until Save, so the overwrite is reversible by simply not saving.
**Root cause:** Treating an in-form field fill like a destructive action. Confirmation dialogs are for irreversible operations; a pre-fill inside an unsaved form is not one.
**Rule going forward:** Never gate in-form, not-yet-persisted changes behind confirm(). Reserve confirmation for actions with immediate side effects (AJAX writes, deletions, resets that persist). The Save button is the real commit point.

## [2026-03-27] — Hardcoding niche dimensions in AI Prompts
**What happened:** We initially hardcoded "Neuroscience" and "Marketing" as scoring dimensions in the n8n AI workflow, making the plugin highly specific to one niche. We had to do a massive refactoring to replace them with universally applicable pillars.
**Root cause:** Overfitting the prompt to the specific use-case of the current website instead of thinking about the plugin as a distributable, agnostic WordPress tool.
**Rule going forward:** Always build AI prompts and data schemas using universally accepted content pillars (Structure, Readability, Engagement, Credibility). Any niche-specific grading should be handled dynamically via user settings (like the Brand Voice preset), not hardcoded in the core prompts.

## [2026-10-08] — Enumerating an out-of-scope project's files in a walkthrough
**What happened:** The entry 4.9 walkthrough narrative listed every file name of the deleted `_archives/turbo-drill/` folder as part of the "Archive removal" block. The owner stopped it: that project is out of scope, and only its removal belongs to this one.
**Root cause:** Treating "what the commit touched" as "what the reviewer needs to see". The ticket asked for the folder to go; its contents were never this project's material, so naming them gives the reviewer nothing and pulls a foreign project into this repo's records.
**Rule going forward:** When a change removes or quarantines material that belongs to another project, the records of this project (plans, narratives, memory, PR text) name the folder and the count, never its files or contents. Stay scoped to this project's artifacts in every write-up.

## [2026-10-12] — Advising from a stale plan file
**What happened:** Asked about the domain, the agent read story 45's plan file (status built, "DNS records to add for GitHub Pages") and guided the owner through adding them. The ticket Notes, ADR 0013 and `docs/distribution.md` had been updated on 2026-10-09 with the opposite decision (Netlify, records not added). The owner then kept GitHub Pages anyway, so no harm, but the advice contradicted the recorded decision.
**Root cause:** The plan file records what was built, not the latest decision; decisions land in the ticket Notes, the ADR and the data sheet, and the plan is not rewritten when they change.
**Rule going forward:** Before advising on anything a ticket covers, read the ticket's Notes and the decision documents it references (ADR, `docs/distribution.md`), and `git log` the ticket for changes after the plan; the plan file alone is not the current state.

## [2026-10-14] — Reaching for another project's host because it was the one on the machine
**What happened:** Asked to run entry 6.2's manual check "on the site over SSH", the agent found a single SSH host in the owner's config and inspected it (read-only) before confirming it was this project's site. It was a staging site of another project the owner runs, with none of this plugin on it. The owner stopped it: that project's hosts are never to be touched without their authorization; this project's only site is the owner's own site named in the project records.
**Root cause:** Treating a credential present on the machine as a credential for the task at hand. The owner's PC carries several projects' access; the project's own records (the plans' Verification sections, the agent-local test-instance note) say which site is this project's, and that site's access lives only in the session that was given it.
**Rule going forward:** Before any command against a remote host, match the host to this project's records; a host or key that the records do not name belongs to another project and is off limits, reads included, until the owner says otherwise. When this project's access is missing, ask for it instead of using what is there. Owner domains stay out of repo artifacts either way.

## [2026-10-16] — Writing an external system's behaviour into code comments instead of memory
**What happened:** Entry 6.7 wrote "n8n 2.x runs `activeVersion`; a save changes only the draft" into a comment of `class-pqm-n8n.php`; entry 6.9, two days later, wrote "n8n republishes a PUT workflow by itself" into another. Both sessions were right about what they had seen (the editor's save, the API's PUT), neither read the other, and the retrospective's adversarial lens flagged them as contradicting assumptions. The 6.12 walk settled it (PUT republishes).
**Root cause:** A fact about an external system (n8n, Freemius, WordPress core) was recorded where only one session reads it. Code comments carry the local why; the knowledge triad puts "what is true now" in `memory/`.
**Rule going forward:** An observed behaviour of an external system goes into `memory/` once, dated and with the version it was seen on (the `n8n-provisioning.md` findings lines are the form); a code comment that depends on it names the memory file instead of restating the fact. Before writing such a comment, grep `memory/` for the system's name.

## [2026-10-14] — Mimicking a third party's rewrite observed once, on a different path
**What happened:** Story 54's diff of 3.5.1 found Freemius adding `freemius` to the readme Contributors; the stripper copied that rewrite and the release check compared the readme byte for byte. 3.5.1 had been uploaded through the Freemius dashboard; 3.7.0 went through the API (story 58) and Freemius left the line alone, so the 3.7.0 release run failed on a cosmetic line. The agent also first reported that run as having happened without reading its result.
**Root cause:** One observation of a vendor's cosmetic edit, made through a different entry point than production, was treated as a contract. And `diff -rq` reported "differ" without the line, so the cause needed the owner's download.
**Rule going forward:** Mimic a third party's rewrite only when it carries meaning; tolerate cosmetic ones in the comparison instead. Compare with an output that names the differing lines. Read a run's conclusion (`gh run list`) before saying a step ran.

## [2026-10-19] — Appending to a record with a string replace that matched nothing
**What happened:** Story 67's plan was first written without the template's Implementation Notes, Plan Change Log and Review Triage Log sections. Every later append targeted those headings with a string replace, matched nothing, wrote the file back unchanged and reported success; the plan merged in PR #150 without its build notes or its review log, noticed only when a later append was grepped for.
**Root cause:** The write was judged by the script finishing, not by the file changing. A replace on a missing anchor is a silent no-op.
**Rule going forward:** After any programmatic edit to a record (plan, ticket, memory), confirm the change landed before moving on: `git status --short` shows the file, or a grep finds the new text; a replace asserts its anchor exists. Fill the whole template at the first write, empty sections included, so later appends have their anchors.

## [2026-10-22] — Recommending a slice without saying what the visitor will see
**What happened:** At story 57's refinement the agent recommended "a hygiene slice now" (story 85) and deferred content, visuals and conversion. The owner accepted; when the preview screenshots came, the site looked like the old one, the logo (story 51) was on no page, and the large UX pass the owner had set as lane 2's goal on 2026-10-21 had not started. The owner asked where it was.
**Root cause:** The option was described by what it fixes (dead links, policies, favicon), not by what a visitor would see after it, and not against the goal the owner had stated for the lane. A logo with no decided placement (`docs/brand.md`: lockup "not decided yet") had no ticket to place it.
**Rule going forward:** When proposing a slice of a user-facing goal, say in one line what the user or visitor will see after it ships and what still will not change, and check it against the goal the owner set for the lane (`priorities-*` memory). An asset that exists but is shown nowhere is a gap to name, not a detail to leave to a later story.

## [2026-10-24] — Rewriting source files from a shell heredoc on Windows
**What happened:** Bug 59's first edits went through Python's `pathlib.Path.write_text()` from a shell heredoc. On Windows that writes CRLF, so four render-matrix rows that compare PHP source text failed while having nothing to do with the change, and the first reading blamed the change. The same heredocs also lost one level of backslashes on the way to Python, so the escapes meant for a newline and a carriage return became real control characters inside this lessons entry and inside a harness `echo` line. The review caught both.
**Root cause:** Python's text mode translates newlines to the platform's separator on write, while the repository's files are LF; and a command string passed through the shell tool does not keep backslash escapes intact.
**Rule going forward:** Edit repository files with the Edit tool, which takes text literally. When a script must write a file, use `read_bytes()` / `write_bytes()` and keep backslash escapes out of the script text. When a harness fails in rows an edit cannot reach, count line endings in the touched files with Python's byte count before reading the failures; a shell `grep -c` for a carriage return inside a command substitution can count every line instead.

## [2026-10-24] — Committing a screenshot taken on the owner's production site
**What happened:** The portfolio's hero image `assets/portfolio/dashboard.png` (committed 2026-10-16 in `bfe298b`, carried by the portfolio PDF) was captured on the owner's production site: the admin bar showed the site's title and the Fix-next queue the owner's post titles. Story 66's first pass searched the records for the owner's domain only, so it missed the image and a memory `description:` that spelled the site's title with a hyphen; the review found both by looking at the image and by searching the title with an optional separator.
**Root cause:** The owner's rule (records name the owner's site, hosts and accounts only generically) was applied as a text search for one domain pattern. A site is also named by its title, in any spelling, and by what a screenshot shows; a text search reads no pixels.
**Rule going forward:** Captures committed to the repository (portfolio, docs, tickets, store assets) come from the showcase site (the owner's Local demo site "Post Quality Showcase", sample posts), never from the owner's production site or its Local copy. Look at every image before committing it for a site title, post titles, user names and URLs. A hygiene search covers the site's title with and without a separator as well as the domain; the pattern stays in the agent's private notes, never in a repository file.

## [2026-10-26] — Confirming a leak at the function that sends, without the path that reaches it
**What happened:** Bug 90's review pass 2 reported that a site which had shared usage data, answered the deactivation form and then opted out of Freemius "Communication" would send the administrator's name and email at deletion. Triage verified it by reading the SDK functions that send (`sync_install()`, `send_install_update()`), which put those fields with no tracking check, graded it real, and the owner chose a fix and new privacy wording for it (loop 2). Pass 3 read the path from its entry point, `_uninstall_plugin_hook()` (SDK 2.13.5 L9866–9876), whose guard keeps that site's state from sending them: the leak did not exist, and loop 3 amended the owner's wording again.
**Root cause:** The verification started at the sending function and checked its body, not whether the claimed state reaches it. The guards upstream, which step 4's triage asks to follow, were never read.
**Rule going forward:** Before grading a finding that claims data leaves, or a state changes, under a given condition, trace the call path from its entry point (the hook, route or cron that starts it) to the send, and write each guard on the path with its line into the triage evidence. A finding read only at the sending function stays `maybe-false` until then, above all when its verdict goes to the owner as a decision.

## [2026-11-03] — Printing a diff of a file that holds secrets
**What happened:** Closing story 98's production walk, the agent wanted to show that `wp-config.php` differed from its backup only in the debug lines. It printed `diff backup live`, masking one constant with `sed`. WP-CLI's `config set` had changed the file's line endings, so `diff` printed every line of both files: the database credentials and the keys and salts reached the session transcript. Nothing reached the repository. The owner was told at once and accepted the risk.
**Root cause:** The redaction assumed `diff` would print only the lines that changed, and masked one known line, instead of never letting the file's content reach the output.
**Rule going forward:** Never print the content of a file that holds secrets (`wp-config.php`, `.env`, key files, a `.secrets/` file, a database dump), not even through `diff`, `grep` or `head`. Compare with `cmp` or a hash. When the changed constants must be named, print only their names (for example, PHP that reads both files and lists the keys whose values differ). A masking `sed` is not a safeguard.
