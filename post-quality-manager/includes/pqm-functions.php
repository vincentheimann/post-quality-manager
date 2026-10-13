<?php
/**
 * Plugin-wide helpers: settings defaults, the model registry and request
 * parameters, voice and context presets, the n8n template, payload and
 * headers, the workflow version report, and the setup progress. Required by
 * the main file right after the constants. They live here and not in the
 * main file because PHP declares a file's top-level functions at compile
 * time: when both builds are installed (ADR 0014, story 54), the main file
 * of the second one returns before any require, and nothing is redeclared.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ─── Translations ───────────────────────────────────────────────────── */
/**
 * Registers languages/ for the text domain on init, never earlier: since
 * WordPress 6.7 the files load just in time, and an earlier load raises a
 * "too early" notice. The React bundle reads the same folder
 * (wp_set_script_translations in PQM_Admin::enqueue_assets).
 */
function pqm_load_textdomain(): void
{
    load_plugin_textdomain('post-quality-manager', false, dirname(plugin_basename(PQM_PLUGIN_FILE)) . '/languages');
}

/* ─── Helper: Settings defaults (single source of truth) ─────────────── */
function pqm_default_settings(): array
{
    $defaults = [
        // Unused since story 44 (the Freemius SDK holds the license); kept so
        // the stored settings shape stays unchanged.
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
        ],
    ];
    // The paid agents (ADR 0014: absent from the Free build).
    if ( pqm_fs()->is__premium_only() ) {
        $defaults['agent_prompts'] += [
            'sources' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium', 'pattern' => 0],
            'seo' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium'],
            'audit' => ['instructions' => '', 'temperature' => 0.0, 'model' => '', 'effort' => 'medium'],
            'rewrite' => ['instructions' => '', 'temperature' => 0.3, 'model' => '', 'effort' => 'high'],
        ];
    }

    return $defaults;
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
    $tokens = [
        'review'    => 2500,
        'takeaways' => 1000,
    ];
    if ( pqm_fs()->is__premium_only() ) {
        $tokens += [
            'sources'   => 2000,
            'seo'       => 1000,
            'audit'     => 5000,
            'rewrite'   => 8000,
        ];
    }

    return $tokens;
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
 * Editorial Standards presets, shared by the settings UI (labels + muted
 * preview) and pqm_resolved_voice() (the text the agents receive).
 * 'custom' is a UI state, not a preset, so it is not listed here.
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

/**
 * Runs $fn in the site language (get_locale(), the site setting — not
 * determine_locale(), the viewer's), so a preset resolves to the same text
 * whoever triggers the command, cron included.
 */
function pqm_in_site_locale(callable $fn)
{
    $switched = switch_to_locale(get_locale());
    try {
        return $fn();
    } finally {
        if ($switched) {
            restore_previous_locale();
        }
    }
}

/**
 * The Editorial Standards text the agents receive (ADR 0011, story 26): the
 * radio is the setting. A selected preset sends its instruction from code,
 * in the site language; Custom (or an unknown key) sends the stored text.
 */
function pqm_resolved_voice(array $settings): string
{
    return pqm_in_site_locale(
        static fn() => pqm_voice_presets()[$settings['brand_voice_preset']]['instruction'] ?? $settings['brand_voice']
    );
}

/**
 * The Website Context the agents receive: a selected Blog Profile sends its
 * text from code, in the site language; Custom (or an unknown key) sends the
 * stored text.
 */
function pqm_resolved_context(array $settings): string
{
    return pqm_in_site_locale(static function () use ($settings) {
        $key = $settings['blog_profile'];
        $profiles = PQM_Admin::blog_profiles();
        return $key !== 'custom' && isset($profiles[$key])
            ? $profiles[$key]['website_context']
            : $settings['website_context'];
    });
}

/* ─── Helper: n8n workflow JSON with site values injected ────────────── */
/**
 * This site's host as the template's __PQM_SITE_HOST__ placeholder gets it —
 * also the suffix of two credential names the admin copy tells users to use.
 */
function pqm_site_host(): string
{
    return wp_parse_url(home_url(), PHP_URL_HOST) ?: 'my-site';
}

/**
 * The Webhook Trigger's path for this site (__PQM_WEBHOOK_PATH__), so two
 * sites on one n8n instance never collide: `pqm-execute-example-com`. The one
 * derivation of it; plain PHP, no filterable WordPress function, so the
 * download, the import URL and provisioning all yield the same path. The
 * `pqm-execute-` prefix marks a plugin path. Known ceiling: two installs on
 * one host (another port or a sub-directory) share the path, as they already
 * share the credential names; add the port and path to the slug when an
 * owner needs it.
 */
function pqm_webhook_path(): string
{
    return 'pqm-execute-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(pqm_site_host())), '-');
}

/**
 * Bundled workflow template with this site's values injected. Shared by the
 * n8n Workflow page download and the REST import-from-URL endpoint.
 * Returns null when the template file is missing.
 */
function pqm_workflow_json(): ?string
{
    // ADR 0014: the Free build serves the template without the paid branches.
    $template_path = PQM_PLUGIN_DIR . 'templates/n8n-workflow-free.json';
    if ( pqm_fs()->is__premium_only() ) {
        $template_path = PQM_PLUGIN_DIR . 'templates/n8n-workflow.json';
    }
    if (!file_exists($template_path)) {
        return null;
    }

    return str_replace(
        ['__PQM_SITE_HOST__', '__PQM_WEBHOOK_PATH__'],
        [pqm_site_host(), pqm_webhook_path()],
        file_get_contents($template_path)
    );
}

