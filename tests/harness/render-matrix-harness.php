<?php
/**
 * Render harness for story 5 — covers the render-level I/O matrix rows:
 * which Settings sections appear per capability set, and the locked
 * fieldsets + upgrade copy per plan state. WP stubs, no WP install.
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = ['caps' => [], 'pro' => false];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
/* Ticket 12: add_filter records its calls (WP's real arity) so a hook's callback is asserted, not grepped. */
$GLOBALS['tw_filters'] = [];
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['tw_filters'][] = [$hook_name, $callback, $priority, $accepted_args];
    return true;
}
function register_setting($option_group, $option_name, $args = []) {}
function plugin_dir_url($f) { return ''; }
function plugin_basename($f) { return basename($f); }
/* Kept local: the rows mutate two globals the checks read — tw_settings (merged over the stored pqm_settings) and tw_options
   (the other options, written by update_option()/delete_option() below). */
function get_option($k, $d = false) { if (isset($GLOBALS['tw_options'][$k])) { return $GLOBALS['tw_options'][$k]; } return $k === 'pqm_settings' ? array_merge(['n8n_webhook_url' => 'https://n8n.example/webhook'], $GLOBALS['tw_settings']) : $d; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $tag === 'pqm_is_pro' ? $GLOBALS['tw']['pro'] : ($tag === 'pqm_ping_callback_wait' ? 0 : $value); } // story 2: the stub plays the callback before the reply
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; } // already the site locale
function restore_previous_locale() { return false; }
function esc_textarea($s) { return (string) $s; }
function wp_kses_post($s) { return (string) $s; }
/* Story 27: the tab-hash guard prints inline under the tab bar. */
function wp_print_inline_script_tag($data, $attributes = []) { echo "<script>$data</script>"; }
function admin_url($p = '') { return '/wp-admin/' . $p; }
function settings_fields($g) { echo "<!-- settings_fields:$g -->"; }
function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) { $GLOBALS['tw_settings_errors_for'][] = $setting; }
function submit_button() { echo '<input type="submit" />'; }
function checked($a, $b = true) { if ((string) $a === (string) $b) { echo " checked"; } }
function selected($a, $b = true) { if ((string) $a === (string) $b) { echo " selected"; } }
function disabled($a, $b = true) { if ($a == $b) { echo " disabled"; } }
function rest_url($p = '') { return '/wp-json/' . $p; }
function wp_create_nonce($a = '') { return 'nonce'; }
function get_current_user_id() { return 1; }
function get_user_meta($u, $k, $s) { return ''; }
function update_user_meta($u, $k, $v) { return true; }
function wp_date($f, $ts = null) { return gmdate($f, $ts ?? time()); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function load_plugin_textdomain(...$a) {}
function is_admin() { return true; }
function wp_next_scheduled(...$a) { return false; }
function wp_schedule_event(...$a) { return true; }
function wp_clear_scheduled_hook(...$a) {}
function home_url($p = '') { return 'https://example.com' . $p; }
/* The workflow version report: written by the ping, read by the status block (kept local with get_option(): same tw_options global). */
function update_option($k, $v, $autoload = null) { $GLOBALS['tw_options'][$k] = $v; return true; }
function add_option($k, $v, $deprecated = '', $autoload = null) { return isset($GLOBALS['tw_options'][$k]) ? false : update_option($k, $v, $autoload); }
function delete_option($k) { unset($GLOBALS['tw_options'][$k]); return true; }
define('MINUTE_IN_SECONDS', 60);
function human_time_diff($from, $to = 0) { return round((($to ?: time()) - $from) / 3600) . ' hours'; }

/* Story 23: the Pattern field lists published wp_block posts holding a List. */
$GLOBALS['tw_patterns'] = []; // id => [title, status, content]
$GLOBALS['tw_settings'] = []; // merged over the stored pqm_settings
function get_posts($args) {
    $out = [];
    foreach ($GLOBALS['tw_patterns'] as $id => [$title, $status, $content]) {
        if ($args['post_type'] === 'wp_block' && $status === $args['post_status']) {
            $out[] = (object) ['ID' => $id, 'post_title' => $title, 'post_content' => $content];
        }
    }
    return $out;
}
function parse_blocks($content) {
    $list = ['blockName' => 'core/list', 'innerBlocks' => []];
    return [
        'group-with-list' => [['blockName' => 'core/group', 'innerBlocks' => [$list]]],
        'paragraph-only'  => [['blockName' => 'core/paragraph', 'innerBlocks' => []]],
        'list'            => [$list],
    ][$content] ?? [];
}

/* Story 42: the Roles tab lists the site's roles (display names translated). */
class WP_Role {
    public $name;
    public $capabilities;
    public function __construct($name, $capabilities = []) { $this->name = $name; $this->capabilities = $capabilities; }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
$GLOBALS['tw_roles'] = []; // slug => [display name, capabilities]
function wp_roles() {
    $roles = (object) ['role_objects' => [], 'role_names' => []];
    foreach ($GLOBALS['tw_roles'] as $slug => [$name, $caps]) {
        $roles->role_objects[$slug] = new WP_Role($slug, $caps);
        $roles->role_names[$slug] = $name;
    }
    return $roles;
}
function translate_user_role($name, $domain = 'default') { return "[$name]"; } // marks the translated label

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php

/* ─── Load the plugin bootstrap (defines pqm_* helpers, loads classes) ── */
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
function render_for(array $caps, bool $pro): string {
    $GLOBALS['tw'] = ['caps' => $caps, 'pro' => $pro];
    ob_start();
    PQM_Admin::render_settings();
    return ob_get_clean();
}

/* 'AI Model' as a bare substring collides with the per-agent option label
 * "Default (use the AI Model above)" — match the section heading instead. */
$admin_sections  = ['n8n Connection', '>AI Model</h2>', 'AI Prompts & Context', 'Monthly Quota'];
$editor_sections = ['Agent-Specific Overrides', 'Automated Reviews (Cron)'];
$locked_fieldset = '<fieldset disabled class="pqm-locked-section">';
$upgrade_overrides = 'Available with Pro. Upgrade to unlock per-agent overrides';
$upgrade_cron      = 'Upgrade to let reviews run on a schedule';
$admin_caps  = ['pqm_manage_agents', 'pqm_manage_settings', 'pqm_run_paid_agents', 'pqm_run_free_agents', 'pqm_view_reviews'];
$editor_caps = ['pqm_manage_agents', 'pqm_run_paid_agents', 'pqm_run_free_agents', 'pqm_view_reviews'];

/* Row: Editor, licensed — exactly the two sections, editable */
echo "Editor, licensed\n";
$html = render_for($editor_caps, true);
foreach ($editor_sections as $s) { check("shows: $s", strpos($html, $s) !== false); }
foreach ($admin_sections as $s) { check("hides: $s", strpos($html, $s) === false); }
check('no locked fieldset', strpos($html, $locked_fieldset) === false);
check('no upgrade copy', strpos($html, $upgrade_overrides) === false && strpos($html, $upgrade_cron) === false);

/* Row: Editor, free site — both sections visible but locked */
echo "Editor, free site\n";
$html = render_for($editor_caps, false);
foreach ($editor_sections as $s) { check("shows: $s", strpos($html, $s) !== false); }
foreach ($admin_sections as $s) { check("hides: $s", strpos($html, $s) === false); }
check('two locked fieldsets', substr_count($html, $locked_fieldset) === 2);
check('both upgrade notes', strpos($html, $upgrade_overrides) !== false && strpos($html, $upgrade_cron) !== false);

/* Row: Admin, free site — all six sections, paid ones locked */
echo "Admin, free site\n";
$html = render_for($admin_caps, false);
foreach (array_merge($admin_sections, $editor_sections) as $s) { check("shows: $s", strpos($html, $s) !== false); }
check('two locked fieldsets', substr_count($html, $locked_fieldset) === 2);

/* Row: Admin, licensed — everything, nothing locked */
echo "Admin, licensed\n";
$html = render_for($admin_caps, true);
foreach (array_merge($admin_sections, $editor_sections) as $s) { check("shows: $s", strpos($html, $s) !== false); }
check('no locked fieldset', strpos($html, $locked_fieldset) === false);

/* Row (story 44): License section — plan from the SDK, no key field, one upsell */
echo "License section (story 44)\n";
$get_pro = '<a href="' . PQM_PRICING_URL . '">Get Pro</a>';
$html = render_for($admin_caps, true);
check('Pro: plan line says Pro, no Get Pro, no lapse copy',
    strpos($html, 'Current plan: Pro') !== false && strpos($html, $get_pro) === false && strpos($html, 'has lapsed') === false);
check('no License Key field in any plan', strpos($html, 'pqm_settings[license_key]') === false
    && strpos(render_for($admin_caps, false), 'pqm_settings[license_key]') === false);
pqm_fs()->has_license = false;
$html = render_for($admin_caps, false);
check('Free: plan line says Free + the one Get Pro link to the pricing page',
    strpos($html, 'Current plan: Free') !== false && substr_count($html, $get_pro) === 1 && strpos($html, 'has lapsed') === false);
pqm_fs()->has_license = true; // expired or cancelled: a license the SDK no longer lets unlock Pro
$html = render_for($admin_caps, false);
check('Lapsed: lapse copy + renew link to the SDK Account page, no Get Pro',
    strpos($html, 'your Pro license has lapsed') !== false
    && strpos($html, '<a href="' . pqm_fs()->get_account_url() . '">Renew on the Account page</a>') !== false
    && strpos($html, $get_pro) === false);
pqm_fs()->has_license = false;
check('Editor: no License section', strpos(render_for($editor_caps, true), '>License</h2>') === false
    && strpos(render_for($editor_caps, false), $get_pro) === false);

/* Row (bug 59): every Upgrade link the SDK renders reaches the public pricing page */
echo "Upgrade links (bug 59)\n";
$pricing_cbs = pqm_fs()->filters['pricing_url'] ?? [];
// How the SDK calls this filter (a real URL, or null as a probe): memory/freemium-and-roles.md.
$through_pricing = static fn($url) => array_reduce($pricing_cbs, static fn($u, $cb) => $cb($u), $url);
check('the SDK pricing_url filter is hooked', $pricing_cbs !== []);
$upgrade_url = $through_pricing(pqm_fs()->get_upgrade_url());
check('an Upgrade URL becomes the public pricing page, never an admin page',
    $upgrade_url === PQM_PRICING_URL && !str_contains($upgrade_url, '/wp-admin/'));
check("the SDK's null probe stays null: its hidden pricing page and the renewal checkout stay registered",
    $through_pricing(null) === null);
check('Settings Get Pro and the SDK Upgrade links share one address',
    str_contains(render_for($admin_caps, false), 'href="' . PQM_PRICING_URL . '"'));
check("the setup dialog's Get Pro link uses the same address (src/setup/config-steps.js)",
    str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/src/setup/config-steps.js'),
        "const PRICING_URL = '" . PQM_PRICING_URL . "';"));

/* Row (ticket 16): settings-only role — the agent sections need
 * pqm_manage_agents: no heading, no fieldset, no field; admin sections stay */
foreach ([[true, 'licensed'], [false, 'free site']] as [$pro, $plan]) {
    echo "Settings-only (pqm_manage_settings), $plan\n";
    $html = render_for(['pqm_manage_settings'], $pro);
    foreach ($admin_sections as $s) { check("shows: $s", strpos($html, $s) !== false); }
    foreach ($editor_sections as $s) { check("hides: $s", strpos($html, $s) === false); }
    check('no locked fieldset', strpos($html, $locked_fieldset) === false);
    check('no upgrade copy', strpos($html, $upgrade_overrides) === false && strpos($html, $upgrade_cron) === false);
    check('no agent or cron field', strpos($html, 'pqm_settings[agent_prompts]') === false
        && strpos($html, 'pqm_settings[cron_') === false);
}

/* Ticket 13: free-site off-switch — the Enable toggle leaves the lock only
 * while stored cron is on; the rest of Automated Reviews stays locked */
$cron_checkbox = 'type="checkbox" name="pqm_settings[cron_enabled]"';
$cron_hidden   = 'type="hidden" name="pqm_settings[cron_enabled]" value="0"';
$off_note      = 'On the free plan, scheduled reviews can be switched off but not back on.';
/** [cron section start, its locked fieldset open, that fieldset's close] or false. */
function cron_fieldset(string $html, string $locked_fieldset) {
    $h2 = strpos($html, 'id="pqm-cron-settings"');
    $open = $h2 === false ? false : strpos($html, $locked_fieldset, $h2);
    return $open === false ? false : [$h2, $open, strpos($html, '</fieldset>', $open)];
}
foreach (['Editor' => $editor_caps, 'Admin' => $admin_caps] as $who => $caps) {
    echo "$who, free site, stored cron on (ticket 13)\n";
    $GLOBALS['tw_settings'] = ['cron_enabled' => true];
    $html = render_for($caps, false);
    [$h2, $open, $close] = cron_fieldset($html, $locked_fieldset) ?: [false, false, false];
    $box    = strpos($html, $cron_checkbox);
    $hidden = strpos($html, $cron_hidden);
    check('still two locked fieldsets', substr_count($html, $locked_fieldset) === 2);
    check('exactly one cron_enabled checkbox', substr_count($html, $cron_checkbox) === 1);
    check('the checkbox is checked and sits outside (before) the locked cron fieldset',
        $box !== false && $box > $h2 && $box < $open
        && (bool) preg_match('/type="checkbox" name="pqm_settings\[cron_enabled\]" value="1" checked/', $html));
    check('the hidden 0 precedes the checkbox', $hidden !== false && $hidden < $box && $hidden > $h2);
    check('the off-switch line sits under the toggle, outside the lock',
        ($note = strpos($html, $off_note)) !== false && $note > $box && $note < $open);
    $inside = substr($html, $open, $close - $open);
    $other_cron = ['cron_frequency', 'cron_batch_size', 'cron_command', 'cron_min_score', 'cron_max_age_days'];
    check('the other cron inputs remain inside the locked fieldset', array_filter($other_cron,
        static fn($k) => str_contains($inside, "name=\"pqm_settings[$k]\"")) === $other_cron
        && substr_count($html, 'name="pqm_settings[cron_') === 2 + count($other_cron));
    check('locked note says the scheduled reviews keep running (not "upgrade to let them run")',
        strpos($html, 'Available with Pro. Your scheduled reviews keep running; upgrade to change their settings.') !== false
        && strpos($html, $upgrade_cron) === false);
}
echo "Editor, free site, stored cron off (ticket 13)\n";
$GLOBALS['tw_settings'] = ['cron_enabled' => false];
$html = render_for($editor_caps, false);
[$h2, $open, $close] = cron_fieldset($html, $locked_fieldset) ?: [false, false, false];
$box = strpos($html, $cron_checkbox);
check('fully locked: the checkbox sits inside the locked cron fieldset', $box !== false && $box > $open && $box < $close);
check('no hidden 0, no off-switch line', strpos($html, $cron_hidden) === false && strpos($html, $off_note) === false);
check('today\'s locked note', strpos($html, $upgrade_cron) !== false && strpos($html, 'keep running') === false);
echo "Editor, licensed, stored cron on (ticket 13)\n";
$GLOBALS['tw_settings'] = ['cron_enabled' => true];
$html = render_for($editor_caps, true);
check('pro: one checked checkbox, no hidden 0, no off-switch line',
    substr_count($html, $cron_checkbox) === 1 && strpos($html, $cron_hidden) === false && strpos($html, $off_note) === false
    && (bool) preg_match('/type="checkbox" name="pqm_settings\[cron_enabled\]" value="1"\s+checked/', $html));
$GLOBALS['tw_settings'] = [];

/* Ticket 3: the cron Command select offers Review, SEO, Audit */
function cron_command_select(string $html): string {
    preg_match('/<select id="pqm_cron_command".*?<\/select>/s', $html, $m);
    return preg_replace('/\s+/', ' ', $m[0] ?? '');
}
$old_line = 'Only "review" is available in this version.';
echo "Editor, licensed, stored cron_command audit (ticket 3)\n";
$GLOBALS['tw_settings'] = ['cron_command' => 'audit'];
$html   = render_for($editor_caps, true);
$select = cron_command_select($html);
check('enabled select named pqm_settings[cron_command]', $select !== ''
    && strpos($select, 'name="pqm_settings[cron_command]"') !== false && strpos($select, 'disabled') === false);
check('options review, seo, audit in order', preg_match_all('/<option value="([a-z]+)"/', $select, $values) === 3
    && $values[1] === ['review', 'seo', 'audit']);
check('labels from the registry', strpos($select, '>Review</option>') !== false
    && strpos($select, '>SEO</option>') !== false && strpos($select, '>Audit</option>') !== false);
check('stored audit selected, only it', substr_count($select, ' selected') === 1
    && strpos($select, '<option value="audit" selected>') !== false);
check('old "Only review" line gone', strpos($html, $old_line) === false);
check('no rewrite option', strpos($select, 'rewrite') === false);
echo "Editor, free site, stored cron_command seo (ticket 3)\n";
$GLOBALS['tw_settings'] = ['cron_command' => 'seo'];
$html = render_for($editor_caps, false);
[$h2, $open, $close] = cron_fieldset($html, $locked_fieldset) ?: [false, false, false];
$sel_at = strpos($html, '<select id="pqm_cron_command"');
check('select inside the locked cron fieldset', $sel_at !== false && $open !== false && $sel_at > $open && $sel_at < $close);
check('stored seo selected', strpos(cron_command_select($html), '<option value="seo" selected>') !== false);
check('old "Only review" line gone (free)', strpos($html, $old_line) === false);
echo "Editor, free site, stored cron on + cron_command audit (ticket 3)\n";
$GLOBALS['tw_settings'] = ['cron_enabled' => true, 'cron_command' => 'audit'];
$html = render_for($editor_caps, false);
[$h2, $open, $close] = cron_fieldset($html, $locked_fieldset) ?: [false, false, false];
$box = strpos($html, $cron_checkbox);
check('paid command skipped by cron: fully locked, checkbox inside the fieldset',
    substr_count($html, $cron_checkbox) === 1 && $box !== false && $box > $open && $box < $close);
check('no off-switch: no hidden 0, no off-switch line', strpos($html, $cron_hidden) === false && strpos($html, $off_note) === false);
check('today\'s locked note, not "keep running"', strpos($html, $upgrade_cron) !== false && strpos($html, 'keep running') === false);
$GLOBALS['tw_settings'] = [];

/* Story 23: Pattern field — Takeaways and Sources only */
echo "Pattern field (story 23)\n";
// 3 user patterns: 1 published with a List, 1 published without one, 1 draft.
$GLOBALS['tw_patterns'] = [
    31 => ['Key Takeaways', 'publish', 'group-with-list'],
    32 => ['Banner', 'publish', 'paragraph-only'],
    33 => ['Draft list', 'draft', 'list'],
];
$GLOBALS['tw_settings'] = ['agent_prompts' => ['takeaways' => ['pattern' => 31]]];
$html = render_for($editor_caps, true);
check('Pattern select for Takeaways and Sources', strpos($html, '<select id="pqm_agent_pattern_takeaways"') !== false
    && strpos($html, 'name="pqm_settings[agent_prompts][takeaways][pattern]"') !== false
    && strpos($html, 'name="pqm_settings[agent_prompts][sources][pattern]"') !== false);
check('no Pattern field for the other agents', substr_count($html, '[pattern]"') === 2);
check('no typed block-name field or datalist left', strpos($html, '[block]"') === false && strpos($html, '<datalist') === false);
preg_match('/<select id="pqm_agent_pattern_takeaways".*?<\/select>/s', $html, $m);
$select = preg_replace('/\s+/', ' ', $m[0] ?? '');
check('options: Plain list (0) + the 1 published pattern with a List, by title',
    preg_match_all('/<option value="(\d+)"/', $select, $values) === 2 && $values[1] === ['0', '31']
    && strpos($select, '<option value="0">Plain list</option>') !== false && strpos($select, '> Key Takeaways </option>') !== false);
check('the stored pattern is selected (Takeaways)', strpos($select, '<option value="31" selected>') !== false);
preg_match('/<select id="pqm_agent_pattern_sources".*?<\/select>/s', $html, $m);
check('nothing stored → Plain list (Sources)', strpos($m[0] ?? '', ' selected') === false);

/* Story 24: the Pattern to use row — label, optgroup, links, listing rule */
echo "Pattern to use row (story 24)\n";
/** The Takeaways box's pattern <td>, whitespace collapsed. */
function takeaways_cell(string $html): string {
    preg_match('/<label for="pqm_agent_pattern_takeaways">.*?<\/td>/s', $html, $m);
    return preg_replace('/\s+/', ' ', $m[0] ?? '');
}
$cell = takeaways_cell($html);
check('label "Pattern to use"', str_contains($cell, '<label for="pqm_agent_pattern_takeaways">Pattern to use</label>'));
check('select: Plain list, then the patterns under an optgroup "Your patterns"',
    preg_match('/<option value="0">Plain list<\/option> <optgroup label="Your patterns"> <option value="31" selected>/', $cell) === 1);
check('"Edit pattern" href is core\'s edit link for the chosen pattern (ends post=31&action=edit), shown',
    preg_match('/<a class="pqm-pattern-edit" href="([^"]+)">Edit pattern<\/a>/', $cell, $m) === 1 && str_ends_with($m[1], 'post.php?post=31&action=edit'));
check('"Manage patterns" → edit.php?post_type=wp_block',
    str_contains($cell, '<a href="/wp-admin/edit.php?post_type=wp_block">Manage patterns</a>'));
check('no "Create a pattern" button while a pattern qualifies', !str_contains($cell, 'Create a pattern'));
check('Takeaways line says where the items land',
    str_contains($cell, "Goes at the top of the post. The items fill the pattern's first List block, so only your published patterns that contain a List block appear here."));
check('Sources line says the end', str_contains($html, "Goes at the end of the post. The items fill the pattern's first List block, so only your published patterns that contain a List block appear here."));
check('no preview iframe (removed 2026-10-06: it pulled the theme\'s third-party scripts into Settings)', !str_contains($html, '<iframe'));

$GLOBALS['tw_settings'] = []; // Plain list chosen
$cell = takeaways_cell(render_for($editor_caps, true));
check('plain list chosen → "Edit pattern" hidden, href at post=0',
    preg_match('/<a class="pqm-pattern-edit pqm-hidden" href="[^"]*post\.php\?post=0&action=edit">Edit pattern<\/a>/', $cell) === 1);

$GLOBALS['tw_settings'] = ['agent_prompts' => ['takeaways' => ['pattern' => 31]]];
$GLOBALS['tw_patterns'] = [32 => ['Other list', 'publish', 'list']]; // the stored 31 no longer qualifies
$cell = takeaways_cell(render_for($editor_caps, true));
check('stored pattern gone → Plain list selected, "Edit pattern" hidden at post=0 (select and link agree)',
    !str_contains($cell, ' selected') && preg_match('/<a class="pqm-pattern-edit pqm-hidden" href="[^"]*post\.php\?post=0&action=edit">/', $cell) === 1);
$GLOBALS['tw_settings'] = [];

$GLOBALS['tw_patterns'] = [32 => ['Banner', 'publish', 'paragraph-only'], 33 => ['Draft list', 'draft', 'list']]; // none eligible
$cell = takeaways_cell(render_for($editor_caps, true));
check('none eligible: select has Plain list only, no optgroup',
    preg_match_all('/<option /', $cell) === 1 && !str_contains($cell, '<optgroup'));
check('"Create a pattern" button → post-new.php?post_type=wp_block',
    str_contains($cell, '<a class="button" href="/wp-admin/post-new.php?post_type=wp_block">Create a pattern</a>'));
check('no "Edit pattern" / "Manage patterns" links', !str_contains($cell, 'Edit pattern') && !str_contains($cell, 'Manage patterns'));

$GLOBALS['tw_patterns'] = [31 => ['Key Takeaways', 'publish', 'group-with-list']];
$html  = render_for($editor_caps, false);
$open  = strpos($html, $locked_fieldset);
$field = strpos($html, '[takeaways][pattern]');
check('free site: the row sits inside the locked Overrides fieldset',
    $open !== false && $field > $open && $field < strpos($html, '</fieldset>', $open));
$free_cells = takeaways_cell($html);
$GLOBALS['tw_patterns'] = [];
$free_cells .= takeaways_cell(render_for($editor_caps, false)); // the empty state too
check('free site: no Edit pattern / Manage patterns links and no Create a pattern button (links escape the disabled fieldset)',
    !str_contains($free_cells, 'Edit pattern') && !str_contains($free_cells, 'Manage patterns') && !str_contains($free_cells, 'Create a pattern')
    && !str_contains($free_cells, '<a '));
$GLOBALS['tw_settings'] = [];

/* Row: remap — capability set is the only driver (an "Author" granted
 * pqm_manage_agents gets the Editor view; roles never checked directly) */
echo "Remap: author granted pqm_manage_agents\n";
$html = render_for(['pqm_manage_agents', 'pqm_run_free_agents', 'pqm_view_reviews'], true);
check('overrides visible after remap', strpos($html, 'Agent-Specific Overrides') !== false);
check('admin sections still hidden', strpos($html, 'n8n Connection') === false);
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('render gates on capabilities, never on role names', strpos($src, "get_role('author'") === false && preg_match("/wp_get_current_user\\(\\)->roles/", $src) === 0);

/* Row: Author / options.php — WP core enforces option_page_capability;
 * register_settings() is executed and its recorded filter asserted (ticket 12);
 * the settings_page_capability() body and the menu cap stay static checks */
echo "Author refusal (option_page_capability executed; menu cap + settings_page_capability body static)\n";
/* Review fix 3: both gates resolve the capability dynamically so a role
 * granted either pqm_manage_settings or pqm_manage_agents gets access. */
check('settings_page_capability() prefers manage_settings, falls back to manage_agents',
    (bool) preg_match("/function settings_page_capability\\(\\).*?pqm_manage_settings.*?pqm_manage_agents/s", $src));
$GLOBALS['tw_filters'] = [];
PQM_Admin::register_settings();
check('option save uses settings_page_capability (register_settings() hooks option_page_capability_pqm_settings_group)',
    $GLOBALS['tw_filters'] === [['option_page_capability_pqm_settings_group', ['PQM_Admin', 'settings_page_capability'], 10, 1]]);
check('Settings menu uses settings_page_capability', (bool) preg_match("/self::settings_page_capability\\(\\),\\s*'pqm-settings'/", $src));
$GLOBALS['tw'] = ['caps' => ['pqm_manage_settings'], 'pro' => true];
check('settings-only role resolves to pqm_manage_settings', PQM_Admin::settings_page_capability() === 'pqm_manage_settings');
$GLOBALS['tw'] = ['caps' => ['pqm_manage_agents'], 'pro' => true];
check('agents-only role resolves to pqm_manage_agents', PQM_Admin::settings_page_capability() === 'pqm_manage_agents');
$GLOBALS['tw'] = ['caps' => ['manage_options'], 'pro' => true];
check('neither pqm cap (story 42): resolves to manage_options, so the Roles tab stays reachable', PQM_Admin::settings_page_capability() === 'manage_options');

/* n8n Workflow page: Set up by hand, the manual steps (n8n provisioning, entry 2; story 40; inside a disclosure since story 67) */
echo "n8n Workflow page — Set up by hand: four steps, Copy rows, links (entry 2)\n";
if (!function_exists('wp_nonce_url')) { function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . '&_wpnonce=stub'; } }
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '', $unused = null) {
        if (!is_array($args)) { // add_query_arg( $key, $value, $url )
            [$args, $url] = [[$args => $url], $unused];
        }
        return $url . '?' . http_build_query($args);
    }
}
if (!function_exists('wp_get_current_user')) { function wp_get_current_user() { return (object) ['ID' => 1, 'user_login' => 'editor-jo']; } }
/* Core prints the profile's Application Passwords section only when this is true (HTTPS or a local environment). */
$GLOBALS['tw_app_passwords'] = true;
if (!function_exists('wp_is_application_passwords_available_for_user')) { function wp_is_application_passwords_available_for_user($user) { return $GLOBALS['tw_app_passwords']; } }
/* The resolver asks PQM_DB::has_result() once a webhook URL is saved: one get_var, stubbed per row */
$wpdb = new class { public $prefix = 'wp_'; public $has_result = null; public function get_var($sql) { return $this->has_result; } };
/* $options are stored for this render only, over what the row already stored. */
$render_workflow = static function (array $settings, array $options = []): string {
    $GLOBALS['tw_settings'] = $settings;
    $kept = $GLOBALS['tw_options'] ?? [];
    $GLOBALS['tw_options'] = $options + $kept + ['date_format' => 'Y-m-d'];
    ob_start();
    PQM_Admin::render_workflow();
    $GLOBALS['tw_options'] = $kept;
    return ob_get_clean();
};
/** The Set up by hand disclosure's content (story 67), '' when the state does not allow it. */
$section_of = static fn(string $html): string => preg_match('/<details class="pqm-disclosure" id="pqm-by-hand"[^>]*>(.*?)<\/details>/s', $html, $m) ? $m[1] : '';
/** The status block (story 67): sentence, details, version line, actions. */
$block_of   = static fn(string $html): string => preg_match('/<div class="pqm-workflow-status"[^>]*>.*?<\/div>/s', $html, $m) ? $m[0] : '';
$state_of   = static fn(string $html): string => preg_match('/<div class="pqm-workflow-status" data-state="([a-z-]+)">/', $html, $m) ? $m[1] : '';
$sentence_of = static fn(string $html): string => preg_match('/<p class="pqm-workflow-sentence">([^<]*)<\/p>/', $html, $m) ? $m[1] : '';
$outcome_of = static fn(string $html): string => preg_match('/<p class="pqm-workflow-outcome (is-[a-z]+)">([^<]*)<\/p>/', $html, $m) ? "$m[1]: $m[2]" : '';
$primaries  = static fn(string $html): int => substr_count($html, 'button-primary');
$disclosures_of = static fn(string $html): array => preg_match_all('/<details class="pqm-disclosure" id="([a-z0-9-]+)"( open)?>/', $html, $m) ? array_combine($m[1], array_map(static fn($o) => $o !== '', $m[2])) : [];
$manual_site = [PQM_SETUP_OPTION => ['steps' => [], 'manual' => true]];
// The manual path (no URL, the owner's choice): Set up by hand open; a saved URL, not checked: the disclosure collapsed.
$fresh = $render_workflow(['n8n_webhook_url' => '', 'webhook_token' => 'tok-35'], $manual_site);
$connected = $render_workflow(['webhook_token' => 'tok-35']);
$section = $section_of($connected);

