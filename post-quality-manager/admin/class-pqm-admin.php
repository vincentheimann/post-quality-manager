<?php
/**
 * Admin pages for the Post Quality Manager plugin.
 *
 * Registers four sub-pages under a "Content Quality" top-level menu:
 * 1. Dashboard — React app: Fix-next queue, stat tiles, score sparkline, nudges
 * 2. Post Reviews — React app: post table with scores, filters, per-post actions
 * 3. n8n Workflow — setup dialog (React island), download/import, request preview, ping
 * 4. Settings — connection, AI model, prompts & context, cron
 *
 * The two React pages (src/, ADR 0005) get only a PHP shell from here; the
 * Settings and n8n Workflow pages render in PHP with pqm-admin.css/.js. The
 * n8n Workflow page also mounts the setup dialog from the same bundle
 * (story 36) — the only React on it.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQM_Admin
{

    /**
     * Commands Automated Reviews can schedule (ticket 3). Never rewrite (it
     * changes the post) nor the insert-oriented takeaways/sources.
     */
    private const CRON_COMMANDS = ['review', 'seo', 'audit'];

    /**
     * Hook suffix of the Post Reviews submenu page, captured at registration
     * so the enqueue gate never depends on the translated menu title
     * (e.g. 'content-quality_page_pqm-posts' on an English site).
     */
    private static ?string $posts_hook = null;

    /** Hook suffix of the Dashboard page (story 5.6), captured the same way. */
    private static ?string $dashboard_hook = null;

    /** Hook suffix of the n8n Workflow page (story 36: hosts the setup dialog island). */
    private static ?string $workflow_hook = null;

    /** Query arg that opens the setup dialog on load (the welcome notice's link). */
    public const SETUP_QUERY_ARG = 'pqm_setup';

    /* ─── Menu Registration ──────────────────────────────────────────── */

    public static function register_pages(): void
    {
        // Story 5.6: the React shell renders the Dashboard.
        self::$dashboard_hook = add_menu_page(
            __('Content Quality', 'post-quality-manager'),
            __('Content Quality', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard_app'],
            'dashicons-chart-line',
            30
        ) ?: null;

        add_submenu_page(
            'pqm-dashboard',
            __('Dashboard', 'post-quality-manager'),
            __('Dashboard', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard_app']
        );

        // Story 5.1: the React shell renders this page.
        self::$posts_hook = add_submenu_page(
            'pqm-dashboard',
            __('Post Reviews', 'post-quality-manager'),
            __('Post Reviews', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-posts',
        [__CLASS__, 'render_posts_app']
        ) ?: null;

        self::$workflow_hook = add_submenu_page(
            'pqm-dashboard',
            __('n8n Workflow', 'post-quality-manager'),
            __('n8n Workflow', 'post-quality-manager'),
            'pqm_manage_settings',
            'pqm-workflow',
        [__CLASS__, 'render_workflow']
        ) ?: null;

        // Editors (pqm_manage_agents) reach the page for the overrides and
        // Automated Reviews sections; the global sections inside stay
        // pqm_manage_settings-only (CAP-3 access matrix). Either capability
        // opens the page, so a role editor can grant access via one or the
        // other with no code change.
        add_submenu_page(
            'pqm-dashboard',
            __('Settings', 'post-quality-manager'),
            __('Settings', 'post-quality-manager'),
            self::settings_page_capability(),
            'pqm-settings',
        [__CLASS__, 'render_settings']
        );
    }

    public static function admin_notices(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'pqm-') === false) {
            return;
        }

        // The n8n Workflow page hosts the setup button — no extra banner there.
        if (strpos($screen->id, 'pqm-workflow') !== false) {
            return;
        }

        // The banner routes to the Admin-only Workflow page — only users who
        // can act on it (pqm_manage_settings) should see it.
        if (!current_user_can('pqm_manage_settings')) {
            return;
        }

        // Shown until the setup is complete (story 36); a site that was
        // already connected and receiving results counts as set up.
        if (!pqm_setup_complete()) {
            $setup_url = admin_url('admin.php?page=pqm-workflow&' . self::SETUP_QUERY_ARG . '=1');
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Welcome to Post Quality Manager! To start reviewing posts, set up the n8n workflow first — a guide walks you through it.', 'post-quality-manager'),
                esc_url($setup_url),
                esc_html__('Set up the workflow', 'post-quality-manager')
            );
        }
    }

    /* ─── Assets ─────────────────────────────────────────────────────── */

    public static function enqueue_assets(string $hook): void
    {
        // Only load on our pages.
        if (strpos($hook, 'pqm-') === false) {
            return;
        }

        // Post Reviews (story 5.1) and the Dashboard (story 5.6) run the
        // React app: built bundle with core-served dependencies, styled
        // only by the kit CSS (story 5.7). pqm-admin.css/.js serve the
        // Settings and n8n Workflow pages alone.
        if ($hook === self::$posts_hook || $hook === self::$dashboard_hook) {
            self::enqueue_app();
            return;
        }

        // Story 36: the n8n Workflow page also mounts the setup dialog from
        // the bundle; pqm-admin.js below keeps the page's own controls
        // (ping, import-URL copy) — the two touch different DOM.
        if ($hook === self::$workflow_hook) {
            self::enqueue_app();
        }

        wp_enqueue_style(
            'pqm-admin',
            PQM_PLUGIN_URL . 'admin/css/pqm-admin.css',
        [],
            filemtime(PQM_PLUGIN_DIR . 'admin/css/pqm-admin.css')
        );

        wp_enqueue_script(
            'pqm-admin',
            PQM_PLUGIN_URL . 'admin/js/pqm-admin.js',
        [],
            filemtime(PQM_PLUGIN_DIR . 'admin/js/pqm-admin.js'),
            true
        );

        $settings = pqm_get_settings();

        wp_localize_script('pqm-admin', 'pqmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pageNonce' => wp_create_nonce('pqm_admin_action'),
            // Effective global default model (Custom resolved like
            // pqm_effective_model()) — the model-caps JS falls back to it when
            // the AI Model section is absent (Editor view of the Settings page).
            'defaultModel' => $settings['default_model'] === 'custom'
                ? ($settings['custom_model'] !== '' ? $settings['custom_model'] : 'claude-sonnet-4-6')
                : $settings['default_model'],
            'blogProfiles' => array_map(
                static fn($p) => ['website_context' => $p['website_context'], 'brand_voice_preset' => $p['brand_voice_preset']],
                self::blog_profiles()
            ),
            'agentDefaults' => pqm_default_settings()['agent_prompts'],
            'voicePresets' => array_map(static fn($p) => $p['instruction'], pqm_voice_presets()),
            'modelRegistry' => array_map(
                static fn($m) => ['temperature' => $m['supports_temperature'], 'effortLevels' => $m['effort_levels']],
                pqm_model_registry()
            ),
            'i18n' => [
                'enterWebhookUrl' => __('Enter your n8n webhook URL first.', 'post-quality-manager'),
                'testing' => __('Sending test ping…', 'post-quality-manager'),
                'networkError' => __('Network error. Please try again.', 'post-quality-manager'),
                'copied' => __('Copied! Paste it in n8n via "Import from URL…".', 'post-quality-manager'),
                'copyFailed' => __('Could not copy automatically. The URL is selected — press Ctrl+C.', 'post-quality-manager'),
            ],
        ]);
    }

    /**
     * Enqueue the built admin app (one bundle; src/index.js mounts whichever
     * page root is present). Dependencies and version come from the
     * dependency-extraction manifest (build/index.asset.php): React,
     * @wordpress/components, api-fetch and i18n all load from WordPress core
     * — the bundle ships no runtime. Missing build output is handled by
     * render_app_root(), which shows the build instructions instead.
     */
    private static function enqueue_app(): void
    {
        $asset_path = PQM_PLUGIN_DIR . 'admin/build/index.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        $version = $asset['version'] ?? PQM_VERSION;

        wp_enqueue_script(
            'pqm-app',
            PQM_PLUGIN_URL . 'admin/build/index.js',
            $asset['dependencies'] ?? [],
            $version,
            true
        );
        wp_set_script_translations('pqm-app', 'post-quality-manager', PQM_PLUGIN_DIR . 'languages');

        // Command labels come from the PHP registry, the one label source;
        // src/kit/copy.js commandLabel() reads them.
        wp_add_inline_script(
            'pqm-app',
            'window.pqmCommandLabels = ' . wp_json_encode(array_map(
                static fn(array $entry): string => $entry['label'],
                PQM_Features::registry()
            )) . ';',
            'before'
        );

        // @wordpress/components styling is served by core, not the bundle.
        wp_enqueue_style('wp-components');

        // Kit styles (story 5.2): wp-scripts extracts src/kit/style.css
        // into the build. Versioned from the asset manifest, no filemtime.
        if (file_exists(PQM_PLUGIN_DIR . 'admin/build/style-index.css')) {
            wp_enqueue_style(
                'pqm-app',
                PQM_PLUGIN_URL . 'admin/build/style-index.css',
                ['wp-components'],
                $version
            );
            // wp-scripts also emits style-index-rtl.css; WP swaps it in on RTL locales.
            wp_style_add_data('pqm-app', 'rtl', 'replace');
        }
    }

    /* ─── Blog Profiles (single source of truth) ─────────────────────── */

    /**
     * Niche presets that pre-fill the editable context fields below.
     * Values are suggestions only — the user can edit everything after applying.
     */
    public static function blog_profiles(): array
    {
        return [
            'marketing' => [
                'label' => __('Marketing Blog', 'post-quality-manager'),
                'website_context' => __('A marketing blog for business professionals. Readers want proven tactics, data-backed claims, and clear next steps they can apply to their own campaigns. Case studies and concrete numbers beat theory.', 'post-quality-manager'),
                'brand_voice_preset' => 'direct',
            ],
            'technical' => [
                'label' => __('Technical / IT Blog', 'post-quality-manager'),
                'website_context' => __('A technical blog for software developers and IT professionals. Readers expect accurate, in-depth content: working code examples, references to official documentation, and honest trade-off discussions. Domain jargon is fine; marketing fluff is not.', 'post-quality-manager'),
                'brand_voice_preset' => 'professional',
            ],
            'lifestyle' => [
                'label' => __('Lifestyle Blog', 'post-quality-manager'),
                'website_context' => __('A lifestyle blog for a general audience. Readers look for relatable stories, practical tips they can apply right away, and an authentic personal perspective.', 'post-quality-manager'),
                'brand_voice_preset' => 'casual',
            ],
            'food' => [
                'label' => __('Food & Recipes Blog', 'post-quality-manager'),
                'website_context' => __('A food blog for home cooks. Readers want reliable recipes with clear steps and exact quantities, practical substitutions, and honest tips from real kitchen experience.', 'post-quality-manager'),
                'brand_voice_preset' => 'casual',
            ],
            'travel' => [
                'label' => __('Travel Blog', 'post-quality-manager'),
                'website_context' => __('A travel blog for independent travelers. Readers are planning real trips: they want practical itineraries, budgets and logistics, first-hand experiences, and honest recommendations.', 'post-quality-manager'),
                'brand_voice_preset' => 'casual',
            ],
            'health' => [
                'label' => __('Health & Wellness Blog', 'post-quality-manager'),
                'website_context' => __('A health and wellness blog for a general audience. Readers expect trustworthy, evidence-based guidance with cited sources, realistic advice, and a supportive, non-alarmist tone.', 'post-quality-manager'),
                'brand_voice_preset' => 'professional',
            ],
            'custom' => [
                'label' => __('Custom', 'post-quality-manager'),
                'website_context' => '',
                'brand_voice_preset' => 'custom',
            ],
        ];
    }

    /* ─── Settings Registration (Settings API) ───────────────────────── */

    public static function register_settings(): void
    {
        register_setting('pqm_settings_group', 'pqm_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);

        // options.php guards the save with manage_options by default; align it
        // with the Settings menu so a remapped role can both open and save.
        // Per-section protection lives in sanitize_settings(): keys of sections
        // the saver could not see carry their stored values.
        add_filter('option_page_capability_pqm_settings_group', [__CLASS__, 'settings_page_capability']);
    }

    /**
     * Capability that opens (and saves) the Settings page for the current
     * user: either pqm_manage_settings or pqm_manage_agents grants access —
     * the sections inside follow their own gates.
     */
    public static function settings_page_capability(): string
    {
        return current_user_can('pqm_manage_settings') ? 'pqm_manage_settings' : 'pqm_manage_agents';
    }

    public static function sanitize_settings($input): array
    {
        // options.php passes null when a save posts no pqm_settings fields at
        // all (e.g. the free-site Editor view, where every section is hidden
        // or locked) — treat it as an empty POST, not a fatal.
        $input = (array) $input;

        $defaults = pqm_default_settings();
        // Sections the saving request could not have posted — hidden by role or
        // locked by plan (disabled fieldset) — must carry their *stored* values,
        // never the defaults: the disabled-controls pitfall, applied per section.
        $existing = pqm_get_settings();
        $registry_keys = array_keys(pqm_model_registry());
        $clean = [];

        if (current_user_can('pqm_manage_settings')) {
            // Admin-only sections: License, n8n Connection, AI Model,
            // AI Prompts & Context, Monthly Quota.
            // Whitespace-only keys trim to empty — the site stays free.
            $clean['license_key'] = trim(sanitize_text_field($input['license_key'] ?? ''));
            $clean['n8n_webhook_url'] = esc_url_raw($input['n8n_webhook_url'] ?? '');
            // A test URL still saves (Save never loses input), with a notice.
            $url_warning = pqm_webhook_url_warning($clean['n8n_webhook_url']);
            if ($url_warning !== '') {
                add_settings_error('pqm_settings', 'pqm_webhook_test_url', $url_warning, 'warning');
            }
            $clean['webhook_token'] = sanitize_text_field($input['webhook_token'] ?? '');
            $clean['notification_url'] = esc_url_raw($input['notification_url'] ?? '');
            $clean['blog_profile'] = array_key_exists($input['blog_profile'] ?? '', self::blog_profiles()) ? $input['blog_profile'] : $defaults['blog_profile'];
            $voice_keys = array_merge(array_keys(pqm_voice_presets()), ['custom']);
            $clean['brand_voice_preset'] = in_array($input['brand_voice_preset'] ?? '', $voice_keys, true) ? $input['brand_voice_preset'] : $defaults['brand_voice_preset'];
            $clean['brand_voice'] = sanitize_textarea_field($input['brand_voice'] ?? '');
            $clean['website_context'] = sanitize_textarea_field($input['website_context'] ?? '');
            // 0 = unlimited; absint also maps negative input to a sane value.
            $clean['author_quota'] = absint($input['author_quota'] ?? $defaults['author_quota']);
            $clean['default_model'] = in_array($input['default_model'] ?? '', array_merge($registry_keys, ['custom']), true)
                ? $input['default_model'] : $defaults['default_model'];
            // Model IDs are plain slugs (letters, digits, dot, dash, underscore, colon).
            $clean['custom_model'] = (string) preg_replace('/[^A-Za-z0-9._:-]/', '', $input['custom_model'] ?? '');
        }
        else {
            // The saver never saw these sections — keep what is stored.
            $admin_only_keys = [
                'license_key', 'n8n_webhook_url', 'webhook_token', 'notification_url',
                'blog_profile', 'brand_voice_preset', 'brand_voice',
                'website_context', 'author_quota', 'default_model', 'custom_model',
            ];
            foreach ($admin_only_keys as $key) {
                $clean[$key] = $existing[$key];
            }
        }

        // Agent sections: Automated Reviews + Agent-Specific Overrides. They
        // come from the request only when the saver has pqm_manage_agents, the
        // site is pro and the request posted the section; otherwise they carry
        // their stored values wholesale (never per field: an absent
        // cron_enabled must not read as "unchecked") — hidden for a role
        // without pqm_manage_agents, locked on a free site (disabled fieldset),
        // or missing on a pro site from a form rendered while the site was
        // free, or a direct POST. "Posted" means the knobs the pro form always
        // posts — not cron_enabled (the free off-switch posts it too) nor
        // cron_command (ticket 3: one of review/seo/audit, else review).
        $can_edit_agents = current_user_can('pqm_manage_agents') && PQM_Features::is_pro();
        $cron_keys = ['cron_enabled', 'cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days', 'cron_command'];
        $cron_knobs = ['cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days'];
        if ($can_edit_agents && array_intersect_key($input, array_flip($cron_knobs))) {
            $clean['cron_enabled'] = !empty($input['cron_enabled']);
            $clean['cron_frequency'] = in_array($input['cron_frequency'] ?? '', ['daily', 'weekly'], true)
                ? $input['cron_frequency'] : $defaults['cron_frequency'];
            $clean['cron_batch_size'] = absint($input['cron_batch_size'] ?? $defaults['cron_batch_size']);
            $clean['cron_min_score'] = absint($input['cron_min_score'] ?? $defaults['cron_min_score']);
            $clean['cron_max_age_days'] = absint($input['cron_max_age_days'] ?? $defaults['cron_max_age_days']);
            $clean['cron_command'] = in_array($input['cron_command'] ?? '', self::CRON_COMMANDS, true)
                ? $input['cron_command'] : $defaults['cron_command'];
        }
        else {
            foreach ($cron_keys as $key) {
                $clean[$key] = $existing[$key];
            }
            // The one exception (ticket 13): on a free site the Enable toggle
            // stays live as an off-switch while stored cron is on, so leftover
            // scheduled reviews can be stopped. It can only switch off — a
            // carried section never sets cron_enabled true, and nothing else is
            // read. Plan-independent, so an off-switch form rendered while free
            // and saved after the site turned pro still switches off.
            if (current_user_can('pqm_manage_agents')
                && !empty($existing['cron_enabled'])
                && isset($input['cron_enabled']) && empty($input['cron_enabled'])) {
                $clean['cron_enabled'] = false;
            }
        }

        if ($can_edit_agents) {
            $effort_levels = ['low', 'medium', 'high', 'xhigh', 'max'];
            // Locked knobs (model doesn't support them) render disabled and are absent
            // from the POST — fall back to the stored value, not the default.
            $clean['agent_prompts'] = [];
            foreach (array_keys($defaults['agent_prompts']) as $agent) {
                if (!isset($input['agent_prompts'][$agent])) {
                    $clean['agent_prompts'][$agent] = $existing['agent_prompts'][$agent] ?? $defaults['agent_prompts'][$agent];
                }
                else {
                    $agent_input = $input['agent_prompts'][$agent];
                    $stored      = $existing['agent_prompts'][$agent] ?? $defaults['agent_prompts'][$agent];
                    $clean['agent_prompts'][$agent] = [
                        'instructions' => sanitize_textarea_field($agent_input['instructions'] ?? ''),
                        'temperature'  => (float) ($agent_input['temperature'] ?? $stored['temperature']),
                        'model'        => in_array($agent_input['model'] ?? '', $registry_keys, true) ? $agent_input['model'] : '',
                        'effort'       => in_array($agent_input['effort'] ?? '', $effort_levels, true)
                            ? $agent_input['effort'] : $stored['effort'],
                    ];
                    // Insert into Post pattern (Takeaways, Sources only): one of
                    // the listed user patterns, anything else is 0 (plain list).
                    if (isset($defaults['agent_prompts'][$agent]['pattern'])) {
                        $patterns ??= PQM_Blocks::patterns();
                        $pattern = absint($agent_input['pattern'] ?? 0);
                        $clean['agent_prompts'][$agent]['pattern'] = isset($patterns[$pattern]) ? $pattern : 0;
                    }
                }
            }
        }
        else {
            $clean['agent_prompts'] = $existing['agent_prompts'];
        }

        return $clean;
    }

    /**
     * Reschedule cron after settings are saved (hooked via updated_option).
     */
    public static function on_settings_updated(string $option): void
    {
        if ($option === 'pqm_settings') {
            PQM_Cron::schedule();
        }
    }

    /* ─── React app pages (stories 5.1, 5.6) ─────────────────────────── */

    /**
     * Dashboard shell (story 5.6). The scheduled-reviews nudge's Dismiss
     * link (URL from GET /dashboard) lands here: the dismissal is persisted
     * before the app fetches, so the nudge is gone on that same load.
     */
    public static function render_dashboard_app(): void
    {
        if (isset($_GET['pqm_dismiss_cron_nudge'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'pqm_dismiss_cron_nudge')
            && current_user_can('pqm_manage_agents')
        ) {
            update_option('pqm_cron_nudge_dismissed', 1, false);
        }

        self::render_app_shell(__('Content Quality Dashboard', 'post-quality-manager'), 'pqm-dashboard-root');
    }

    public static function render_posts_app(): void
    {
        self::render_app_shell(__('Post Reviews', 'post-quality-manager'), 'pqm-post-reviews-root');
    }

    /**
     * PHP shell shared by the React pages: heading + root div only — every
     * row, badge, tile and panel renders client-side from REST data
     * (ADR 0005, one render source). On a fresh clone without built assets,
     * the page explains the next step instead of staying blank.
     */
    private static function render_app_shell(string $title, string $root_id): void
    {
        ?>
        <div class="wrap pqm-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <?php self::render_app_root($root_id); ?>
        </div>
        <?php
    }

    /**
     * The root div the bundle mounts on (src/index.js) — or, on a fresh
     * clone without built assets, the next step instead of a blank spot.
     * Shared by the React pages and the setup island (story 36).
     */
    private static function render_app_root(string $root_id): void
    {
        if (file_exists(PQM_PLUGIN_DIR . 'admin/build/index.asset.php')) {
            printf('<div id="%s"></div>', esc_attr($root_id));
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p><?php
            echo wp_kses(
                sprintf(
                    /* translators: 1: npm install command, 2: npm run build command */
                    __('This page has not been built yet. Run %1$s and then %2$s in the repository root, then reload this page.', 'post-quality-manager'),
                    '<code>npm install</code>',
                    '<code>npm run build</code>'
                ),
                ['code' => []]
            );
            ?></p>
        </div>
        <?php
    }

    /* ─── Page: Settings ─────────────────────────────────────────────── */

    public static function render_settings(): void
    {
        $settings = pqm_get_settings();
        $workflow_page_url = admin_url('admin.php?page=pqm-workflow');
        $registry = pqm_model_registry();
        // Section gates (CAP-3 access matrix): the global sections are hidden
        // without pqm_manage_settings; the agent sections (Agent-Specific
        // Overrides, Automated Reviews) are hidden without pqm_manage_agents
        // and render visible but locked on a free site. Role-hidden beats
        // plan-locked, mirroring PQM_Features::why_not().
        $can_manage_settings = current_user_can('pqm_manage_settings');
        $can_manage_agents = current_user_can('pqm_manage_agents');
        $is_pro = PQM_Features::is_pro();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Settings', 'post-quality-manager'); ?></h1>

            <?php if (!empty($_GET['settings-updated'])): ?>
            <!-- Custom save notice: settings ride the webhook payload, so no n8n re-import is ever needed. -->
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved — they apply on the next command. No n8n re-import needed.', 'post-quality-manager'); ?></p>
            </div>
            <?php
            // Notices the sanitizer raised (e.g. an n8n test URL). Core shows
            // these only under Settings → *, so this page prints its own.
            settings_errors('pqm_settings');
            ?>
            <?php endif; ?>

            <?php if ($can_manage_settings && empty($settings['n8n_webhook_url'])): ?>
            <div class="notice notice-info inline pqm-inline-notice">
                <p>
                    <?php esc_html_e('New here? Generate and import the review workflow first, then come back to paste its webhook URL.', 'post-quality-manager'); ?>
                    <a href="<?php echo esc_url($workflow_page_url); ?>"><?php esc_html_e('Open the n8n Workflow page', 'post-quality-manager'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('pqm_settings_group'); ?>

                <?php if ($can_manage_settings): ?>
                <!-- License (CAP-2: the key is the only plan switch — same zip) -->
                <h2><?php esc_html_e('License', 'post-quality-manager'); ?></h2>
                <p class="description"><?php
                    echo esc_html($is_pro
                        ? __('Current plan: Pro — all agents, per-agent overrides, and scheduled reviews are unlocked.', 'post-quality-manager')
                        : __('Current plan: Free — Review and Key Takeaways are included. Enter a license key to unlock the rest.', 'post-quality-manager'));
                ?></p>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_license_key"><?php esc_html_e('License Key', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="text" id="pqm_license_key" name="pqm_settings[license_key]"
                                   value="<?php echo esc_attr($settings['license_key']); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Your Post Quality Manager license key. It applies when you save — clear it to switch the site back to Free.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- n8n Connection -->
                <h2 class="pqm-section-gap"><?php esc_html_e('n8n Connection', 'post-quality-manager'); ?></h2>
                <p class="description"><?php
                    printf(
                        /* translators: %s: link to the n8n Workflow page */
                        esc_html__('The workflow itself — download, import URL, and what each agent sends to Claude — lives on the %s page.', 'post-quality-manager'),
                        '<a href="' . esc_url($workflow_page_url) . '">' . esc_html__('n8n Workflow', 'post-quality-manager') . '</a>'
                    );
                ?></p>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_n8n_webhook_url"><?php esc_html_e('n8n Webhook URL', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="url" id="pqm_n8n_webhook_url" name="pqm_settings[n8n_webhook_url]"
                                   value="<?php echo esc_attr($settings['n8n_webhook_url']); ?>"
                                   class="regular-text" placeholder="https://your-n8n.example.com/webhook/pqm-execute" />
                            <button type="button" class="button pqm-test-connection">
                                <?php esc_html_e('Send test ping', 'post-quality-manager'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('The webhook URL of your n8n workflow.', 'post-quality-manager'); ?></p>
                            <p class="pqm-test-result" aria-live="polite"></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_webhook_token"><?php esc_html_e('Webhook Token', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="text" id="pqm_webhook_token" name="pqm_settings[webhook_token]"
                                   value="<?php echo esc_attr($settings['webhook_token']); ?>"
                                   class="regular-text" readonly />
                            <p class="description"><?php esc_html_e('Shared secret for both directions, always in the X-PQM-Token header: WordPress sends it to start the workflow (your n8n Header Auth credential holds the same value), and n8n sends it back when posting results.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_notification_url"><?php esc_html_e('Notification URL', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="url" id="pqm_notification_url" name="pqm_settings[notification_url]"
                                   value="<?php echo esc_attr($settings['notification_url']); ?>"
                                   class="regular-text" placeholder="https://your-chat.example.com/hooks/..." />
                            <p class="description"><?php esc_html_e('Optional. kChat/Slack/Teams webhook for formatted reports.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- AI Model -->
                <h2 class="pqm-section-gap"><?php esc_html_e('AI Model', 'post-quality-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Which Claude model runs your commands. Model changes apply on the next command — no n8n re-import needed.', 'post-quality-manager'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_default_model"><?php esc_html_e('Default Model', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_default_model" name="pqm_settings[default_model]">
                                <?php foreach ($registry as $model_id => $model): ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($settings['default_model'], $model_id); ?>>
                                        <?php
                                        echo esc_html($model['label']);
                                        if ($model['pricing']) {
                                            /* translators: %s: price per million tokens, input / output */
                                            echo esc_html(sprintf(__(' (%s per MTok)', 'post-quality-manager'), $model['pricing']));
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?php selected($settings['default_model'], 'custom'); ?>><?php esc_html_e('Custom model ID…', 'post-quality-manager'); ?></option>
                            </select>
                            <input type="text" id="pqm_custom_model" name="pqm_settings[custom_model]"
                                   value="<?php echo esc_attr($settings['custom_model']); ?>"
                                   class="regular-text code<?php echo $settings['default_model'] === 'custom' ? '' : ' pqm-hidden'; ?>"
                                   placeholder="claude-…"
                                   aria-label="<?php esc_attr_e('Custom Claude model ID', 'post-quality-manager'); ?>" />
                            <p class="description"><?php esc_html_e('The Claude model every agent uses unless it sets its own below. Prices are per million tokens (input / output). For a custom model ID, temperature and effort are not sent — the model runs with its own defaults.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- AI Prompts & Context -->
                <h2 class="pqm-section-gap"><?php esc_html_e('AI Prompts & Context', 'post-quality-manager'); ?></h2>
                <p class="pqm-context-intro"><?php esc_html_e('Brand Voice and Website Context are sent with every AI command, so reviews and rewrites match your site. The Blog Profile is an optional shortcut that pre-fills these two fields — you always keep the final say.', 'post-quality-manager'); ?></p>
                <div class="notice notice-info inline pqm-inline-notice">
                    <p><strong><?php esc_html_e('Agnostic Scoring Framework', 'post-quality-manager'); ?></strong></p>
                    <p><?php esc_html_e('All posts are evaluated on 4 universal pillars: 1. Structure (flow & hierarchy), 2. Readability (accessibility), 3. Engagement (hook & actionable value), 4. Credibility (E-E-A-T and factual support).', 'post-quality-manager'); ?></p>
                </div>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Blog Profile', 'post-quality-manager'); ?></th>
                        <td>
                            <?php
                            $profiles = self::blog_profiles();
                            $current_profile = $settings['blog_profile'];
                            ?>
                            <fieldset class="pqm-profile-fieldset">
                                <?php foreach ($profiles as $val => $profile): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[blog_profile]" value="<?php echo esc_attr($val); ?>" <?php checked($current_profile, $val); ?> class="pqm-blog-profile" />
                                        <?php echo esc_html($profile['label']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Pre-fills the two fields below with a starting point for your niche — everything stays editable. Choose Custom to write your own; editing a field switches back to Custom automatically.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_brand_voice"><?php esc_html_e('Brand Voice & Tone', 'post-quality-manager'); ?></label></th>
                        <td>
                            <?php
                            $voice_presets  = pqm_voice_presets();
                            $current_preset = $settings['brand_voice_preset'];
                            $preset_labels  = array_map(static fn($p) => $p['label'], $voice_presets);
                            $preset_labels['custom'] = __('Custom', 'post-quality-manager');
                            // WYSIWYG: the textarea always shows exactly what the AI will receive.
                            $display_voice = isset($voice_presets[$current_preset])
                                ? $voice_presets[$current_preset]['instruction']
                                : $settings['brand_voice'];
                            ?>
                            <fieldset class="pqm-voice-fieldset">
                                <?php foreach ($preset_labels as $val => $lbl): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[brand_voice_preset]" value="<?php echo esc_attr($val); ?>" <?php checked($current_preset, $val); ?> class="pqm-voice-preset" />
                                        <?php echo esc_html($lbl); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <textarea id="pqm_brand_voice" name="pqm_settings[brand_voice]" rows="4" class="large-text"
                                      placeholder="<?php esc_attr_e('e.g. Professional yet accessible: active voice, short paragraphs, no buzzwords.', 'post-quality-manager'); ?>"><?php echo esc_textarea($display_voice); ?></textarea>
                            <p class="description"><?php esc_html_e('How the AI should sound — this exact text is sent with every command. Pick a preset to load its instruction and adjust it, or write your own.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_website_context"><?php esc_html_e('Website Context', 'post-quality-manager'); ?></label></th>
                        <td>
                            <textarea id="pqm_website_context" name="pqm_settings[website_context]" rows="4" class="large-text"
                                      placeholder="<?php esc_attr_e('e.g. A blog about home automation for tech-savvy homeowners. Readers want hands-on tutorials, honest product experiences, and privacy-friendly setups.', 'post-quality-manager'); ?>"><?php echo esc_textarea($settings['website_context']); ?></textarea>
                            <p class="description"><?php esc_html_e('What the site is about and who reads it — the AI uses this to judge relevance, depth, and tone fit. Two or three sentences are plenty.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php endif; // pqm_manage_settings ?>

                <?php if ($can_manage_agents): ?>
                <!-- Agent-Specific Overrides (paid: locked, not hidden, on a free site) -->
                <h2 class="pqm-section-gap"><?php
                    esc_html_e('Agent-Specific Overrides', 'post-quality-manager');
                    if (!$is_pro): ?> <span class="dashicons dashicons-lock pqm-section-lock" aria-hidden="true"></span><?php endif;
                ?></h2>
                <?php if (!$is_pro): ?>
                <p class="description pqm-locked-note"><?php esc_html_e('Available with Pro. Upgrade to unlock per-agent overrides — Review and Takeaways stay free.', 'post-quality-manager'); ?></p>
                <?php /* One disabled ancestor fieldset keeps every inner control
                       inert and out of the POST, whatever the model-caps JS
                       toggles on the controls themselves. */ ?>
                <fieldset disabled class="pqm-locked-section">
                <?php endif; ?>
                <p class="description pqm-agents-intro"><?php esc_html_e('Optionally pick a different model, adjust how it works, and add instructions per agent. Only the controls the selected model supports are shown. Changes apply on the next command — no n8n re-import needed.', 'post-quality-manager'); ?></p>

                <?php
                $effort_labels = [
                    'low'    => __('Low — fastest', 'post-quality-manager'),
                    'medium' => __('Medium — balanced', 'post-quality-manager'),
                    'high'   => __('High — thorough', 'post-quality-manager'),
                    'xhigh'  => __('Extra high — most thorough', 'post-quality-manager'),
                    'max'    => __('Max — no limits', 'post-quality-manager'),
                ];
                foreach (self::agent_labels() as $key => $label):
                    $agent_data = $settings['agent_prompts'][$key];
                    $caps       = pqm_model_caps(pqm_effective_model($key, $settings));
                    $has_effort = !empty($caps['effort_levels']);
                ?>
                    <details class="pqm-agent-box" data-agent="<?php echo esc_attr($key); ?>">
                        <summary>
                            <span class="dashicons dashicons-admin-generic pqm-agent-icon"></span>
                            <?php echo esc_html($label); ?>
                        </summary>
                        <table class="form-table">
                            <tr>
                                <th><label for="pqm_agent_model_<?php echo esc_attr($key); ?>"><?php esc_html_e('Model', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <select id="pqm_agent_model_<?php echo esc_attr($key); ?>"
                                            name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][model]"
                                            class="pqm-agent-model">
                                        <option value=""><?php
                                        // The "above" wording only makes sense when the AI Model section is on the page.
                                        echo esc_html($can_manage_settings
                                            ? __('Default (use the AI Model above)', 'post-quality-manager')
                                            : __('Default (site default model)', 'post-quality-manager'));
                                        ?></option>
                                        <?php foreach ($registry as $model_id => $model): ?>
                                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($agent_data['model'], $model_id); ?>>
                                                <?php echo esc_html($model['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pqm_agent_effort_<?php echo esc_attr($key); ?>"><?php esc_html_e('Reasoning Effort', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <select id="pqm_agent_effort_<?php echo esc_attr($key); ?>"
                                            name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][effort]"
                                            class="pqm-agent-effort"<?php disabled(!$has_effort); ?>>
                                        <?php foreach ($effort_labels as $level => $level_label): ?>
                                            <option value="<?php echo esc_attr($level); ?>"
                                                    <?php selected($agent_data['effort'], $level); ?>
                                                    <?php disabled(!in_array($level, $caps['effort_levels'], true)); ?>>
                                                <?php echo esc_html($level_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description pqm-effort-desc<?php echo $has_effort ? '' : ' pqm-hidden'; ?>"><?php esc_html_e('How much the model reasons before answering — higher means smarter but slower and more tokens.', 'post-quality-manager'); ?></p>
                                    <p class="description pqm-effort-hint<?php echo $has_effort ? ' pqm-hidden' : ''; ?>"><?php esc_html_e('Not available for the selected model — use Temperature instead.', 'post-quality-manager'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pqm_agent_temp_<?php echo esc_attr($key); ?>"><?php esc_html_e('Temperature', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <div class="pqm-temp-row<?php echo $caps['supports_temperature'] ? '' : ' pqm-locked'; ?>">
                                        <input type="range" step="0.1" min="0" max="1"
                                               id="pqm_agent_temp_<?php echo esc_attr($key); ?>"
                                               name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][temperature]"
                                               value="<?php echo esc_attr(number_format((float)$agent_data['temperature'], 1)); ?>"
                                               class="pqm-temp-range"<?php disabled(!$caps['supports_temperature']); ?> />
                                        <span class="pqm-temp-value"><?php echo esc_html(number_format((float)$agent_data['temperature'], 1)); ?></span>
                                    </div>
                                    <p class="description pqm-temp-desc<?php echo $caps['supports_temperature'] ? '' : ' pqm-hidden'; ?>"><?php esc_html_e('0.0 (Strict/Analytical) to 1.0 (Creative & Loose).', 'post-quality-manager'); ?></p>
                                    <p class="description pqm-temp-hint<?php echo $caps['supports_temperature'] ? ' pqm-hidden' : ''; ?>"><?php esc_html_e('Not supported by the selected model — use Reasoning Effort instead.', 'post-quality-manager'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pqm_agent_inst_<?php echo esc_attr($key); ?>"><?php esc_html_e('Additional Instructions', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <textarea id="pqm_agent_inst_<?php echo esc_attr($key); ?>"
                                              name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][instructions]"
                                              rows="4" class="large-text"><?php echo esc_textarea($agent_data['instructions']); ?></textarea>
                                    <p class="description"><?php esc_html_e('Added to the agent\'s core JSON instructions.', 'post-quality-manager'); ?></p>
                                </td>
                            </tr>
                            <?php if (isset($agent_data['pattern'])): $patterns ??= PQM_Blocks::patterns();
                                // A stored pattern that no longer qualifies is the plain list here, as in insert().
                                $pattern_id = isset($patterns[(int) $agent_data['pattern']]) ? (int) $agent_data['pattern'] : 0; ?>
                            <tr>
                                <th><label for="pqm_agent_pattern_<?php echo esc_attr($key); ?>"><?php esc_html_e('Pattern to use', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <div class="pqm-pattern-row">
                                        <select id="pqm_agent_pattern_<?php echo esc_attr($key); ?>"
                                                name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][pattern]"
                                                class="pqm-pattern-select">
                                            <option value="0"><?php esc_html_e('Plain list', 'post-quality-manager'); ?></option>
                                            <?php if ($patterns): ?>
                                            <optgroup label="<?php esc_attr_e('Your patterns', 'post-quality-manager'); ?>">
                                                <?php foreach ($patterns as $id => $title): ?>
                                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($pattern_id, $id); ?>>
                                                        <?php echo esc_html($title); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                        </select>
                                        <?php // Free sites get no links: insert() ignores the pattern unless Pro, and links escape the disabled fieldset. ?>
                                        <?php if ($is_pro && $patterns): ?>
                                        <?php // Core's edit link (post.php?post=ID&action=edit); the JS swaps the ID on selection change and hides it at 0. ?>
                                        <a class="pqm-pattern-edit<?php echo $pattern_id ? '' : ' pqm-hidden'; ?>"
                                           href="<?php echo esc_url(admin_url('post.php?post=' . $pattern_id . '&action=edit')); ?>"><?php esc_html_e('Edit pattern', 'post-quality-manager'); ?></a>
                                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=wp_block')); ?>"><?php esc_html_e('Manage patterns', 'post-quality-manager'); ?></a>
                                        <?php elseif ($is_pro): ?>
                                        <a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=wp_block')); ?>"><?php esc_html_e('Create a pattern', 'post-quality-manager'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php
                                        echo esc_html($key === 'sources'
                                            ? __("Goes at the end of the post. The items fill the pattern's first List block, so only your published patterns that contain a List block appear here.", 'post-quality-manager')
                                            : __("Goes at the top of the post. The items fill the pattern's first List block, so only your published patterns that contain a List block appear here.", 'post-quality-manager'));
                                    ?></p>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th></th>
                                <td>
                                    <button type="button" class="button pqm-reset-agent" data-agent="<?php echo esc_attr($key); ?>">
                                        <?php esc_html_e('Reset to Defaults', 'post-quality-manager'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </details>
                <?php endforeach; ?>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>

                <!-- Automated Reviews (paid: locked, not hidden, on a free site) -->
                <?php
                // Ticket 13: while stored cron is on, a free site keeps the
                // Enable toggle live, outside the lock, as an off-switch. The
                // hidden 0 makes the unchecked state post; the sanitizer only
                // ever reads it to switch off.
                // Only while the stored command still runs on free (ticket 3):
                // a paid seo/audit schedule is skipped by PQM_Cron, so nothing
                // "keeps running" and the section stays fully locked.
                $cron_off_switch = !$is_pro && !empty($settings['cron_enabled'])
                    && PQM_Features::plan_allows((string) $settings['cron_command']);
                ?>
                <h2 id="pqm-cron-settings" class="pqm-section-gap"><?php
                    esc_html_e('Automated Reviews (Cron)', 'post-quality-manager');
                    if (!$is_pro): ?> <span class="dashicons dashicons-lock pqm-section-lock" aria-hidden="true"></span><?php endif;
                ?></h2>
                <?php if (!$is_pro): ?>
                <p class="description pqm-locked-note"><?php echo esc_html($cron_off_switch
                    ? __('Available with Pro. Your scheduled reviews keep running; upgrade to change their settings.', 'post-quality-manager')
                    : __('Available with Pro. Upgrade to let reviews run on a schedule.', 'post-quality-manager')); ?></p>
                <?php if ($cron_off_switch): ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Enable', 'post-quality-manager'); ?></th>
                        <td>
                            <input type="hidden" name="pqm_settings[cron_enabled]" value="0" />
                            <label>
                                <input type="checkbox" name="pqm_settings[cron_enabled]" value="1" checked />
                                <?php esc_html_e('Automatically run an agent on a schedule', 'post-quality-manager'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('On the free plan, scheduled reviews can be switched off but not back on.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>
                <fieldset disabled class="pqm-locked-section">
                <?php endif; ?>
                <table class="form-table">
                    <?php if (!$cron_off_switch): ?>
                    <tr>
                        <th><?php esc_html_e('Enable', 'post-quality-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pqm_settings[cron_enabled]" value="1"
                                       <?php checked($settings['cron_enabled']); ?> />
                                <?php esc_html_e('Automatically run an agent on a schedule', 'post-quality-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="pqm_cron_frequency"><?php esc_html_e('Frequency', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_cron_frequency" name="pqm_settings[cron_frequency]">
                                <option value="daily" <?php selected($settings['cron_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'post-quality-manager'); ?></option>
                                <option value="weekly" <?php selected($settings['cron_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'post-quality-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_batch_size"><?php esc_html_e('Batch Size', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_batch_size" name="pqm_settings[cron_batch_size]"
                                   value="<?php echo esc_attr($settings['cron_batch_size']); ?>"
                                   min="1" max="20" class="small-text" />
                            <p class="description"><?php esc_html_e('Number of posts per scheduled run.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_command"><?php esc_html_e('Command', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_cron_command" name="pqm_settings[cron_command]">
                                <?php foreach (self::CRON_COMMANDS as $cron_cmd): ?>
                                <option value="<?php echo esc_attr($cron_cmd); ?>" <?php selected($settings['cron_command'], $cron_cmd); ?>><?php echo esc_html(PQM_Features::label($cron_cmd)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('The agent that runs on each schedule. Posts are picked by their latest result from that agent.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_min_score"><?php esc_html_e('Min Score Threshold', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_min_score" name="pqm_settings[cron_min_score]"
                                   value="<?php echo esc_attr($settings['cron_min_score']); ?>"
                                   min="0" max="100" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-run posts whose result from this agent scores below this value.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_max_age_days"><?php esc_html_e('Max Result Age (days)', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_max_age_days" name="pqm_settings[cron_max_age_days]"
                                   value="<?php echo esc_attr($settings['cron_max_age_days']); ?>"
                                   min="1" max="365" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-run posts whose latest result from this agent is older than this.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>
                <?php endif; // pqm_manage_agents ?>

                <?php if ($can_manage_settings): ?>
                <!-- Author Quota -->
                <h2 id="pqm-quota-settings" class="pqm-section-gap"><?php esc_html_e('Monthly Quota', 'post-quality-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_author_quota"><?php esc_html_e('AI Actions per User', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_author_quota" name="pqm_settings[author_quota]"
                                   value="<?php echo esc_attr($settings['author_quota']); ?>"
                                   min="0" step="1" class="small-text" />
                            <p class="description"><?php esc_html_e('Monthly limit of manual AI actions for users without the paid-agents capability. Set 0 for unlimited. Scheduled reviews are not counted.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php endif; // pqm_manage_settings ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ─── Page: n8n Workflow ─────────────────────────────────────────── */

    /**
     * Agent keys => human labels, shared by the Settings page and the
     * n8n Workflow page's configuration preview.
     */
    private static function agent_labels(): array
    {
        return [
            'review'    => __('Review Agent', 'post-quality-manager'),
            'takeaways' => __('Key Takeaways Agent', 'post-quality-manager'),
            'sources'   => __('Sources Agent', 'post-quality-manager'),
            'seo'       => __('SEO Agent', 'post-quality-manager'),
            'audit'     => __('Audit Agent', 'post-quality-manager'),
            'rewrite'   => __('Rewrite Agent', 'post-quality-manager'),
        ];
    }

    /**
     * The n8n "Import from URL" link: the workflow route with the webhook
     * token. Shared by this page and the setup dialog (GET /setup).
     */
    public static function workflow_import_url(): string
    {
        return add_query_arg('token', rawurlencode(pqm_get_settings()['webhook_token']), rest_url(PQM_REST_NAMESPACE . '/workflow'));
    }

    /**
     * Everything about generating and connecting the n8n workflow: the
     * setup dialog's mount point (story 36), download / import URL,
     * per-agent request preview, template version status, and the
     * connection test.
     */
    public static function render_workflow(): void
    {
        $settings     = pqm_get_settings();
        $download_url = wp_nonce_url(admin_url('admin-post.php?action=pqm_download_workflow'), 'pqm_download_workflow');
        $import_url   = self::workflow_import_url();
        $settings_url = admin_url('admin.php?page=pqm-settings');
        $seen_version = (int) get_option('pqm_workflow_version_seen', 0);
        $registry     = pqm_model_registry();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('n8n Workflow', 'post-quality-manager'); ?></h1>

            <?php
            // Story 36: the setup dialog (src/setup/) mounts here — its
            // button, and the Modal it opens. The sections below always show.
            self::render_app_root('pqm-setup-root');
            ?>

            <?php if ($seen_version > 0 && $seen_version < PQM_WORKFLOW_VERSION): ?>
            <div class="notice notice-warning inline pqm-inline-notice">
                <p><strong><?php esc_html_e('Workflow update available.', 'post-quality-manager'); ?></strong>
                <?php esc_html_e('Your n8n instance runs an older version of the workflow. Download or re-import it below to get the latest pipeline — your plugin settings stay as they are.', 'post-quality-manager'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Workflow file -->
            <h2><?php esc_html_e('Workflow File', 'post-quality-manager'); ?></h2>
            <p class="description"><?php esc_html_e('The review workflow tailored to this site, ready to import into n8n. After importing, connect these three credentials:', 'post-quality-manager'); ?></p>
            <?php self::render_credentials_list($settings['webhook_token']); ?>
            <p>
                <a href="<?php echo esc_url($download_url); ?>" class="button button-primary">
                    <?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?>
                </a>
            </p>
            <?php self::render_import_url($import_url); ?>
            <?php self::render_credentials_hint(); ?>
            <p class="description">
                <?php
                printf(
                    /* translators: 1: bundled template version, 2: version last reported by n8n or a dash */
                    esc_html__('Template version: %1$s · Your n8n instance last reported: %2$s', 'post-quality-manager'),
                    esc_html((string) PQM_WORKFLOW_VERSION),
                    $seen_version > 0 ? esc_html((string) $seen_version) : esc_html__('— (reports after the first command)', 'post-quality-manager')
                );
                ?>
            </p>

            <!-- Per-agent request preview -->
            <h2 class="pqm-section-gap"><?php esc_html_e('What Each Agent Sends to Claude', 'post-quality-manager'); ?></h2>
            <p class="description"><?php
                printf(
                    /* translators: %s: link to the Settings page */
                    esc_html__('These values travel with every command, so model changes in %s apply instantly — no re-import needed. Only plugin updates that change the workflow itself require a re-import.', 'post-quality-manager'),
                    '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'post-quality-manager') . '</a>'
                );
            ?></p>
            <table class="widefat striped pqm-llm-preview">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Agent', 'post-quality-manager'); ?></th>
                        <th><?php esc_html_e('Model', 'post-quality-manager'); ?></th>
                        <th><?php esc_html_e('Reasoning Effort', 'post-quality-manager'); ?></th>
                        <th><?php esc_html_e('Temperature', 'post-quality-manager'); ?></th>
                        <th><?php esc_html_e('Max Output Tokens', 'post-quality-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (self::agent_labels() as $key => $label):
                        $params = pqm_llm_params($key, $settings);
                        $model_label = isset($registry[$params['model']]) ? $registry[$params['model']]['label'] : $params['model'];
                    ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><?php echo esc_html($model_label); ?> <code><?php echo esc_html($params['model']); ?></code></td>
                        <td><?php echo esc_html($params['output_config']['effort'] ?? '—'); ?></td>
                        <td><?php echo isset($params['temperature']) ? esc_html(number_format($params['temperature'], 1)) : '—'; ?></td>
                        <td><?php echo esc_html((string) $params['max_tokens']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Connection test -->
            <h2 class="pqm-section-gap"><?php esc_html_e('Connection Test', 'post-quality-manager'); ?></h2>
            <p class="description"><?php esc_html_e('Sends a lightweight ping to the saved n8n webhook URL. This verifies WordPress → n8n only; the return path is confirmed by your first successful review.', 'post-quality-manager'); ?></p>
            <p>
                <button type="button" class="button pqm-test-connection">
                    <?php esc_html_e('Send test ping', 'post-quality-manager'); ?>
                </button>
                <span class="pqm-test-result" aria-live="polite"></span>
            </p>
        </div>
<?php
    }

    /* ─── Workflow Generator (admin-post download) ───────────────────── */

    /**
     * "Or import from URL" block shown under the workflow download button.
     * Classes only, no IDs (pqm-admin.js binds the Copy button by class).
     */
    private static function render_import_url(string $import_url): void
    {
?>
        <div class="pqm-import-url">
            <p class="pqm-import-url-intro"><?php esc_html_e('Or import from URL — no file needed:', 'post-quality-manager'); ?></p>
            <div class="pqm-import-url-row">
                <input type="text" class="regular-text code pqm-import-url-field"
                       value="<?php echo esc_attr($import_url); ?>" readonly
                       aria-label="<?php esc_attr_e('Workflow import URL', 'post-quality-manager'); ?>" />
                <button type="button" class="button pqm-copy-import-url"><?php esc_html_e('Copy', 'post-quality-manager'); ?></button>
            </div>
            <p class="pqm-copy-feedback" aria-live="polite"></p>
            <p class="description"><?php esc_html_e('In n8n, open the workflow menu, choose "Import from URL…" and paste this link. It includes your webhook token, which also opens your n8n workflow\'s trigger — keep it private.', 'post-quality-manager'); ?></p>
        </div>
<?php
    }

    /**
     * The three credentials the imported workflow needs, the webhook token
     * shown inline for the Header Auth one, each under the name the
     * template expects (so a renamed credential matches). Shown in the Workflow File
     * section — a v3 site re-imports from there. The setup dialog carries
     * its own, step-by-step copy of these (src/setup/steps.js).
     */
    private static function render_credentials_list(string $token): void
    {
?>
        <?php $host = pqm_site_host(); // the template's credential names (__PQM_SITE_HOST__) ?>
        <ul class="pqm-credentials-list">
            <li><?php
                printf(
                    /* translators: 1: credential name, e.g. "Post Quality webhook - example.com", 2: the header name X-PQM-Token, 3: this site's webhook token */
                    esc_html__('%1$s — a Header Auth credential: set its Name field to %2$s and its Value to %3$s. Pick it in the Webhook Trigger only, so only this site can start the workflow.', 'post-quality-manager'),
                    '<strong>' . esc_html('Post Quality webhook - ' . $host) . '</strong>',
                    '<code>X-PQM-Token</code>',
                    '<code>' . esc_html($token) . '</code>'
                );
            ?></li>
            <li><?php
                printf(
                    /* translators: %s: credential name, e.g. "WordPress - example.com" */
                    esc_html__('%s — a Basic Auth credential with an Application Password for a user who can edit posts. Pick it in Update WP Post (Rewrite).', 'post-quality-manager'),
                    '<strong>' . esc_html('WordPress - ' . $host) . '</strong>'
                );
            ?></li>
            <li><?php
                printf(
                    /* translators: 1: credential name "Anthropic - Claude", 2: the header name x-api-key */
                    esc_html__('%1$s — a second Header Auth credential: Name %2$s, Value your Anthropic API key. Pick it in the six Claude nodes.', 'post-quality-manager'),
                    '<strong>Anthropic - Claude</strong>',
                    '<code>x-api-key</code>'
                );
            ?></li>
        </ul>
<?php
    }

    /**
     * After an import, n8n flags every credentialed node as "Credentials …
     * are not set": it links credentials by an instance-specific ID the
     * template cannot carry, and only fills a node in once it is opened.
     * Shown in the Workflow File section.
     */
    private static function render_credentials_hint(): void
    {
?>
        <div class="notice notice-info inline pqm-inline-notice">
            <p><strong><?php esc_html_e('n8n says "Credentials … are not set" after the import?', 'post-quality-manager'); ?></strong>
            <?php esc_html_e('n8n does not link saved credentials to an imported workflow by itself. Open each flagged node once and close it again — the Webhook Trigger, the six Claude nodes and Update WP Post (Rewrite) — and n8n picks your saved credential. You have two Header Auth credentials, so choose in each node: Post Quality webhook (X-PQM-Token) in the Webhook Trigger, Anthropic - Claude in the six Claude nodes. If you have none yet, create it from the credential field in the node. Then save and activate the workflow.', 'post-quality-manager'); ?></p>
        </div>
<?php
    }

    /**
     * Serve the bundled n8n workflow template with this site's values injected.
     */
    public static function download_workflow(): void
    {
        if (!current_user_can('pqm_manage_settings')) {
            wp_die(esc_html__('Insufficient permissions.', 'post-quality-manager'));
        }
        check_admin_referer('pqm_download_workflow');

        $json = pqm_workflow_json();
        if ($json === null) {
            wp_die(esc_html__('Workflow template not found. Please reinstall the plugin.', 'post-quality-manager'));
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'my-site';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="post-quality-workflow-' . sanitize_file_name($host) . '.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download, not HTML.
        exit;
    }

    /* ─── Save webhook URL + ping: one home (POST /setup/connect, AJAX ping) ── */

    /**
     * Merge-save only the webhook URL. Reads the stored option and updates
     * a single key — the Settings form's full-array sanitize contract and
     * the form-free Workflow page both stay intact. Used by the setup
     * dialog's connect route (story 36).
     *
     * @return true|WP_Error True once stored; the refusal otherwise.
     */
    public static function save_webhook_url(string $url): bool|WP_Error
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return new WP_Error('pqm_webhook_url_empty', __('Enter your n8n webhook URL first.', 'post-quality-manager'), ['status' => 400]);
        }

        // A stored test URL would finish the setup on a URL that misses
        // every real run — refuse it before saving.
        $warning = pqm_webhook_url_warning($url);
        if ($warning !== '') {
            return new WP_Error('pqm_webhook_test_url', $warning, ['status' => 400]);
        }

        $settings = get_option('pqm_settings', []);
        $settings['n8n_webhook_url'] = $url;
        update_option('pqm_settings', $settings); // updated_option hook reschedules cron.

        return true;
    }

    /**
     * Send a lightweight "ping" command to the n8n webhook. With a v4
     * import a 2xx proves two things: this site reaches n8n, and the
     * Webhook Trigger accepted the X-PQM-Token header (story 35). Shared by
     * the AJAX handler and the setup dialog's connect route (story 36).
     *
     * @return array{ok: bool, message: string}
     */
    public static function ping_webhook(string $url): array
    {
        // A test URL would answer while the n8n editor listens and then miss
        // every real run — refuse before sending.
        $warning = pqm_webhook_url_warning($url);
        if ($warning !== '') {
            return ['ok' => false, 'message' => $warning];
        }

        $response = wp_remote_post($url, [
            'headers' => pqm_n8n_headers(pqm_get_settings()),
            'body'    => wp_json_encode(['command' => 'ping', 'source' => 'pqm-test-connection']),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => sprintf(
                /* translators: %s: connection error message */
                __('Could not connect to n8n: %s', 'post-quality-manager'),
                $response->get_error_message()
            )];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            // An import older than v4 (seen 1–3) ignores the header, so its
            // 2xx proves only the reachability; 0 = no report yet (fresh setup).
            $seen = (int) get_option('pqm_workflow_version_seen', 0);
            if ($seen > 0 && $seen < PQM_WORKFLOW_VERSION) {
                return ['ok' => true, 'message' => __('Ping OK — this site reaches n8n. Your n8n workflow is an older version that does not check the webhook credential: download or re-import it on the n8n Workflow page.', 'post-quality-manager')];
            }
            return ['ok' => true, 'message' => __('Ping OK — this site reaches n8n and the webhook credential is right. Your first review confirms the return path.', 'post-quality-manager')];
        }

        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => sprintf(
                /* translators: %d: HTTP status code (401 or 403) */
                __('n8n refused the webhook credential (HTTP %d). In n8n, open the Webhook Trigger\'s Header Auth credential: its Name must be X-PQM-Token and its Value this site\'s webhook token. Then try again.', 'post-quality-manager'),
                $code
            )];
        }

        if ($code === 404) {
            return ['ok' => false, 'message' => __('n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'post-quality-manager')];
        }

        return ['ok' => false, 'message' => sprintf(
            /* translators: %d: HTTP status code */
            __('n8n responded with HTTP %d. Check your workflow configuration.', 'post-quality-manager'),
            $code
        )];
    }

    /* ─── AJAX: Test Connection ──────────────────────────────────────── */

    /**
     * Ping the n8n webhook and report the outcome. Tests the URL from the
     * Settings field so it works before saving; the n8n Workflow page sends
     * none, so the saved URL is pinged.
     */
    public static function ajax_test_connection(): void
    {
        check_ajax_referer('pqm_admin_action');

        if (!current_user_can('pqm_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        $url = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        if (empty($url)) {
            $url = pqm_get_settings()['n8n_webhook_url'];
        }
        if (empty($url)) {
            wp_send_json_error(__('Enter your n8n webhook URL first.', 'post-quality-manager'));
        }

        $ping = self::ping_webhook($url);
        if ($ping['ok']) {
            wp_send_json_success($ping['message']);
        }
        wp_send_json_error($ping['message']);
    }
}
