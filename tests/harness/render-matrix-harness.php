<?php
/**
 * Render harness for story 5 — covers the render-level I/O matrix rows:
 * which Settings sections appear per capability set, and the locked
 * fieldsets + upgrade copy per plan state. WP stubs, no WP install.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = ['caps' => [], 'pro' => false];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function add_action(...$a) {}
/* Ticket 12: add_filter records its calls (WP's real arity) so a hook's callback is asserted, not grepped. */
$GLOBALS['tw_filters'] = [];
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['tw_filters'][] = [$hook_name, $callback, $priority, $accepted_args];
    return true;
}
function register_setting($option_group, $option_name, $args = []) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return ''; }
function plugin_basename($f) { return basename($f); }
function wp_generate_uuid4() { return 'stub-uuid'; }
function get_option($k, $d = false) { if (isset($GLOBALS['tw_options'][$k])) { return $GLOBALS['tw_options'][$k]; } return $k === 'pqm_settings' ? array_merge(['n8n_webhook_url' => 'https://n8n.example/webhook'], $GLOBALS['tw_settings']) : $d; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $tag === 'pqm_is_pro' ? $GLOBALS['tw']['pro'] : $value; }
function __($s, $d = null) { return $s; }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; } // already the site locale
function restore_previous_locale() { return false; }
function _e($s, $d = null) { echo $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html_e($s, $d = null) { echo $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_attr_e($s, $d = null) { echo $s; }
function esc_html($s) { return (string) $s; }
function esc_attr($s) { return (string) $s; }
function esc_url($s) { return (string) $s; }
function esc_url_raw($s) { return (string) $s; }
function esc_textarea($s) { return (string) $s; }
function wp_kses_post($s) { return (string) $s; }
/* Story 36: the build notice (CI's php job runs unbuilt) goes through wp_kses. */
function wp_kses($s, $allowed_html, $allowed_protocols = []) { return (string) $s; }
/* Story 27: the tab-hash guard prints inline under the tab bar. */
function wp_print_inline_script_tag($data, $attributes = []) { echo "<script>$data</script>"; }
function admin_url($p = '') { return '/wp-admin/' . $p; }
function settings_fields($g) { echo "<!-- settings_fields:$g -->"; }
function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) { $GLOBALS['tw_settings_errors_for'][] = $setting; }
function submit_button() { echo '<input type="submit" />'; }
function checked($a, $b = true) { if ((string) $a === (string) $b) { echo " checked"; } }
function selected($a, $b = true) { if ((string) $a === (string) $b) { echo " selected"; } }
function disabled($a, $b = true) { if ($a == $b) { echo " disabled"; } }
function sanitize_text_field($s) { return (string) $s; }
function sanitize_textarea_field($s) { return (string) $s; }
function sanitize_key($s) { return strtolower((string) $s); }
function absint($n) { return abs((int) $n); }
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
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
/* The workflow version report: written by the ping, read by the status block. */
function update_option($k, $v, $autoload = null) { $GLOBALS['tw_options'][$k] = $v; return true; }
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

/* ─── Load the plugin bootstrap (defines pqm_* helpers, loads classes) ── */
require __DIR__ . '/fs-stub.php'; // the Freemius SDK stand-in (story 44)
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Runner ─────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

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
$get_pro = '<a href="https://postquality.com/pricing">Get Pro</a>';
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

/* n8n Workflow page: the Workflow File section, the manual path (n8n provisioning, entry 2; story 40) */
echo "n8n Workflow page — Workflow File: four steps, Copy rows, links (entry 2)\n";
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
/* pqm_setup_complete() asks PQM_DB::has_result() once a webhook URL is saved: one get_var, stubbed per row */
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
$section_of = static fn(string $html): string => preg_match('/Workflow File<\/h2>.*?<h2/s', $html, $m) ? $m[0] : '';
$fresh = $render_workflow(['n8n_webhook_url' => '', 'webhook_token' => 'tok-35']);
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
check('step 4: save, Publish (older n8n: Active), copy the production URL, paste it in Settings, then the test ping',
    str_contains($section, 'Save the workflow, then click "Publish" in the header above the canvas (older n8n versions: switch the "Active" toggle on).')
    && str_contains($section, 'Double-click the Webhook Trigger, choose "Production URL" and copy the address')
    && str_contains($section, 'save, then come back and use "Send test ping" below.')
    && strpos($section, 'Publish and connect') < strpos($section, 'Production URL'));
check('step 4 warns that a Test URL (/webhook-test/) is refused, as the dialog does',
    str_contains($section, '<p class="description">Not the Test URL: an address with /webhook-test/ only listens while the n8n editor is open, so this site refuses it.</p>'));

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
check('... each row: the field\'s aria-label = the Copy button\'s object, naming the credential inside a credential block; aria-live feedback, no id',
    count($rows) === substr_count($section, 'pqm-copy-row') && !array_filter($rows, static fn($r) => $r[3] !== $r[4])
    && array_column($rows, 3) === [
        'Workflow import URL',
        'Post Quality webhook - example.com: Credential name', 'Post Quality webhook - example.com: Name', 'Post Quality webhook - example.com: Value',
        'Anthropic - Claude: Credential name', 'Anthropic - Claude: Name',
        'WordPress - example.com: Credential name', 'WordPress - example.com: User',
    ]
    && !str_contains($section, ' id="'));
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
$app_off_section = $section_of($render_workflow(['webhook_token' => 'tok-35']));
$GLOBALS['tw_app_passwords'] = true;
check('Application Passwords turned off: says so in words, no profile link, only Rewrite needs it',
    str_contains($app_off_section, '<p>' . $app_off . '</p>') && !str_contains($app_off_section, 'profile.php')
    && str_contains($app_off_section, 'WordPress - example.com: User'));
check('Anthropic key: links to the API keys page of the Anthropic Console, as the dialog names it',
    str_contains($section, 'Value: your Anthropic API key. Create one on the <a href="https://platform.claude.com/settings/keys">API keys page of the Anthropic Console</a>.'));
check('step 4 links to the Connection tab of Settings',
    str_contains($step4, '<a href="/wp-admin/admin.php?page=pqm-settings#tab-connection">the Connection tab of Settings</a>'));
$n8n_link = static fn(string $s): ?string => preg_match('/In <a href="([^"]+)">your n8n<\/a>, open a new, empty workflow/', $s, $m) ? $m[1] : null;
check('n8n link: only the webhook URL saved → its origin', $n8n_link($section) === 'https://n8n.example');
$with_base = $section_of($render_workflow([], [PQM_N8n::OPTION => ['base_url' => 'https://n8n.owner.example:8443', 'api_key' => 'n8n-key-SECRET-42', 'probe' => null]]));
check('n8n link: a stored n8n Instance address wins', $n8n_link($with_base) === 'https://n8n.owner.example:8443');
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

/* Story 40 AC4: the novice is handed to the dialog first */
$lead = 'These are the steps to set up n8n by hand, and to re-import the workflow. To have n8n set up for you with an n8n API key, use "Set up the workflow" at the top of this page.';
check('setup not complete: the first line names the manual steps and points to the guided setup', str_starts_with(trim(substr($section_of($fresh), strlen('Workflow File</h2>'))), '<p class="description">' . $lead . '</p>'));
check('connected, no result yet: still recommended', str_contains($section, $lead));
check('setup complete (the dialog\'s first review): no recommendation, steps unchanged',
    !str_contains($done = $section_of($render_workflow([], [PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]])), $lead)
    && str_contains($done, '<ol class="pqm-workflow-steps">'));