/* AC1: four steps in journey order, no credential above the import */
preg_match_all('/<li>\s*<h3>([^<]+)<\/h3>/', $section, $steps);
check('four numbered steps, in journey order', str_contains($section, '<ol class="pqm-workflow-steps">')
    && $steps[1] === ['Import the workflow', 'Create the credentials', 'Check the credential in each node', 'Publish and connect']);
$import_at = strpos($section, 'value="/wp-json/post-quality/v1/workflow?token=tok-35"');
$download_at = strpos($section, 'Download n8n workflow');
$step2_at = strpos($section, 'Create the credentials');
check('step 1 imports as the dialog does: the import URL row, then Download as the fallback; no credential is listed above them',
    $import_at !== false && $import_at < $download_at && $download_at < $step2_at
    && str_contains($section, 'Importing from the URL not working (for example, n8n cannot reach this site)? Download the file and choose "Import", then "From file", in the same menu.')
    && str_contains($section, '<a href="/wp-admin/admin-post.php?action=pqm_download_workflow&_wpnonce=stub" class="button">Download n8n workflow</a>')
    && !str_contains(substr($section, 0, $step2_at), 'Post Quality webhook - ') && !str_contains(substr($section, 0, $step2_at), 'Anthropic - Claude'));
check('... the import URL row, and only it, sits in .pqm-import-url (pqm-admin.js picks its "where to paste" line by that wrapper)',
    preg_match('/<div class="pqm-import-url">\s*<div class="pqm-copy-row">\s*<span class="pqm-copy-label">Workflow import URL<\/span>/', $section) === 1
    && substr_count($section, 'class="pqm-import-url"') === 1);
