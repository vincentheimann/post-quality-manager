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
        // Story 26: preview texts in the site locale, like the payload sends them.
        [$profile_texts, $voice_texts] = pqm_in_site_locale(static fn() => [
            array_map(static fn($p) => $p['website_context'], self::blog_profiles()),
            array_map(static fn($p) => $p['instruction'], pqm_voice_presets()),
        ]);

        wp_localize_script('pqm-admin', 'pqmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pageNonce' => wp_create_nonce('pqm_admin_action'),
            // Effective global default model (Custom resolved like
            // pqm_effective_model()) — the model-caps JS falls back to it when
            // the AI Model section is absent (Editor view of the Settings page).
            'defaultModel' => $settings['default_model'] === 'custom'
                ? ($settings['custom_model'] !== '' ? $settings['custom_model'] : 'claude-sonnet-4-6')
                : $settings['default_model'],
            'blogProfiles' => $profile_texts,
            'agentDefaults' => pqm_default_settings()['agent_prompts'],
            'voicePresets' => $voice_texts,
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
     * Niche presets for Website Context (story 26): a selected profile is
     * the setting, resolved at send time by pqm_resolved_context(); 'custom'
     * sends the stored website_context.
     */
    public static function blog_profiles(): array
    {
        return [
            'marketing' => [
                'label' => __('Marketing Blog', 'post-quality-manager'),
                'website_context' => __('A marketing blog for business professionals. Readers want proven tactics, data-backed claims, and clear next steps they can apply to their own campaigns. Case studies and concrete numbers beat theory.', 'post-quality-manager'),
            ],
            'technical' => [
                'label' => __('Technical / IT Blog', 'post-quality-manager'),
                'website_context' => __('A technical blog for software developers and IT professionals. Readers expect accurate, in-depth content: working code examples, references to official documentation, and honest trade-off discussions. Domain jargon is fine; marketing fluff is not.', 'post-quality-manager'),
            ],
            'lifestyle' => [
                'label' => __('Lifestyle Blog', 'post-quality-manager'),
                'website_context' => __('A lifestyle blog for a general audience. Readers look for relatable stories, practical tips they can apply right away, and an authentic personal perspective.', 'post-quality-manager'),
            ],
            'food' => [
                'label' => __('Food & Recipes Blog', 'post-quality-manager'),
                'website_context' => __('A food blog for home cooks. Readers want reliable recipes with clear steps and exact quantities, practical substitutions, and honest tips from real kitchen experience.', 'post-quality-manager'),
            ],
            'travel' => [
                'label' => __('Travel Blog', 'post-quality-manager'),
                'website_context' => __('A travel blog for independent travelers. Readers are planning real trips: they want practical itineraries, budgets and logistics, first-hand experiences, and honest recommendations.', 'post-quality-manager'),
            ],
            'health' => [
                'label' => __('Health & Wellness Blog', 'post-quality-manager'),
                'website_context' => __('A health and wellness blog for a general audience. Readers expect trustworthy, evidence-based guidance with cited sources, realistic advice, and a supportive, non-alarmist tone.', 'post-quality-manager'),
            ],
            'custom' => [
                'label' => __('Custom', 'post-quality-manager'),
                'website_context' => '',
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
     * user: pqm_manage_settings if held, else pqm_manage_agents if held, else
     * core manage_options — so a site administrator whose role lost both
     * plugin caps still reaches the Roles tab (story 42: never behind a
     * plugin capability). The sections inside follow their own gates.
     */
    public static function settings_page_capability(): string
    {
        if (current_user_can('pqm_manage_settings')) {
            return 'pqm_manage_settings';
        }
        return current_user_can('pqm_manage_agents') ? 'pqm_manage_agents' : 'manage_options';
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

        // Roles tab (story 42): written to the site's roles, never stored in
        // the option (roles_posted, roles_shown and roles never reach $clean).
        // Only for a manage_options saver on a pro site whose request posted
        // the tab — the marker is absent for anyone without the tab, inside
        // the locked (free) fieldset and from programmatic update_option()
        // calls, so none of those touches a role.
        if (current_user_can('manage_options') && PQM_Features::is_pro() && !empty($input['roles_posted'])) {
            self::save_roles((array) ($input['roles'] ?? []), (array) ($input['roles_shown'] ?? []));
        }

        return $clean;
    }

    /**
     * Plugin capability => its Roles-tab column label, in capabilities()
     * order. Plain actions only: a pqm_ slug never reaches the screen.
     */
    private static function role_cap_labels(): array
    {
        return [
            'pqm_view_reviews'    => __('View reviews', 'post-quality-manager'),
            'pqm_run_free_agents' => __('Run Review and Key Takeaways', 'post-quality-manager'),
            'pqm_run_paid_agents' => __('Run paid agents', 'post-quality-manager'),
            'pqm_manage_agents'   => __('Manage agents', 'post-quality-manager'),
            'pqm_manage_settings' => __('Manage settings', 'post-quality-manager'),
        ];
    }

    /**
     * Apply the cells the Administrator changed on the Roles tab. The panel
     * is in the one Settings form, so every pro Administrator save posts it,
     * from any tab: writing the posted state would undo a role change made
     * elsewhere after the page loaded. So a cell is written only where the
     * posted box differs from what that row rendered (roles_shown), and a
     * role with no roles_shown entry (created after the page loaded, or a
     * forged row) is never touched. A role holding manage_options holds all
     * five whatever was posted. Only the five pqm_ caps on roles are touched
     * (never core caps, never per-user grants), and only where the role's
     * state changes — idempotent, so options.php running the sanitizer twice
     * on a first save is harmless.
     *
     * @param array $posted role slug => list of checked capabilities.
     * @param array $shown  role slug => comma-separated capabilities the row rendered checked.
     */
    private static function save_roles(array $posted, array $shown): void
    {
        $caps = array_keys(PQM_Features::capabilities());
        foreach (wp_roles()->role_objects as $role_slug => $role) {
            // Pinned by capability, never by role name.
            if ($role->has_cap('manage_options')) {
                foreach ($caps as $cap) {
                    if (!$role->has_cap($cap)) {
                        $role->add_cap($cap);
                    }
                }
                continue;
            }
            if (!isset($shown[$role_slug]) || !is_string($shown[$role_slug])) {
                continue;
            }
            $was     = array_map('trim', explode(',', $shown[$role_slug]));
            $checked = (array) ($posted[$role_slug] ?? []);
            foreach ($caps as $cap) {
                $want = in_array($cap, $checked, true);
                if ($want === in_array($cap, $was, true)) {
                    continue; // Cell untouched on the form: leave the role as it is now.
                }
                if ($want !== $role->has_cap($cap)) {
                    $want ? $role->add_cap($cap) : $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * After settings are saved (hooked via updated_option): reschedule cron,
     * and forget the workflow report when the webhook URL changed — it
     * described the workflow at the old URL. Covers the Settings form and
     * the setup dialog (`save_webhook_url()`), whose ping then records anew.
     *
     * @param mixed $old_value
     * @param mixed $value
     */
    public static function on_settings_updated(string $option, $old_value = null, $value = null): void
    {
        if ($option !== 'pqm_settings') {
            return;
        }
        PQM_Cron::schedule();

        if ((is_array($old_value) ? ($old_value['n8n_webhook_url'] ?? '') : '')
            !== (is_array($value) ? ($value['n8n_webhook_url'] ?? '') : '')) {
            delete_option('pqm_workflow_version_seen');
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
        // Story 27: tabs over the one form (pqm-admin.js switches them, so
        // every save still posts every section). A tab exists only when one
        // of its sections renders; the server marks the first one active.
        $tabs = array_filter([
            'connection' => $can_manage_settings ? __('Connection', 'post-quality-manager') : '',
            'ai'         => $can_manage_settings || $can_manage_agents ? __('AI', 'post-quality-manager') : '',
            'automation' => $can_manage_agents ? __('Automation', 'post-quality-manager') : '',
            'usage'      => $can_manage_settings ? __('Usage & Limits', 'post-quality-manager') : '',
            // Story 42: core manage_options, never a pqm_ cap, so the tab can't remove itself.
            'roles'      => current_user_can('manage_options') ? __('Roles', 'post-quality-manager') : '',
        ]);
        $first_tab = array_key_first($tabs);
        $panel_open = static function (string $slug) use ($first_tab): void {
            printf('<div class="pqm-tab-panel%s" id="pqm-panel-%s">', $slug === $first_tab ? ' is-active' : '', esc_attr($slug));
        };
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Settings', 'post-quality-manager'); ?></h1>

            <?php if (!empty($_GET['settings-updated'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's redirect flag after options.php saves; it only shows a notice. ?>
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

            <?php // Hidden without JS (.no-js): every section then shows. ?>
            <nav class="nav-tab-wrapper pqm-tabs" aria-label="<?php esc_attr_e('Settings sections', 'post-quality-manager'); ?>">
                <?php foreach ($tabs as $slug => $tab_label): ?>
                <a href="#tab-<?php echo esc_attr($slug); ?>" id="pqm-tab-<?php echo esc_attr($slug); ?>" class="nav-tab<?php echo $slug === $first_tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html($tab_label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            // The server can't see the hash, so it marks the first tab active.
            // When the hash names another, hide every panel until
            // pqm-admin.js (footer) opens the right one, instead of painting
            // the first panel and then swapping it.
            wp_print_inline_script_tag(sprintf(
                'if (location.hash && location.hash !== %s) document.documentElement.classList.add("pqm-tabs-pending");',
                wp_json_encode('#tab-' . $first_tab)
            ));
            ?>

            <form method="post" action="options.php">
                <?php settings_fields('pqm_settings_group'); ?>

                <?php if ($can_manage_settings): $panel_open('connection'); ?>
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
                </div><!-- #pqm-panel-connection -->
                <?php endif; // pqm_manage_settings ?>

                <?php if (isset($tabs['ai'])): $panel_open('ai'); ?>
                <?php if ($can_manage_settings): ?>
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
                <h2 id="pqm-context-settings" class="pqm-section-gap"><?php esc_html_e('AI Prompts & Context', 'post-quality-manager'); ?></h2>
                <p class="pqm-context-intro"><?php esc_html_e('Your Blog Profile and Editorial Standards are sent with your AI commands, so reviews and rewrites fit your site. A preset sends the text shown under it; choose Custom to write your own.', 'post-quality-manager'); ?></p>
                <div class="notice notice-info inline pqm-inline-notice">
                    <p><strong><?php esc_html_e('Agnostic Scoring Framework', 'post-quality-manager'); ?></strong></p>
                    <p><?php esc_html_e('All posts are evaluated on 4 universal pillars: 1. Structure (flow & hierarchy), 2. Readability (accessibility), 3. Engagement (hook & actionable value), 4. Credibility (E-E-A-T and factual support).', 'post-quality-manager'); ?></p>
                </div>
                <?php
                /* Story 26: the radio is the setting. A preset shows its text
                 * muted (what the AI gets); the textarea shows only under
                 * Custom and always posts, so custom text survives a preset.
                 * Preview texts resolve like the payload (site locale). */
                $profiles        = self::blog_profiles();
                $current_profile = $settings['blog_profile'];
                $current_preset  = $settings['brand_voice_preset'];
                $profile_text    = pqm_resolved_context(['blog_profile' => $current_profile, 'website_context' => '']);
                $preset_text     = pqm_resolved_voice(['brand_voice_preset' => $current_preset, 'brand_voice' => '']);
                $preset_labels   = array_map(static fn($p) => $p['label'], pqm_voice_presets());
                $preset_labels['custom'] = __('Custom', 'post-quality-manager');
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Blog Profile', 'post-quality-manager'); ?></th>
                        <td>
                            <fieldset class="pqm-profile-fieldset">
                                <legend class="screen-reader-text"><?php esc_html_e('Blog Profile', 'post-quality-manager'); ?></legend>
                                <?php foreach ($profiles as $val => $profile): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[blog_profile]" value="<?php echo esc_attr($val); ?>" <?php checked($current_profile, $val); ?> class="pqm-blog-profile" />
                                        <?php echo esc_html($profile['label']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Who you write for — sent as the Website Context with every Review, Rewrite, SEO and Audit. Custom: describe your site and readers in two or three sentences.', 'post-quality-manager'); ?></p>
                            <p class="description pqm-preset-text" id="pqm_profile_text" aria-live="polite"<?php echo $profile_text === '' ? ' hidden' : ''; ?>><?php echo esc_html($profile_text); ?></p>
                            <div class="pqm-custom-field" id="pqm_context_field"<?php echo $profile_text === '' ? '' : ' hidden'; ?>>
                                <textarea id="pqm_website_context" name="pqm_settings[website_context]" rows="4" class="large-text"
                                          aria-label="<?php esc_attr_e('Website Context', 'post-quality-manager'); ?>" aria-describedby="pqm_website_context_empty"
                                          placeholder="<?php esc_attr_e('e.g. A blog about home automation for tech-savvy homeowners. Readers want hands-on tutorials, honest product experiences, and privacy-friendly setups.', 'post-quality-manager'); ?>"><?php echo esc_textarea($settings['website_context']); ?></textarea>
                                <p class="description" id="pqm_website_context_empty"><?php esc_html_e('Empty: the AI assumes "a general audience blog".', 'post-quality-manager'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Editorial Standards', 'post-quality-manager'); ?></th>
                        <td>
                            <fieldset class="pqm-voice-fieldset">
                                <legend class="screen-reader-text"><?php esc_html_e('Editorial Standards', 'post-quality-manager'); ?></legend>
                                <?php foreach ($preset_labels as $val => $lbl): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[brand_voice_preset]" value="<?php echo esc_attr($val); ?>" <?php checked($current_preset, $val); ?> class="pqm-voice-preset" />
                                        <?php echo esc_html($lbl); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('The voice and rules every post follows: your own if you write alone, the shared ones for a team. Sent with every Review and Rewrite.', 'post-quality-manager'); ?></p>
                            <p class="description pqm-preset-text" id="pqm_voice_text" aria-live="polite"<?php echo $preset_text === '' ? ' hidden' : ''; ?>><?php echo esc_html($preset_text); ?></p>
                            <div class="pqm-custom-field" id="pqm_voice_field"<?php echo $preset_text === '' ? '' : ' hidden'; ?>>
                                <textarea id="pqm_brand_voice" name="pqm_settings[brand_voice]" rows="4" class="large-text"
                                          aria-label="<?php esc_attr_e('Editorial Standards', 'post-quality-manager'); ?>" aria-describedby="pqm_brand_voice_empty"
                                          placeholder="<?php esc_attr_e('e.g. Active voice, short paragraphs, no buzzwords. Back every statistic with a source.', 'post-quality-manager'); ?>"><?php echo esc_textarea($settings['brand_voice']); ?></textarea>
                                <p class="description" id="pqm_brand_voice_empty"><?php esc_html_e('Empty: the AI falls back to "professional yet accessible, active voice".', 'post-quality-manager'); ?></p>
                            </div>
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
                <?php endif; // pqm_manage_agents ?>
                </div><!-- #pqm-panel-ai -->
                <?php endif; // AI tab ?>

                <?php if ($can_manage_agents): $panel_open('automation'); ?>
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
                </div><!-- #pqm-panel-automation -->
                <?php endif; // pqm_manage_agents ?>

                <?php if ($can_manage_settings): $panel_open('usage'); ?>
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
                </div><!-- #pqm-panel-usage -->
                <?php endif; // pqm_manage_settings ?>

                <?php if (isset($tabs['roles'])): $panel_open('roles'); ?>
                <!-- Roles (story 42, Pro: locked, not hidden, on a free site). The site's roles are the store: sanitize_settings() writes them. -->
                <h2 class="pqm-section-gap"><?php
                    esc_html_e('What each role can do', 'post-quality-manager');
                    if (!$is_pro): ?> <span class="dashicons dashicons-lock pqm-section-lock" aria-hidden="true"></span><?php endif;
                ?></h2>
                <?php if (!$is_pro): ?>
                <p class="description pqm-locked-note"><?php esc_html_e('Available with Pro. Upgrade to choose what each role can do — until then, every role keeps what is shown here.', 'post-quality-manager'); ?></p>
                <fieldset disabled class="pqm-locked-section">
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Saving applies these choices to every user with that role. Locked rows belong to roles that run the whole site, such as Administrator: you cannot take anything away from them.', 'post-quality-manager'); ?></p>
                <?php /* Tells "tab posted with every box cleared" from "tab not
                       posted"; inert (never posted) inside the locked fieldset. */ ?>
                <input type="hidden" name="pqm_settings[roles_posted]" value="1" />
                <table class="widefat striped pqm-roles-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Role', 'post-quality-manager'); ?></th>
                            <?php foreach (self::role_cap_labels() as $cap_label): ?>
                            <th scope="col"><?php echo esc_html($cap_label); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $wp_roles = wp_roles();
                    foreach ($wp_roles->role_objects as $role_slug => $role):
                        // Pinned by capability, never by role name; sanitize_settings() re-grants all five.
                        // Pro shows a pinned row all-checked; Free shows its current grants, like every row.
                        $pinned = $role->has_cap('manage_options');
                        $role_label = translate_user_role($wp_roles->role_names[$role_slug] ?? $role_slug);
                        $row_checked = array_values(array_filter(
                            array_keys(self::role_cap_labels()),
                            static fn($cap) => ($pinned && $is_pro) || $role->has_cap($cap)
                        ));
                        $hint_id = 'pqm-role-hint-' . $role_slug;
                    ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($role_label);
                                /* What this row rendered checked: Save writes only the cells that differ.
                                   None for a pinned row, so one that loses manage_options before Save is skipped. */
                                if (!$pinned): ?>
                                <input type="hidden" name="pqm_settings[roles_shown][<?php echo esc_attr($role_slug); ?>]" value="<?php echo esc_attr(implode(',', $row_checked)); ?>" /><?php endif; ?></th>
                            <?php foreach (self::role_cap_labels() as $cap => $cap_label): ?>
                            <td><input type="checkbox" name="pqm_settings[roles][<?php echo esc_attr($role_slug); ?>][]" value="<?php echo esc_attr($cap); ?>"
                                       aria-label="<?php echo esc_attr($role_label . ': ' . $cap_label); ?>"<?php
                                if ($cap === 'pqm_view_reviews'): ?> aria-describedby="<?php echo esc_attr($hint_id); ?>"<?php endif;
                                checked(in_array($cap, $row_checked, true)); disabled($pinned); ?> /><?php
                                if ($cap === 'pqm_view_reviews'): ?><span class="pqm-role-hint" id="<?php echo esc_attr($hint_id); ?>"><?php esc_html_e('Without this, the role does not see Dashboard or Post Reviews.', 'post-quality-manager'); ?></span><?php endif; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('A role with Run paid agents is not subject to the monthly quota (Usage & Limits).', 'post-quality-manager'); ?></p>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>
                </div><!-- #pqm-panel-roles -->
                <?php endif; // manage_options ?>

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
        $settings_url = admin_url('admin.php?page=pqm-settings#tab-ai'); // the model changes it names
        $registry     = pqm_model_registry();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('n8n Workflow', 'post-quality-manager'); ?></h1>

            <?php
            // Story 36: the setup dialog (src/setup/) mounts here — its
            // button, and the Modal it opens. The sections below always show.
            self::render_app_root('pqm-setup-root');
            echo self::render_workflow_status(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
            ?>

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
            <p class="description"><?php esc_html_e('Sends a lightweight ping to the saved n8n webhook URL and checks which workflow version answers. This verifies WordPress → n8n only; the return path is confirmed by your first successful review.', 'post-quality-manager'); ?></p>
            <p>
                <button type="button" class="button pqm-test-connection">
                    <?php esc_html_e('Send test ping', 'post-quality-manager'); ?>
                </button>
                <span class="pqm-test-result" aria-live="polite"></span>
            </p>
        </div>
<?php
    }

    /**
     * The workflow's version status: the "update available" notice when the
     * last report is older than the bundled template, and the line saying
     * which version answered, when and how. One renderer for the page load
     * and the ping replies (AJAX ping, POST /setup/connect), whose
     * `status_html` the page swaps in for `.pqm-workflow-status`.
     */
    public static function render_workflow_status(): string
    {
        $report = pqm_workflow_report();

        if ($report === null) {
            $line = sprintf(
                /* translators: %s: bundled template version */
                __('Template version: %s · Your n8n workflow: not checked yet — send a test ping once it is imported and active.', 'post-quality-manager'),
                PQM_WORKFLOW_VERSION
            );
        } else {
            $version = $report['version'] > 0
                /* translators: %d: workflow version number */
                ? sprintf(__('version %d', 'post-quality-manager'), $report['version'])
                : __('an older version that does not report its number', 'post-quality-manager');

            if ($report['at'] === 0) {
                // Stored by a pre-v5 plugin: a command reported it, time unknown.
                $how = __('reported by a command', 'post-quality-manager');
            } else {
                $when = time() - $report['at'] < MINUTE_IN_SECONDS
                    ? __('just now', 'post-quality-manager')
                    /* translators: %s: time span, e.g. "3 hours" */
                    : sprintf(__('%s ago', 'post-quality-manager'), human_time_diff($report['at']));
                $how = $report['via'] === 'test'
                    /* translators: %s: when, e.g. "just now" or "3 hours ago" */
                    ? sprintf(__('checked %s by a test ping', 'post-quality-manager'), $when)
                    /* translators: %s: when, e.g. "just now" or "3 hours ago" */
                    : sprintf(__('checked %s by a command', 'post-quality-manager'), $when);
            }

            $line = sprintf(
                /* translators: 1: bundled template version, 2: "version 4" or "an older version…", 3: e.g. "checked just now by a test ping" */
                __('Template version: %1$s · Your n8n workflow: %2$s, %3$s.', 'post-quality-manager'),
                PQM_WORKFLOW_VERSION,
                $version,
                $how
            );
        }

        ob_start();
?>
        <div class="pqm-workflow-status">
            <?php if ($report !== null && $report['version'] < PQM_WORKFLOW_VERSION): ?>
            <div class="notice notice-warning inline pqm-inline-notice">
                <p><strong><?php esc_html_e('Workflow update available.', 'post-quality-manager'); ?></strong>
                <?php esc_html_e('Your n8n instance runs an older version of the workflow. Download or re-import it below to get the latest pipeline — your plugin settings stay as they are.', 'post-quality-manager'); ?></p>
            </div>
            <?php endif; ?>
            <p class="description"><?php echo esc_html($line); ?></p>
        </div>
<?php
        return (string) ob_get_clean();
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

        // The user is about to re-import: the last report no longer says
        // what runs in n8n, so the page shows "not checked yet" until a ping.
        delete_option('pqm_workflow_version_seen');

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
     * Send a lightweight "ping" command to the n8n webhook. A v5+ Webhook
     * Trigger answers `{"ok":true,"workflow_version":N}`; with an up-to-date
     * import a 2xx then proves that this site reaches n8n and that the
     * trigger accepted the X-PQM-Token header (story 35). An older import
     * answers without a version (n8n's default body): reachability only.
     * Shared by the AJAX handler and the setup dialog's connect route
     * (story 36); the callers decide whether to record the version.
     *
     * @return array{ok: bool, message: string, version: ?int, outdated: bool}
     *         `version` is the replied workflow version (0 = an older
     *         workflow that does not report it), null when the ping failed.
     *         `outdated`: a 2xx from a workflow older than the bundled
     *         template — connected, but shown as a warning (D2).
     */
    public static function ping_webhook(string $url): array
    {
        // A test URL would answer while the n8n editor listens and then miss
        // every real run — refuse before sending.
        $warning = pqm_webhook_url_warning($url);
        if ($warning !== '') {
            return ['ok' => false, 'message' => $warning, 'version' => null, 'outdated' => false];
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
            ), 'version' => null, 'outdated' => false];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            // The reply says which workflow answered. Without a version (or
            // not JSON) it is a v1–v4 import, which may ignore the header:
            // its 2xx proves only the reachability.
            $body    = json_decode((string) wp_remote_retrieve_body($response), true);
            $version = is_array($body) ? absint($body['workflow_version'] ?? 0) : 0;
            if ($version >= PQM_WORKFLOW_VERSION) {
                return ['ok' => true, 'message' => __('Ping OK — this site reaches n8n, the webhook credential is right and your workflow is up to date. Your first review confirms the return path.', 'post-quality-manager'), 'version' => $version, 'outdated' => false];
            }
            return ['ok' => true, 'message' => __('Ping OK — this site reaches n8n, but your n8n workflow is an older version: download or re-import it on the n8n Workflow page, then send another ping.', 'post-quality-manager'), 'version' => $version, 'outdated' => true];
        }

        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => sprintf(
                /* translators: %d: HTTP status code (401 or 403) */
                __('n8n refused the webhook credential (HTTP %d). In n8n, open the Webhook Trigger\'s Header Auth credential: its Name must be X-PQM-Token and its Value this site\'s webhook token. Then try again.', 'post-quality-manager'),
                $code
            ), 'version' => null, 'outdated' => false];
        }

        if ($code === 404) {
            return ['ok' => false, 'message' => __('n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'post-quality-manager'), 'version' => null, 'outdated' => false];
        }

        return ['ok' => false, 'message' => sprintf(
            /* translators: %d: HTTP status code */
            __('n8n responded with HTTP %d. Check your workflow configuration.', 'post-quality-manager'),
            $code
        ), 'version' => null, 'outdated' => false];
    }

    /* ─── AJAX: Test Connection ──────────────────────────────────────── */

    /**
     * Ping the n8n webhook and report the outcome. Tests the URL from the
     * Settings field so it works before saving; the n8n Workflow page sends
     * none, so the saved URL is pinged. Success answers `{message, outdated}`,
     * plus `status_html` when the saved URL answered: only then is the
     * replied version recorded (an unsaved URL may be another workflow).
     * Errors answer the message string.
     */
    public static function ajax_test_connection(): void
    {
        check_ajax_referer('pqm_admin_action');

        if (!current_user_can('pqm_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        $saved_url = pqm_get_settings()['n8n_webhook_url'];
        $url       = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        if (empty($url)) {
            $url = $saved_url;
        }
        if (empty($url)) {
            wp_send_json_error(__('Enter your n8n webhook URL first.', 'post-quality-manager'));
        }

        $ping = self::ping_webhook($url);
        if (!$ping['ok']) {
            wp_send_json_error($ping['message']);
        }

        $data = ['message' => $ping['message'], 'outdated' => $ping['outdated']];
        if ($url === $saved_url) {
            pqm_record_workflow_version($ping['version'], 'test');
            $data['status_html'] = self::render_workflow_status();
        }
        wp_send_json_success($data);
    }

    /* ─── AJAX: Workflow status (re-read on tab focus) ───────────────── */

    /**
     * Answer the n8n Workflow page's status block, `{status_html}`, as it
     * renders now. The open page asks whenever its tab regains focus, so a
     * download, an import from URL or a command's report made meanwhile
     * shows without a reload (D1). Reads only — never pings n8n.
     */
    public static function ajax_workflow_status(): void
    {
        check_ajax_referer('pqm_admin_action');

        if (!current_user_can('pqm_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        wp_send_json_success(['status_html' => self::render_workflow_status()]);
    }
}