$wpdb->has_result = '1';
check('legacy connected + a result stored: no recommendation', !str_contains($section_of($render_workflow([])), $lead));
$wpdb->has_result = null;

/* Why the reader is here (pqm_manual_reason()): a stored failure, the owner's choice, or nothing */
$at = 1760400000; // 2025-10-14 00:00 UTC
$failed = ['cause' => 'api_disabled', 'message' => 'n8n answered 404: the public API is turned off on this instance (N8N_PUBLIC_API_DISABLED), or this is not your n8n address. Check the address.', 'at' => $at];
$probe_line = 'On ' . gmdate('Y-m-d', $at) . ', the n8n Instance check did not pass: ' . $failed['message'] . ' Set up the workflow by hand with the steps below.';
$choice_line = 'You chose to set up n8n by hand: follow the steps below.';
$passing = ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => $at];
foreach ([
    'fresh site: no reason line' => [[], null],
    'a stored failure: the dated line with its cause sentence' => [[PQM_N8n::OPTION => ['failed' => $failed]], $probe_line],
    'a stored failure on a completed setup: the line still shows' => [[PQM_N8n::OPTION => ['failed' => $failed], PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]], $probe_line],
    'by choice: "You chose to set up n8n by hand"' => [[PQM_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]], $choice_line],
    'a failure and the choice: the failure line wins' => [[PQM_N8n::OPTION => ['failed' => $failed], PQM_SETUP_OPTION => ['steps' => [], 'manual' => true]], $probe_line],
    'choice, then connected (key + passing probe): no reason line' => [[PQM_N8n::OPTION => ['base_url' => 'https://n8n.example', 'api_key' => 'k', 'probe' => $passing], PQM_SETUP_OPTION => ['steps' => [], 'manual' => true]], null],
    'a re-check failed after a passing probe: the passing probe hides it' => [[PQM_N8n::OPTION => ['base_url' => 'https://n8n.example', 'api_key' => 'k', 'probe' => $passing, 'failed' => $failed]], null],
] as $label => [$options, $line]) {
    $s = $section_of($render_workflow([], $options));
    $shown = array_values(array_filter([$probe_line, $choice_line], static fn($l) => str_contains($s, $l)));
    check($label, $shown === ($line === null ? [] : [$line])
        && ($line === null || str_contains($s, '<div class="notice notice-info inline pqm-inline-notice"><p>' . $line . '</p></div>')));
}
$GLOBALS['tw_options'] = ['date_format' => 'Y-m-d', PQM_N8n::OPTION => ['failed' => $failed]];
check('pqm_manual_reason(): {kind: probe, message, at in the site date format}', pqm_manual_reason() === ['kind' => 'probe', 'message' => $failed['message'], 'at' => gmdate('Y-m-d', $at)]);
$GLOBALS['tw_options'] = [PQM_SETUP_OPTION => ['manual' => true]];
check('pqm_manual_reason(): {kind: choice}', pqm_manual_reason() === ['kind' => 'choice']);
$GLOBALS['tw_options'] = [];
check('pqm_manual_reason(): null on a fresh site', pqm_manual_reason() === null);