check('n8n labels as 2.42.3 shows them (en.json at the tag): "Import" → "From URL" / "From file", "Create credential"; none of the older names',
    str_contains($section, 'choose "Import", then "From URL"') && str_contains($section, 'choose "Import", then "From file"')
    && str_contains($section, 'open the arrow beside "Create workflow" on the n8n Overview page, choose "Create credential"')
    && !preg_match('/Import from (URL|File)"|"Create" in the side menu|first credential of/', $section));
check('step 4: save, Publish (older n8n: Active), copy the production URL, paste it here (story 67: the field is in the step)',
    str_contains($section, 'Save the workflow, then click "Publish" in the header above the canvas (older n8n versions: switch the "Active" toggle on).')
    && str_contains($section, 'Double-click the Webhook Trigger, choose "Production URL" and copy the address')
    && str_contains($section, '<p>Paste it here: the plugin saves it and tests the connection.</p>')
    && strpos($section, 'Publish and connect') < strpos($section, 'Production URL') && !str_contains($section, 'Send test ping') && !str_contains($section, 'tab-connection'));
check('step 4 warns that a Test URL (/webhook-test/) is refused, as the dialog does',
    str_contains($section, '<p class="description">Not the Test URL: an address with /webhook-test/ only listens while the n8n editor is open, so this site refuses it.</p>'));
$save_form = static fn(string $html, string $suffix, string $button): string => '<form class="pqm-webhook-form" data-pqm-post="setup/connect">'
    . "\n            <label class=\"pqm-copy-label\" for=\"pqm_webhook_url_$suffix\">n8n Webhook URL</label>\n            <span class=\"pqm-copy-controls\">\n"
    . "                <input type=\"url\" id=\"pqm_webhook_url_$suffix\" name=\"webhook_url\" class=\"regular-text code pqm-webhook-url-field\"\n"
    . '                       value="' . $html . "\"\n                       placeholder=\"https://your-n8n.example.com/webhook/pqm-execute-example-com\" />\n"
    . "                <button type=\"submit\" class=\"$button pqm-save-webhook\" data-pqm-busy=\"Saving and testing…\">Save and test</button>\n            </span>\n        </form>";
check('step 4 holds the webhook URL field and Save and test, posting to setup/connect (save_webhook_url(), then the ping): secondary on a set-up site…',
    str_contains($section, $save_form('https://n8n.example/webhook', 'step', 'button')) && substr_count($section, 'pqm-save-webhook') === 1);
check('... and the manual path\'s one primary, the field empty, Set up by hand open', str_contains($section_of($fresh), $save_form('', 'step', 'button button-primary'))
    && $primaries($fresh) === 1 && ($disclosures_of($fresh)['pqm-by-hand'] ?? null) === true && $state_of($fresh) === 'manual');

/* AC2: the first-credential warning, before the import */
/* n8n 2.42.3 (editor-ui source at the tag): import and opening a node pick the most recently saved credential of the type; a hand pick spreads to every empty node of that type */
$warning = 'Before you import: n8n fills in credentials by itself. A node gets the credential of its type you saved most recently, even one made for another workflow, when it is imported or opened; and a credential you pick in one node is added to every other node of that type that has none. Both Header Auth credentials share a type, so follow the order in step 3 and check the name in each node.';
check('step 1 warns, before the import, how n8n fills in credentials by itself (most recent of the type; a pick spreads to empty nodes)',
    ($w = strpos($section, $warning)) !== false && $w > strpos($section, 'Import the workflow') && $w < $import_at);

/* AC3 (entry): re-import and the path, in step 4 */
$step4 = substr($section, strpos($section, 'Publish and connect'));
check('step 4: a re-import over a version 6+ workflow is published only once the old one is unpublished',
    str_contains($step4, 'Re-importing over a workflow of version 6 or later? It uses this site\'s webhook path too, so n8n publishes the new one only once the old one is unpublished: open the old one, choose "Unpublish" from the arrow beside "Publish"'));
check('step 4: the production URL ends in /webhook/ + pqm_webhook_path()',
    str_contains($step4, 'it ends in <code>/webhook/' . pqm_webhook_path() . '</code>.') && pqm_webhook_path() === 'pqm-execute-example-com');

/* Story 40 AC2: a Copy row for every value typed into n8n */
preg_match_all('/<div class="pqm-copy-row">\s*<span class="pqm-copy-label">([^<]+)<\/span>\s*<span class="pqm-copy-controls">\s*<input type="text" class="regular-text code pqm-copy-field" value="([^"]*)" readonly\s+aria-label="([^"]+)" \/>\s*<button type="button" class="button pqm-copy-button" aria-label="Copy ([^"]+)">Copy<\/button>\s*<\/span>\s*<p class="pqm-copy-feedback" aria-live="polite"><\/p>\s*<\/div>/', $section, $rows, PREG_SET_ORDER);
$copied = array_map(static fn($r) => [$r[1], $r[2]], $rows);
check('Copy rows (Pro): import URL, then each credential\'s name and fields, the WordPress one with the current user',
    $copied === [
        ['Workflow import URL', '/wp-json/post-quality/v1/workflow?token=tok-35'],
        ['Credential name', 'Post Quality webhook - example.com'], ['Name', 'X-PQM-Token'], ['Value', 'tok-35'],
        ['Credential name', 'Anthropic - Claude'], ['Name', 'x-api-key'],
        ['Credential name', 'WordPress - example.com'], ['User', 'editor-jo'],
    ]);
check('... each row: the field\'s aria-label = the Copy button\'s object, naming the credential inside a credential block; aria-live feedback, no id on a row',
    count($rows) === substr_count($section, 'pqm-copy-row') && !array_filter($rows, static fn($r) => $r[3] !== $r[4])
    && array_column($rows, 3) === [
        'Workflow import URL',
        'Post Quality webhook - example.com: Credential name', 'Post Quality webhook - example.com: Name', 'Post Quality webhook - example.com: Value',
        'Anthropic - Claude: Credential name', 'Anthropic - Claude: Name',
        'WordPress - example.com: Credential name', 'WordPress - example.com: User',
    ]
    && !preg_match('/pqm-copy-(field|button)[^>]* id="/', $section));
preg_match_all('/<button[^>]* aria-label="([^"]+)"/', $section, $buttons);
check('... no two buttons in the section share an aria-label', $buttons[1] !== [] && count($buttons[1]) === count(array_unique($buttons[1])));
check('the token is a labeled value row, never part of a sentence', !str_contains($section, '<code>tok-35</code>')
    && substr_count($section, 'tok-35') === 2); // the Value row and the import URL row
check('import URL copy says the token also opens the n8n trigger',
    str_contains($section, 'It includes your webhook token, which also opens your n8n workflow\'s trigger — keep it private.'));

/* Story 40 AC3: links only where they are accurate, naming their destination */
$app_off = 'Password: Application Passwords are turned off on this site, so none can be created for this user. Usually the site does not run on HTTPS; sometimes a security plugin turns them off. Only Rewrite needs this credential.';
check('Application Passwords available: links to the user\'s own profile (Application Passwords section), says the user must edit posts',
    str_contains($section, 'created in <a href="/wp-admin/profile.php#application-passwords-section">Application Passwords on your profile page</a>. n8n edits posts with it when you run Rewrite, so the user must be allowed to edit posts.')
    && !str_contains($section, $app_off));
$GLOBALS['tw_app_passwords'] = false;
$app_off_page = $render_workflow(['webhook_token' => 'tok-35']);
$app_off_section = $section_of($app_off_page);
$GLOBALS['tw_app_passwords'] = true;
check('Application Passwords turned off: says so in words, no profile link, only Rewrite needs it',
    str_contains($app_off_section, '<p>' . $app_off . '</p>') && !str_contains($app_off_section, 'profile.php')
    && str_contains($app_off_section, 'WordPress - example.com: User'));
$rewrite_off = '<div class="notice notice-warning inline pqm-inline-notice"><p>Application Passwords are turned off on this site, so no WordPress credential can be created for this user. Rewrite stays off until they are available and a WordPress credential is set in n8n on the Update WP Post (Rewrite) node.</p></div>';
check('... 4b (Pro, state Set up): one warning line right under the status block says Rewrite stays off until a WordPress credential is added; none with Application Passwords on',
    substr_count($app_off_page, $rewrite_off) === 1 && preg_match('/<\/div>\s*' . preg_quote($rewrite_off, '/') . '/', $app_off_page) === 1
    && strpos($app_off_page, $rewrite_off) < strpos($app_off_page, '<details') && !str_contains($connected, 'Rewrite stays off'));
$GLOBALS['tw_app_passwords'] = false;
check('... not in another state (not set up): the line is state 4\'s', !str_contains($render_workflow(['n8n_webhook_url' => '', 'webhook_token' => 'tok-35']), 'Rewrite stays off'));
$GLOBALS['tw_app_passwords'] = true;
check('Anthropic key: links to the API keys page of the Anthropic Console, as the dialog names it',
    str_contains($section, 'Value: your Anthropic API key. Create one on the <a href="https://platform.claude.com/settings/keys">API keys page of the Anthropic Console</a>.'));
$n8n_link = static fn(string $s): ?string => preg_match('/In <a href="([^"]+)">your n8n<\/a>, open a new, empty workflow/', $s, $m) ? $m[1] : null;
check('n8n link: only the webhook URL saved → its origin', $n8n_link($section) === 'https://n8n.example');
$with_base = $section_of($render_workflow([], [PQM_N8n::OPTION => ['base_url' => 'https://n8n.owner.example:8443', 'api_key' => 'n8n-key-SECRET-42', 'probe' => null]]));
check('n8n link: a stored n8n connection address wins', $n8n_link($with_base) === 'https://n8n.owner.example:8443');
check('... and the stored n8n API key is not in the page', !str_contains($with_base, 'n8n-key-SECRET-42'));
check('n8n link: neither stored → no link, plain words', $n8n_link($section_of($fresh)) === null
    && str_contains($section_of($fresh), 'In n8n, open a new, empty workflow, open the workflow menu (the three dots at the top right), choose "Import", then "From URL", and paste this link:'));
check('n8n link: a saved webhook URL with a port and a sub-path → scheme, host and port only',
    $n8n_link($section_of($render_workflow(['n8n_webhook_url' => 'http://192.168.1.20:5678/n8n/webhook/pqm-execute-example-com']))) === 'http://192.168.1.20:5678');
foreach (['not a url', 'ftp://n8n.example/webhook/pqm-execute-example-com', 'https:///webhook/pqm-execute'] as $odd) {
    check("n8n link: saved webhook URL \"$odd\" (no http(s) host) → no link", $n8n_link($section_of($render_workflow(['n8n_webhook_url' => $odd]))) === null);
}
check('every link text names its destination (no "click here", no bare "here")', !preg_match('/>\s*(click )?here\s*</i', $section));

/* Step 3: today's hint, plus "check the name" */
check('step 3: Claude nodes first (a pick spreads to the empty ones), then the Webhook Trigger and Update WP Post (Rewrite) even if set, then check every node',
    str_contains($section, 'Do the Claude nodes first: double-click one, pick the Anthropic credential in its credential field and close it; n8n adds it to the other Claude nodes that have none. Then double-click the Webhook Trigger and pick the webhook credential, and Update WP Post (Rewrite) and pick the WordPress one, even if one is already set. Last, open each node — the Webhook Trigger, the six Claude nodes and Update WP Post (Rewrite) — and check the credential name.'));
check('step 3 says which credential goes in which node',
    str_contains($section, 'Pick <strong>Post Quality webhook - example.com</strong> in the Webhook Trigger, <strong>Anthropic - Claude</strong> in the six Claude nodes and <strong>WordPress - example.com</strong> in Update WP Post (Rewrite).'));
check('step 2 (Pro): three credential blocks, each with its type and node', substr_count($section, '<div class="pqm-workflow-credential">') === 3
    && str_contains($section, 'Type: Header Auth. Goes in the Webhook Trigger only, so only this site can start the workflow.')
    && str_contains($section, 'Type: Header Auth. Goes in the six Claude nodes.') && str_contains($section, 'Type: Basic Auth. Goes in Update WP Post (Rewrite).'));
/* The section names the credentialed nodes literally — keep it tied to the template. */
$tpl = json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true);
$cred = [];
foreach ($tpl['nodes'] as $n) { if (!empty($n['credentials'])) { $cred[$n['name']] = array_key_first($n['credentials']); } }
$claude = array_filter($cred, static fn($type, $name) => $type === 'httpHeaderAuth' && str_starts_with($name, 'Claude - '), ARRAY_FILTER_USE_BOTH);
check('template credentialed nodes = Webhook Trigger + the six Claude nodes + Update WP Post (Rewrite), as step 3 says',
    count($cred) === 8 && count($claude) === 6 && ($cred['Update WP Post (Rewrite)'] ?? null) === 'httpBasicAuth'
    && ($cred['Webhook Trigger'] ?? null) === 'httpHeaderAuth');
$cred_names = [];
foreach ($tpl['nodes'] as $n) { foreach ($n['credentials'] ?? [] as $c) { $cred_names[] = str_replace('__PQM_SITE_HOST__', 'example.com', $c['name']); } }
$cred_names = array_values(array_unique($cred_names));
sort($cred_names);
check('the section names the template\'s three credentials, so a rename in one place fails here',
    $cred_names === ['Anthropic - Claude', 'Post Quality webhook - example.com', 'WordPress - example.com']
    && !array_filter($cred_names, static fn($cn) => !str_contains($section, 'value="' . $cn . '"')));

/* Story 40 AC4 / story 67: the intro names the manual steps and points to the key path (the n8n connection disclosure) */
$lead = '<p class="description">The steps to set up n8n by hand, and to re-import the workflow after a plugin update. To have the plugin set n8n up for you instead, connect your n8n with an API key under "n8n connection".</p>';
check('the disclosure opens with the intro, on the manual path and on a set-up site alike', str_starts_with(trim(substr(trim($section_of($fresh)), strlen('<summary><h2>Set up by hand</h2></summary>'))), $lead)
    && str_contains($section, $lead));
check('... a hand-made site with results: Set up by hand still reachable, collapsed', ($wpdb->has_result = '1') && ($disclosures_of($done = $render_workflow([]))['pqm-by-hand'] ?? null) === false
    && str_contains($section_of($done), '<ol class="pqm-workflow-steps">'));
