<?php
/**
 * Plugin Name:       Post Quality Manager
 * Plugin URI:        https://github.com/vincentheimann/post-quality-manager
 * Description:       Content quality dashboard — store review results from n8n workflows, schedule automated reviews, and get a global overview of your blog content quality.
 * Version:           3.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Vincent Heimann
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-quality-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ─── Constants ──────────────────────────────────────────────────────── */
define('PQM_VERSION', '3.0.0');
define('PQM_PLUGIN_FILE', __FILE__);
define('PQM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PQM_REST_NAMESPACE', 'post-quality/v1');

/* ─── Includes ───────────────────────────────────────────────────────── */
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-activator.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-db.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-rest-api.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-cron.php';
require_once PQM_PLUGIN_DIR . 'admin/class-pqm-admin.php';

/* ─── Activation / Deactivation ──────────────────────────────────────── */
register_activation_hook(__FILE__, ['PQM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PQM_Cron', 'clear_scheduled_events']);

/* ─── REST API ───────────────────────────────────────────────────────── */
add_action('rest_api_init', ['PQM_REST_API', 'register_routes']);

/* ─── Admin ──────────────────────────────────────────────────────────── */
if (is_admin()) {
    add_action('admin_menu', ['PQM_Admin', 'register_pages']);
    add_action('admin_init', ['PQM_Admin', 'register_settings']);
    add_action('admin_notices', ['PQM_Admin', 'admin_notices']);
    add_action('admin_enqueue_scripts', ['PQM_Admin', 'enqueue_assets']);
    add_action('updated_option', ['PQM_Admin', 'on_settings_updated']);
    add_action('wp_ajax_pqm_trigger_command', ['PQM_Admin', 'ajax_trigger_command']);
}

/* ─── Cron ───────────────────────────────────────────────────────────── */
add_action('pqm_cron_review_posts', ['PQM_Cron', 'run_scheduled_review']);
add_filter('cron_schedules', ['PQM_Cron', 'add_custom_schedules']);

/* ─── Helper: Get plugin settings with defaults ──────────────────────── */
function pqm_get_settings(): array
{
    $defaults = [
        'n8n_webhook_url' => '',
        'webhook_token' => wp_generate_uuid4(),
        'notification_url' => '',
        'cron_enabled' => false,
        'cron_frequency' => 'daily',
        'cron_batch_size' => 5,
        'cron_min_score' => 60,
        'cron_max_age_days' => 30,
        'cron_command' => 'review',
        'agent_prompts' => [
            'review' => ['instructions' => '', 'temperature' => 0.3],
            'takeaways' => ['instructions' => '', 'temperature' => 0.3],
            'sources' => ['instructions' => '', 'temperature' => 0.0],
            'seo' => ['instructions' => '', 'temperature' => 0.0],
            'audit' => ['instructions' => '', 'temperature' => 0.0],
            'rewrite' => ['instructions' => '', 'temperature' => 0.3],
        ],
    ];

    $saved = get_option('pqm_settings', []);

    $merged = wp_parse_args($saved, $defaults);
    if (isset($saved['agent_prompts']) && is_array($saved['agent_prompts'])) {
        $merged['agent_prompts'] = wp_parse_args($saved['agent_prompts'], $defaults['agent_prompts']);
        foreach (array_keys($defaults['agent_prompts']) as $agent) {
            if (isset($saved['agent_prompts'][$agent]) && is_array($saved['agent_prompts'][$agent])) {
                $merged['agent_prompts'][$agent] = wp_parse_args($saved['agent_prompts'][$agent], $defaults['agent_prompts'][$agent]);
            }
        }
    }

    return $merged;
}