$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
$outdated = $render_workflow([]);
check('update notice renders for an older import and no longer promises untouched credentials',
    str_contains($outdated, 'Workflow update available.') && !str_contains($outdated, 'your plugin settings stay as they are')
    && !str_contains($outdated, 'credentials are untouched'));
check('update notice tells a re-importing site its production URL changes (provisioning entry 3)',
    str_contains($outdated, '<strong>Workflow update available.</strong>')
    && str_contains($outdated, 'Your n8n workflow is older than this plugin needs.')
    && str_contains($outdated, 'A re-import gives it a new production URL.'));
// A report of version 6 or later against a newer template keeps its per-site path: no new URL.
// Not renderable while PQM_WORKFLOW_VERSION is 6 (a v6 report is current), so the guard is
// asserted on the source: the clause renders only under `version < 6`.
check('update notice: the new-production-URL clause only for a workflow older than version 6 (source)',
    (bool) preg_match('/if \(\$report\[\'version\'\] < 6\) \{\s*esc_html_e\(\'A re-import gives it a new production URL\.\'/', $src));

/* The update notice carries the one primary action (plan-update-notice-primary-action) */
$reimport = '<p><a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p>';
$status_of = static fn(): string => PQM_Admin::render_workflow_status();
$notices_of = static fn(string $html): array => preg_match_all('/<div class="notice notice-warning inline pqm-inline-notice">.*?<\/div>/s', $html, $m) ? $m[0] : [];
$primaries = static fn(string $html): int => substr_count($html, 'button-primary');
$only_in_first = static fn(string $html): bool => count($notices_of($html)) >= 1
    && substr_count($html, $reimport) === 1 && str_contains($notices_of($html)[0], $reimport);
check('older workflow: the update notice ends with the primary "Re-import the workflow" button to #pqm-workflow-file, the page\'s only primary',
    $only_in_first($status = $status_of()) && str_ends_with(trim(str_replace('</div>', '', $notices_of($status)[0])), $reimport)
    && $primaries($outdated) === 1 && str_contains($outdated, '<h2 id="pqm-workflow-file" tabindex="-1">'));
