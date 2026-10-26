<?php
/**
 * CLI harness — story 26 "AI Prompts & Context": the radio is the setting.
 * Matrix rows 1–8 of the plan against postquality_resolved_voice() /
 * postquality_resolved_context() (the payload's brand_voice and website_context)
 * and sanitize_settings() (the round trip through a preset), with the
 * locale switch stubbed to prove the site language is used and restored.
 * Run: php voice-harness.php
 */

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['pqm_test_caps'] = [];
$GLOBALS['tw'] = ['options' => []];

/* Locale: the site setting vs. the triggering user's language. __() tags
 * every string with the current locale so a wrong language is visible. */
$GLOBALS['pqm_site_locale'] = 'en_US';
$GLOBALS['pqm_locale_stack'] = ['en_US']; // top = current locale
$GLOBALS['pqm_locale_calls'] = ['switch' => 0, 'restore' => 0];

function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/post-quality-manager/'; }
function is_admin() { return false; }
function current_locale() { return end($GLOBALS['pqm_locale_stack']); }
function __($s, $d = null) { return current_locale() === 'en_US' ? $s : '[' . current_locale() . '] ' . $s; }
function get_locale() { return $GLOBALS['pqm_site_locale']; }
function switch_to_locale($locale) {
    // Core returns false (and switches nothing) when $locale is already current.
    if ($locale === current_locale()) { return false; }
    $GLOBALS['pqm_locale_calls']['switch']++;
    $GLOBALS['pqm_locale_stack'][] = $locale;
    return true;
}
function restore_previous_locale() {
    $GLOBALS['pqm_locale_calls']['restore']++;
    array_pop($GLOBALS['pqm_locale_stack']);
    return current_locale();
}
function apply_filters($tag, $value, ...$a) { return $value; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['pqm_test_caps'], true); }
function add_settings_error(...$a) {}

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
function settings(array $stored): array { return array_merge(postquality_default_settings(), $stored); }

$direct    = postquality_voice_presets()['direct']['instruction'];
$technical = PostQuality_Admin::blog_profiles()['technical']['website_context'];
$custom_voice   = 'Use British English. Cite a source for every statistic.';
$custom_context = 'A blog about home automation for tech-savvy homeowners.';

/* ─── Rows 1–4: Editorial Standards → brand_voice ───────────────────── */
echo "\nRow 1: preset selected\n";
check('Direct instruction, whatever the stored text',
    postquality_resolved_voice(settings(['brand_voice_preset' => 'direct', 'brand_voice' => 'Stale text'])) === $direct);

echo "\nRow 2: Custom + typed text\n";
check('the typed text',
    postquality_resolved_voice(settings(['brand_voice_preset' => 'custom', 'brand_voice' => $custom_voice])) === $custom_voice);

echo "\nRow 3: Custom, empty\n";
check("'' (the template default applies)",
    postquality_resolved_voice(settings(['brand_voice_preset' => 'custom', 'brand_voice' => ''])) === '');

echo "\nRow 4: unknown preset key\n";
check('the stored text (treated as Custom)',
    postquality_resolved_voice(settings(['brand_voice_preset' => 'retired', 'brand_voice' => $custom_voice])) === $custom_voice);

/* ─── Rows 5–6: Blog Profile → website_context ──────────────────────── */
echo "\nRow 5: profile selected\n";
check('Technical text, whatever the stored text',
    postquality_resolved_context(settings(['blog_profile' => 'technical', 'website_context' => 'Stale text'])) === $technical);

echo "\nRow 6: Custom profile + typed text\n";
check('the typed text',
    postquality_resolved_context(settings(['blog_profile' => 'custom', 'website_context' => $custom_context])) === $custom_context);
check("Custom, empty: '' (the template default applies)",
    postquality_resolved_context(settings(['blog_profile' => 'custom', 'website_context' => ''])) === '');
check('unknown profile key: the stored text',
    postquality_resolved_context(settings(['blog_profile' => 'retired', 'website_context' => $custom_context])) === $custom_context);

/* ─── Row 7: a user in another locale triggers ──────────────────────── */
echo "\nRow 7: trigger by a user in fr_FR, site en_US\n";
$GLOBALS['pqm_locale_stack'] = ['en_US', 'fr_FR']; // the admin's profile language is current
$GLOBALS['pqm_locale_calls'] = ['switch' => 0, 'restore' => 0];
check('sanity: preset text read directly is in fr_FR', postquality_voice_presets()['direct']['instruction'] === '[fr_FR] ' . $direct);
$both = settings(['brand_voice_preset' => 'direct', 'blog_profile' => 'technical']);
check('brand_voice in en_US', postquality_resolved_voice($both) === $direct);
check('website_context in en_US', postquality_resolved_context($both) === $technical);
check('switched to the site locale once per resolution', $GLOBALS['pqm_locale_calls']['switch'] === 2);
check('restored once per switch', $GLOBALS['pqm_locale_calls']['restore'] === 2);
check('the user locale is current again', current_locale() === 'fr_FR');

echo "\nRow 7b: already in the site locale (cron)\n";
$GLOBALS['pqm_locale_stack'] = ['en_US'];
$GLOBALS['pqm_locale_calls'] = ['switch' => 0, 'restore' => 0];
check('brand_voice in en_US', postquality_resolved_voice($both) === $direct);
check('no switch, so no restore', $GLOBALS['pqm_locale_calls'] === ['switch' => 0, 'restore' => 0]);

echo "\nRow 7c: the locale is restored when resolution throws\n";
$GLOBALS['pqm_locale_stack'] = ['en_US', 'fr_FR'];
try {
    postquality_in_site_locale(static function () { throw new RuntimeException('boom'); });
} catch (RuntimeException $e) {
}
check('the user locale is current again', current_locale() === 'fr_FR');
$GLOBALS['pqm_locale_stack'] = ['en_US'];

/* ─── Row 8: round trip through a preset ────────────────────────────── */
echo "\nRow 8: Custom text saved, switch to a preset, Save, switch back\n";
$GLOBALS['pqm_test_caps'] = ['pqm_manage_settings'];
$saved = settings([
    'blog_profile' => 'custom', 'website_context' => $custom_context,
    'brand_voice_preset' => 'custom', 'brand_voice' => $custom_voice,
]);
$GLOBALS['tw']['options'] = ['pqm_settings' => $saved];
// The hidden Custom textareas still post their stored text.
$on_preset = PostQuality_Admin::sanitize_settings([
    'blog_profile'       => 'technical',
    'brand_voice_preset' => 'direct',
    'website_context'    => $saved['website_context'],
    'brand_voice'        => $saved['brand_voice'],
]);
check('preset saved: payload sends the Technical text', postquality_resolved_context($on_preset) === $technical);
check('preset saved: payload sends the Direct instruction', postquality_resolved_voice($on_preset) === $direct);
check('preset saved: custom context kept', $on_preset['website_context'] === $custom_context);
check('preset saved: custom standards kept', $on_preset['brand_voice'] === $custom_voice);

$GLOBALS['tw']['options'] = ['pqm_settings' => $on_preset];
$back = PostQuality_Admin::sanitize_settings([
    'blog_profile'       => 'custom',
    'brand_voice_preset' => 'custom',
    'website_context'    => $on_preset['website_context'],
    'brand_voice'        => $on_preset['brand_voice'],
]);
check('back on Custom: the custom context is sent', postquality_resolved_context($back) === $custom_context);
check('back on Custom: the custom standards are sent', postquality_resolved_voice($back) === $custom_voice);

harness_exit();
