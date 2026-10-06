<?php
/**
 * Plugin Name:       Post Quality Manager
 * Plugin URI:        https://github.com/vincentheimann/post-quality-manager
 * Description:       Content quality dashboard — store review results from n8n workflows, schedule automated reviews, and get a global overview of your blog content quality.
 * Version:           3.0.0
 * Requires at least: 6.6
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
// Bumped whenever templates/n8n-workflow.json changes in a way that requires
// a re-import. The template echoes its own version in every WP callback so
// the n8n Workflow page can flag outdated imports.
define('PQM_WORKFLOW_VERSION', 2);

/* ─── Includes ───────────────────────────────────────────────────────── */
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-activator.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-db.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-license.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-features.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-blocks.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-rest-api.php';
require_once PQM_PLUGIN_DIR . 'includes/class-pqm-cron.php';
require_once PQM_PLUGIN_DIR . 'admin/class-pqm-admin.php';

/* ─── Activation / Deactivation ──────────────────────────────────────── */
register_activation_hook(__FILE__, ['PQM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PQM_Cron', 'clear_scheduled_events']);
// Capability upgrade for installs updated without reactivation (REST and cron requests included).
add_action('init', ['PQM_Activator', 'maybe_upgrade']);

/* ─── REST API ───────────────────────────────────────────────────────── */
add_action('rest_api_init', ['PQM_REST_API', 'register_routes']);

/* ─── Admin ──────────────────────────────────────────────────────────── */
if (is_admin()) {
    add_action('admin_menu', ['PQM_Admin', 'register_pages']);
    add_action('admin_init', ['PQM_Admin', 'register_settings']);
    add_action('admin_notices', ['PQM_Admin', 'admin_notices']);
    add_action('admin_enqueue_scripts', ['PQM_Admin', 'enqueue_assets']);
    add_action('updated_option', ['PQM_Admin', 'on_settings_updated']);
    add_action('wp_ajax_pqm_test_connection', ['PQM_Admin', 'ajax_test_connection']);
    add_action('wp_ajax_pqm_save_webhook_url', ['PQM_Admin', 'ajax_save_webhook_url']);
    add_action('admin_post_pqm_download_workflow', ['PQM_Admin', 'download_workflow']);
}

/* ─── Cron ───────────────────────────────────────────────────────────── */
add_action('pqm_cron_review_posts', ['PQM_Cron', 'run_scheduled_review']);
add_filter('cron_schedules', ['PQM_Cron', 'add_custom_schedules']);

/* ─── Helper: Settings defaults (single source of truth) ─────────────── */
function pqm_default_settings(): array
{
    return [
        // Stub licensing (CAP-2): any non-empty key = pro, via PQM_License.
        'license_key' => '',
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
        // Monthly manual-trigger limit per quota-subject user; 0 = unlimited (CAP-4).
        'author_quota' => 20,
        'default_model' => 'claude-sonnet-4-6',
        'custom_model' => '',
        // Per-agent: 'model' => '' means "use the default model above".
        'agent_prompts' => [
            'review' => ['instructions' => '', 'temperature' => 0.3, 'model' => '', 'effort' => 'high'],
            // 'pattern': the user pattern (wp_block ID) Insert into Post copies the items into,
            // 0 = plain list (PQM_Blocks) — these two agents only.
            'takeaways' => ['instructions' => '', 'temperature' => 0.3, 'model' => '', 'effort' => 'medium', 'pattern' => 0],
            'sources' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium', 'pattern' => 0],
            'seo' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium'],
            'audit' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium'],
            'rewrite' => ['instructions' => '', 'temperature' => 0.3, 'model' => '', 'effort' => 'high'],
        ],
    ];
}

/* ─── Helper: Claude model registry (single source of truth) ─────────── */
/**
 * Curated Claude models with the request parameters each one accepts.
 * Consumed by the settings UI, the sanitizer, the admin JS (via
 * wp_localize_script) and pqm_llm_params() — supporting a new model
 * means adding one entry here.
 *
 * Capability notes (Claude API):
 * - Opus 4.7+ and Sonnet 5 reject the temperature parameter (HTTP 400).
 * - effort levels vary per model; Haiku 4.5 rejects effort entirely.
 * - Sonnet 5 runs adaptive thinking when the thinking parameter is omitted;
 *   'thinking' => 'disabled' sends {type: "disabled"} so thinking tokens
 *   don't eat the agents' output budgets on this structured-JSON pipeline.
 */
function pqm_model_registry(): array
{
    return [
        'claude-opus-4-8' => [
            'label'                => __('Claude Opus 4.8 — highest quality', 'post-quality-manager'),
            'pricing'              => '$5 / $25',
            'supports_temperature' => false,
            'effort_levels'        => ['low', 'medium', 'high', 'xhigh', 'max'],
            'thinking'             => null,
        ],
        'claude-sonnet-5' => [
            'label'                => __('Claude Sonnet 5 — best quality/cost balance', 'post-quality-manager'),
            'pricing'              => '$3 / $15',
            'supports_temperature' => false,
            'effort_levels'        => ['low', 'medium', 'high', 'xhigh', 'max'],
            'thinking'             => 'disabled',
        ],
        'claude-sonnet-4-6' => [
            'label'                => __('Claude Sonnet 4.6 — proven default', 'post-quality-manager'),
            'pricing'              => '$3 / $15',
            'supports_temperature' => true,
            'effort_levels'        => ['low', 'medium', 'high', 'max'],
            'thinking'             => null,
        ],
        'claude-haiku-4-5' => [
            'label'                => __('Claude Haiku 4.5 — fastest, lowest cost', 'post-quality-manager'),
            'pricing'              => '$1 / $5',
            'supports_temperature' => true,
            'effort_levels'        => [],
            'thinking'             => null,
        ],
    ];
}

