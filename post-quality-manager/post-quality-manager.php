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
    add_action('wp_ajax_pqm_test_connection', ['PQM_Admin', 'ajax_test_connection']);
    add_action('admin_post_pqm_download_workflow', ['PQM_Admin', 'download_workflow']);
}

/* ─── Cron ───────────────────────────────────────────────────────────── */
add_action('pqm_cron_review_posts', ['PQM_Cron', 'run_scheduled_review']);
add_filter('cron_schedules', ['PQM_Cron', 'add_custom_schedules']);

/* ─── Helper: Settings defaults (single source of truth) ─────────────── */
function pqm_default_settings(): array
{
    return [
        'n8n_webhook_url' => '',
        'webhook_token' => wp_generate_uuid4(),
        'notification_url' => '',
        'blog_profile' => 'custom',
        'brand_voice_preset' => 'custom',
        'brand_voice' => '',
        'website_context' => '',
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
}

/* ─── Helper: Brand voice presets (single source of truth) ───────────── */
/**
 * Preset voice instructions shared by the settings UI (labels + pre-fill)
 * and the n8n payload builder (legacy resolution). 'custom' is a UI state,
 * not a preset, so it is not listed here.
 */
function pqm_voice_presets(): array
{
    return [
        'professional' => [
            'label'       => __('Professional & Authoritative', 'post-quality-manager'),
            'instruction' => __('Professional and authoritative: precise, confident wording; active voice; back claims with evidence; no hype or filler.', 'post-quality-manager'),
        ],
        'casual' => [
            'label'       => __('Casual & Conversational', 'post-quality-manager'),
            'instruction' => __('Casual and conversational: warm, first-person tone; everyday words; short sentences; reads like advice from a knowledgeable friend.', 'post-quality-manager'),
        ],
        'direct' => [
            'label'       => __('Direct & Action-Oriented', 'post-quality-manager'),
            'instruction' => __('Direct and action-oriented: lead with the point; concrete next steps; cut anything that does not help the reader act.', 'post-quality-manager'),
        ],
    ];
}

/* ─── Helper: n8n workflow JSON with site values injected ────────────── */
/**
 * Bundled workflow template with this site's values injected. Shared by the
 * settings-page download and the REST import-from-URL endpoint.
 * Returns null when the template file is missing.
 */
function pqm_workflow_json(): ?string
{
    $template_path = PQM_PLUGIN_DIR . 'templates/n8n-workflow.json';
    if (!file_exists($template_path)) {
        return null;
    }

    $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'my-site';

    return str_replace('__PQM_SITE_HOST__', $host, file_get_contents($template_path));
}

/* ─── Helper: Get plugin settings with defaults ──────────────────────── */
function pqm_get_settings(): array
{
    $defaults = pqm_default_settings();

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