$wpdb->has_result = null;
check('... a managed site (a setup map): no Set up by hand', $disclosures_of($render_workflow([], [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c1'], 'path' => 'x', 'wizard_created' => true, 'at' => 1]])) === ['pqm-n8n-connection' => false, 'pqm-webhook' => false]);

/* Story 67: the five states, one per matrix row — the sentence, the one primary, the body, the disclosures */
echo "n8n Workflow page — the site's state, one body (story 67)\n";
$at = 1760400000; // 2025-10-14 00:00 UTC
$failed = ['cause' => 'api_disabled', 'message' => 'n8n answered 404: the public API is turned off on this instance (N8N_PUBLIC_API_DISABLED), or this is not your n8n address. Check the address.', 'at' => $at];
$passing = ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => $at];
$key_passing = [PQM_N8n::OPTION => ['base_url' => 'https://n8n.example', 'api_key' => 'k', 'probe' => $passing]];
$no_url = ['n8n_webhook_url' => '', 'webhook_token' => 'tok-35'];
$set_up_button = '<button type="button" class="button button-primary pqm-open-setup">Set up n8n</button>';
// The JSON body rides a data attribute through esc_attr() (identity here; `&quot;` on WordPress).
$by_hand_link = '<p><a href="#pqm-by-hand" class="pqm-by-hand-link" data-pqm-post="setup" data-pqm-body="{"manual":true}" data-pqm-focus="pqm-by-hand">Set up by hand instead</a></p>';
// Row 1: not set up
$page = $render_workflow($no_url);
check('1 not set up: "Not connected to n8n yet.", the line accent, primary Set up n8n (opens the wizard), no Test again, no version line',
    $state_of($page) === 'not-set-up' && $sentence_of($page) === 'Not connected to n8n yet.' && str_contains($block_of($page), '<p class="pqm-workflow-actions">' . $set_up_button . '</p>')
    && $primaries($page) === 1 && !str_contains($page, 'pqm-test-connection') && !str_contains($page, 'pqm-workflow-version'));
check('... the paragraph names the three values and what the plugin creates; the "Set up by hand instead" link (POST setup {manual: true}, then Set up by hand focused); no disclosure',
    str_contains($page, '<p>The setup asks for three values: your n8n address, an n8n API key with an expiry date, and an Anthropic API key. The plugin then creates everything in n8n for you: the credentials, the workflow and its publication.</p>')
    && str_contains($page, $by_hand_link) && $disclosures_of($page) === [] && !str_contains($page, 'pqm-n8n-connection-root'));
$built = file_exists(dirname(__DIR__, 2) . '/post-quality-manager/admin/build/index.asset.php');
check('... the page: H1, the setup island root (or, unbuilt, the build notice), then one body region (aria-live polite) holding the renderer\'s output',
    ($built ? preg_match('/<h1>n8n Workflow<\/h1>\s*<div id="pqm-setup-root"><\/div>\s*<div id="pqm-workflow-body" aria-live="polite">/', $page) === 1 : str_contains($page, 'This page has not been built yet.'))
    && substr_count($page, 'pqm-workflow-body') === 1 && str_contains($page, PQM_Admin::render_workflow_body()));
// Row 2: connected, not set up
$page = $render_workflow($no_url, $key_passing);
check('2 connected, not set up: "Connected to n8n at {host}. The workflow is not set up yet.", info accent, primary Set up n8n, the n8n connection disclosure only, the by-hand link, no leftovers notice',
    $state_of($page) === 'connected' && $sentence_of($page) === 'Connected to n8n at n8n.example. The workflow is not set up yet.' && str_contains($page, $set_up_button) && $primaries($page) === 1
    && $disclosures_of($page) === ['pqm-n8n-connection' => false] && (!$built || str_contains($page, 'id="pqm-n8n-connection-root"')) && str_contains($page, $by_hand_link) && !str_contains($page, 'An earlier setup left'));
check('... leftovers waiting: the info notice', str_contains($render_workflow($no_url, $key_passing + [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://n8n.example', 'credentials' => ['c1'], 'at' => 1]]),
    '<div class="notice notice-info inline pqm-inline-notice"><p>An earlier setup left a workflow or credentials in n8n. Setting up removes them first.</p></div>'));
check('... a record of another instance (story 71): state 2 without the leftovers notice (the wizard names that instance instead)',
    $state_of($page = $render_workflow($no_url, $key_passing + [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://other.example', 'credentials' => ['c1'], 'at' => 1]])) === 'connected'
    && !str_contains($page, 'An earlier setup left'));
check('... a passing key with a setup map but no URL saved (the URL save refused): connected too, the wizard offers the paste-URL body', $state_of($render_workflow($no_url, $key_passing + [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c1'], 'path' => 'x', 'wizard_created' => true, 'at' => 1]])) === 'connected');
// Story 74: a refusal of the stored key replaces its probe; the page asks PQM_N8n::connected().
$key_refused = [PQM_N8n::OPTION => ['probe' => ['ok' => false, 'cause' => 'key', 'message' => 'n8n refused this API key.', 'at' => $at]] + $key_passing[PQM_N8n::OPTION]];
check('... a stored refusal of the key (story 74): not state 2 — state 1, Set up n8n the one primary; the n8n connection disclosure stays (the island shows the refusal, Forget key)',
    $state_of($page = $render_workflow($no_url, $key_refused)) === 'not-set-up' && $primaries($page) === 1 && str_contains($page, $set_up_button)
    && $disclosures_of($page) === ['pqm-n8n-connection' => false]);
check('... the same with a setup map: not connected either, no Update workflow, no Rotate token', $state_of($page = $render_workflow($no_url, $key_refused + [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c1'], 'path' => 'x', 'wizard_created' => true, 'at' => 1]])) !== 'connected'
    && !str_contains($page, 'pqm-update-workflow') && !str_contains($page, 'pqm-rotate-token'));
// Row 3: manual path
$probe_line = 'On ' . gmdate('Y-m-d', $at) . ', the n8n connection check did not pass: ' . $failed['message'];
foreach ([
    'by choice' => [$manual_site, 'You chose to set up n8n by hand.'],
    'a failed check that offered the manual path' => [[PQM_N8n::OPTION => ['failed' => $failed]], $probe_line],
    'a failed check on a completed setup: the reason still shows' => [[PQM_N8n::OPTION => ['failed' => $failed], PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]], $probe_line],
    'a failure and the choice: the failure wins' => [[PQM_N8n::OPTION => ['failed' => $failed]] + $manual_site, $probe_line],
] as $label => [$options, $reason]) {
    $page = $render_workflow($no_url, $options);
    check("3 manual path ($label): \"Setting up by hand.\" + the reason, info accent, Set up by hand open with Save and test the one primary, the n8n connection disclosure (Connect, secondary)",
        $state_of($page) === 'manual' && $sentence_of($page) === 'Setting up by hand.' && str_contains($block_of($page), '<p>' . $reason . '</p>')
        && $disclosures_of($page) === ['pqm-by-hand' => true, 'pqm-n8n-connection' => false] && $primaries($page) === 1 && str_contains($page, 'button button-primary pqm-save-webhook')
        && !str_contains($page, 'pqm-open-setup') && !str_contains($page, 'pqm-test-connection'));
}
$page = $render_workflow($no_url, $key_passing + $manual_site);
check('... the stored choice on a connected site (key + passing probe): state 3, "You chose to set up n8n by hand.", Set up by hand open (the matrix: a stored choice → manual)',
    $state_of($page) === 'manual' && str_contains($block_of($page), '<p>You chose to set up n8n by hand.</p>') && ($disclosures_of($page)['pqm-by-hand'] ?? null) === true);
// The link's round trip: state 2's "Set up by hand instead" posts {manual: true}, the page re-renders.
$GLOBALS['tw_settings'] = $no_url;
$GLOBALS['tw_options']  = $key_passing + ['date_format' => 'Y-m-d'];
check('... state 2 offers the link', str_contains($render_workflow($no_url), 'class="pqm-by-hand-link" data-pqm-post="setup"') && $state_of($render_workflow($no_url)) === 'connected');
// What POST /setup {manual: true} stores (setup-harness.php runs the route itself).
update_option(PQM_SETUP_OPTION, ['steps' => [], 'manual' => true], false);
$page = $render_workflow($no_url);
check('... after its POST setup {manual: true}: the body re-renders as state 3 with #pqm-by-hand open, the link\'s focus target', ($GLOBALS['tw_options'][PQM_SETUP_OPTION]['manual'] ?? null) === true
    && $state_of($page) === 'manual' && str_contains($page, '<details class="pqm-disclosure" id="pqm-by-hand" open>'));
$GLOBALS['tw_options'] = [];
check('... a re-check failed after a passing probe: the passing probe hides it — state 2', $state_of($render_workflow($no_url, $key_passing + [PQM_N8n::OPTION => $key_passing[PQM_N8n::OPTION] + ['failed' => $failed]])) === 'connected');
$GLOBALS['tw_options'] = ['date_format' => 'Y-m-d', PQM_N8n::OPTION => ['failed' => $failed]];
check('pqm_manual_reason(): {kind: probe, message, at in the site date format}', pqm_manual_reason() === ['kind' => 'probe', 'message' => $failed['message'], 'at' => gmdate('Y-m-d', $at)]);
$GLOBALS['tw_options'] = $manual_site;
check('pqm_manual_reason(): {kind: choice}', pqm_manual_reason() === ['kind' => 'choice']);
$GLOBALS['tw_options'] = [];
check('pqm_manual_reason(): null on a fresh site', pqm_manual_reason() === null);
// Row 4: set up (and 4a)
$current = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]];
$page = $render_workflow(['webhook_token' => 'tok-35'], $current);
$webhook_disclosure = static fn(string $html): string => preg_match('/<details class="pqm-disclosure" id="pqm-webhook">(.*?)<\/details>/s', $html, $m) ? $m[1] : '';
check('4 set up, no result: "Your workflow answers.", success accent, "Version N · checked just now by a test.", primary Run your first review (the wizard\'s review step), Test again secondary',
    $state_of($page) === 'set-up' && $sentence_of($page) === 'Your workflow answers.' && str_contains($page, '<p class="description pqm-workflow-version">Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.</p>')
    && str_contains($block_of($page), '<p class="pqm-workflow-actions"><button type="button" class="button button-primary pqm-open-setup" data-step="review">Run your first review</button> <button type="button" class="button pqm-test-connection">Test again</button></p>')
    && $primaries($page) === 1);
check('... the three disclosures, collapsed: n8n connection, Webhook, Set up by hand (hand-made: no map)', $disclosures_of($page) === ['pqm-by-hand' => false, 'pqm-n8n-connection' => false, 'pqm-webhook' => false]);
check('... Webhook: the URL field with Save and test (secondary), the token as a Copy row, its description; no Rotate token on a hand-made setup',
    str_contains($webhook_disclosure($page), $save_form('https://n8n.example/webhook', 'disclosure', 'button')) && str_contains($webhook_disclosure($page), '<span class="pqm-copy-label">Webhook token</span>')
    && str_contains($webhook_disclosure($page), 'value="tok-35"') && !str_contains($page, 'pqm-rotate-token') && str_contains($webhook_disclosure($page), 'Shared secret for both directions, always in the X-PQM-Token header: this site sends it'));
$wpdb->has_result = '1';
$page = $render_workflow([], $current);
$wpdb->has_result = null;
check('4 set up, results stored: primary Open Post Reviews', str_contains($block_of($page), '<a class="button button-primary" href="/wp-admin/admin.php?page=pqm-posts">Open Post Reviews</a>') && $primaries($page) === 1);
// The configuration steps still open (story 55) as one secondary link; the wizard resumes at the first.
$built_here = file_exists(dirname(__DIR__, 2) . '/post-quality-manager/admin/build/index.asset.php');
$finish = static fn(string $steps): string => '<button type="button" class="button-link pqm-open-setup">Finish setting up: ' . $steps . '</button>';
check('... ' . ($built_here ? 'built: "Finish setting up: Blog Profile, Editorial Standards, scheduled reviews" (Pro, none ticked) as a secondary, one primary' : 'unbuilt: no Finish setting up (no dialog to open)'),
    $built_here ? (str_contains($block_of($page), $finish('Blog Profile, Editorial Standards, scheduled reviews')) && $primaries($page) === 1) : !str_contains($page, 'Finish setting up'));
$wpdb->has_result = '1';
$page = $render_workflow([], $current + [PQM_SETUP_OPTION => ['steps' => ['profile' => true, 'schedule' => true], 'complete' => true]]);
check('... profile and schedule ticked: only Editorial Standards named', !$built_here || str_contains($block_of($page), $finish('Editorial Standards')));
$page = $render_workflow([], $current + [PQM_SETUP_OPTION => ['steps' => ['profile' => true, 'standards' => true, 'schedule' => true], 'complete' => true]]);
check('... every step saved or skipped: the link is gone, Open Post Reviews and Test again only', !str_contains($page, 'Finish setting up') && !str_contains($page, 'pqm-open-setup') && $primaries($page) === 1);
// The Free build (no scheduled-reviews step) is free-build-harness.php's row.
$page = $render_workflow([], $current + [PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]]);
check('... 4a (saved, not checked) and 4 without results carry no Finish link: the wizard is already the primary there', !str_contains($render_workflow([]), 'Finish setting up') && (($wpdb->has_result = null) === null) && !str_contains($render_workflow([], $current), 'Finish setting up'));
$wpdb->has_result = null;
$page = $render_workflow([]);
check('4a saved, not checked: "Saved, not checked yet.", info accent, primary Test the connection, no version line, the same disclosures',
    $state_of($page) === 'not-checked' && $sentence_of($page) === 'Saved, not checked yet.' && !str_contains($page, 'pqm-workflow-version')
    && str_contains($block_of($page), '<p class="pqm-workflow-actions"><button type="button" class="button button-primary pqm-test-connection">Test the connection</button></p>') && $primaries($page) === 1
    && $disclosures_of($page) === ['pqm-by-hand' => false, 'pqm-n8n-connection' => false, 'pqm-webhook' => false]);
// Row 5: needs attention
$failed_report = $current;
$failed_report['pqm_workflow_version_seen']['failed'] = ['message' => 'n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'at' => time() - 7200];
$page = $render_workflow([], $failed_report);
check('5 ping fails: "The last test {when} did not pass: {message}", error accent, primary Test again, the last passing version kept under it, the three disclosures',
    $state_of($page) === 'failing' && $sentence_of($page) === 'The last test 2 hours ago did not pass: n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'
    && str_contains($block_of($page), '<p class="pqm-workflow-actions"><button type="button" class="button button-primary pqm-test-connection">Test again</button></p>') && $primaries($page) === 1
    && str_contains($page, 'Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.') && $disclosures_of($page) === ['pqm-by-hand' => false, 'pqm-n8n-connection' => false, 'pqm-webhook' => false]);
$page = $render_workflow([], ['pqm_workflow_version_seen' => ['failed' => $failed_report['pqm_workflow_version_seen']['failed']]]);
check('... a failure before any passing check: the same, without a version line', $state_of($page) === 'failing' && !str_contains($page, 'pqm-workflow-version') && $primaries($page) === 1);
$sentence = 'In n8n, the webhook credential “Header Auth account” is set on Claude - Review Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.';
$page = $render_workflow([], ['pqm_workflow_version_seen' => $current['pqm_workflow_version_seen'] + ['miswired' => $sentence]]);
check('5 mis-wired: the diagnosis sentence is the status sentence, error accent, primary Test again, no other fix', $state_of($page) === 'failing' && $sentence_of($page) === $sentence
    && str_contains($block_of($page), 'button button-primary pqm-test-connection">Test again</button>') && $primaries($page) === 1 && !str_contains($page, 'pqm-reimport') && !str_contains($page, 'pqm-update-workflow'));
check('... the sentence is escaped once, where it renders (stored raw)', substr_count($src, "esc_html(\$sentence)") === 1 && str_contains($src, "\$sentence = \$report['miswired']; // raw"));
$page = $render_workflow([], ['pqm_workflow_version_seen' => 5]); // 5: the last version before the per-site webhook path
check('5 outdated (a legacy int, hand-made): "Your workflow is version 5; this plugin needs N.", warning accent, the new-production-URL note (pre-v6), primary Re-import the workflow (to #pqm-by-hand), Test again secondary',
    $state_of($page) === 'outdated' && $sentence_of($page) === 'Your workflow is version 5; this plugin needs ' . PQM_WORKFLOW_VERSION . '.'
    && str_contains($block_of($page), '<p>A re-import gives it a new production URL.</p>') && str_contains($block_of($page), '<p class="description pqm-workflow-version">Version 5 · reported by a command.</p>')
    && str_contains($block_of($page), '<p class="pqm-workflow-actions"><a class="button button-primary pqm-reimport" href="#pqm-by-hand">Re-import the workflow</a> <button type="button" class="button pqm-test-connection">Test again</button></p>')
    && $primaries($page) === 1 && ($disclosures_of($page)['pqm-by-hand'] ?? null) === false);
$page = $render_workflow([], ['pqm_workflow_version_seen' => 6]);
check('... a version 6 workflow (the per-site path already): outdated, no new-production-URL note (a re-import keeps its URL)',
    $state_of($page) === 'outdated' && $sentence_of($page) === 'Your workflow is version 6; this plugin needs ' . PQM_WORKFLOW_VERSION . '.' && !str_contains($block_of($page), 'A re-import gives it a new production URL.'));
// 6 = the first version with a per-site webhook path: a report of version 6 or later keeps its path, asserted on the source too.
check('... the new-production-URL note only for a workflow older than version 6 (source)',
    (bool) preg_match("/\\\$state\\['problem'\\] === 'outdated' && \\\$report\\['version'\\] < 6\\) \\{\\s*\\\$details\\[\\] = __\\('A re-import gives it a new production URL\\.'/", $src));
$page = $render_workflow([], ['pqm_workflow_version_seen' => ['version' => 0, 'via' => 'test', 'at' => time()]]);
check('5 outdated (a v1–v4 import): "an older version that does not report its number; this plugin needs N."',
    $sentence_of($page) === 'Your workflow is an older version that does not report its number; this plugin needs ' . PQM_WORKFLOW_VERSION . '.' && $state_of($page) === 'outdated');
$page = $render_workflow([], ['pqm_workflow_version_seen' => $current['pqm_workflow_version_seen'] + ['variant' => 'free']]);
check('5 Free variant on Pro: the Free-variant sentence, warning accent, Re-import primary', $state_of($page) === 'outdated'
    && $sentence_of($page) === 'Your workflow is the Free variant: it lacks the Sources, SEO, Audit and Rewrite branches.' && str_contains($page, 'button-primary pqm-reimport') && $primaries($page) === 1);
$page = $render_workflow([], ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => time(), 'variant' => 'free']]);
check('... older and Free at once: the version sentence wins, one primary', str_starts_with($sentence_of($page), 'Your workflow is version ') && $primaries($page) === 1);
$page = $render_workflow([], ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => time(), 'miswired' => $sentence]]);
check('... mis-wired and older at once (hand-made): the mis-wiring first, Test again the one primary, Re-import the workflow secondary after it, Set up by hand present',
    $sentence_of($page) === $sentence && $primaries($page) === 1
    && str_contains($block_of($page), '<p class="pqm-workflow-actions"><button type="button" class="button button-primary pqm-test-connection">Test again</button> <a class="button pqm-reimport" href="#pqm-by-hand">Re-import the workflow</a></p>')
    && ($disclosures_of($page)['pqm-by-hand'] ?? null) === false);
$page = $render_workflow([], ['pqm_workflow_version_seen' => $current['pqm_workflow_version_seen'] + ['variant' => 'free', 'miswired' => $sentence]]);
check('... mis-wired and the Free variant at once: the same, Re-import secondary', $sentence_of($page) === $sentence && $primaries($page) === 1 && str_contains($page, '<a class="button pqm-reimport" href="#pqm-by-hand">'));
$page = $render_workflow([], ['pqm_workflow_version_seen' => $current['pqm_workflow_version_seen'] + ['miswired' => '']]);
check('an empty sentence: set up', $state_of($page) === 'set-up');
$GLOBALS['tw_options'] = [];
$GLOBALS['tw_settings'] = [];

/* n8n provisioning entry 9: on a site the plugin set up or manages, the fix is Update workflow */
echo "Status block — Update workflow on a managed site (provisioning entry 9)\n";
$managed_site = [
    PQM_N8n::OPTION       => ['base_url' => 'https://n8n.example', 'api_key' => 'k', 'probe' => $passing],
    PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic'], 'path' => 'pqm-execute-example-com', 'wizard_created' => true, 'at' => 1],
];
$managed_url = ['n8n_webhook_url' => 'https://n8n.example/webhook/pqm-execute-example-com'];
$update      = '<button type="button" class="button button-primary pqm-update-workflow" data-pqm-post="n8n/workflow" data-pqm-busy="Updating the workflow…">Update workflow</button>';
$rotate      = '<p><button type="button" class="button pqm-rotate-token" data-pqm-post="n8n/token" data-pqm-busy="Rotating the token…">Rotate token</button></p>';
$in_place    = 'The plugin can update it in place: the workflow\'s nodes are replaced; its credentials, webhook path and name stay.'; // story 76 (D3)
$older       = ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => time()];
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => $older]);
check('older, managed: the primary Update workflow (POST n8n/workflow), the in-place sentence; no re-import, no "new production URL", no Set up by hand',
    str_contains($block_of($page), '<p class="pqm-workflow-actions">' . $update . ' <button type="button" class="button pqm-test-connection">Test again</button></p>') && !str_contains($page, 'pqm-reimport')
    && $primaries($page) === 1 && str_contains($block_of($page), '<p>' . $in_place . '</p>') && !str_contains($page, 'new production URL')
    && $disclosures_of($page) === ['pqm-n8n-connection' => false, 'pqm-webhook' => false]);
check('... Webhook: Rotate token (POST n8n/token) under the token row', str_contains($webhook_disclosure($page), $rotate)
    && strpos($webhook_disclosure($page), $rotate) > strpos($webhook_disclosure($page), '<span class="pqm-copy-label">Webhook token</span>'));
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1]);
check('... a pre-v6 report (a legacy int): no "new production URL" sentence either, the update keeps the URL', str_contains($page, $update)
    && str_contains($page, $in_place) && !str_contains($page, 'new production URL'));
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time(), 'variant' => 'free']]);
check('Free variant on Pro, managed: the same button', str_contains($sentence_of($page), 'Free variant') && str_contains($page, $update) && !str_contains($page, 'pqm-reimport') && $primaries($page) === 1);
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => $older + ['miswired' => $sentence]]);
check('sentence stored and older, managed: Test again the one primary, Update workflow secondary after it', $sentence_of($page) === $sentence
    && str_contains($block_of($page), '<button type="button" class="button button-primary pqm-test-connection">Test again</button> <button type="button" class="button pqm-update-workflow" data-pqm-post="n8n/workflow"')
    && $primaries($page) === 1 && ($disclosures_of($page)['pqm-by-hand'] ?? null) === null);
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time(), 'miswired' => $sentence]]);
check('sentence stored, current, managed: Test again the one primary, no Update workflow (nothing to update)', $primaries($page) === 1 && !str_contains($page, 'pqm-update-workflow') && !str_contains($page, 'pqm-reimport'));
// Story 76: an adopted map's Update asks first (the island's Modal); a provisioned one keeps its one click.
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => $older]);
check('story 76, provisioned: the Update button carries no data-pqm-confirm, the same in-place sentence', str_contains($page, $update) && !str_contains($page, 'data-pqm-confirm') && str_contains($page, $in_place));
$adopted_site = [PQM_N8n::SETUP_OPTION => ['wizard_created' => false] + $managed_site[PQM_N8n::SETUP_OPTION]] + $managed_site;
$update_asks  = str_replace('data-pqm-post="n8n/workflow"', 'data-pqm-post="n8n/workflow" data-pqm-confirm="update"', $update);
$page = $render_workflow($managed_url, $adopted_site + ['pqm_workflow_version_seen' => $older]);
check('story 76, adopted and older: the primary Update workflow carries data-pqm-confirm="update", still one primary, the same in-place sentence',
    str_contains($block_of($page), '<p class="pqm-workflow-actions">' . $update_asks . ' <button type="button" class="button pqm-test-connection">Test again</button></p>')
    && $primaries($page) === 1 && str_contains($block_of($page), '<p>' . $in_place . '</p>'));