/* ─── Helper: the workflow's version report ──────────────────────────── */
/**
 * What the n8n workflow last reported about itself:
 * ['version' => int, 'via' => 'test'|'command', 'at' => timestamp], plus
 * 'variant' => 'free' when the Free template answered (story 54: a report
 * without it is the Pro template, as every template before the Free one).
 * Version 0 = an older workflow that does not report its number (a v1–v4
 * trigger answering a test ping). Null when nothing was reported since the
 * template was last served. A pre-v5 plugin stored a bare int from a
 * command's callback: read as that, with no time.
 */
function pqm_workflow_report(): ?array
{
    $report = get_option('pqm_workflow_version_seen', null);
    if (is_numeric($report) && (int) $report > 0) {
        return ['version' => (int) $report, 'via' => 'command', 'at' => 0];
    }
    if (!is_array($report)) {
        return null;
    }

    return [
        'version' => absint($report['version'] ?? 0),
        'via'     => ($report['via'] ?? '') === 'test' ? 'test' : 'command',
        'at'      => absint($report['at'] ?? 0),
    ] + (($report['variant'] ?? '') === 'free' ? ['variant' => 'free'] : []);
}

/**
 * Store the version the workflow just reported, how ('test' = a test ping,
 * 'command' = a command's result callback), when, and its variant when it
 * is the Free template ('pro' is stored as no variant).
 */
function pqm_record_workflow_version(int $version, string $via, string $variant): void
{
    $report = ['version' => $version, 'via' => $via, 'at' => time()];
    if ($variant === 'free') {
        $report['variant'] = 'free';
    }
    update_option('pqm_workflow_version_seen', $report, false);
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

    // The post's own REST address (any REST-enabled post type; rest_url()
    // is right for any scheme, port, subdirectory and permalink setting).
    // The rewrite PATCHes it as-is; '' when the post type is not in REST.
    // The PATCH still needs HTTPS (or a 'local' environment): it signs in
    // with an Application Password, which core offers only there.
    $rest_route = rest_get_route_for_post($post);

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
        'post_rest_url'     => $rest_route !== '' ? rest_url($rest_route) : '',
        'excerpt'           => $excerpt,
        'content'           => mb_substr($content_plain, 0, 15000),
        'content_html'      => mb_substr($content_html, 0, 20000),
        'seo_score'         => $yoast_active ? (int) get_post_meta($post_id, '_yoast_wpseo_linkdex', true) : null,
        'readability_score' => $yoast_active ? (int) get_post_meta($post_id, '_yoast_wpseo_content_score', true) : null,
        'focus_keyphrase'   => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true) : '',
        'meta_description'  => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true) : '',
        'seo_title'         => $yoast_active ? (string) get_post_meta($post_id, '_yoast_wpseo_title', true) : '',
        'brand_voice'       => pqm_resolved_voice($settings),
        'website_context'   => pqm_resolved_context($settings),
        'agent_prompts'     => $settings['agent_prompts'],
        'llm'               => pqm_llm_map($settings),
    ];
}

/* ─── Helper: n8n request headers (single source of truth) ───────────── */
/**
 * Headers for every call from the plugin to n8n (Run, cron, test ping).
 * The webhook token travels as X-PQM-Token, the same header n8n sends back
 * on save-review: the template's Webhook Trigger checks it with n8n Header
 * Auth, so knowing the URL alone starts nothing. Older imports ignore it.
 */
function pqm_n8n_headers(array $settings): array
{
    return [
        'Content-Type' => 'application/json',
        'X-PQM-Token'  => (string) ($settings['webhook_token'] ?? ''),
    ];
}

/* ─── Helper: n8n test-URL warning ───────────────────────────────────── */
/**
 * Warning copy for an n8n test webhook URL, '' for any other URL. A
 * /webhook-test/ URL only listens while the workflow is open in the n8n
 * editor, so it would pass a ping and then miss every real run.
 */
function pqm_webhook_url_warning(string $url): string
{
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if (strpos($path, '/webhook-test/') === false) {
        return '';
    }

    return __('This is an n8n test URL: it only listens while the workflow is open in the n8n editor. Use the production URL (with /webhook/ instead of /webhook-test/) and activate the workflow.', 'post-quality-manager');
}

/* ─── Helper: setup guide progress (story 36) ────────────────────────── */
/**
 * Option holding the setup dialog's progress, once per site (setup is a
 * site task one administrator may finish for another):
 * `['steps' => [step => bool], 'complete' => bool]`, autoload off, deleted
 * on uninstall (PQM_Activator::uninstall() names it literally: the harnesses
 * load that class without this file).
 */
define('PQM_SETUP_OPTION', 'pqm_setup_progress');

/**
 * Whether the site is set up: the dialog's first review marked it, or —
 * for sites connected before the dialog existed — n8n is connected and a
 * real result is stored (PQM_DB::has_result(): failed runs do not count,
 * and imports older than the version handshake do).
 */
function pqm_setup_complete(): bool
{
    $progress = get_option(PQM_SETUP_OPTION, []);
    if (!empty($progress['complete'])) {
        return true;
    }

    return !empty(pqm_get_settings()['n8n_webhook_url']) && PQM_DB::has_result();
}
