<?php
/**
 * CLI harness — story 5 "Settings sections follow role and plan".
 * Sanitizer matrix: saver capability × plan × posted sections.
 * Run: php sanitize-matrix-harness.php
 */

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['pqm_test_caps'] = [];
$GLOBALS['pqm_test_pro']  = false;
$GLOBALS['tw'] = ['options' => []];

function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/post-quality-manager/'; }
function is_admin() { return false; }
function apply_filters($tag, $value, ...$a) {
    if ($tag === 'pqm_is_pro') {
        // null = pass-through (no mu-plugin): the provider's answer stands.
        $forced = $GLOBALS['pqm_test_pro'];
        return $forced === null ? (bool) $value : (bool) $forced;
    }
    return $value;
}
function current_user_can($cap) { return in_array($cap, $GLOBALS['pqm_test_caps'], true); }
/* Story 35: the sanitizer raises a warning notice for an n8n test URL. */
$GLOBALS['pqm_test_settings_errors'] = [];
function add_settings_error($setting, $code, $message, $type = 'error') {
    $GLOBALS['pqm_test_settings_errors'][] = [$setting, $code, $message, $type];
}
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
/* Story 42: the Roles tab writes the site's roles; every add_cap/remove_cap is logged. */
class WP_Role {
    public $name;
    public $capabilities;
    public function __construct($name, $capabilities = []) { $this->name = $name; $this->capabilities = $capabilities; }
    public function add_cap($cap, $grant = true) { $GLOBALS['pqm_test_role_calls'][] = "add {$this->name} $cap"; $this->capabilities[$cap] = $grant; }
    public function remove_cap($cap) { $GLOBALS['pqm_test_role_calls'][] = "remove {$this->name} $cap"; unset($this->capabilities[$cap]); }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
$GLOBALS['pqm_test_roles'] = [];
$GLOBALS['pqm_test_role_calls'] = [];
function wp_roles() { return (object) ['role_objects' => $GLOBALS['pqm_test_roles']]; }
/* Per-user grants are never read: any user query would be recorded here. */
function get_users($args = []) { $GLOBALS['pqm_test_user_queries'][] = $args; return []; }

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
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

function setup($caps, $pro, $stored) {
    $GLOBALS['pqm_test_caps'] = $caps;
    $GLOBALS['pqm_test_pro'] = $pro;
    $GLOBALS['tw']['options'] = ['pqm_settings' => $stored];
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
// Story 67: the URL and the token live on the n8n Workflow page; a posted value never reaches them.
check('posted webhook url ignored: the stored one kept', $out['n8n_webhook_url'] === 'https://n8n.example.com/webhook/pqm');
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
    'cron_command' => 'rewrite', // never schedulable (ticket 3): falls back to review
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
check('cron_command rewrite → review (not schedulable)', $out['cron_command'] === 'review');
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

/* ─── Case 5b (story 3): license_key sanitize + trim + carry — unread
       since story 44, but the stored settings contract is unchanged ────── */
echo "\nCase 5b: license_key — admin saver sanitizes + trims, non-admin carries\n";
setup(['pqm_manage_agents', 'pqm_manage_settings'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['license_key' => '  PQM-NEW-KEY  ']);
check('admin key trimmed and stored', $out['license_key'] === 'PQM-NEW-KEY');
$out = PQM_Admin::sanitize_settings(['license_key' => '   ']);
check('whitespace-only key becomes empty', $out['license_key'] === '');
$out = PQM_Admin::sanitize_settings(['license_key' => '<b>PQM-KEY</b>']);
check('key passes sanitize_text_field (tags stripped)', $out['license_key'] === 'PQM-KEY');
$out = PQM_Admin::sanitize_settings(['license_key' => '']);
check('admin posting an empty key empties it', $out['license_key'] === '');
setup(['pqm_manage_agents'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['license_key' => 'EDITOR-SMUGGLED-KEY']);
check('non-admin saver cannot set the key — stored key survives byte-identical',
    $out['license_key'] === 'PQM-STORED-KEY');

/* ─── Case 5c (story 44): filter pass-through — the plan comes from the
       SDK's license, never from a stored or posted license_key ─────────── */
echo "\nCase 5c: filter pass-through — plan state during sanitize comes from the SDK license\n";
$stored_free = array_merge($stored_option, ['license_key' => '']);
setup(['pqm_manage_agents', 'pqm_manage_settings'], null, $stored_option);
pqm_fs()->pro = false;
$out = PQM_Admin::sanitize_settings(['license_key' => 'PQM-NEW-KEY', 'cron_batch_size' => '99']);
check('stub-era key stored + key posted, no SDK license: cron still carried (site is free)',
    $out['cron_batch_size'] === 7 && $out['cron_enabled'] === true);
$out = PQM_Admin::sanitize_settings(['n8n_webhook_url' => 'https://n8n.example.com/webhook/pqm']);
check('the next admin save clears the stub-era key (no License Key field posts it)', $out['license_key'] === '');
setup(['pqm_manage_agents', 'pqm_manage_settings'], null, $stored_free);
pqm_fs()->pro = true;
$out = PQM_Admin::sanitize_settings([
    'cron_enabled' => '1', 'cron_frequency' => 'weekly',
    'cron_batch_size' => '9', 'cron_min_score' => '55', 'cron_max_age_days' => '45',
    'agent_prompts' => ['review' => ['instructions' => 'stored instr', 'temperature' => '0.7', 'model' => 'claude-haiku-4-5', 'effort' => 'low']],
]);
check('SDK license valid, no key stored: this save applies cron (site is pro)', $out['cron_batch_size'] === 9);
pqm_fs()->pro = false;

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
/* Story 67: the Settings form posts neither the webhook URL nor the token (both live on the n8n Workflow
 * page); whatever the admin save posts, the stored values carry. */
foreach (['absent' => [], 'posted anyway' => ['n8n_webhook_url' => 'https://forged.example/webhook/x', 'webhook_token' => 'forged']] as $what => $post) {
    setup(['pqm_manage_agents', 'pqm_manage_settings'], true, $stored_option);
    $out = PQM_Admin::sanitize_settings($post + ['blog_profile' => 'marketing']);
    check("Admin, URL and token $what: both kept as stored", $out['n8n_webhook_url'] === 'https://n8n.example.com/webhook/pqm'
        && $out['webhook_token'] === 'stored-token' && $out['blog_profile'] === 'marketing');
}
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

/* ─── Case 9 (ticket 16): settings-only saver — the agent sections need
       pqm_manage_agents, so a posted cron + overrides section carries ───── */
echo "\nCase 9: settings-only saver (pqm_manage_settings), pro site — cron + overrides posted\n";
setup(['pqm_manage_settings'], true, $stored_option);
$out = PQM_Admin::sanitize_settings([
    'n8n_webhook_url' => 'https://other-n8n.example.com/webhook/pqm',
    'webhook_token' => 'new-token',
    'blog_profile' => 'marketing',
    'author_quota' => '40',
    'default_model' => 'claude-sonnet-5',
    'cron_enabled' => '', 'cron_frequency' => 'daily', 'cron_batch_size' => '3',
    'cron_min_score' => '80', 'cron_max_age_days' => '10',
    'agent_prompts' => [
        'review' => ['instructions' => 'smuggled', 'temperature' => '0.1', 'model' => '', 'effort' => 'max'],
        'seo'    => ['instructions' => 'smuggled seo', 'model' => 'claude-sonnet-5'],
    ],
]);
$expected_stored = pqm_get_settings();
check('admin-only keys applied; the posted URL and token ignored (story 67: page-local, never from the form)',
    $out['n8n_webhook_url'] === 'https://n8n.example.com/webhook/pqm' && $out['webhook_token'] === 'stored-token'
    && $out['blog_profile'] === 'marketing' && $out['author_quota'] === 40 && $out['default_model'] === 'claude-sonnet-5');
foreach ($CRON_KEYS as $key) {
    check("'$key' carried byte-identically from stored", $out[$key] === $expected_stored[$key]);
}
check('cron_enabled true survives the posted empty checkbox', $out['cron_enabled'] === true);
check('agent_prompts carried byte-identically from stored', $out['agent_prompts'] === $expected_stored['agent_prompts']);
setup(['pqm_manage_settings'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['cron_batch_size' => '3', 'agent_prompts' => ['review' => ['instructions' => 'smuggled']]]);
$expected_stored = pqm_get_settings();
check('free site: cron + agent_prompts carried too',
    $out['cron_batch_size'] === 7 && $out['agent_prompts'] === $expected_stored['agent_prompts']);

/* ─── Case 10 (ticket 13): free-site off-switch — the Enable toggle may
       only switch stored cron off; everything else still carries ───────── */
echo "\nCase 10: free site, cron_enabled off-switch\n";
$stored_off = array_merge($stored_option, ['cron_enabled' => false]);
foreach (['Admin' => ['pqm_manage_agents', 'pqm_manage_settings'], 'Editor' => ['pqm_manage_agents']] as $who => $caps) {
    setup($caps, false, $stored_option);
    $out = PQM_Admin::sanitize_settings(['cron_enabled' => '0']);
    $expected_stored = pqm_get_settings();
    check("$who, switch off: cron_enabled false", $out['cron_enabled'] === false);
    $others = array_diff($CRON_KEYS, ['cron_enabled']);
    $carried = array_filter($others, static fn($key) => $out[$key] === $expected_stored[$key]);
    check("$who, switch off: every other cron key carried byte-identically", $carried === $others);
    check("$who, switch off: agent_prompts carried byte-identically", $out['agent_prompts'] === $expected_stored['agent_prompts']);

    $out = PQM_Admin::sanitize_settings(['cron_enabled' => '1']);
    check("$who, leave on: posted 1 keeps cron_enabled true", $out['cron_enabled'] === true);

    setup($caps, false, $stored_off);
    $out = PQM_Admin::sanitize_settings(['cron_enabled' => '1']);
    check("$who, smuggle on: stored false stays false", $out['cron_enabled'] === false);

    setup($caps, false, $stored_option);
    $out = PQM_Admin::sanitize_settings([
        'cron_enabled' => '0', 'cron_batch_size' => '99', 'cron_frequency' => 'daily',
        'cron_min_score' => '1', 'cron_max_age_days' => '2', 'cron_command' => 'rewrite',
    ]);
    check("$who, other knobs smuggled: only enabled flips", $out['cron_enabled'] === false
        && $out['cron_batch_size'] === 7 && $out['cron_frequency'] === 'weekly'
        && $out['cron_min_score'] === 55 && $out['cron_max_age_days'] === 45 && $out['cron_command'] === 'review');
}
setup(['pqm_manage_settings'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['cron_enabled' => '0']);
check('no agents cap: settings-only saver posting 0 → carried (true)', $out['cron_enabled'] === true);
setup(['pqm_manage_agents'], false, $stored_option);
$out = PQM_Admin::sanitize_settings(['cron_enabled' => '']);
check('empty string posted counts as unchecked → false', $out['cron_enabled'] === false);

/* Stale off-switch form (rendered free) saved after the site turned pro:
   cron_enabled alone is not "the section posted" — the knobs carry. */
setup(['pqm_manage_agents'], true, $stored_option);
$out = PQM_Admin::sanitize_settings(['cron_enabled' => '1']);
$expected_stored = pqm_get_settings();
$carried = array_filter($CRON_KEYS, static fn($key) => $out[$key] === $expected_stored[$key]);
check('pro, only cron_enabled 1 posted: every cron key carried', $carried === $CRON_KEYS);
$out = PQM_Admin::sanitize_settings(['cron_enabled' => '0']);
$others = array_diff($CRON_KEYS, ['cron_enabled']);
$carried = array_filter($others, static fn($key) => $out[$key] === $expected_stored[$key]);
check('pro, only cron_enabled 0 posted, stored true: switched off', $out['cron_enabled'] === false);
check('…and every other cron key carried', $carried === $others);

/* ─── Case 11 (ticket 12): free site, an agents-holder POSTs agent_prompts
       with new values (direct POST past the locked fieldset) — stored wins ── */
echo "\nCase 11: free site, pqm_manage_agents saver smuggles agent_prompts\n";
foreach (['Admin' => ['pqm_manage_agents', 'pqm_manage_settings'], 'Editor' => ['pqm_manage_agents']] as $who => $caps) {
    setup($caps, false, $stored_option);
    $out = PQM_Admin::sanitize_settings(['agent_prompts' => [
        'review'    => ['instructions' => 'smuggled', 'temperature' => '0.1', 'model' => 'claude-sonnet-5', 'effort' => 'max'],
        'seo'       => ['instructions' => 'smuggled seo', 'temperature' => '0.9', 'model' => '', 'effort' => 'high'],
        'takeaways' => ['instructions' => 'smuggled takeaways', 'temperature' => '0.2', 'model' => '', 'effort' => 'low', 'pattern' => '0'],
    ]]);
    check("$who: agent_prompts === stored", $out['agent_prompts'] === pqm_get_settings()['agent_prompts']);
}

/* ─── Case 12 (ticket 3): the schedulable commands — review, seo, audit
       from a posted section on a pro site; anything else falls back ───── */
echo "\nCase 12: cron_command whitelist (review / seo / audit)\n";
$cron_section = ['cron_enabled' => '1', 'cron_frequency' => 'daily', 'cron_batch_size' => '3',
    'cron_min_score' => '70', 'cron_max_age_days' => '10'];
foreach (['Admin' => ['pqm_manage_agents', 'pqm_manage_settings'], 'Editor' => ['pqm_manage_agents']] as $who => $caps) {
    foreach (['review' => 'review', 'seo' => 'seo', 'audit' => 'audit', 'rewrite' => 'review',
              'takeaways' => 'review', 'foo' => 'review', '' => 'review'] as $posted => $want) {
        setup($caps, true, $stored_option);
        $out = PQM_Admin::sanitize_settings($cron_section + ['cron_command' => $posted]);
        check("$who, pro, posted '$posted' → stored '$want'", $out['cron_command'] === $want);
    }
    setup($caps, true, $stored_option);
    $out = PQM_Admin::sanitize_settings($cron_section + ['cron_command' => ['audit']]);
    check("$who, pro, array posted → review", $out['cron_command'] === 'review');
}
$stored_audit = array_merge($stored_option, ['cron_command' => 'audit']);
setup(['pqm_manage_agents'], true, $stored_audit);
$out = PQM_Admin::sanitize_settings(['license_key' => 'PQM-STORED-KEY']);
check('pro, section absent: stored audit carried (bug 19)', $out['cron_command'] === 'audit');
$out = PQM_Admin::sanitize_settings($cron_section);
check('pro, section posted without cron_command → review', $out['cron_command'] === 'review');
foreach (['Admin' => ['pqm_manage_agents', 'pqm_manage_settings'], 'Editor' => ['pqm_manage_agents']] as $who => $caps) {
    setup($caps, false, $stored_audit);
    $out = PQM_Admin::sanitize_settings($cron_section + ['cron_command' => 'seo']);
    check("$who, free site, posted seo: stored audit carried (locked)", $out['cron_command'] === 'audit');
    $out = PQM_Admin::sanitize_settings(['cron_enabled' => '0', 'cron_command' => 'seo']);
    check("$who, free off-switch with seo smuggled: off, audit carried",
        $out['cron_enabled'] === false && $out['cron_command'] === 'audit');
}
setup(['pqm_manage_settings'], true, $stored_audit);
$out = PQM_Admin::sanitize_settings($cron_section + ['cron_command' => 'seo']);
check('settings-only saver, pro, posted seo: stored audit carried (story 16)', $out['cron_command'] === 'audit');

/* ─── Case 13 (story 35): n8n test URL — warning helper + save notice ── */
echo "\nCase 13: n8n test URL warning (story 35)\n";
foreach ([
    'https://n8n.example.com/webhook-test/pqm-execute'        => true,  // self-hosted test URL
    'https://acme.app.n8n.cloud/webhook-test/pqm-execute'     => true,  // Cloud test URL
    'http://192.168.1.20:5678/n8n/webhook-test/pqm-execute'  => true,  // path prefix, port
    'https://acme.app.n8n.cloud/webhook/pqm-execute'          => false, // Cloud production
    'https://n8n.example.com/webhook/pqm-execute'             => false, // self-hosted production
    'http://192.168.1.20:5678/n8n/webhook/pqm-execute'       => false, // self-hosted, prefix + port
    'https://webhook-test.example.com/webhook/pqm-execute'    => false, // only the path counts
    ''                                                        => false,
] as $url => $warn) {
    $copy = pqm_webhook_url_warning($url);
    check(($warn ? 'warns' : 'no warning') . ": '$url'", $warn ? str_starts_with($copy, 'This is an n8n test URL') : $copy === '');
}
$test_url = 'https://acme.app.n8n.cloud/webhook-test/pqm-execute';
setup(['pqm_manage_agents', 'pqm_manage_settings'], true, $stored_option);
$GLOBALS['pqm_test_settings_errors'] = [];
$out = PQM_Admin::sanitize_settings(['n8n_webhook_url' => $test_url]);
// Story 67: the form no longer posts the URL; a posted test URL is ignored and raises no notice
// (save_webhook_url() on the n8n Workflow page refuses it instead).
check('a posted test URL: the stored URL kept', $out['n8n_webhook_url'] === 'https://n8n.example.com/webhook/pqm');
check('... and no notice', $GLOBALS['pqm_test_settings_errors'] === []);
$GLOBALS['pqm_test_settings_errors'] = [];
PQM_Admin::sanitize_settings(['n8n_webhook_url' => 'https://acme.app.n8n.cloud/webhook/pqm-execute']);
check('production URL: no notice', $GLOBALS['pqm_test_settings_errors'] === []);
setup(['pqm_manage_agents'], true, array_merge($stored_option, ['n8n_webhook_url' => $test_url]));
PQM_Admin::sanitize_settings(['n8n_webhook_url' => 'https://other.example.com/webhook/x']);
check('Editor save (URL carried, not posted): no notice', $GLOBALS['pqm_test_settings_errors'] === []);

/* ─── Case 14 (story 27): the tabs share one form — a save from any tab
       posts every section, so the other tabs' keys stay as stored ────── */
echo "\nCase 14: save from one tab — every key outside it === stored\n";
/**
 * The POST a pro site's Settings form sends, rendered from the stored values
 * $s: every section ($agents_only = the Editor's two sections). Disabled
 * knobs and options do not post, an unchecked checkbox posts nothing.
 */
function form_post(array $s, bool $agents_only = false): array {
    $post = [];
    if (!$agents_only) {
        // Story 67: no field posts the webhook URL or the token (page-local, n8n Workflow page).
        foreach (['license_key', 'notification_url', 'default_model', 'custom_model',
                  'blog_profile', 'brand_voice_preset', 'website_context', 'brand_voice', 'author_quota'] as $key) {
            $post[$key] = (string) $s[$key];
        }
    }
    foreach ($s['agent_prompts'] as $agent => $a) {
        $caps = pqm_model_caps(pqm_effective_model($agent, $s));
        $row  = ['model' => $a['model'], 'instructions' => $a['instructions']];
        if (in_array($a['effort'], $caps['effort_levels'], true)) { $row['effort'] = $a['effort']; }
        if ($caps['supports_temperature']) { $row['temperature'] = number_format((float) $a['temperature'], 1); }
        if (isset($a['pattern'])) { $row['pattern'] = (string) $a['pattern']; }
        $post['agent_prompts'][$agent] = $row;
    }
    if ($s['cron_enabled']) { $post['cron_enabled'] = '1'; }
    foreach (['cron_frequency', 'cron_batch_size', 'cron_command', 'cron_min_score', 'cron_max_age_days'] as $key) {
        $post[$key] = (string) $s[$key];
    }
    return $post;
}
$TABS = [
    'connection' => ['license_key', 'notification_url'],
    'ai'         => ['default_model', 'custom_model', 'blog_profile', 'brand_voice_preset', 'brand_voice', 'website_context', 'agent_prompts'],
    'automation' => $CRON_KEYS,
    'usage'      => ['author_quota'],
];
// Story 67: the two page-local keys are saved on the n8n Workflow page, never by the form.
$PAGE_LOCAL = ['n8n_webhook_url', 'webhook_token'];
$tab_keys = array_merge($PAGE_LOCAL, ...array_values($TABS));
sort($tab_keys);
$all_keys = array_keys(pqm_default_settings());
sort($all_keys);
check('every settings key sits in exactly one tab, or is page-local', $tab_keys === $all_keys);
foreach ([
    'Admin'  => [['pqm_manage_agents', 'pqm_manage_settings'], false, [
        'connection' => [['notification_url' => ''], static fn($o) => $o['notification_url'] === ''],
        'ai'         => [['blog_profile' => 'marketing'], static fn($o) => $o['blog_profile'] === 'marketing'],
        'automation' => [['cron_batch_size' => '3'], static fn($o) => $o['cron_batch_size'] === 3],
        'usage'      => [['author_quota' => '30'], static fn($o) => $o['author_quota'] === 30],
    ]],
    'Editor' => [['pqm_manage_agents'], true, [
        'ai'         => [['agent_prompts' => ['seo' => ['instructions' => 'editor seo']]],
                         static fn($o) => $o['agent_prompts']['seo']['instructions'] === 'editor seo'],
        'automation' => [['cron_frequency' => 'daily'], static fn($o) => $o['cron_frequency'] === 'daily'],
    ]],
] as $who => [$caps, $agents_only, $edits]) {
    setup($caps, true, $stored_option);
    $stored = pqm_get_settings();
    $out = PQM_Admin::sanitize_settings(form_post($stored, $agents_only));
    check("$who, pro: the untouched form saves every key as stored",
        array_filter($all_keys, static fn($key) => $out[$key] === $stored[$key]) === $all_keys);
    foreach ($edits as $tab => [$change, $applied]) {
        $out = PQM_Admin::sanitize_settings(array_replace_recursive(form_post($stored, $agents_only), $change));
        $outside = array_merge(...array_values(array_diff_key($TABS, [$tab => true])));
        $same = array_filter($outside, static fn($key) => $out[$key] === $stored[$key]);
        check("$who, pro, one field changed in $tab: applied", $applied($out));
        check("$who, pro, one field changed in $tab: every key outside it === stored", $same === $outside);
    }
}

/* ─── Case 15 (story 42): the Roles tab writes the site's roles ──────── */
echo "\nCase 15: Roles tab — written only by a manage_options saver, pro, tab posted\n";
$PQM_CAPS = array_keys(PQM_Features::capabilities());
/** The four core roles with the shipped map plus core caps, and a custom role `writer`; resets the call log. */
function roles_world() {
    $map = PQM_Features::capabilities();
    $core = [
        'administrator' => ['manage_options' => true, 'edit_posts' => true],
        'editor'        => ['edit_others_posts' => true, 'edit_posts' => true],
        'author'        => ['edit_posts' => true],
        'contributor'   => ['edit_posts' => true],
        'writer'        => ['read' => true],
    ];
    $GLOBALS['pqm_test_roles'] = [];
    foreach ($core as $name => $caps) {
        foreach ($map as $cap => $holders) {
            if (in_array($name, $holders, true)) { $caps[$cap] = true; }
        }
        $GLOBALS['pqm_test_roles'][$name] = new WP_Role($name, $caps);
    }
    $GLOBALS['pqm_test_role_calls'] = [];
}
$GLOBALS['pqm_test_user_queries'] = []; // accumulates over the whole case
/** Each role's capabilities, as stored. */
function roles_snapshot() { return array_map(static fn($role) => $role->capabilities, $GLOBALS['pqm_test_roles']); }
/**
 * The Roles tab as the browser posts it, rendered from the roles as they are
 * now (pro): the marker, each unpinned row's roles_shown (what it rendered checked; a
 * pinned row posts none) and every checked, enabled box (pinned rows
 * are disabled, so never posted). $overrides replaces a row's boxes.
 */
function roles_post(array $overrides = []) {
    $roles = [];
    $shown = [];
    foreach ($GLOBALS['pqm_test_roles'] as $name => $role) {
        if ($role->has_cap('manage_options')) { continue; } // a pinned row posts no roles_shown and no boxes (disabled)
        $checked = array_values(array_filter(array_keys(PQM_Features::capabilities()), [$role, 'has_cap']));
        $shown[$name] = implode(',', $checked);
        if ($checked) { $roles[$name] = $checked; }
    }
    return ['roles_posted' => '1', 'roles_shown' => $shown, 'roles' => array_replace($roles, $overrides)];
}
$SITE_ADMIN = ['manage_options', 'pqm_manage_agents', 'pqm_manage_settings'];

roles_world();
setup($SITE_ADMIN, true, $stored_option);
$before = roles_snapshot();
$out = PQM_Admin::sanitize_settings(roles_post(['author' => ['pqm_run_free_agents']]));
$expect = $before;
unset($expect['author']['pqm_view_reviews']);
check('Admin, pro, uncheck Author · View reviews: Author lacks it', !$GLOBALS['pqm_test_roles']['author']->has_cap('pqm_view_reviews'));
check('… every other role byte-identical, Author otherwise unchanged', roles_snapshot() === $expect);
check('… exactly one role call: remove author pqm_view_reviews', $GLOBALS['pqm_test_role_calls'] === ['remove author pqm_view_reviews']);
check('… none of roles, roles_shown, roles_posted stored in the option',
    !array_key_exists('roles', $out) && !array_key_exists('roles_shown', $out) && !array_key_exists('roles_posted', $out));
$out_keys = array_keys($out);
$default_keys = array_keys(pqm_default_settings());
sort($out_keys);
sort($default_keys);
check('… the option keeps exactly the settings keys (shape unchanged)', $out_keys === $default_keys);

roles_world();
setup($SITE_ADMIN, true, $stored_option);
$before = roles_snapshot();
PQM_Admin::sanitize_settings(roles_post());
check('untouched tab saved: no role call at all', $GLOBALS['pqm_test_role_calls'] === [] && roles_snapshot() === $before);
PQM_Admin::sanitize_settings(roles_post(['author' => ['pqm_run_free_agents']]));
$GLOBALS['pqm_test_role_calls'] = [];
PQM_Admin::sanitize_settings(roles_post(['author' => ['pqm_run_free_agents']]));
check('the sanitizer running twice (first save of the option): second run makes no role call', $GLOBALS['pqm_test_role_calls'] === []);

/* Pinned rows: capability-keyed, whatever the POST says. */
foreach ([
    'omits the Administrator row'            => ['roles_posted' => '1', 'roles' => []],
    'posts the Administrator row unchecked'  => ['roles_posted' => '1', 'roles' => ['administrator' => []]],
    'posts only View reviews for it'         => ['roles_posted' => '1', 'roles' => ['administrator' => ['pqm_view_reviews']]],
] as $label => $post) {
    roles_world();
    setup($SITE_ADMIN, true, $stored_option);
    PQM_Admin::sanitize_settings($post);
    check("pinned: a POST that $label leaves the Administrator holding all five",
        array_values(array_filter($PQM_CAPS, [$GLOBALS['pqm_test_roles']['administrator'], 'has_cap'])) === $PQM_CAPS);
}
roles_world();
setup($SITE_ADMIN, true, $stored_option);
$GLOBALS['pqm_test_roles']['administrator']->remove_cap('pqm_manage_agents'); // a role editor removed it
$GLOBALS['pqm_test_roles']['writer']->add_cap('manage_options');               // a custom site-admin role
$GLOBALS['pqm_test_role_calls'] = [];
$post = roles_post();
$post['roles'] = []; // every enabled box cleared
PQM_Admin::sanitize_settings($post);
check('pinned: Save re-grants a missing cap to the Administrator', $GLOBALS['pqm_test_roles']['administrator']->has_cap('pqm_manage_agents'));
check('pinned by capability, not role name: the custom manage_options role gets all five',
    array_values(array_filter($PQM_CAPS, [$GLOBALS['pqm_test_roles']['writer'], 'has_cap'])) === $PQM_CAPS);
check('every box cleared: Editor, Author hold no pqm cap; their core caps stay',
    array_intersect_key(roles_snapshot()['editor'] + roles_snapshot()['author'], array_flip($PQM_CAPS)) === []
    && roles_snapshot()['editor'] === ['edit_others_posts' => true, 'edit_posts' => true]
    && roles_snapshot()['author'] === ['edit_posts' => true]);

roles_world();
setup($SITE_ADMIN, true, $stored_option);
$before = roles_snapshot();
PQM_Admin::sanitize_settings(roles_post(['writer' => ['pqm_run_free_agents']]));
check('custom role: writer gains pqm_run_free_agents only', roles_snapshot()['writer'] === ['read' => true, 'pqm_run_free_agents' => true]);
check('custom role: the other roles byte-identical', array_diff_key(roles_snapshot(), ['writer' => 1]) === array_diff_key($before, ['writer' => 1]));

roles_world();
setup($SITE_ADMIN, true, $stored_option);
PQM_Admin::sanitize_settings(roles_post(['author' => ['pqm_view_reviews', 'manage_options', 'edit_others_posts', ['nested']], 'ghost' => $PQM_CAPS]));
check('only the five pqm caps are written: a posted core cap is ignored, an unknown role skipped',
    $GLOBALS['pqm_test_role_calls'] === ['remove author pqm_run_free_agents'] && !isset($GLOBALS['pqm_test_roles']['ghost']));

/* Stale page (review loop 1): the panel is in the one form, so every pro
 * Administrator save posts it, from any tab. Only the cells that differ
 * from what the row rendered are written. */
roles_world();
setup($SITE_ADMIN, true, $stored_option);
$loaded = roles_post(); // the page as it loaded
$GLOBALS['pqm_test_roles']['author']->add_cap('pqm_run_paid_agents');      // granted elsewhere after load
$GLOBALS['pqm_test_roles']['editor']->remove_cap('pqm_manage_agents');     // removed elsewhere after load
$GLOBALS['pqm_test_roles']['reviewer'] = new WP_Role('reviewer', ['read' => true, 'pqm_view_reviews' => true]); // created after load
$GLOBALS['pqm_test_role_calls'] = [];
$before = roles_snapshot();
PQM_Admin::sanitize_settings($loaded);
check('stale page, no box changed: no role call, every role as it is now',
    $GLOBALS['pqm_test_role_calls'] === [] && roles_snapshot() === $before);
check('stale page: a cap granted elsewhere after load survives a save that did not touch that cell',
    $GLOBALS['pqm_test_roles']['author']->has_cap('pqm_run_paid_agents'));
check('stale page: a cap removed elsewhere after load stays removed',
    !$GLOBALS['pqm_test_roles']['editor']->has_cap('pqm_manage_agents'));
check('stale page: a role absent from the POST (created since) keeps its caps',
    $GLOBALS['pqm_test_roles']['reviewer']->capabilities === ['read' => true, 'pqm_view_reviews' => true]);
$changed = $loaded;
$changed['roles']['author'] = array_values(array_diff($changed['roles']['author'], ['pqm_view_reviews']));
PQM_Admin::sanitize_settings($changed);
check('stale page: an Administrator who unchecks a box still removes it, and only that cell is written',
    $GLOBALS['pqm_test_role_calls'] === ['remove author pqm_view_reviews']
    && $GLOBALS['pqm_test_roles']['author']->has_cap('pqm_run_paid_agents'));
$GLOBALS['pqm_test_role_calls'] = [];
$forged = $loaded;
$forged['roles']['reviewer'] = $PQM_CAPS; // boxes for a row the page never rendered
PQM_Admin::sanitize_settings($forged);
check('stale page: a row with no roles_shown entry (forged) is never touched',
    $GLOBALS['pqm_test_role_calls'] === [] && $GLOBALS['pqm_test_roles']['reviewer']->capabilities === ['read' => true, 'pqm_view_reviews' => true]);
$GLOBALS['pqm_test_role_calls'] = [];
$checked_box = $loaded;
$checked_box['roles']['contributor'] = ['pqm_view_reviews']; // contributor rendered with nothing checked
PQM_Admin::sanitize_settings($checked_box);
check('stale page: a box checked on a row that rendered empty is granted',
    $GLOBALS['pqm_test_role_calls'] === ['add contributor pqm_view_reviews']);

/* A role pinned at load (no roles_shown posted) that lost manage_options before Save is skipped. */
roles_world();
setup($SITE_ADMIN, true, $stored_option);
$GLOBALS['pqm_test_roles']['writer']->add_cap('manage_options');
foreach ($PQM_CAPS as $cap) { $GLOBALS['pqm_test_roles']['writer']->add_cap($cap); }
$pinned_at_load = roles_post(); // writer rendered pinned: no roles_shown, no boxes
$GLOBALS['pqm_test_roles']['writer']->remove_cap('manage_options'); // lost it before Save
$GLOBALS['pqm_test_role_calls'] = [];
PQM_Admin::sanitize_settings($pinned_at_load);
check('pinned at load, manage_options lost before Save: the role keeps its pqm caps, no role call',
    !isset($pinned_at_load['roles_shown']['writer']) && $GLOBALS['pqm_test_role_calls'] === []
    && array_values(array_filter($PQM_CAPS, [$GLOBALS['pqm_test_roles']['writer'], 'has_cap'])) === $PQM_CAPS);

/* Nobody else's save touches a role. */
roles_world();
$all_cleared = roles_post();
$all_cleared['roles'] = [];
foreach ([
    'Editor (pqm_manage_agents, no manage_options), tab forged'  => [['pqm_manage_agents'], true, $all_cleared],
    'settings-only (pqm_manage_settings), tab forged'             => [['pqm_manage_settings'], true, $all_cleared],
    'Admin, free site, tab forged'                                => [$SITE_ADMIN, false, $all_cleared],
    'Admin, free site, locked tab (nothing posted)'               => [$SITE_ADMIN, false, []],
    'Admin, pro, boxes without the marker'                        => [$SITE_ADMIN, true, ['roles' => []]],
    'Admin, pro, programmatic update_option (setup dialog)'       => [$SITE_ADMIN, true, null],
] as $label => [$caps, $pro, $post]) {
    roles_world();
    setup($caps, $pro, $stored_option);
    $before = roles_snapshot();
    PQM_Admin::sanitize_settings($post ?? array_merge(pqm_get_settings(), ['n8n_webhook_url' => 'https://n8n.example.com/webhook/new']));
    check("$label: roles unchanged, no role call", roles_snapshot() === $before && $GLOBALS['pqm_test_role_calls'] === []);
}
check('no save ever queried users (per-user grants untouched)', $GLOBALS['pqm_test_user_queries'] === []);

harness_exit();