check('... the notice no longer says "Download or re-import it below"', !str_contains($outdated, 'Download or re-import it below'));
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time(), 'variant' => 'free']];
check('Free variant on Pro: the same button, in that notice', $only_in_first($status = $status_of()) && count($notices_of($status)) === 1
    && str_contains($notices_of($status)[0], 'Your workflow is the Free variant'));
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => time(), 'variant' => 'free']];
check('older and Free at once: two notices, one button, in the first (the update notice)', $only_in_first($status = $status_of())
    && count($notices_of($status)) === 2 && str_contains($notices_of($status)[0], 'Workflow update available.') && $primaries($status) === 1);
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]];
check('up to date: no notice, no button', $notices_of($status = $status_of()) === [] && !str_contains($status, 'pqm-reimport'));
$GLOBALS['tw_options'] = [];
check('not checked yet: no notice, no button', $notices_of($status = $status_of()) === [] && !str_contains($status, 'pqm-reimport'));

/* n8n provisioning entry 7: a stored mis-wiring sentence comes first, its Test again the one primary */
echo "Status block — the mis-wiring sentence (provisioning entry 7)\n";
$sentence    = 'In n8n, the webhook credential “Header Auth account” is set on Claude - Review Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.';
$test_again = '<p><button type="button" class="button button-primary pqm-test-connection">Test again</button>
                <span class="pqm-test-result" aria-live="polite"></span></p>';
$error_of    = static fn(string $html): string => preg_match('/<div class="notice notice-error inline pqm-inline-notice">.*?<\/div>/s', $html, $m) ? $m[0] : '';
$secondary   = '<p><a class="button pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p>';
$any_notice  = static fn(string $html): bool => (bool) preg_match('/class="notice notice-/', $html);
$one_primary = static fn(string $html): bool => $any_notice($html) ? $primaries($html) === 1 : $primaries($html) === 0;
$states      = [];
foreach ([
    'up to date'           => ['version' => PQM_WORKFLOW_VERSION],
    'older'                => ['version' => PQM_WORKFLOW_VERSION - 1],
    'Free variant on Pro'  => ['version' => PQM_WORKFLOW_VERSION, 'variant' => 'free'],
    'older and Free'       => ['version' => PQM_WORKFLOW_VERSION - 1, 'variant' => 'free'],
] as $label => $report) {
    $GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $report + ['via' => 'test', 'at' => time(), 'miswired' => $sentence]];
    $states[] = $status = $status_of();
    check("sentence stored, $label: an error notice first, the sentence and Test again (a .pqm-test-connection) with its own result line",
        ($error = $error_of($status)) !== '' && strpos($status, 'class="notice notice-error') === strpos($status, 'class="notice ')
        && str_contains($error, '<p>' . $sentence . '</p>') && str_contains($error, $test_again) && substr_count($status, 'pqm-test-result') === 1);
    check("... $label: Test again is the one primary; every re-import button secondary", $primaries($status) === 1
        && substr_count($status, 'pqm-reimport') === substr_count($status, $secondary) && str_contains($status, 'Template version: '));
}
check('... the sentence is escaped once, where it renders (stored raw)', substr_count($src, 'esc_html($miswired)') === 1);
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time(), 'miswired' => '']];
check('an empty sentence: no error notice, no button', $error_of($status = $status_of()) === '' && $primaries($status) === 0);
foreach ([
    [], // not checked yet
    ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1],
    ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]],
    ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time(), 'variant' => 'free']],
    ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'command', 'at' => time(), 'variant' => 'free']],
] as $options) {
    $GLOBALS['tw_options'] = $options;
    $states[] = $status_of();
}
check('every state (' . count($states) . '): at most one .button-primary in the block, one whenever it shows a notice',
    !array_filter($states, static fn($s) => !$one_primary($s)));
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
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function wp_remote_post($url, $args) {
    $GLOBALS['tw_pings'][] = [$url, $args];
    return $GLOBALS['tw_ping_error'] ?? ['response' => ['code' => $GLOBALS['tw_ping_code'] ?? 200], 'body' => $GLOBALS['tw_ping_body'] ?? ''];
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public $code = '', public $message = '', public $data = null) {}
        public function get_error_message() { return $this->message; }
    }
}
function is_wp_error($t) { return $t instanceof WP_Error; }
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