$page = $render_workflow($managed_url, $adopted_site + ['pqm_workflow_version_seen' => $older + ['miswired' => $sentence]]);
check('... adopted, mis-wired and older: the secondary Update workflow asks too, Test again the one primary', $primaries($page) === 1
    && str_contains($block_of($page), '<button type="button" class="button pqm-update-workflow" data-pqm-post="n8n/workflow" data-pqm-confirm="update" data-pqm-busy="'));
foreach ([
    'no key'                       => static function () { unset($GLOBALS['tw_options'][PQM_N8n::OPTION]); },
    'a key without a passing probe' => static function () { $GLOBALS['tw_options'][PQM_N8n::OPTION]['probe'] = null; },
    'a stored refusal of the key (story 74)' => static function () { $GLOBALS['tw_options'][PQM_N8n::OPTION]['probe'] = ['ok' => false, 'cause' => 'key', 'message' => 'n8n refused this API key.', 'at' => 1]; },
    'no map'                       => static function () { unset($GLOBALS['tw_options'][PQM_N8n::SETUP_OPTION]); },
    'the URL on another path'      => static function () { $GLOBALS['tw_settings'] = ['n8n_webhook_url' => 'https://n8n.example/webhook/elsewhere']; },
    'the map made on another instance (story 71)' => static function () { $GLOBALS['tw_options'][PQM_N8n::SETUP_OPTION]['base_url'] = 'https://other.example'; },
] as $label => $change) {
    $GLOBALS['tw_settings'] = $managed_url;
    $GLOBALS['tw_options']  = $managed_site + ['pqm_workflow_version_seen' => $older];
    $change();
    $page = $render_workflow($GLOBALS['tw_settings']);
    check("gate fails ($label): Re-import the workflow as before, no Update workflow, no Rotate token; its target, the Set up by hand disclosure, is on the page", str_contains($page, 'button-primary pqm-reimport" href="#pqm-by-hand"')
        && !str_contains($page, 'pqm-update-workflow') && !str_contains($page, 'pqm-rotate-token') && !str_contains($page, $in_place) && $primaries($page) === 1
        && str_contains($page, '<details class="pqm-disclosure" id="pqm-by-hand">'));
}
$GLOBALS['tw_settings'] = $managed_url;
$page = $render_workflow($managed_url, $managed_site + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]]);
check('up to date, managed: set up, no update button, no Set up by hand, Rotate token still there', $state_of($page) === 'set-up' && !str_contains($page, 'pqm-update-workflow')
    && !str_contains($page, 'id="pqm-by-hand"') && str_contains($page, $rotate));
/* Without the built assets, nothing can open the dialog: the setup button is left out, the build warning says why. */
check('the setup button renders only with the built assets (source: app_built() gates $setup_button; render_app_root() uses the same check)',
    (bool) preg_match('/\$setup_button = static fn\(string \$label, string \$step = \'\'\): string => !self::app_built\(\) \? \'\' :/', $src)
    && substr_count($src, 'self::app_built()') === 3 && // the setup button, render_app_root(), the Finish setting up link
     str_contains($src, "private static function app_built(): bool\n    {\n        return file_exists(PQM_PLUGIN_DIR . 'admin/build/index.asset.php');"));
$page = $render_workflow(['n8n_webhook_url' => '', 'webhook_token' => 'tok-35']);
check('... state 1 ' . ($built ? 'built: Set up n8n is the one primary' : 'unbuilt: no Set up n8n, no primary, the build warning'), $built
    ? str_contains($page, 'button-primary pqm-open-setup">Set up n8n</button>') && $primaries($page) === 1
    : !str_contains($page, 'pqm-open-setup') && $primaries($page) === 0 && str_contains($page, 'This page has not been built yet.'));
check('the body renders no secret: not the n8n key', !str_contains($page, '"k"') && !str_contains($page, 'api_key'));
$GLOBALS['tw_options'] = [];
$GLOBALS['tw_settings'] = [];

/* Story 35: the token's reach, both directions */
echo "n8n Workflow page — secured connection copy (story 35)\n";
$settings_html = render_for(['pqm_manage_settings'], true);
check('Settings token description says both directions',
    str_contains($settings_html, 'Shared secret for both directions, always in the X-PQM-Token header: WordPress sends it to start the workflow'));
$GLOBALS['tw_settings'] = [];

/* Story 35: the ping — executed through ajax_test_connection() */
echo "Test ping — test URL refused, token header sent (story 35)\n";
class PQM_Json_Exit extends Exception { public function __construct(public bool $ok, public $data, public $status = null) { parent::__construct(); } }
/* Records the nonce action; a bad nonce dies like core's (-1, HTTP 403) before any payload. */
function check_ajax_referer($action = -1, ...$a) {
    $GLOBALS['tw_referer'][] = $action;
    if (!empty($GLOBALS['tw_bad_nonce'])) { throw new PQM_Json_Exit(false, -1, 403); }
    return true;
}
function wp_unslash($v) { return $v; }
function wp_send_json_error($data = null, $status = null) { throw new PQM_Json_Exit(false, $data, $status); }
function wp_send_json_success($data = null, $status = null) { throw new PQM_Json_Exit(true, $data, $status); }
function wp_remote_post($url, $args) {
    $GLOBALS['tw_pings'][] = [$url, $args];
    pqm_harness_ping_callback($args, $GLOBALS['tw_ping_callback'] ?? 'received'); // story 2: n8n calls the site back before the plugin reads the reply
    return $GLOBALS['tw_ping_error'] ?? ['response' => ['code' => $GLOBALS['tw_ping_code'] ?? 200], 'body' => $GLOBALS['tw_ping_body'] ?? ''];
}
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
PQM_REST_API::register_routes(); // story 2: the stub's callback lands on the registered /ping/{id} route
function ping(string $url): PQM_Json_Exit {
    $GLOBALS['tw_pings'] = [];
    $_POST = ['webhook_url' => $url];
    try { PQM_Admin::ajax_test_connection(); } catch (PQM_Json_Exit $e) { return $e; }
    return new PQM_Json_Exit(false, 'no response sent');
}
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$GLOBALS['tw_settings'] = ['webhook_token' => 'tok-35'];
foreach (['https://n8n.example.com/webhook-test/pqm-execute', 'https://acme.app.n8n.cloud/webhook-test/pqm-execute'] as $url) {
    $r = ping($url);
    check("test URL $url: error with the warning copy, nothing sent", $r->ok === false && $r->data === pqm_webhook_url_warning($url)
        && str_starts_with($r->data, 'This is an n8n test URL') && $GLOBALS['tw_pings'] === []);
}
foreach (['https://acme.app.n8n.cloud/webhook/pqm-execute', 'https://n8n.example.com/webhook/pqm-execute'] as $url) {
    $r = ping($url);
    check("production URL $url: ping sent with X-PQM-Token, success", $r->ok === true && count($GLOBALS['tw_pings']) === 1
        && $GLOBALS['tw_pings'][0][1]['headers'] === ['Content-Type' => 'application/json', 'X-PQM-Token' => 'tok-35']);
}

