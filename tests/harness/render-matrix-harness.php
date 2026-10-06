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
function add_filter(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return ''; }
function plugin_basename($f) { return basename($f); }
function wp_generate_uuid4() { return 'stub-uuid'; }
function get_option($k, $d = false) { return $k === 'pqm_settings' ? array_merge(['n8n_webhook_url' => 'https://n8n.example/webhook'], $GLOBALS['tw_settings']) : $d; }
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
function admin_url($p = '') { return '/wp-admin/' . $p; }
function settings_fields($g) { echo "<!-- settings_fields:$g -->"; }
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
 * static check that the filter and the menu agree on pqm_manage_agents */
echo "Author refusal (static: option_page_capability + menu cap)\n";
/* Review fix 3: both gates resolve the capability dynamically so a role
 * granted either pqm_manage_settings or pqm_manage_agents gets access. */
check('settings_page_capability() prefers manage_settings, falls back to manage_agents',
    (bool) preg_match("/function settings_page_capability\\(\\).*?pqm_manage_settings.*?pqm_manage_agents/s", $src));
check('option save uses settings_page_capability', (bool) preg_match("/option_page_capability_pqm_settings_group'\\s*,\\s*\\[__CLASS__,\\s*'settings_page_capability'\\]/", $src));
check('Settings menu uses settings_page_capability', (bool) preg_match("/self::settings_page_capability\\(\\),\\s*'pqm-settings'/", $src));
$GLOBALS['tw'] = ['caps' => ['pqm_manage_settings'], 'pro' => true];
check('settings-only role resolves to pqm_manage_settings', PQM_Admin::settings_page_capability() === 'pqm_manage_settings');
$GLOBALS['tw'] = ['caps' => ['pqm_manage_agents'], 'pro' => true];
check('agents-only role resolves to pqm_manage_agents', PQM_Admin::settings_page_capability() === 'pqm_manage_agents');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