/* Workflow version handshake (v5): the status block and the ping that refreshes it */
echo "Workflow version status and the ping handshake\n";
$status_of = static function (string $html): string {
    return preg_match('/<div class="pqm-workflow-status">.*?<p class="description">.*?<\/p>\s*<\/div>/s', $html, $m) ? $m[0] : '';
};
$notice = 'Workflow update available.';
$saved  = 'https://n8n.example/webhook'; // the stub's stored n8n_webhook_url
$seen   = static fn() => $GLOBALS['tw_options']['pqm_workflow_version_seen'] ?? null;

/* Row: fresh / just served — option absent */
$GLOBALS['tw_options'] = [];
$page = $render_workflow([]);
$block = $status_of($page);
$island = max((int) strpos($page, 'id="pqm-setup-root"'), (int) strpos($page, 'This page has not been built yet.')); // built or not
check('fresh: one status block, right under the setup island, before Workflow File',
    $block !== '' && substr_count($page, 'class="pqm-workflow-status"') === 1 && $island > 0
    && $island < strpos($page, 'pqm-workflow-status') && strpos($page, 'pqm-workflow-status') < strpos($page, 'Workflow File</h2>'));
check('fresh: "not checked yet — send a test ping", no notice', !str_contains($block, $notice)
    && str_contains($block, 'Template version: ' . PQM_WORKFLOW_VERSION . ' · Your n8n workflow: not checked yet — send a test ping once it is imported and active.'));
check('the page renders the block through the one renderer', str_contains($page, PQM_Admin::render_workflow_status()));

/* Row: legacy int option from a pre-v5 plugin — notice for N < current, no time */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
$block = PQM_Admin::render_workflow_status();
check('legacy int ' . (PQM_WORKFLOW_VERSION - 1) . ': notice, "version N, reported by a command", no time',
    str_contains($block, $notice) && str_contains($block, 'Your n8n workflow: version ' . (PQM_WORKFLOW_VERSION - 1) . ', reported by a command.')
    && !str_contains($block, 'ago') && !str_contains($block, 'just now'));
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => (string) PQM_WORKFLOW_VERSION];
check('legacy int = current (stored as a string by WP): no notice', !str_contains(PQM_Admin::render_workflow_status(), $notice));

/* Row: a command's report, hours old */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => time() - 7200]];
$block = PQM_Admin::render_workflow_status();
check('command report, 2 h old: "checked 2 hours ago by a command", no notice', !str_contains($block, $notice)
    && str_contains($block, 'Your n8n workflow: version ' . PQM_WORKFLOW_VERSION . ', checked 2 hours ago by a command.'));

/* Row: ping, v5 reply, saved URL — starting from the "update available" state */
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
check('before the ping: the notice shows', str_contains($status_of($render_workflow([])), $notice));
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping(''); // the n8n Workflow page sends no URL: the saved one is pinged
$report = $seen();
check('v5 reply, saved URL: pinged the saved URL, stored {5, via test, at now}', $GLOBALS['tw_pings'][0][0] === $saved
    && is_array($report) && $report['version'] === PQM_WORKFLOW_VERSION && $report['via'] === 'test' && abs($report['at'] - time()) <= 5);
check('v5 reply: message says reachable, credential right, workflow up to date', $r->ok === true
    && $r->data['message'] === 'Ping OK — this site reaches n8n, the webhook credential is right and your workflow is up to date. Your first review confirms the return path.');
check('v5 reply: outdated false (the page shows a success)', ($r->data['outdated'] ?? null) === false);
check('v5 reply: the shape unchanged by entry 7 (no miswired key; no n8n key stored, so no diagnosis)', array_keys($r->data) === ['message', 'outdated', 'status_html']
    && !isset($seen()['miswired']));
check('v5 reply: status_html = the renderer\'s block, notice gone, "version 5, checked just now by a test ping"',
    ($r->data['status_html'] ?? '') === PQM_Admin::render_workflow_status() && !str_contains($r->data['status_html'], $notice)
    && str_contains($r->data['status_html'], 'Your n8n workflow: version ' . PQM_WORKFLOW_VERSION . ', checked just now by a test ping.'));