/* Story 36: the ping names a refused credential, and an old import's 2xx proves reachability only */
echo "Test ping — refused credential, pre-v4 import (story 36)
";
$prod = 'https://acme.app.n8n.cloud/webhook/pqm-execute';
foreach ([401, 403] as $code) {
    $GLOBALS['tw_ping_code'] = $code;
    $r = ping($prod);
    check("n8n $code: error naming the Webhook Trigger's credential, its Name and Value", $r->ok === false
        && $r->data === "n8n refused the webhook credential (HTTP $code). In n8n, open the Webhook Trigger's Header Auth credential: its Name must be X-PQM-Token and its Value this site's webhook token. Then try again.");
}
$GLOBALS['tw_ping_code'] = 200;
$GLOBALS['tw_options'] = [];
$_POST = [];
$GLOBALS['tw_settings'] = [];

/* Workflow version handshake (v5): the status block and the ping that refreshes it (story 67: the whole body) */
echo "Workflow version status and the ping handshake\n";
$saved  = 'https://n8n.example/webhook'; // the stub's stored n8n_webhook_url
$seen   = static fn() => $GLOBALS['tw_options']['pqm_workflow_version_seen'] ?? null;

/* Row: fresh / just served — option absent (the stub saves a URL: not checked yet) */
$GLOBALS['tw_options'] = [];
$page = $render_workflow([]);
$block = $block_of($page);
$island = max((int) strpos($page, 'id="pqm-setup-root"'), (int) strpos($page, 'This page has not been built yet.')); // built or not
check('fresh: one status block, right under the setup island, first in the body',
    $block !== '' && substr_count($page, 'class="pqm-workflow-status"') === 1 && $island > 0
    && $island < strpos($page, 'pqm-workflow-status') && strpos($page, 'pqm-workflow-body') < strpos($page, 'pqm-workflow-status'));
check('fresh: "Saved, not checked yet.", no version line', $state_of($page) === 'not-checked' && $sentence_of($page) === 'Saved, not checked yet.' && !str_contains($block, 'pqm-workflow-version'));
check('the page renders the body through the one renderer', str_contains($page, PQM_Admin::render_workflow_body()));

/* Row: legacy int option from a pre-v5 plugin — needs attention for N < current, no time */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
$block = $block_of(PQM_Admin::render_workflow_body());
check('legacy int ' . (PQM_WORKFLOW_VERSION - 1) . ': outdated, "Version N · reported by a command.", no time',
    str_contains($block, 'this plugin needs ' . PQM_WORKFLOW_VERSION . '.') && str_contains($block, 'Version ' . (PQM_WORKFLOW_VERSION - 1) . ' · reported by a command.')
    && !str_contains($block, 'ago') && !str_contains($block, 'just now'));
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => (string) PQM_WORKFLOW_VERSION];
check('legacy int = current (stored as a string by WP): set up', $state_of(PQM_Admin::render_workflow_body()) === 'set-up');

/* Row: a command's report, hours old */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => time() - 7200]];
$block = $block_of(PQM_Admin::render_workflow_body());
check('command report, 2 h old: set up, "Version N · checked 2 hours ago by a command."', str_contains($block, 'data-state="set-up"')
    && str_contains($block, 'Version ' . PQM_WORKFLOW_VERSION . ' · checked 2 hours ago by a command.'));

/* Row: ping, v5 reply, saved URL — starting from the "needs attention" state */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
check('before the ping: outdated', $state_of($render_workflow([])) === 'outdated');
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping(''); // the n8n Workflow page sends no URL: the saved one is pinged
$report = $seen();
check('v5 reply, saved URL: pinged the saved URL, stored {5, via test, at now}', $GLOBALS['tw_pings'][0][0] === $saved
    && is_array($report) && $report['version'] === PQM_WORKFLOW_VERSION && $report['via'] === 'test' && abs($report['at'] - time()) <= 5);
check('v5 reply, the callback received: message says reachable, credential right, workflow up to date, the round trip works (story 2)', $r->ok === true
    && $r->data['message'] === 'Ping OK — this site reaches n8n, the webhook credential is right, your workflow is up to date and n8n called this site back with the right token: the round trip works.');
check('... the ping body carried callback_url = /ping/{id} and auth_token = the webhook token; the pending ping is gone',
    (bool) preg_match('#^/wp-json/post-quality/v1/ping/[A-Za-z0-9]{20}$#', json_decode($GLOBALS['tw_pings'][0][1]['body'], true)['callback_url'] ?? '')
    && json_decode($GLOBALS['tw_pings'][0][1]['body'], true)['auth_token'] === pqm_get_settings()['webhook_token'] && !isset($GLOBALS['tw_options']['pqm_ping']));
check('v5 reply: outdated false (the page shows a success)', ($r->data['outdated'] ?? null) === false);
check('v5 reply: the shape unchanged by entry 7 (no miswired key; no n8n key stored, so no diagnosis)', array_keys($r->data) === ['message', 'outdated', 'status_html']
    && !isset($seen()['miswired']));
check('v5 reply: status_html = the renderer\'s body with the message first (a success): set up, "Version 5 · checked just now by a test."',
    ($r->data['status_html'] ?? '') === PQM_Admin::render_workflow_body($r->data['message'], 'success') && $state_of($r->data['status_html']) === 'set-up'
    && $outcome_of($r->data['status_html']) === 'is-success: ' . $r->data['message']
    && str_contains($r->data['status_html'], 'Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.'));
$r = ping($saved);
check('a posted URL equal to the saved one: recorded too, status_html sent', $r->ok === true && isset($r->data['status_html']) && $seen()['via'] === 'test');

/* Row: ping, older reply (v1–v4: n8n's default body, or not JSON) */
foreach (['n8n default body' => '{"message":"Workflow was started"}', 'non-JSON body' => 'Workflow was started', 'empty body' => ''] as $label => $body) {
    $GLOBALS['tw_options'] = [];
    $GLOBALS['tw_ping_body'] = $body;
    $r = ping('');
    check("older reply ($label): stored {0, via test}", ($seen()['version'] ?? null) === 0 && $seen()['via'] === 'test');
    check("older reply ($label): reachability only, points to re-importing, never claims the credential",
        $r->ok === true && !str_contains($r->data['message'], 'credential')
        && $r->data['message'] === 'Ping OK — this site reaches n8n, but your n8n workflow is an older version: download or re-import it on the n8n Workflow page, paste the re-imported workflow\'s production URL, from its Webhook Trigger, as the n8n Webhook URL in Settings, then send another ping. A workflow older than version 6 gets a new production URL: from version 6, each site\'s workflow has its own.');
    check("older reply ($label): outdated true (the page shows a warning, not a success)", ($r->data['outdated'] ?? null) === true);
    check("older reply ($label): status_html needs attention, the message a warning, \"an older version that does not report its number\"",
        $state_of($r->data['status_html']) === 'outdated' && $outcome_of($r->data['status_html']) === 'is-warning: ' . $r->data['message']
        && str_contains($r->data['status_html'], 'Your workflow is an older version that does not report its number; this plugin needs ' . PQM_WORKFLOW_VERSION . '.'));
}
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . (PQM_WORKFLOW_VERSION - 1) . '}';
$r = ping('');
check('reply with an older number: older-version message, needs attention, outdated true, no callback waited for', ($seen()['version'] ?? null) === PQM_WORKFLOW_VERSION - 1
    && str_contains($r->data['message'], 'an older version') && !str_contains($r->data['message'], 'call this site back') && $state_of($r->data['status_html']) === 'outdated' && $r->data['outdated'] === true);
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":5}'; // 5: the last version before the per-site webhook path
$r = ping('');
check('reply with a pre-v6 number: message and block say the re-import gives a new production URL',
    str_ends_with($r->data['message'], 'A workflow older than version 6 gets a new production URL: from version 6, each site\'s workflow has its own.') && str_contains($r->data['status_html'], 'A re-import gives it a new production URL.'));

/* Row (story 2): the callback refused or missing — a failed test, recorded, the page needs attention */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000]];
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$GLOBALS['tw_ping_callback'] = 'refused';
$r = ping('');
check('current reply, the callback refused (wrong token): error, the message names the token and the POST Result to WordPress node', $r->ok === false
    && $r->data['message'] === 'n8n reached this site, but the callback was refused: it carried a token that is not this site\'s webhook token. In n8n, open the POST Result to WordPress node and set its X-PQM-Token header to {{ $json.auth_token }}, then test again.');
check('... recorded as the failure, the version kept; the body needs attention with "did not pass", Test again the one primary; no pending ping left',
    (pqm_workflow_report()['failed']['message'] ?? '') === $r->data['message'] && pqm_workflow_report()['version'] === PQM_WORKFLOW_VERSION
    && $state_of($r->data['status_html']) === 'failing' && $outcome_of($r->data['status_html']) === 'is-error: ' . $r->data['message']
    && $sentence_of($r->data['status_html']) === 'The last test just now did not pass: ' . $r->data['message'] && $primaries($r->data['status_html']) === 1 && !isset($GLOBALS['tw_options']['pqm_ping']));
$GLOBALS['tw_ping_callback'] = 'none';
$r = ping('');
check('current reply, no callback within the wait: error, the message names the wait, this site\'s REST address and the execution log', $r->ok === false
    && $r->data['message'] === 'n8n accepted the ping but did not call this site back within 0 seconds. n8n must reach /wp-json/ (this site\'s REST API): a local, private or password-protected site cannot be called back. Check the ping\'s execution in n8n, then test again.'
    && $state_of($r->data['status_html']) === 'failing' && (pqm_workflow_report()['failed']['message'] ?? '') === $r->data['message'] && !isset($GLOBALS['tw_options']['pqm_ping']));
$GLOBALS['tw_ping_callback'] = 'received';
$r = ping('');
check('... the next ping with the callback received clears the failure: set up, the round trip proven', $r->ok === true && !isset(pqm_workflow_report()['failed']) && $state_of($r->data['status_html']) === 'set-up');
check('the wait is the filter pqm_ping_callback_wait, PING_WAIT = 8 s by default, one read per 500 ms with the options cache bypassed (source)',
    (bool) preg_match("/apply_filters\('pqm_ping_callback_wait', self::PING_WAIT\)/", $src) && PQM_Admin::PING_WAIT === 8
    && (bool) preg_match("/wp_cache_delete\(self::PING_OPTION, 'options'\);.*?usleep\(500000\);/s", $src));

/* Row: ping, unsaved URL (a posted URL ≠ saved) — message only */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping('https://other.example/webhook/pqm-execute');
check('unsaved URL: up-to-date message, outdated false, nothing stored, no status_html', $r->ok === true
    && str_contains($r->data['message'], 'your workflow is up to date') && !isset($r->data['status_html']) && $r->data['outdated'] === false
    && $seen() === PQM_WORKFLOW_VERSION - 1);

/* Rows (story 54): the template variant on the Pro build (the harness runs the Pro build) */
$free_notice = 'Your workflow is the Free variant: it lacks the Sources, SEO, Audit and Rewrite branches.';
$GLOBALS['tw_options'] = [];
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . ',"workflow_variant":"free"}';
$r = ping('');
check('Free template answers on the Pro build: stored {5, via test, variant free}',
    ($seen()['variant'] ?? null) === 'free' && $seen()['version'] === PQM_WORKFLOW_VERSION && $seen()['via'] === 'test');
check('... outdated true, the message names the Free variant and points to re-importing', $r->ok === true && $r->data['outdated'] === true
    && $r->data['message'] === 'Ping OK — this site reaches n8n, but your n8n workflow is the Free variant, without the Sources, SEO, Audit and Rewrite branches: download or re-import it on the n8n Workflow page, paste the re-imported workflow\'s production URL, from its Webhook Trigger, as the n8n Webhook URL in Settings, then send another ping.');
check('... status_html: the Free-variant sentence once, not the version one',
    $sentence_of($r->data['status_html']) === $free_notice && !str_contains($r->data['status_html'], 'this plugin needs'));
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping('');
check('legacy v5 reply (no variant): read as Pro, up to date, set up', !isset($seen()['variant'])
    && $r->data['outdated'] === false && $state_of($r->data['status_html']) === 'set-up');
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . ',"workflow_variant":"pro"}';
$r = ping('');
check('a reply stating pro: stored without a variant, up to date', !isset($seen()['variant']) && $r->data['outdated'] === false);

/* Row: ping fails — the failure recorded on the report (story 67), the version kept; the body needs attention */
$before = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => 1700000000];
foreach ([401, 403, 404, 500] as $code) {
    $GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $before];
    $GLOBALS['tw_ping_code'] = $code;
    $r = ping('');
    check("ping fails (HTTP $code): error {message, status_html}, the failure recorded with its message, the version kept", $r->ok === false && is_array($r->data)
        && array_keys($r->data) === ['message', 'status_html'] && pqm_workflow_report() === $before + ['failed' => ['message' => $r->data['message'], 'at' => pqm_workflow_report()['failed']['at'] ?? 0]]
        && abs((pqm_workflow_report()['failed']['at'] ?? 0) - time()) <= 5);
    check("ping fails (HTTP $code): the body needs attention (failing), the message its outcome line and its sentence, Test again the one primary",
        $state_of($r->data['status_html']) === 'failing' && $outcome_of($r->data['status_html']) === 'is-error: ' . $r->data['message']
        && $sentence_of($r->data['status_html']) === 'The last test just now did not pass: ' . $r->data['message'] && $primaries($r->data['status_html']) === 1
        && str_contains($r->data['status_html'], 'button button-primary pqm-test-connection">Test again</button>'));
}
$GLOBALS['tw_ping_code'] = 200;
$GLOBALS['tw_ping_error'] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
$r = ping('');
check('ping fails (WP_Error): "Could not connect" message, recorded',
    $r->ok === false && $r->data['message'] === 'Could not connect to n8n: cURL error 28: Operation timed out' && (pqm_workflow_report()['failed']['message'] ?? null) === $r->data['message']);
unset($GLOBALS['tw_ping_error']);
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping('');
check('a passing ping after: the failure cleared, set up', !isset(pqm_workflow_report()['failed']) && $state_of($r->data['status_html']) === 'set-up');
$GLOBALS['tw_ping_code'] = 404;
$GLOBALS['tw_options'] = [];
ping('https://other.example/webhook/pqm-execute');
check('a failed ping of an unsaved URL records nothing', $seen() === null);
$GLOBALS['tw_ping_code'] = 200;
unset($GLOBALS['tw_ping_body']);

/* Row: tab regains focus, the dialog closes — ajax_workflow_status() answers the body, never pings (D1) */
$status_read = static function (): PQM_Json_Exit {
    $GLOBALS['tw_pings'] = [];
    $GLOBALS['tw_referer'] = [];
    try { PQM_Admin::ajax_workflow_status(); } catch (PQM_Json_Exit $e) { return $e; }
    return new PQM_Json_Exit(false, 'no response sent');
};
$stored = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => time() - 7200];
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $stored];
$r = $status_read();
check('status read: checks the page nonce (pqm_admin_action)', $GLOBALS['tw_referer'] === ['pqm_admin_action']);
check('status read: answers {status_html} = the renderer\'s body, no outcome line, nothing else', $r->ok === true
    && $r->data === ['status_html' => PQM_Admin::render_workflow_body()] && str_contains($r->data['status_html'], 'checked 2 hours ago by a command')
    && !str_contains($r->data['status_html'], 'pqm-workflow-outcome'));
