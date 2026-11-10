<?php
/**
 * Plugin Name:       Post Quality Manager
 * Plugin URI:        https://postquality.com
 * Description:       Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin.
 * x-release-please-start-version
 * Version:           3.13.2
 * x-release-please-end
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Vincent Heimann
 * Author URI:        https://postquality.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-quality-manager
 *
 * @fs_premium_only /includes/class-pqm-cron.php, /templates/n8n-workflow.json
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ─── Licensing: Freemius SDK (ADR 0013, story 44) ───────────────────── */
// Loads first, before plugins_loaded. PostQuality_License reads the license state
// from it; the SDK's own screens (opt-in, license activation, Account) carry
// the key. Nothing is sent before the opt-in choice (is_org_compliant).
// The function_exists guard is also the harness seam: tests/harness/fs-stub.php
// defines postquality_fs() first, so no harness loads the SDK.
if (!function_exists('postquality_fs')) {
    function postquality_fs()
    {
        global $postquality_fs;

        if (!isset($postquality_fs)) {
            require_once __DIR__ . '/vendor/freemius/start.php';

            $postquality_fs = fs_dynamic_init([
                'id'                  => '41144',
                'slug'                => 'post-quality-manager',
                'type'                => 'plugin',
                'public_key'          => 'pk_ca2d449d0ee2b01bbdbdeb9bf3789',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                // Account only in the menu: no pricing, contact, support or
                // affiliation entry. Upsell links open POSTQUALITY_PRICING_URL (bug 59).
                'menu'                => [
                    'slug'        => 'postquality-dashboard',
                    'account'     => true,
                    'pricing'     => false,
                    'contact'     => false,
                    'support'     => false,
                    'affiliation' => false,
                    'addons'      => false,
                ],
                // Both keys as Freemius's deployment writes them (story 54 diff,
                // 2026-10-13): is_live explicit, has_premium_version left to its
                // default (= has_paid_plans), so the generated builds match the source.
                'is_live'             => true,
            ]);
        }

        return $postquality_fs;
    }

    postquality_fs();
    do_action('postquality_fs_loaded');
} elseif (postquality_fs() instanceof Freemius) {
    // Both builds are installed (ADR 0014): the Free one from the directory
    // and the Pro download beside it (post-quality-manager-premium/), or the
    // reverse. The build loaded first owns the SDK; this file only tells it
    // which build is being activated and loads nothing twice (every function
    // below would be redeclared). The SDK's activation hook then deactivates
    // the other build — Freemius's dual-activation pattern. Ceiling: two
    // copies of the same build, which the SDK does not tell apart either.
    postquality_fs()->set_basename(!postquality_fs()->is_premium(), __FILE__);
    return;
}

/* ─── Constants ──────────────────────────────────────────────────────── */
define('POSTQUALITY_VERSION', '3.13.2'); // x-release-please-version
define('POSTQUALITY_PLUGIN_FILE', __FILE__);
define('POSTQUALITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POSTQUALITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POSTQUALITY_REST_NAMESPACE', 'post-quality/v1');
// The public pricing page, the one place Pro is sold (ADR 0013): every upsell link opens it (bug 59).
define('POSTQUALITY_PRICING_URL', 'https://postquality.com/pricing/');
// Bumped whenever templates/n8n-workflow.json changes in a way that requires
// a re-import, together with the template's Parse Input stamp and its Webhook
// Trigger reply. The workflow reports its version on a test ping (v5+) and in
// every WP callback, so the n8n Workflow page can flag outdated imports.
define('POSTQUALITY_WORKFLOW_VERSION', 11);

/* ─── Includes ───────────────────────────────────────────────────────── */
// Every postquality_* helper lives in includes/pqm-functions.php, never here: PHP
// declares a file's top-level functions at compile time, so the other build's
// early return above (dual activation) could not stop them being redeclared.
require_once POSTQUALITY_PLUGIN_DIR . 'includes/pqm-functions.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-activator.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-db.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-license.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-features.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-blocks.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-n8n.php';
require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-rest-api.php';
require_once POSTQUALITY_PLUGIN_DIR . 'admin/class-pqm-admin.php';
require_once POSTQUALITY_PLUGIN_DIR . 'admin/class-pqm-meta-box.php';