$r = ping($saved);
check('Settings field holding the saved URL: recorded too, status_html sent', $r->ok === true && isset($r->data['status_html']) && $seen()['via'] === 'test');

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
    check("older reply ($label): status_html shows the notice and \"an older version\"",
        str_contains($r->data['status_html'], $notice)
        && str_contains($r->data['status_html'], 'Your n8n workflow: an older version that does not report its number, checked just now by a test ping.'));
}
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . (PQM_WORKFLOW_VERSION - 1) . '}';
$r = ping('');
check('reply with an older number: older-version message, the notice, outdated true', ($seen()['version'] ?? null) === PQM_WORKFLOW_VERSION - 1
    && str_contains($r->data['message'], 'an older version') && str_contains($r->data['status_html'], $notice) && $r->data['outdated'] === true);
check('reply with an older number: message and notice say the re-import gives a new production URL',
    str_ends_with($r->data['message'], 'A workflow older than version 6 gets a new production URL: from version 6, each site\'s workflow has its own.') && str_contains($r->data['status_html'], 'A re-import gives it a new production URL.'));

/* Row: ping, unsaved URL (Settings field ≠ saved) — message only */
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
check('... status_html carries the Free-variant notice once, not the update one',
    substr_count($r->data['status_html'], $free_notice) === 1 && !str_contains($r->data['status_html'], $notice));
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}';
$r = ping('');
check('legacy v5 reply (no variant): read as Pro, up to date, no Free-variant notice', !isset($seen()['variant'])
    && $r->data['outdated'] === false && !str_contains($r->data['status_html'], $free_notice));
$GLOBALS['tw_ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . ',"workflow_variant":"pro"}';
$r = ping('');
check('a reply stating pro: stored without a variant, up to date', !isset($seen()['variant']) && $r->data['outdated'] === false);

/* Row: ping fails — messages unchanged, nothing stored */
$before = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => 1700000000];
foreach ([401, 403, 404, 500] as $code) {
    $GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => $before];
    $GLOBALS['tw_ping_code'] = $code;
    $r = ping('');
    check("ping fails (HTTP $code): error string, nothing stored", $r->ok === false && is_string($r->data) && $seen() === $before);
}
$GLOBALS['tw_ping_code'] = 200;
$GLOBALS['tw_ping_error'] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
$r = ping('');
check('ping fails (WP_Error): "Could not connect" string, nothing stored',
    $r->ok === false && $r->data === 'Could not connect to n8n: cURL error 28: Operation timed out' && $seen() === $before);
unset($GLOBALS['tw_ping_error'], $GLOBALS['tw_ping_body']);

/* Row: tab regains focus — ajax_workflow_status() answers the block, never pings (D1) */
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
check('status read: answers {status_html} = the renderer\'s block, nothing else', $r->ok === true
    && $r->data === ['status_html' => PQM_Admin::render_workflow_status()] && str_contains($r->data['status_html'], 'checked 2 hours ago by a command'));
check('status read: never pings n8n, writes nothing', $GLOBALS['tw_pings'] === [] && $GLOBALS['tw_options'] === ['pqm_workflow_version_seen' => $stored]);
unset($GLOBALS['tw_options']['pqm_workflow_version_seen']);
check('status read after a serve (option gone): "not checked yet"', str_contains($status_read()->data['status_html'], 'not checked yet'));
$GLOBALS['tw']['caps'] = ['pqm_manage_agents', 'pqm_view_reviews'];
$r = $status_read();
check('status read without pqm_manage_settings: 403 error, no block', $r->ok === false && $r->status === 403 && is_string($r->data));
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$GLOBALS['tw_bad_nonce'] = true;
$r = $status_read();
check('status read with a bad or expired nonce: dies (-1, 403) before any block', $r->ok === false && $r->data === -1 && $r->status === 403);
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