check('status read: never pings n8n, writes nothing', $GLOBALS['tw_pings'] === [] && $GLOBALS['tw_options'] === ['pqm_workflow_version_seen' => $stored]);
unset($GLOBALS['tw_options']['pqm_workflow_version_seen']);
check('status read after a serve (option gone): "Saved, not checked yet."', str_contains($status_read()->data['status_html'], 'Saved, not checked yet.'));
$GLOBALS['tw']['caps'] = ['pqm_manage_agents', 'pqm_view_reviews'];
$r = $status_read();
check('status read without pqm_manage_settings: 403 error, no body', $r->ok === false && $r->status === 403 && is_string($r->data));
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$GLOBALS['tw_bad_nonce'] = true;
$r = $status_read();
check('status read with a bad or expired nonce: dies (-1, 403) before any body', $r->ok === false && $r->data === -1 && $r->status === 403);
unset($GLOBALS['tw_bad_nonce']);
check('the status read is hooked as wp_ajax_pqm_workflow_status, beside the ping',
    str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php'),
        "add_action('wp_ajax_pqm_workflow_status', ['PQM_Admin', 'ajax_workflow_status']);"));

/* Row: the webhook URL changes — the report described the old URL's workflow */
$report = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()];
$old = ['n8n_webhook_url' => 'https://old.example/webhook/pqm-execute', 'cron_enabled' => false];
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $report];
PQM_Admin::on_settings_updated('pqm_settings', $old, ['n8n_webhook_url' => 'https://new.example/webhook/pqm-execute'] + $old);
check('settings saved with another webhook URL: report forgotten ("not checked yet")', $seen() === null);
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $report];
PQM_Admin::on_settings_updated('pqm_settings', $old, ['cron_enabled' => true] + $old);
check('settings saved with the same webhook URL: report kept', $seen() === $report);
PQM_Admin::on_settings_updated('blogname', 'a', 'b');
check('another option updated: report untouched', $seen() === $report);
check('updated_option is hooked with its three args (old and new value)',
    str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php'),
        "add_action('updated_option', ['PQM_Admin', 'on_settings_updated'], 10, 3);"));

/* The setup dialog swaps the body after its connect and its provisioning; every close asks for a refresh (src/, tests/js/) */
$steps_js = file_get_contents(dirname(__DIR__, 2) . '/src/setup/steps.js');
$setup_js = file_get_contents(dirname(__DIR__, 2) . '/src/setup/index.js');
$swap_js  = file_get_contents(dirname(__DIR__, 2) . '/src/kit/swap-status-block.js'); // the one swap for React callers
$entry_js = file_get_contents(dirname(__DIR__, 2) . '/src/index.js');
check('the setup dialog swaps #pqm-workflow-body\'s content with status_html (connect and provisioning), warns when outdated, and asks for a refresh on close',
    str_contains($swap_js, "document.getElementById( 'pqm-workflow-body' )") && str_contains($swap_js, 'body.innerHTML = html')
    && str_contains($steps_js, "import swapStatusBlock from '../kit/swap-status-block';")
    && str_contains($steps_js, 'swapStatusBlock( data.status_html )') && str_contains($steps_js, 'swapStatusBlock( reply.status_html )')
    && (bool) preg_match("/data\\.outdated \\) \\{\\s*status = 'warning';/", $steps_js)
    && str_contains($setup_js, 'requestWorkflowRefresh( SETUP_CLOSED_EVENT, focus )') && !str_contains($setup_js, 'window.location.reload'));
check('the n8n connection island remounts on every swap, secondary on the page; no island reads the block for a primary button any more',
    str_contains($entry_js, 'document.addEventListener( WORKFLOW_STATUS_EVENT, mountConnection )') && str_contains($entry_js, '<N8nConnection variant="secondary" />')
    && !file_exists(dirname(__DIR__, 2) . '/src/kit/use-reimport-shown.js'));

$GLOBALS['tw_options'] = [];
$_POST = [];
$GLOBALS['tw_settings'] = [];

/* Story 36: the setup dialog island — mount point, assets, welcome notice */
echo "n8n Workflow page — setup dialog island (story 36)\n";
$built = file_exists(dirname(__DIR__, 2) . '/post-quality-manager/admin/build/index.asset.php');
foreach (['fresh site' => ['n8n_webhook_url' => ''], 'connected site' => []] as $label => $settings) {
    $html = $render_workflow($settings);
    check("$label: the island's root div (or, unbuilt, the build notice) sits under the title",
        $built ? substr_count($html, '<div id="pqm-setup-root"></div>') === 1 : str_contains($html, 'This page has not been built yet.'));
    check("$label: no inline wizard", !str_contains($html, 'pqm-wizard'));
}
check('fresh site: the status block\'s Set up n8n opens the dialog (.pqm-open-setup); nothing else on the page', str_contains($render_workflow(['n8n_webhook_url' => '']), 'pqm-open-setup')
    && !str_contains($render_workflow(['n8n_webhook_url' => '']), 'pqm-copy-button'));
check('connected site: Set up by hand (import-URL copy) and the test button render', str_contains($html, 'pqm-copy-button') && str_contains($html, 'pqm-test-connection'));
$GLOBALS['tw_settings'] = [];

/* The Workflow hook loads both the app bundle (island) and pqm-admin.js (ping, copy) */
$GLOBALS['tw_enqueued'] = [];
function add_menu_page($pt, $mt, $cap, $slug, $cb = null, $icon = '', $pos = null) { return 'toplevel_page_' . $slug; }
function add_submenu_page($parent, $pt, $mt, $cap, $slug, $cb = null) { return 'post-quality_page_' . $slug; }
function wp_enqueue_script($handle, ...$a) { $GLOBALS['tw_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$a) {}
function wp_localize_script($handle, $name, $data) { $GLOBALS['tw_localized'][$handle] = $data; }
function wp_set_script_translations(...$a) {}
function wp_add_inline_script(...$a) {}
function wp_style_add_data(...$a) {}
PQM_Admin::register_pages();
PQM_Admin::enqueue_assets('post-quality_page_pqm-workflow');
check('Workflow page enqueues pqm-admin.js' . ($built ? ' and the app bundle' : ' (app bundle skipped: unbuilt)'),
    in_array('pqm-admin', $GLOBALS['tw_enqueued'], true) && ($built ? in_array('pqm-app', $GLOBALS['tw_enqueued'], true) : true));
check('Copy feedback: the import URL\'s own line, a generic "Copied." for every other row, a "value is selected" fallback',
    ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copied'] ?? null) === 'Copied! Paste it in n8n under "Import", then "From URL".'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copiedValue'] ?? null) === 'Copied.'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copyFailed'] ?? null) === 'Could not copy automatically. The value is selected — press Ctrl+C.');
check('the inline wizard\'s i18n keys are gone', !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['saving'])
    && !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['savedPingFailed']));
check('the REST actions (story 67): the REST root and a wp_rest nonce are localized; the busy labels ride the buttons (data-pqm-busy), the test\'s and the network line in i18n; no Settings-field line',
    ($GLOBALS['tw_localized']['pqm-admin']['restUrl'] ?? null) === '/wp-json/post-quality/v1/'
    && ($GLOBALS['tw_localized']['pqm-admin']['restNonce'] ?? null) === 'nonce'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['testing'] ?? null) === 'Testing the connection…'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['networkError'] ?? null) === 'The request did not go through. Check your connection and try again.'
    && !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['updating']) && !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['enterWebhookUrl']));
$GLOBALS['tw_enqueued'] = [];
PQM_Admin::enqueue_assets('post-quality_page_pqm-settings');
check('Settings page: pqm-admin.js only, no app bundle', $GLOBALS['tw_enqueued'] === ['pqm-admin']);

/* Welcome notice: until setup is complete, managers only, links to the auto-opening dialog */
$GLOBALS['tw_screen'] = 'toplevel_page_pqm-dashboard';
function get_current_screen() { return (object) ['id' => $GLOBALS['tw_screen']]; }
$notice_for = static function (array $caps, array $settings, array $options): string {
    $GLOBALS['tw']['caps'] = $caps;
    $GLOBALS['tw_settings'] = $settings;
    $GLOBALS['tw_options'] = $options;
    ob_start();
    PQM_Admin::admin_notices();
    $out = ob_get_clean();
    $GLOBALS['tw_options'] = [];
    $GLOBALS['tw_settings'] = [];
    return $out;
};
/* pqm_setup_complete() asks PQM_DB::has_result(): one get_var, stubbed per row */
$wpdb = new class { public $prefix = 'wp_'; public $has_result = null; public function get_var($sql) { return $this->has_result; } };
$manager = ['pqm_manage_settings'];
$link = 'href="/wp-admin/admin.php?page=pqm-workflow&pqm_setup=1"';
$html = $notice_for($manager, ['n8n_webhook_url' => ''], []);
check('fresh site: notice offers "Set up the workflow", linked to the auto-opening dialog',
    str_contains($html, '>Set up the workflow</a>') && str_contains($html, $link));
check('connected, no result yet, no progress: notice still shows', str_contains($notice_for($manager, [], []), $link));
$wpdb->has_result = '1';
check('legacy connected + a result stored, no progress option: no notice', $notice_for($manager, [], []) === '');
check('result stored but URL emptied since: notice shows again', str_contains($notice_for($manager, ['n8n_webhook_url' => ''], []), $link));
$wpdb->has_result = null;
check('version handshake seen (a ping\'s report) but no real result stored: notice still shows', str_contains($notice_for($manager, [], ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]]), $link));
check('progress complete (the dialog\'s first review): no notice', $notice_for($manager, ['n8n_webhook_url' => ''], [PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]]) === '');
check('non-manager: no notice', $notice_for(['pqm_manage_agents', 'pqm_view_reviews'], ['n8n_webhook_url' => ''], []) === '');
$GLOBALS['tw_screen'] = 'post-quality_page_pqm-workflow';
check('n8n Workflow page itself: no notice (the button is there)', $notice_for($manager, ['n8n_webhook_url' => ''], []) === '');
$GLOBALS['tw']['caps'] = [];

/* Story 35: sanitizer warnings are printed on this custom page after a save */
echo "Settings save notice (story 35)
";
$_GET['settings-updated'] = 'true';
$GLOBALS['tw_settings_errors_for'] = [];
render_for(['pqm_manage_settings'], true);
unset($_GET['settings-updated']);
check('after a save, render_settings() prints settings_errors(pqm_settings)', $GLOBALS['tw_settings_errors_for'] === ['pqm_settings']);

/* Story 26: the radio is the setting — muted preview vs. Custom-only field */
echo "AI Prompts & Context rows (story 26)\n";
/** The opening tag of the element with this id. */
$tag_of = static function (string $html, string $id): string {
    return preg_match('/<[a-z]+[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/', $html, $m) ? $m[0] : '';
};
$is_hidden = static fn(string $tag): bool => (bool) preg_match('/\shidden(\s|>|=)/', $tag);
$technical = PQM_Admin::blog_profiles()['technical']['website_context'];
$direct    = pqm_voice_presets()['direct']['instruction'];

$GLOBALS['tw_settings'] = ['blog_profile' => 'technical', 'website_context' => 'My context',
    'brand_voice_preset' => 'direct', 'brand_voice' => 'My rules'];
$html = render_for($admin_caps, true);
check('preset: profile preview holds the Technical text',
    preg_match('/<p class="description pqm-preset-text" id="pqm_profile_text"[^>]*>' . preg_quote($technical, '/') . '<\/p>/', $html) === 1);
check('preset: voice preview holds the Direct text',
    preg_match('/<p class="description pqm-preset-text" id="pqm_voice_text"[^>]*>' . preg_quote($direct, '/') . '<\/p>/', $html) === 1);
check('preset: previews shown', !$is_hidden($tag_of($html, 'pqm_profile_text')) && !$is_hidden($tag_of($html, 'pqm_voice_text')));
check('preset: Custom wrappers hidden', $is_hidden($tag_of($html, 'pqm_context_field')) && $is_hidden($tag_of($html, 'pqm_voice_field')));
foreach (['pqm_website_context' => 'website_context', 'pqm_brand_voice' => 'brand_voice'] as $id => $key) {
    $tag = $tag_of($html, $id);
    check("preset: hidden $id keeps name pqm_settings[$key]", strpos($tag, 'name="pqm_settings[' . $key . ']"') !== false);
    check("preset: hidden $id is not disabled", strpos($tag, 'disabled') === false);
}
check('preset: hidden textareas keep the stored custom text',
    strpos($html, '>My context</textarea>') !== false && strpos($html, '>My rules</textarea>') !== false);
check('previews announce changes (aria-live)',
    strpos($tag_of($html, 'pqm_profile_text'), 'aria-live="polite"') !== false && strpos($tag_of($html, 'pqm_voice_text'), 'aria-live="polite"') !== false);
check('both radio groups are named by a legend',
    strpos($html, '<legend class="screen-reader-text">Blog Profile</legend>') !== false
    && strpos($html, '<legend class="screen-reader-text">Editorial Standards</legend>') !== false);

$GLOBALS['tw_settings'] = ['blog_profile' => 'custom', 'website_context' => 'My context',
    'brand_voice_preset' => 'custom', 'brand_voice' => 'My rules'];
$html = render_for($admin_caps, true);
check('Custom: previews hidden', $is_hidden($tag_of($html, 'pqm_profile_text')) && $is_hidden($tag_of($html, 'pqm_voice_text')));
check('Custom: wrappers shown', !$is_hidden($tag_of($html, 'pqm_context_field')) && !$is_hidden($tag_of($html, 'pqm_voice_field')));
check('Custom: context description names the empty default', strpos($html, 'Empty: the AI assumes "a general audience blog".') !== false);
check('Custom: standards description names the empty default', strpos($html, 'Empty: the AI falls back to "professional yet accessible, active voice".') !== false);
$GLOBALS['tw_settings'] = [];

