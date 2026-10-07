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

/* ─── Load the plugin bootstrap (defines pqm_* helpers, loads classes) ── */
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

/* n8n Workflow page: the credentials hint sits next to both import paths */
echo "n8n Workflow page — credentials hint\n";
if (!function_exists('wp_nonce_url')) { function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . '&_wpnonce=stub'; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url = '') { return $url . '?' . http_build_query((array) $args); } }
$hint = 'n8n says "Credentials … are not set" after the import?';
$render_workflow = static function (array $settings): string {
    $GLOBALS['tw_settings'] = $settings;
    ob_start();
    PQM_Admin::render_workflow();
    return ob_get_clean();
};
/* Story 36: the inline wizard is gone — every site gets the connected sections, once */
$fresh = $render_workflow(['n8n_webhook_url' => '']);
$connected = $render_workflow([]);
foreach (['fresh site' => $fresh, 'connected site' => $connected] as $label => $html) {
    preg_match('/Workflow File<\/h2>.*?<h2/s', $html, $m);
    check("$label: the hint sits in the Workflow File section, once", str_contains($m[0] ?? '', $hint) && substr_count($html, $hint) === 1);
}
check('no second "activate" line after the hint', !str_contains($connected, 'Then activate the workflow in n8n.'));
check('hint names the nodes to open', str_contains($connected, 'the Webhook Trigger, the six Claude nodes and Update WP Post (Rewrite)'));
$GLOBALS['tw_options'] = ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1];
$outdated = $render_workflow([]);
$GLOBALS['tw_options'] = [];
check('update notice renders for an older import and no longer promises untouched credentials',
    str_contains($outdated, 'Workflow update available.') && str_contains($outdated, 'your plugin settings stay as they are')
    && !str_contains($outdated, 'credentials are untouched'));
/* The hint names the credentialed nodes literally — keep it tied to the template. */
$tpl = json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true);
$cred = [];
foreach ($tpl['nodes'] as $n) { if (!empty($n['credentials'])) { $cred[$n['name']] = array_key_first($n['credentials']); } }
$claude = array_filter($cred, static fn($type, $name) => $type === 'httpHeaderAuth' && str_starts_with($name, 'Claude - '), ARRAY_FILTER_USE_BOTH);
check('template credentialed nodes = Webhook Trigger + the six Claude nodes + Update WP Post (Rewrite), as the hint says',
    count($cred) === 8 && count($claude) === 6 && ($cred['Update WP Post (Rewrite)'] ?? null) === 'httpBasicAuth'
    && ($cred['Webhook Trigger'] ?? null) === 'httpHeaderAuth');
$GLOBALS['tw_settings'] = [];

/* Story 35: the third credential, the reachability prerequisite, the token's reach */
echo "n8n Workflow page — secured connection copy (story 35)
";
$header_auth = '<strong>Post Quality webhook - example.com</strong> — a Header Auth credential: set its Name field to <code>X-PQM-Token</code> and its Value to <code>tok-35</code>. Pick it in the Webhook Trigger only';
$connected = $render_workflow(['webhook_token' => 'tok-35']);
preg_match('/Workflow File<\/h2>.*?<h2/s', $connected, $m);
check('Workflow File (where a v3 site re-imports): three credentials, each with the node it goes in; the webhook one with Name field and token inline',
    str_contains($m[0] ?? '', 'connect these three credentials:') && str_contains($m[0] ?? '', $header_auth)
    && str_contains($m[0] ?? '', '<strong>WordPress - example.com</strong> — a Basic Auth credential with an Application Password for a user who can edit posts. Pick it in Update WP Post (Rewrite).')
    && str_contains($m[0] ?? '', '<strong>Anthropic - Claude</strong> — a second Header Auth credential: Name <code>x-api-key</code>, Value your Anthropic API key. Pick it in the six Claude nodes.'));
check('hint says which Header Auth credential goes in which node',
    str_contains($connected, 'Post Quality webhook (X-PQM-Token) in the Webhook Trigger, Anthropic - Claude in the six Claude nodes.'));
check('import-URL copy says the token also opens the n8n trigger',
    str_contains($connected, 'It includes your webhook token, which also opens your n8n workflow\'s trigger — keep it private.'));
$settings_html = render_for(['pqm_manage_settings'], true);
check('Settings token description says both directions',
    str_contains($settings_html, 'Shared secret for both directions, always in the X-PQM-Token header: WordPress sends it to start the workflow'));
$GLOBALS['tw_settings'] = [];

/* Story 35: the ping — executed through ajax_test_connection() */
echo "Test ping — test URL refused, token header sent (story 35)\n";
class PQM_Json_Exit extends Exception { public function __construct(public bool $ok, public $data) { parent::__construct(); } }
function check_ajax_referer(...$a) { return true; }
function wp_unslash($v) { return $v; }
function wp_send_json_error($data = null, $status = null) { throw new PQM_Json_Exit(false, $data); }
function wp_send_json_success($data = null, $status = null) { throw new PQM_Json_Exit(true, $data); }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function wp_remote_post($url, $args) { $GLOBALS['tw_pings'][] = [$url, $args]; return ['response' => ['code' => $GLOBALS['tw_ping_code'] ?? 200]]; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
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
$proved = 'this site reaches n8n and the webhook credential is right';
foreach ([0 => true, PQM_WORKFLOW_VERSION => true, 1 => false, PQM_WORKFLOW_VERSION - 1 => false] as $seen => $credential_proved) {
    $GLOBALS['tw_options'] = $seen ? ['pqm_workflow_version_seen' => $seen] : [];
    $r = ping($prod);
    check($credential_proved
        ? "2xx, version seen $seen: says the credential is right"
        : "2xx, version seen $seen (pre-v4 import): reachability only, points to re-importing",
        $r->ok === true && ($credential_proved
            ? str_contains($r->data, $proved)
            : !str_contains($r->data, $proved) && str_contains($r->data, 'download or re-import it on the n8n Workflow page')));
}
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
        str_contains($html, 'Workflow File</h2>') && str_contains($html, 'pqm-copy-import-url') && str_contains($html, 'pqm-test-connection'));
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
check('version handshake seen but no real result stored: notice still shows', str_contains($notice_for($manager, [], ['pqm_workflow_version_seen' => 4]), $link));
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

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