/**
 * Capabilities for a model ID. Unknown IDs (the Custom escape hatch) get a
 * conservative profile: no sampling, no effort, no thinking parameter —
 * every current Claude model accepts that request shape.
 */
function pqm_model_caps(string $model): array
{
    return pqm_model_registry()[$model] ?? [
        'label'                => $model,
        'pricing'              => '',
        'supports_temperature' => false,
        'effort_levels'        => [],
        'thinking'             => null,
    ];
}

/**
 * Model an agent will actually call: its own override when set, otherwise
 * the global default (resolving the Custom free-text ID).
 */
function pqm_effective_model(string $agent, array $settings): string
{
    $override = $settings['agent_prompts'][$agent]['model'] ?? '';
    if ($override !== '' && isset(pqm_model_registry()[$override])) {
        return $override;
    }

    if ($settings['default_model'] === 'custom') {
        return $settings['custom_model'] !== '' ? $settings['custom_model'] : 'claude-sonnet-4-6';
    }

    return $settings['default_model'];
}

/* ─── Helper: per-agent Claude request parameters ────────────────────── */
/**
 * Output budget per agent (tokens). Fixed pipeline values, not a setting.
 */
function pqm_agent_max_tokens(): array
{
    return [
        'review'    => 2500,
        'takeaways' => 1000,
        'sources'   => 2000,
        'seo'       => 1000,
        'audit'     => 5000,
        'rewrite'   => 8000,
    ];
}

/**
 * Ready-to-merge Claude request fragment for one agent. The n8n workflow
 * spreads this object into the request body as-is, so every LLM option
 * lives here (plugin-side) and never requires a workflow re-import.
 */
function pqm_llm_params(string $agent, array $settings): array
{
    $model = pqm_effective_model($agent, $settings);
    $caps  = pqm_model_caps($model);
    $agent_settings = $settings['agent_prompts'][$agent] ?? [];

    $params = [
        'model'      => $model,
        'max_tokens' => pqm_agent_max_tokens()[$agent] ?? 2000,
    ];

    if ($caps['supports_temperature']) {
        $params['temperature'] = round((float) ($agent_settings['temperature'] ?? 0), 1);
    }

    if (!empty($caps['effort_levels'])) {
        $effort = $agent_settings['effort'] ?? 'high';
        if (!in_array($effort, $caps['effort_levels'], true)) {
            $effort = 'high'; // every effort-capable model supports 'high'
        }
        $params['output_config'] = ['effort' => $effort];
    }

    if ($caps['thinking'] === 'disabled') {
        $params['thinking'] = ['type' => 'disabled'];
    }

    return $params;
}

/**
 * The full agent => request-fragment map shipped in every n8n payload.
 */
function pqm_llm_map(array $settings): array
{
    $map = [];
    foreach (array_keys(pqm_default_settings()['agent_prompts']) as $agent) {
        $map[$agent] = pqm_llm_params($agent, $settings);
    }
    return $map;
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
 * n8n Workflow page download and the REST import-from-URL endpoint.
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
                // Also migrates pre-model/effort settings: missing keys fall back to defaults.
                $merged['agent_prompts'][$agent] = wp_parse_args($saved['agent_prompts'][$agent], $defaults['agent_prompts'][$agent]);
            }
        }
    }

    return $merged;
}

/* ─── Helper: n8n webhook payload (single source of truth) ───────────── */
/**
 * Payload sent to the n8n webhook for one post + command. Shared by the
 * REST trigger endpoint and the cron batch so both ship identical data —
 * including the per-agent 'llm' request fragments.
 */
function pqm_n8n_payload(WP_Post $post, string $command, array $settings): array
{
    $post_id       = $post->ID;
    $content_html  = get_post_field('post_content', $post_id);
    $content_plain = wp_strip_all_tags(apply_filters('the_content', $content_html));
    $yoast_active  = defined('WPSEO_VERSION');

    $excerpt = has_excerpt($post_id)
        ? wp_strip_all_tags(get_the_excerpt($post_id))
        : wp_trim_words($content_plain, 55, '...');

    // Resolve brand voice: a selected preset always means its instruction
    // (also covers settings saved before the WYSIWYG voice field existed).
    $voice_presets  = pqm_voice_presets();
    $preset         = $settings['brand_voice_preset'];
    $resolved_voice = isset($voice_presets[$preset])
        ? $voice_presets[$preset]['instruction']
        : $settings['brand_voice'];

    return [
        'callback_url'      => rest_url(PQM_REST_NAMESPACE . '/save-review'),
        'auth_token'        => $settings['webhook_token'],
        'notification_url'  => $settings['notification_url'],
        'command'           => $command,
        'post_id'           => $post_id,
        'slug'              => $post->post_name,
        'post_type'         => $post->post_type,
        'title'             => html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'),
        'link'              => get_permalink($post_id),
        'excerpt'           => $excerpt,
        'content'           => mb_substr($content_plain, 0, 15000),
        'content_html'      => mb_substr($content_html, 0, 20000),
        'seo_score'         => $yoast_active ? (int) get_post_meta($post_id, '_yoast_wpseo_linkdex', true) : null,
        'readability_score' => $yoast_active ? (int) get_post_meta($post_id, '_yoast_wpseo_content_score', true) : null,
        'focus_keyphrase'   => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true) : '',
        'meta_description'  => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true) : '',
        'seo_title'         => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_title', true) : '',
        'brand_voice'       => $resolved_voice,
        'website_context'   => $settings['website_context'],
        'agent_prompts'     => $settings['agent_prompts'],
        'llm'               => pqm_llm_map($settings),
    ];
}