/* ─── Activation / Deactivation / Uninstall ──────────────────────────── */
register_activation_hook(__FILE__, ['PostQuality_Activator', 'activate']);
// The SDK owns the uninstall hook (WordPress would run an uninstall.php
// instead), and calls this after its own cleanup, opted in or not.
postquality_fs()->add_action('after_uninstall', ['PostQuality_Activator', 'uninstall']);
// The plugin's own icon (story 51) for the SDK's screens, so it never fetches
// one from Freemius (before the opt-in choice) nor writes one into vendor/.
postquality_fs()->add_filter('plugin_icon', static fn() => POSTQUALITY_PLUGIN_DIR . 'admin/img/post-quality-manager.png');
// Every Upgrade link the SDK renders (the Account page's Upgrade tab and plan buttons, the lapse
// notices) opens the public pricing page, not the SDK's hidden in-admin one (bug 59). The SDK also
// asks this filter with null whether a custom URL replaces its page: answering null keeps that page,
// and the license-aware renewal checkout built on it, registered. See memory/freemium-and-roles.md.
postquality_fs()->add_filter('pricing_url', static fn($url) => null === $url ? null : POSTQUALITY_PRICING_URL);
// The deactivation feedback form opens only on a site that shares usage data now, so a site that
// skipped the opt-in or opted out since sees no form and stores no answer (bug 90, the privacy
// policy's promise). See memory/freemium-and-roles.md.
postquality_fs()->add_filter('show_deactivation_feedback_form', static function () {
    $fs = postquality_fs();

    return PostQuality_License::shares_usage_data() && !$fs::is_deactivation_snoozed();
});
if ( postquality_fs()->is__premium_only() ) {
    // Pro waiting for its license asks for the license, not for an opt-in; the SDK keeps the link.
    // See memory/freemium-and-roles.md (story 65).
    postquality_fs()->add_filter('sticky_message_connect_account', static fn() => sprintf(
        '%s <b><a href="%s">%s</a></b>',
        esc_html__('Pro is installed. Enter your license key to unlock it.', 'post-quality-manager'),
        esc_url(postquality_fs()->get_activation_url()),
        esc_html__('Activate your license', 'post-quality-manager')
    ));
    // The SDK's sync detaches an expired license, so the lapse is recorded at the transition (an expiry
    // or a cancellation only) and ends when a license comes back; the SDK's expired-license notice gets
    // the renew link, fixed when the notice is added (cron included). See memory/freemium-and-roles.md (bug 93).
    postquality_fs()->add_action('after_license_deactivation', ['PostQuality_License', 'note_license_deactivation__premium_only']);
    postquality_fs()->add_action('after_license_change', ['PostQuality_License', 'record_lapse__premium_only']);
    postquality_fs()->add_action('after_license_activation', ['PostQuality_License', 'forget_lapse__premium_only']);
    postquality_fs()->add_filter('sticky_message_license_expired', static fn($message) => sprintf(
        '%s <a href="%s">%s</a>',
        $message,
        esc_url(POSTQUALITY_PRICING_URL),
        esc_html__('Renew Pro', 'post-quality-manager')
    ));
}
// Once nothing is awaited (licensed, opted in or skipped), a stored opt-in notice is a leftover: it goes.
// See memory/freemium-and-roles.md (story 65).
add_action('admin_init', static function () {
    if (!postquality_fs()->is_activation_mode()) {
        postquality_fs()->remove_sticky('connect_account');
    }
});
// At deletion, a site that does not share usage data now drops any stored feedback answer before
// the SDK's own uninstall step runs (bug 90). See memory/freemium-and-roles.md.
add_action('uninstall_' . plugin_basename(__FILE__), ['PostQuality_License', 'forget_feedback_unless_sharing'], 9);
// Capability upgrade for installs updated without reactivation (REST and cron requests included).
add_action('init', ['PostQuality_Activator', 'maybe_upgrade']);

/* ─── REST API ───────────────────────────────────────────────────────── */
add_action('rest_api_init', ['PostQuality_REST_API', 'register_routes']);

/* ─── Admin ──────────────────────────────────────────────────────────── */
if (is_admin()) {
    add_action('admin_menu', ['PostQuality_Admin', 'register_pages']);
    add_action('admin_init', ['PostQuality_Admin', 'register_settings']);
    add_action('admin_init', ['PostQuality_Admin', 'privacy_policy_content']);
    add_action('admin_notices', ['PostQuality_Admin', 'admin_notices']);
    add_action('admin_enqueue_scripts', ['PostQuality_Admin', 'enqueue_assets']);
    add_action('updated_option', ['PostQuality_Admin', 'on_settings_updated'], 10, 3);
    add_action('wp_ajax_postquality_test_connection', ['PostQuality_Admin', 'ajax_test_connection']);
    add_action('wp_ajax_postquality_workflow_status', ['PostQuality_Admin', 'ajax_workflow_status']);
    add_action('admin_post_postquality_download_workflow', ['PostQuality_Admin', 'download_workflow']);
    // Story 109: the Post Quality box under the content of posts, and its script on the post edit screens.
    add_action('add_meta_boxes_post', ['PostQuality_Meta_Box', 'register']);
    add_action('admin_enqueue_scripts', ['PostQuality_Meta_Box', 'enqueue']);
}

/* ─── Cron (Pro build only) ──────────────────────────────────────────── */
// ADR 0014: Freemius strips this block and the file (@fs_premium_only) from
// the Free build. A schedule a former Pro install left behind then has no
// callback and runs nothing.
if ( postquality_fs()->is__premium_only() ) {
    require_once POSTQUALITY_PLUGIN_DIR . 'includes/class-pqm-cron.php';
    register_deactivation_hook(__FILE__, ['PostQuality_Cron', 'clear_scheduled_events']);
    add_action('postquality_cron_review_posts', ['PostQuality_Cron', 'run_scheduled_review']);
    add_filter('cron_schedules', ['PostQuality_Cron', 'add_custom_schedules']);
}