/* The setup dialog swaps the block after its connect and its provisioning (pqm-admin.js: tests/js/workflow-status.test.js) */
$steps_js = file_get_contents(dirname(__DIR__, 2) . '/src/setup/steps.js');
$swap_js  = file_get_contents(dirname(__DIR__, 2) . '/src/kit/swap-status-block.js'); // the one swap since n8n provisioning entry 10
check('the setup dialog replaces .pqm-workflow-status with status_html (connect and provisioning) and warns when outdated',
    str_contains($swap_js, "document.querySelector( '.pqm-workflow-status' )") && str_contains($swap_js, 'block.outerHTML = html')
    && str_contains($steps_js, "import swapStatusBlock from '../kit/swap-status-block';")
    && str_contains($steps_js, 'swapStatusBlock( data.status_html )') && str_contains($steps_js, 'swapStatusBlock( reply.status_html )')
    && (bool) preg_match("/data\\.outdated \\) \\{\\s*status = 'warning';/", $steps_js));

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
    check("$label: connected sections always render (Workflow File, import-URL copy, Connection Test)",
        str_contains($html, 'Workflow File</h2>') && str_contains($html, 'pqm-copy-button') && str_contains($html, 'pqm-test-connection'));
}
$GLOBALS['tw_settings'] = [];

/* The Workflow hook loads both the app bundle (island) and pqm-admin.js (ping, copy) */
$GLOBALS['tw_enqueued'] = [];
function add_menu_page($pt, $mt, $cap, $slug, $cb = null, $icon = '', $pos = null) { return 'toplevel_page_' . $slug; }
function add_submenu_page($parent, $pt, $mt, $cap, $slug, $cb = null) { return 'content-quality_page_' . $slug; }
function wp_enqueue_script($handle, ...$a) { $GLOBALS['tw_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$a) {}
function wp_localize_script($handle, $name, $data) { $GLOBALS['tw_localized'][$handle] = $data; }
function wp_set_script_translations(...$a) {}
function wp_add_inline_script(...$a) {}
function wp_style_add_data(...$a) {}
PQM_Admin::register_pages();
PQM_Admin::enqueue_assets('content-quality_page_pqm-workflow');
check('Workflow page enqueues pqm-admin.js' . ($built ? ' and the app bundle' : ' (app bundle skipped: unbuilt)'),
    in_array('pqm-admin', $GLOBALS['tw_enqueued'], true) && ($built ? in_array('pqm-app', $GLOBALS['tw_enqueued'], true) : true));
check('Copy feedback: the import URL\'s own line, a generic "Copied." for every other row, a "value is selected" fallback',
    ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copied'] ?? null) === 'Copied! Paste it in n8n under "Import", then "From URL".'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copiedValue'] ?? null) === 'Copied.'
    && ($GLOBALS['tw_localized']['pqm-admin']['i18n']['copyFailed'] ?? null) === 'Could not copy automatically. The value is selected — press Ctrl+C.');
check('the inline wizard\'s i18n keys are gone', !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['saving'])
    && !isset($GLOBALS['tw_localized']['pqm-admin']['i18n']['savedPingFailed']));
$GLOBALS['tw_enqueued'] = [];
PQM_Admin::enqueue_assets('content-quality_page_pqm-settings');
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
$GLOBALS['tw_screen'] = 'content-quality_page_pqm-workflow';
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
    'AI Prompts & Context' => '>AI Prompts & Context</h2>', 'Agent-Specific Overrides' => '>Agent-Specific Overrides',
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
    'Admin'         => [$admin_caps, ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context', 'Agent-Specific Overrides'], 'automation' => ['Automated Reviews'], 'usage' => ['Monthly Quota']]],
    'Editor'        => [$editor_caps, ['ai' => ['Agent-Specific Overrides'], 'automation' => ['Automated Reviews']]],
    'Settings-only' => [['pqm_manage_settings'], ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context'], 'usage' => ['Monthly Quota']]],
    // Story 42: core manage_options adds Roles, last.
    'Site admin'    => [array_merge($admin_caps, ['manage_options']), ['connection' => ['License', 'n8n Connection'],
        'ai' => ['AI Model', 'AI Prompts & Context', 'Agent-Specific Overrides'], 'automation' => ['Automated Reviews'],
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
check('n8n Workflow page: the Settings link opens the AI tab', str_contains($render_workflow([]), 'href="/wp-admin/admin.php?page=pqm-settings#tab-ai"'));
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

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
