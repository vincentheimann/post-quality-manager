<?php
/**
 * CLI harness — story 5 "Settings sections follow role and plan".
 * Sanitizer matrix: saver capability × plan × posted sections.
 * Run: php sanitize-matrix-harness.php
 */

error_reporting(E_ALL);

/* ─── WP stubs ───────────────────────────────────────────────────────── */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['pqm_test_caps']    = [];
$GLOBALS['pqm_test_pro']     = false;
$GLOBALS['pqm_test_options'] = [];

function plugin_dir_path($file) { return rtrim(dirname($file), '/\\') . '/'; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/post-quality-manager/'; }
function register_activation_hook($f, $cb) {}
function register_deactivation_hook($f, $cb) {}
function add_action(...$a) {}
function add_filter(...$a) {}
function is_admin() { return false; }
function __($s, $d = null) { return $s; }
function apply_filters($tag, $value, ...$a) {
    if ($tag === 'pqm_is_pro') {
        // null = pass-through (no mu-plugin): the provider's answer stands.
        $forced = $GLOBALS['pqm_test_pro'];
        return $forced === null ? (bool) $value : (bool) $forced;
    }
    return $value;
}
function current_user_can($cap) { return in_array($cap, $GLOBALS['pqm_test_caps'], true); }
function get_option($name, $default = false) { return $GLOBALS['pqm_test_options'][$name] ?? $default; }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, (array) $args); }
function wp_generate_uuid4() { return 'freshly-generated-default-token'; }
function esc_url_raw($url) { return trim((string) $url); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function absint($n) { return abs((int) $n); }
/* Story 23: PQM_Blocks::patterns() reads published wp_block posts and parses them. */
$GLOBALS['pqm_test_patterns'] = []; // id => [title, status, content]
function get_posts($args) {
    $out = [];
    foreach ($GLOBALS['pqm_test_patterns'] as $id => [$title, $status, $content]) {
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

require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Fixtures ───────────────────────────────────────────────────────── */
// Stored values differ from every default so default-leakage is detectable.
$stored_option = [
    'license_key'        => 'PQM-STORED-KEY',
    'n8n_webhook_url'    => 'https://n8n.example.com/webhook/pqm',
    'webhook_token'      => 'stored-token',
    'notification_url'   => 'https://chat.example.com/hook',
    'blog_profile'       => 'technical',
    'brand_voice_preset' => 'professional',
    'brand_voice'        => 'Stored voice',
    'website_context'    => 'Stored context',
    'cron_enabled'       => true, // default is false — the checkbox ambiguity case
    'cron_frequency'     => 'weekly',
    'cron_batch_size'    => 7,
    'cron_min_score'     => 55,
    'cron_max_age_days'  => 45,
    'cron_command'       => 'review',
    'author_quota'       => 12,
    'default_model'      => 'claude-opus-4-8',
    'custom_model'       => '',
    'agent_prompts'      => [
        'review' => ['instructions' => 'stored instr', 'temperature' => 0.7, 'model' => 'claude-haiku-4-5', 'effort' => 'low'],
    ],
];

$ADMIN_ONLY = ['license_key', 'n8n_webhook_url', 'webhook_token', 'notification_url', 'blog_profile',
    'brand_voice_preset', 'brand_voice', 'website_context', 'author_quota', 'default_model', 'custom_model'];
$CRON_KEYS  = ['cron_enabled', 'cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days', 'cron_command'];

$pass = 0;
$fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "FAIL  $label\n"; }
}
function setup($caps, $pro, $stored) {
    $GLOBALS['pqm_test_caps'] = $caps;
    $GLOBALS['pqm_test_pro'] = $pro;
    $GLOBALS['pqm_test_options'] = ['pqm_settings' => $stored];
}

/* ─── Case 1: Editor, licensed — overrides + cron POST, globals absent ── */
echo "\nCase 1: Editor (pqm_manage_agents), pro site\n";
setup(['pqm_manage_agents'], true, $stored_option);
$input = [
    // The two visible sections only.
    'cron_enabled'   => '1',
    'cron_frequency' => 'daily',
    'cron_batch_size' => '3',
    'cron_min_score' => '70',
    'cron_max_age_days' => '10',
    'agent_prompts'  => [
        'review' => ['instructions' => 'editor instr', 'temperature' => '0.5', 'model' => '', 'effort' => 'high'],
    ],
];
$out = PQM_Admin::sanitize_settings($input);
$expected_stored = pqm_get_settings(); // merged stored view
foreach ($ADMIN_ONLY as $key) {
    check("admin-only '$key' carried from stored", $out[$key] === $expected_stored[$key]);
}
check('webhook token is the stored one, not a fresh default', $out['webhook_token'] === 'stored-token');
check('editor cron change applied', $out['cron_frequency'] === 'daily' && $out['cron_batch_size'] === 3);
check('editor override change applied', $out['agent_prompts']['review']['instructions'] === 'editor instr'
    && $out['agent_prompts']['review']['temperature'] === 0.5);

/* ─── Case 2: Editor, free site — nothing POSTs (both sections locked) ── */
echo "\nCase 2: Editor (pqm_manage_agents), free site — empty POST\n";
setup(['pqm_manage_agents'], false, $stored_option);
$out = PQM_Admin::sanitize_settings([]);
$expected_stored = pqm_get_settings();
foreach (array_merge($ADMIN_ONLY, $CRON_KEYS) as $key) {
    check("'$key' identical before and after", $out[$key] === $expected_stored[$key]);
}
check('cron_enabled true survives the absent checkbox', $out['cron_enabled'] === true);
check('agent_prompts carried wholesale', $out['agent_prompts'] === $expected_stored['agent_prompts']);
check('stored values are not the defaults (fixture sanity)', $out['cron_frequency'] === 'weekly'
    && $out['default_model'] === 'claude-opus-4-8' && $out['author_quota'] === 12);

/* ─── Case 2b: options.php passes null when no pqm_settings field posted ── */
echo "\nCase 2b: Editor, free site — null input (options.php update_option(null))\n";
setup(['pqm_manage_agents'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(null);
$expected_stored = pqm_get_settings();
check('null input does not fatal and returns an array', is_array($out));
foreach (array_merge($ADMIN_ONLY, $CRON_KEYS) as $key) {
    check("null input: '$key' carried from stored", $out[$key] === $expected_stored[$key]);
}
check('null input: agent_prompts carried wholesale', $out['agent_prompts'] === $expected_stored['agent_prompts']);

/* ─── Case 3: Admin, free site — globals POST, locked sections absent ── */
echo "\nCase 3: Admin (both caps), free site — globals-only POST\n";
setup(['pqm_manage_agents', 'pqm_manage_settings'], false, $stored_option);
$input = [
    'n8n_webhook_url' => 'https://new-n8n.example.com/webhook/pqm',
    'webhook_token' => 'stored-token',
    'notification_url' => '',
    'blog_profile' => 'marketing',
    'brand_voice_preset' => 'direct',
    'brand_voice' => 'New voice',
    'website_context' => 'New context',
    'author_quota' => '30',
    'default_model' => 'claude-sonnet-5',
    'custom_model' => '',
];
$out = PQM_Admin::sanitize_settings($input);
$expected_stored = pqm_get_settings();
check('global change applied (webhook url)', $out['n8n_webhook_url'] === 'https://new-n8n.example.com/webhook/pqm');
check('global change applied (model + quota)', $out['default_model'] === 'claude-sonnet-5' && $out['author_quota'] === 30);
foreach ($CRON_KEYS as $key) {
    check("locked '$key' carried from stored", $out[$key] === $expected_stored[$key]);
}
check('cron_enabled true survives the free-site save', $out['cron_enabled'] === true);
check('locked agent_prompts carried from stored', $out['agent_prompts'] === $expected_stored['agent_prompts']);
check('carried override is the stored one, not the default',
    $out['agent_prompts']['review']['temperature'] === 0.7 && $out['agent_prompts']['review']['model'] === 'claude-haiku-4-5');

/* ─── Case 4: Admin, licensed — full POST behaves as today ───────────── */
echo "\nCase 4: Admin (both caps), pro site — full POST\n";
setup(['pqm_manage_agents', 'pqm_manage_settings'], true, $stored_option);
$input = [
    'n8n_webhook_url' => 'https://n8n.example.com/webhook/pqm',
    'webhook_token' => 'stored-token',
    'notification_url' => 'https://chat.example.com/hook',
    'blog_profile' => 'lifestyle',
    'brand_voice_preset' => 'casual',
    'brand_voice' => 'Casual voice',
    'website_context' => 'Casual context',
    'author_quota' => '0',
    'default_model' => 'custom',
    'custom_model' => 'claude-x-1!!', // sanitized to slug chars
    'cron_enabled' => '1',
    'cron_frequency' => 'daily',
    'cron_batch_size' => '9',
    'cron_min_score' => '65',
    'cron_max_age_days' => '20',
    'cron_command' => 'rewrite', // ignored: forced to the fixed default
    'agent_prompts' => [
        // Locked knobs absent (model without temp/effort posts neither).
        'seo' => ['instructions' => 'seo instr', 'model' => 'claude-sonnet-5'],
        'review' => ['instructions' => '', 'temperature' => '0.2', 'model' => '', 'effort' => 'max'],
    ],
];
$out = PQM_Admin::sanitize_settings($input);
check('globals applied', $out['blog_profile'] === 'lifestyle' && $out['author_quota'] === 0);
check('custom model id sanitized', $out['custom_model'] === 'claude-x-1');
check('cron applied', $out['cron_enabled'] === true && $out['cron_batch_size'] === 9);
check('cron_command stays the fixed default', $out['cron_command'] === 'review');
check('posted agent values applied', $out['agent_prompts']['review']['effort'] === 'max'
    && $out['agent_prompts']['review']['temperature'] === 0.2);
check('locked temperature knob falls back to stored (seo: default 0.0? no — stored)',
    $out['agent_prompts']['seo']['temperature'] === 0.0 && $out['agent_prompts']['seo']['effort'] === 'medium');
check('seo model override applied', $out['agent_prompts']['seo']['model'] === 'claude-sonnet-5');

/* Case 4b: locked knob stored fallback uses the *stored* value when it differs */
echo "\nCase 4b: pro save, review posts without temperature/effort — stored carries\n";
$out2 = PQM_Admin::sanitize_settings(array_merge($input, [
    'agent_prompts' => ['review' => ['instructions' => 'only instr', 'model' => '']],
]));
check('stored temperature (0.7) and effort (low) carried per knob',
    $out2['agent_prompts']['review']['temperature'] === 0.7 && $out2['agent_prompts']['review']['effort'] === 'low');

/* ─── Case 5: cron_enabled false stays false on a free save ──────────── */
echo "\nCase 5: free-site save with stored cron_enabled=false stays false\n";
$stored_off = array_merge($stored_option, ['cron_enabled' => false]);
setup(['pqm_manage_agents', 'pqm_manage_settings'], false, $stored_off);
$out = PQM_Admin::sanitize_settings(['n8n_webhook_url' => 'https://n8n.example.com/webhook/pqm']);
check('cron_enabled false carried (not re-enabled)', $out['cron_enabled'] === false);

/* ─── Case 5b (story 3): license_key sanitize + trim + carry ─────────── */
echo "\nCase 5b: license_key — admin saver sanitizes + trims, non-admin carries\n";
setup(['pqm_manage_agents', 'pqm_manage_settings'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['license_key' => '  PQM-NEW-KEY  ']);
check('admin key trimmed and stored', $out['license_key'] === 'PQM-NEW-KEY');
$out = PQM_Admin::sanitize_settings(['license_key' => '   ']);
check('whitespace-only key becomes empty (site stays free)', $out['license_key'] === '');
$out = PQM_Admin::sanitize_settings(['license_key' => '<b>PQM-KEY</b>']);
check('key passes sanitize_text_field (tags stripped)', $out['license_key'] === 'PQM-KEY');
$out = PQM_Admin::sanitize_settings(['license_key' => '']);
check('admin clearing the field empties the key (revert to free)', $out['license_key'] === '');
setup(['pqm_manage_agents'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['license_key' => 'EDITOR-SMUGGLED-KEY']);
check('non-admin saver cannot set the key — stored key survives byte-identical',
    $out['license_key'] === 'PQM-STORED-KEY');

/* ─── Case 5c (story 3): same-save interplay — sanitizer reads the
       pre-save plan state (stored key), not the key being posted ───────── */
echo "\nCase 5c: filter pass-through — plan state during sanitize comes from the stored key\n";
$stored_free = array_merge($stored_option, ['license_key' => '']);
setup(['pqm_manage_agents', 'pqm_manage_settings'], null, $stored_free);
$out = PQM_Admin::sanitize_settings(['license_key' => 'PQM-NEW-KEY', 'cron_batch_size' => '99']);
check('entering a key on a free site: posted key stored', $out['license_key'] === 'PQM-NEW-KEY');
check('…but this save still carries cron from stored (pre-save state is free)',
    $out['cron_batch_size'] === 7 && $out['cron_enabled'] === true);
setup(['pqm_manage_agents', 'pqm_manage_settings'], null, $stored_option);
$out = PQM_Admin::sanitize_settings([
    'license_key' => '', 'cron_enabled' => '1', 'cron_frequency' => 'weekly',
    'cron_batch_size' => '9', 'cron_min_score' => '55', 'cron_max_age_days' => '45',
    'agent_prompts' => ['review' => ['instructions' => 'stored instr', 'temperature' => '0.7', 'model' => 'claude-haiku-4-5', 'effort' => 'low']],
]);
check('clearing the key on a pro site: key emptied', $out['license_key'] === '');
check('…and this save still applies cron (pre-save state is pro)', $out['cron_batch_size'] === 9);

/* ─── Case 6: result is always a complete settings array ─────────────── */
echo "\nCase 6: every default key present in every branch's output\n";
foreach ([
    [['pqm_manage_agents'], true],
    [['pqm_manage_agents'], false],
    [['pqm_manage_agents', 'pqm_manage_settings'], true],
    [['pqm_manage_agents', 'pqm_manage_settings'], false],
] as [$caps, $pro]) {
    setup($caps, $pro, $stored_option);
    $out = PQM_Admin::sanitize_settings($pro && in_array('pqm_manage_settings', $caps, true) ? $input : []);
    $missing = array_diff(array_keys(pqm_default_settings()), array_keys($out));
    check('caps=[' . implode(',', $caps) . '] pro=' . var_export($pro, true) . ' → no missing keys', $missing === []);
}

/* ─── Case 7 (story 23): Pattern setting — Takeaways and Sources only ── */
echo "\nCase 7: agent 'pattern' key — a listed pattern kept, anything else → 0, absent elsewhere\n";
// User patterns (wp_block): 31 published with a List (nested), 32 without one, 33 a draft.
$GLOBALS['pqm_test_patterns'] = [
    31 => ['Key Takeaways', 'publish', 'group-with-list'],
    32 => ['Banner', 'publish', 'paragraph-only'],
    33 => ['Draft list', 'draft', 'list'],
];
check('defaults: pattern 0 for takeaways and sources only',
    pqm_default_settings()['agent_prompts']['takeaways']['pattern'] === 0
    && pqm_default_settings()['agent_prompts']['sources']['pattern'] === 0
    && !isset(pqm_default_settings()['agent_prompts']['review']['pattern']));
check('no typed block-name key left in the defaults', !isset(pqm_default_settings()['agent_prompts']['takeaways']['block']));
setup(['pqm_manage_agents'], true, $stored_option);
$agent = static fn($pattern) => ['instructions' => '', 'temperature' => '0.3', 'model' => '', 'effort' => 'medium', 'pattern' => $pattern];
$out = PQM_Admin::sanitize_settings(['agent_prompts' => [
    'takeaways' => $agent('31'),
    'sources'   => $agent(31),
    'review'    => $agent('31'), // posted where no Pattern field exists
]]);
check('published pattern with a List kept, stored as an int', $out['agent_prompts']['takeaways']['pattern'] === 31
    && $out['agent_prompts']['sources']['pattern'] === 31);
check('no pattern key stored on review, even when posted', !array_key_exists('pattern', $out['agent_prompts']['review']));
foreach (['a page ID' => '7', 'a list-less pattern' => '32', 'a draft pattern' => '33', 'abc' => 'abc', 'empty' => ''] as $label => $bad) {
    $out = PQM_Admin::sanitize_settings(['agent_prompts' => ['takeaways' => $agent($bad)]]);
    check("$label stored as 0", $out['agent_prompts']['takeaways']['pattern'] === 0);
}
$no_pattern = $agent('31');
unset($no_pattern['pattern']);
$out = PQM_Admin::sanitize_settings(['agent_prompts' => ['takeaways' => $no_pattern]]);
check('takeaways posted without a pattern field → 0', $out['agent_prompts']['takeaways']['pattern'] === 0);
setup(['pqm_manage_agents'], false, array_merge($stored_option, ['agent_prompts' => [
    'takeaways' => ['instructions' => '', 'temperature' => 0.3, 'model' => '', 'effort' => 'medium', 'pattern' => 31],
]]));
$out = PQM_Admin::sanitize_settings([]);
check('free site: stored pattern carried (section locked)', $out['agent_prompts']['takeaways']['pattern'] === 31);
check('read side: merged settings keep pattern on takeaways/sources only',
    pqm_get_settings()['agent_prompts']['sources']['pattern'] === 0
    && !isset(pqm_get_settings()['agent_prompts']['seo']['pattern']));

/* ─── Case 8 (bug 19): pro site, the plan-gated sections absent from the
       POST (form rendered while free, or a direct POST) carry stored ───── */
echo "\nCase 8: pro site, cron + overrides not posted — stored values carry\n";
$GLOBALS['pqm_test_patterns'] = [];
foreach (['Admin' => ['pqm_manage_agents', 'pqm_manage_settings'], 'Editor' => ['pqm_manage_agents']] as $who => $caps) {
    foreach (['license_key only' => ['license_key' => 'PQM-STORED-KEY'], 'empty POST' => [], 'null input' => null] as $what => $post) {
        setup($caps, true, $stored_option);
        $out = PQM_Admin::sanitize_settings($post);
        $expected_stored = pqm_get_settings();
        $carried = array_filter($CRON_KEYS, static fn($key) => $out[$key] === $expected_stored[$key]);
        check("$who, $what: every cron key identical to stored", $carried === $CRON_KEYS);
        check("$who, $what: agent_prompts identical to stored", $out['agent_prompts'] === $expected_stored['agent_prompts']);
    }
}
check('carried values are the stored ones, not the defaults', $out['cron_enabled'] === true
    && $out['agent_prompts']['review']['model'] === 'claude-haiku-4-5');
setup(['pqm_manage_agents'], true, $stored_option);
$out = PQM_Admin::sanitize_settings([
    'cron_frequency' => 'daily', 'cron_batch_size' => '3', 'cron_min_score' => '80', 'cron_max_age_days' => '10',
    'agent_prompts'  => ['seo' => ['instructions' => 'seo instr', 'model' => '']],
]);
check('cron posted without the checkbox → cron_enabled false (unchecked)', $out['cron_enabled'] === false);
check('…and the posted cron values are applied', $out['cron_frequency'] === 'daily' && $out['cron_batch_size'] === 3
    && $out['cron_min_score'] === 80 && $out['cron_max_age_days'] === 10);
check('an agent the POST omits carries its stored entry',
    $out['agent_prompts']['review'] === pqm_get_settings()['agent_prompts']['review']);
check('the posted agent is applied', $out['agent_prompts']['seo']['instructions'] === 'seo instr');

echo "\n──────────────\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