/* Story 27: the Settings tabs — which tabs per role and plan, each heading in its panel */
echo "Settings tabs (story 27)\n";
$headings = [
    'License' => '>License</h2>', 'n8n Connection' => '>n8n Connection</h2>', 'AI Model' => '>AI Model</h2>',
    'AI Prompts & Context' => '>AI Prompts & Context</h2>', 'What Each Agent Sends to Claude' => '>What Each Agent Sends to Claude</h2>',
    'Agent-Specific Overrides' => '>Agent-Specific Overrides',
    'Automated Reviews' => '>Automated Reviews (Cron)', 'Monthly Quota' => '>Monthly Quota</h2>',
    'What each role can do' => '>What each role can do',
];
/** slug => the panel's HTML, from its opening tag to the next panel or the submit button. */
function tab_panels(string $html): array {
    preg_match_all('/<div class="pqm-tab-panel(?: is-active)?" id="pqm-panel-([a-z]+)">/', $html, $m, PREG_OFFSET_CAPTURE);
    $panels = [];
    foreach ($m[1] as $i => [$slug]) {
        $from = $m[0][$i][1];
        $to = $m[0][$i + 1][1] ?? strpos($html, '<input type="submit" />');
        $panels[$slug] = substr($html, $from, $to - $from);
    }
    return $panels;
}
$tab_cases = [
    // Story 67: the per-agent table (What Each Agent Sends to Claude) sits in AI, after the prompts it reads.
    'Admin'         => [$admin_caps, ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context', 'What Each Agent Sends to Claude', 'Agent-Specific Overrides'], 'automation' => ['Automated Reviews'], 'usage' => ['Monthly Quota']]],
    'Editor'        => [$editor_caps, ['ai' => ['Agent-Specific Overrides'], 'automation' => ['Automated Reviews']]],
    'Settings-only' => [['pqm_manage_settings'], ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context', 'What Each Agent Sends to Claude'], 'usage' => ['Monthly Quota']]],
    // Story 42: core manage_options adds Roles, last.
    'Site admin'    => [array_merge($admin_caps, ['manage_options']), ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context', 'What Each Agent Sends to Claude', 'Agent-Specific Overrides'], 'automation' => ['Automated Reviews'],
        'usage' => ['Monthly Quota'], 'roles' => ['What each role can do']]],
];
foreach ($tab_cases as $who => [$caps, $expect]) {
    foreach ([[true, 'licensed'], [false, 'free site']] as [$pro, $plan]) {
        $html   = render_for($caps, $pro);
        $panels = tab_panels($html);
        $slugs  = array_keys($expect);
        preg_match_all('/<a href="#tab-([a-z]+)" id="pqm-tab-\1" class="nav-tab( nav-tab-active)?">/', $html, $m);
        check("$who, $plan: tabs " . implode(', ', $slugs) . ', panels the same, in order', $m[1] === $slugs && array_keys($panels) === $slugs);
        check("$who, $plan: the first tab and only its panel start active",
            $m[2] === array_merge([' nav-tab-active'], array_fill(0, count($slugs) - 1, ''))
            && substr_count($html, 'pqm-tab-panel is-active') === 1 && str_contains($html, '<div class="pqm-tab-panel is-active" id="pqm-panel-' . $slugs[0] . '">'));
        check("$who, $plan: the hash guard names the first tab and sits before the panels",
            ($guard = strpos($html, 'location.hash !== "#tab-' . $slugs[0] . '"')) !== false
            && $guard < strpos($html, 'class="pqm-tab-panel'));
        $want = $placed = [];
        foreach ($expect as $slug => $names) { foreach ($names as $name) { $want[$name] = $slug; } }
        foreach ($headings as $name => $needle) {
            $in = array_keys(array_filter($panels, static fn($p) => str_contains($p, $needle)));
            if ($in || str_contains($html, $needle)) { $placed[$name] = count($in) === 1 && substr_count($html, $needle) === 1 ? $in[0] : 'misplaced'; }
        }
        ksort($want);
        ksort($placed);
        check("$who, $plan: each heading sits once, in its panel; the others are absent", $placed === $want);
        check("$who, $plan: no empty panel, each one closed before the next",
            array_filter($panels, static fn($p, $slug) => str_contains($p, '<h2') && substr_count($p, '<div') === substr_count($p, '</div>')
                && str_ends_with(rtrim($p), "</div><!-- #pqm-panel-$slug -->"), ARRAY_FILTER_USE_BOTH) === $panels);
        $anchors = array_intersect_key(['AI Prompts & Context' => 'pqm-context-settings',
            'Automated Reviews' => 'pqm-cron-settings', 'Monthly Quota' => 'pqm-quota-settings'], $want);
        check("$who, $plan: the deep-link anchors sit in their sections' panels", array_filter($anchors,
            static fn($id, $name) => str_contains($panels[$want[$name]] ?? '', "id=\"$id\""), ARRAY_FILTER_USE_BOTH) === $anchors);
        if (isset($expect['automation'])) {
            /** Whether the panel holds one locked fieldset that also closes inside it. */
            $locked_in = static function (string $p) use ($locked_fieldset): bool {
                $open = strpos($p, $locked_fieldset);
                return substr_count($p, $locked_fieldset) === 1 && strpos($p, '</fieldset>', $open) !== false;
            };
            $roles_tab = isset($expect['roles']); // story 42: its own lock, in its own panel
            check("$who, $plan: " . ($pro ? 'no locked fieldset' : 'the Overrides lock sits in AI, the Cron lock in Automation'
                . ($roles_tab ? ', the Roles lock in Roles' : '')), $pro
                ? !str_contains($html, $locked_fieldset)
                : substr_count($html, $locked_fieldset) === 2 + (int) $roles_tab && $locked_in($panels['ai']) && $locked_in($panels['automation'])
                    && (!$roles_tab || $locked_in($panels['roles'])));
        }
    }
}
check('tab labels: Connection, AI, Automation, Usage & Limits',
    preg_match_all('/class="nav-tab(?: nav-tab-active)?">([^<]+)<\/a>/', render_for($admin_caps, true), $m) === 4
    && $m[1] === ['Connection', 'AI', 'Automation', 'Usage & Limits']);
$css = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/css/pqm-admin.css');
preg_match_all('/^([^{}\n]*\.pqm-tab-panel[^{]*)\{[^}]*display:\s*none/m', $css, $m);
// .pqm-tabs-pending is set only by the inline script, so it is JS-only too.
check('JS off: the tab bar hides (.no-js) and panels hide only under .js or .pqm-tabs-pending', str_contains($css, '.no-js .pqm-tabs { display: none; }')
    && $m[1] !== [] && array_filter($m[1], static fn($sel) => str_starts_with(trim($sel), '.js ')
        || str_starts_with(trim($sel), '.pqm-tabs-pending ')) === $m[1]);
/* Story 67: the per-agent table moved to Settings → AI; Settings → Connection shows the URL read-only and links the token to the page */
$settings_html = render_for($admin_caps, true);
$connection_panel = tab_panels($settings_html)['connection'] ?? '';
check('Settings → AI: the per-agent table, after the AI Prompts & Context table; the n8n Workflow page has none',
    ($t = strpos(tab_panels($settings_html)['ai'] ?? '', '<table class="widefat striped pqm-llm-preview">')) !== false && $t > strpos(tab_panels($settings_html)['ai'], 'Editorial Standards')
    && !str_contains($render_workflow([]), 'pqm-llm-preview') && !str_contains($render_workflow([]), 'What Each Agent Sends'));
check('Settings → Connection: the URL read-only with "Change it on the n8n Workflow page", no field, no Send test ping; the token row a link to the page; Notification URL kept',
    str_contains($connection_panel, '<code class="pqm-settings-readonly">https://n8n.example/webhook</code>')
    && str_contains($connection_panel, 'Change it on the <a href="/wp-admin/admin.php?page=pqm-workflow">n8n Workflow page</a>.')
    && !str_contains($connection_panel, 'pqm_settings[n8n_webhook_url]') && !str_contains($connection_panel, 'pqm_settings[webhook_token]') && !str_contains($connection_panel, 'pqm-test-connection')
    && str_contains($connection_panel, '<a href="/wp-admin/admin.php?page=pqm-workflow">Shown on the n8n Workflow page</a>')
    && str_contains($connection_panel, 'name="pqm_settings[notification_url]"'));
$GLOBALS['tw_settings'] = ['n8n_webhook_url' => ''];
$n = render_for($admin_caps, true);
check('... no URL saved: "Not set."', str_contains(tab_panels($n)['connection'] ?? '', '<span class="pqm-settings-readonly">Not set.</span>'));
check('... the "New here?" notice links to the n8n Workflow page only (no "paste its webhook URL")',
    str_contains($n, 'New here? Set up the n8n workflow first; everything about it lives on one page.')
    && str_contains($n, '>Open the n8n Workflow page</a>') && !str_contains($n, 'paste'));
$GLOBALS['tw_settings'] = [];

/* Story 42: the Roles tab — manage_options only, the site's roles × the five caps */
echo "Roles tab (story 42)\n";
$site_admin_caps = array_merge($admin_caps, ['manage_options']);
$GLOBALS['tw_roles'] = [
    // A role editor left the Administrator without two caps: the pinned row still shows all five.
    'administrator' => ['Administrator', ['manage_options' => true, 'pqm_view_reviews' => true, 'pqm_run_free_agents' => true, 'pqm_manage_settings' => true]],
    'editor'        => ['Editor', ['pqm_view_reviews' => true, 'pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true, 'pqm_manage_agents' => true]],
    'author'        => ['Author', ['pqm_run_free_agents' => true]], // View reviews removed on the tab
    'writer'        => ['Writer', ['pqm_view_reviews' => false]],   // custom role, an explicit deny
];
$role_columns = ['View reviews', 'Run Review and Key Takeaways', 'Run paid agents', 'Manage agents', 'Manage settings'];
$role_hint_text = 'Without this, the role does not see Dashboard or Post Reviews.</span>';
$quota_note = 'A role with Run paid agents is not subject to the monthly quota';
$marker = '<input type="hidden" name="pqm_settings[roles_posted]" value="1" />';
check('tab labels with manage_options: Connection, AI, Automation, Usage & Limits, Roles',
    preg_match_all('/class="nav-tab(?: nav-tab-active)?">([^<]+)<\/a>/', render_for($site_admin_caps, true), $m) === 5
    && $m[1] === ['Connection', 'AI', 'Automation', 'Usage & Limits', 'Roles']);
foreach ([[true, 'licensed'], [false, 'free site']] as [$pro, $plan]) {
    $html  = render_for($site_admin_caps, $pro);
    $panel = tab_panels($html)['roles'] ?? '';
    check("$plan: the panel opens with the headline", str_contains($panel, '>What each role can do'));
    preg_match_all('/<th scope="col">([^<]+)<\/th>/', $panel, $m);
    check("$plan: columns Role + the five plain-action labels, in capabilities() order", $m[1] === array_merge(['Role'], $role_columns));
    preg_match_all('/<th scope="row">([^<]+)/', $panel, $m);
    check("$plan: one row per site role, translated display names, custom role included", array_map('trim', $m[1]) === ['[Administrator]', '[Editor]', '[Author]', '[Writer]']);
    $visible = strip_tags($panel);
    check("$plan: no pqm_ slug in the visible text", !str_contains($visible, 'pqm_'));
    check("$plan: no \"rights\" or manage_options wording in the visible text (brand.md jargon rule)",
        !preg_match('/\bright(s)?\b/i', $visible) && !str_contains($visible, 'manage_options') && !str_contains($visible, "site's options"));
    check("$plan: the intro explains locked rows in plain words",
        str_contains($panel, 'Saving applies these choices to every user with that role. Locked rows belong to roles that run the whole site, such as Administrator: you cannot take anything away from them.'));
    preg_match_all('/<input type="checkbox" name="pqm_settings\[roles\]\[([a-z_]+)\]\[\]" value="(pqm_[a-z_]+)"\s+aria-label="([^"]*)"(?: aria-describedby="([^"]*)")?( checked)?( disabled)? \/>/', $panel, $boxes, PREG_SET_ORDER);
    $grid = [];
    foreach ($boxes as $box) { $grid[$box[1]][$box[2]] = [!empty($box[5]), !empty($box[6]), $box[3], $box[4] ?? '']; }
    check("$plan: 4 rows × 5 checkboxes, columns in capabilities() order",
        count($boxes) === 20 && array_map('array_keys', $grid) === array_fill_keys(['administrator', 'editor', 'author', 'writer'], array_keys(PQM_Features::capabilities())));
    if ($pro) {
        check('licensed: the manage_options row is all checked and disabled, even for the caps its role lacks',
            array_map(static fn($c) => [$c[0], $c[1]], $grid['administrator'] ?? []) === array_fill_keys(array_keys(PQM_Features::capabilities()), [true, true]));
    } else {
        check('free site: the manage_options row is disabled and shows its current grants, like every row',
            array_map(static fn($c) => [$c[0], $c[1]], $grid['administrator'] ?? []) === [
                'pqm_view_reviews' => [true, true], 'pqm_run_free_agents' => [true, true], 'pqm_run_paid_agents' => [false, true],
                'pqm_manage_agents' => [false, true], 'pqm_manage_settings' => [true, true]]);
    }
    preg_match_all('/<input type="hidden" name="pqm_settings\[roles_shown\]\[([a-z_]+)\]" value="([^"]*)" \/>/', $panel, $m);
    $shown = array_combine($m[1], $m[2]);
    $checked_in = static fn($slug) => implode(',', array_keys(array_filter($grid[$slug] ?? [], static fn($c) => $c[0])));
    check("$plan: each unpinned row posts roles_shown = exactly the caps it rendered checked; the pinned row posts none",
        array_keys($shown) === ['editor', 'author', 'writer']
        && $shown === array_combine(array_keys($shown), array_map($checked_in, array_keys($shown)))
        && $shown['writer'] === '' && $shown['author'] === 'pqm_run_free_agents');
    $held = static fn($slug) => array_map(static fn($c) => $c[0], $grid[$slug] ?? []);
    check("$plan: other rows checked exactly where the role holds the cap now, none disabled",
        $held('editor') === ['pqm_view_reviews' => true, 'pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true, 'pqm_manage_agents' => true, 'pqm_manage_settings' => false]
        && $held('author') === ['pqm_view_reviews' => false, 'pqm_run_free_agents' => true, 'pqm_run_paid_agents' => false, 'pqm_manage_agents' => false, 'pqm_manage_settings' => false]
        && !in_array(true, $held('writer'), true)
        && array_filter(array_merge(...array_map('array_values', array_values(array_diff_key($grid, ['administrator' => 1])))), static fn($c) => $c[1]) === []);
    check("$plan: each box is named by role and column (aria-label)", ($grid['author']['pqm_run_paid_agents'][2] ?? '') === '[Author]: Run paid agents');
    check("$plan: the hint sits right after each row's View reviews box, nowhere else, linked by aria-describedby",
        substr_count($panel, $role_hint_text) === 4
        && preg_match_all('/value="pqm_view_reviews"[^>]*aria-describedby="(pqm-role-hint-[a-z_]+)"[^>]*\/><span class="pqm-role-hint" id="\1">' . preg_quote($role_hint_text, '/') . '/', $panel) === 4
        && count(array_filter(array_merge(...array_map('array_values', array_values($grid))), static fn($c) => $c[3] !== '')) === 4);
    check("$plan: the paid-agents quota note sits under the table",
        ($note = strpos($panel, $quota_note)) !== false && $note > strpos($panel, '</table>'));
    check("$plan: one roles_posted marker, before the table", substr_count($panel, $marker) === 1 && strpos($panel, $marker) < strpos($panel, '<table'));
    if ($pro) {
        check('licensed: no lock, no locked note, no fieldset in the panel',
            !str_contains($panel, 'dashicons-lock') && !str_contains($panel, 'pqm-locked-note') && !str_contains($panel, '<fieldset'));
    } else {
        $open = strpos($panel, $locked_fieldset);
        check('free site: lock icon in the headline and the upgrade note, before the lock',
            (bool) preg_match('/>What each role can do <span class="dashicons dashicons-lock pqm-section-lock" aria-hidden="true"><\/span><\/h2>/', $panel)
            && ($up = strpos($panel, '<p class="description pqm-locked-note">Available with Pro. Upgrade to choose what each role can do — until then, every role keeps what is shown here.</p>')) !== false && $up < $open);
        check('free site: one disabled fieldset wraps the marker, roles_shown, the table and the note (nothing posts)',
            $open !== false && $open < strpos($panel, $marker) && $open < strpos($panel, 'pqm_settings[roles_shown]')
            && strpos($panel, '</fieldset>', $open) > strpos($panel, $quota_note));
    }
}
foreach (['Admin without manage_options' => $admin_caps, 'Editor' => $editor_caps, 'Settings-only' => ['pqm_manage_settings']] as $who => $caps) {
    foreach ([true, false] as $pro) {
        $html = render_for($caps, $pro);
        check("$who, " . ($pro ? 'licensed' : 'free site') . ': no Roles tab, panel or field in the markup',
            !str_contains($html, 'tab-roles') && !str_contains($html, 'pqm-panel-roles') && !str_contains($html, 'pqm_settings[roles')
            && !str_contains($html, 'What each role can do'));
    }
}
check('CSS: the hint is hidden unless its View reviews box is unchecked (no JS)',
    (bool) preg_match('/^\.pqm-role-hint \{ display: none;/m', $css)
    && (bool) preg_match('/^\.pqm-roles-table input:not\(:checked\) \+ \.pqm-role-hint \{ display: inline; \}/m', $css));
$GLOBALS['tw_roles'] = [];

harness_exit();
