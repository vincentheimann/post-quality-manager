<?php
/**
 * Plugin Name:       Post Quality Manager
 * Plugin URI:        https://postquality.com
 * Description:       Content quality dashboard — store review results from n8n workflows, schedule automated reviews, and get a global overview of your blog content quality.
 * x-release-please-start-version
 * Version:           3.5.0
 * x-release-please-end
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            Vincent Heimann
 * Author URI:        https://postquality.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-quality-manager
 * Domain Path:       /languages
 *
 * @fs_premium_only /includes/class-pqm-cron.php, /templates/n8n-workflow.json
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ─── Licensing: Freemius SDK (ADR 0013, story 44) ───────────────────── */
// Loads first, before plugins_loaded. PQM_License reads the license state
// from it; the SDK's own screens (opt-in, license activation, Account) carry
// the key. Nothing is sent before the opt-in choice (is_org_compliant).
// The function_exists guard is also the harness seam: tests/harness/fs-stub.php
// defines pqm_fs() first, so no harness loads the SDK.
if (!function_exists('pqm_fs')) {
    function pqm_fs()
    {
        global $pqm_fs;

        if (!isset($pqm_fs)) {
            require_once __DIR__ . '/vendor/freemius/start.php';

            $pqm_fs = fs_dynamic_init([
                'id'                  => '41144',
                'slug'                => 'post-quality-manager',
                'type'                => 'plugin',
                'public_key'          => 'pk_ca2d449d0ee2b01bbdbdeb9bf3789',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                // Account only: no pricing, contact, support or affiliation
                // submenu; the one upsell is the Settings → License section.
                'menu'                => [
                    'slug'        => 'pqm-dashboard',
                    'account'     => true,
                    'pricing'     => false,
                    'contact'     => false,
                    'support'     => false,
                    'affiliation' => false,
                    'addons'      => false,
                ],
            ]);
        }

        return $pqm_fs;
    }

    pqm_fs();
    do_action('pqm_fs_loaded');
} elseif (pqm_fs() instanceof Freemius) {
    // Both builds are installed (ADR 0014): the Free one from the directory
    // and the Pro download beside it (post-quality-manager-premium/), or the
    // reverse. The build loaded first owns the SDK; this file only tells it
    // which build is being activated and loads nothing twice (every function
    // below would be redeclared). The SDK's activation hook then deactivates
    // the other build — Freemius's dual-activation pattern. Ceiling: two
    // copies of the same build, which the SDK does not tell apart either.
    pqm_fs()->set_basename(!pqm_fs()->is_premium(), __FILE__);
    return;
}

/* ─── Constants ──────────────────────────────────────────────────────── */
define('PQM_VERSION', '3.5.0'); // x-release-please-version
define('PQM_PLUGIN_FILE', __FILE__);
define('PQM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PQM_REST_NAMESPACE', 'post-quality/v1');
// Bumped whenever templates/n8n-workflow.json changes in a way that requires
// a re-import, together with the template's Parse Input stamp and its Webhook
// Trigger reply. The workflow reports its version on a test ping (v5+) and in
// every WP callback, so the n8n Workflow page can flag outdated imports.
define('PQM_WORKFLOW_VERSION', 5);

/* ─── Includes ───────────────────────────────────────────────────────── */
// Every pqm_* helper lives in includes/pqm-functions.php, never here: PHP
// declares a file's top-level functions at compile time, so the other build's
// early return above (dual activation) could not stop them being redeclared.
require_once PQM_PLUGIN_DIR . 'includes/pqm-functions.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-activator.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-db.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-license.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-features.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-blocks.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-rest-api.php';
require_once PQM_PLUGIN_DIR . 'admin/class-pqm-admin.php';

/* ─── Activation / Deactivation / Uninstall ──────────────────────────── */
register_activation_hook(__FILE__, ['PQM_Activator', 'activate']);
// The SDK owns the uninstall hook (WordPress would run an uninstall.php
// instead), and calls this after its own cleanup, opted in or not.
pqm_fs()->add_action('after_uninstall', ['PQM_Activator', 'uninstall']);
// The plugin's own icon (story 51) for the SDK's screens, so it never fetches
// one from Freemius (before the opt-in choice) nor writes one into vendor/.
pqm_fs()->add_filter('plugin_icon', static fn() => PQM_PLUGIN_DIR . 'admin/img/post-quality-manager.png');
// Capability upgrade for installs updated without reactivation (REST and cron requests included).
add_action('init', ['PQM_Activator', 'maybe_upgrade']);

/* ─── Translations ───────────────────────────────────────────────────── */
add_action('init', 'pqm_load_textdomain');

/* ─── REST API ───────────────────────────────────────────────────────── */
add_action('rest_api_init', ['PQM_REST_API', 'register_routes']);

/* ─── Admin ──────────────────────────────────────────────────────────── */
if (is_admin()) {
    add_action('admin_menu', ['PQM_Admin', 'register_pages']);
    add_action('admin_init', ['PQM_Admin', 'register_settings']);
    add_action('admin_notices', ['PQM_Admin', 'admin_notices']);
    add_action('admin_enqueue_scripts', ['PQM_Admin', 'enqueue_assets']);
    add_action('updated_option', ['PQM_Admin', 'on_settings_updated'], 10, 3);
    add_action('wp_ajax_pqm_test_connection', ['PQM_Admin', 'ajax_test_connection']);
    add_action('wp_ajax_pqm_workflow_status', ['PQM_Admin', 'ajax_workflow_status']);
    add_action('admin_post_pqm_download_workflow', ['PQM_Admin', 'download_workflow']);
}

/* ─── Cron (Pro build only) ──────────────────────────────────────────── */
// ADR 0014: Freemius strips this block and the file (@fs_premium_only) from
// the Free build. A schedule a former Pro install left behind then has no
// callback and runs nothing.
if ( pqm_fs()->is__premium_only() ) {
    require_once PQM_PLUGIN_DIR . 'includes/class-pqm-cron.php';
    register_deactivation_hook(__FILE__, ['PQM_Cron', 'clear_scheduled_events']);
    add_action('pqm_cron_review_posts', ['PQM_Cron', 'run_scheduled_review']);
    add_filter('cron_schedules', ['PQM_Cron', 'add_custom_schedules']);
}
