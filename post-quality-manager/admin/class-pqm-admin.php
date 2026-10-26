<?php
/**
 * Admin pages for the Post Quality Manager plugin.
 *
 * Registers four sub-pages under a "Post Quality" top-level menu:
 * 1. Dashboard — React app: Fix-next queue, stat tiles, score sparkline, nudges
 * 2. Post Reviews — React app: post table with scores, filters, per-post actions
 * 3. n8n Workflow — one body rendered from the site's state (story 67):
 *    status block, one primary action, the state's body, the disclosures
 *    (Set up by hand, n8n connection, Webhook); the setup dialog and the
 *    n8n connection are React islands
 * 4. Settings — connection, AI model, prompts & context, cron
 *
 * The two React pages (src/, ADR 0005) get only a PHP shell from here; the
 * Settings and n8n Workflow pages render in PHP with pqm-admin.css/.js. The
 * n8n Workflow page also mounts the setup dialog and the n8n connection
 * island from the same bundle (stories 36, 67).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PostQuality_Admin
{

    /**
     * Hook suffix of the Post Reviews submenu page, captured at registration
     * so the enqueue gate never depends on the translated menu title
     * (e.g. 'post-quality_page_postquality-posts' on an English site).
     */
    private static ?string $posts_hook = null;

    /** Hook suffix of the Dashboard page (story 5.6), captured the same way. */
    private static ?string $dashboard_hook = null;

    /** Hook suffix of the n8n Workflow page (story 36: hosts the setup dialog island). */
    private static ?string $workflow_hook = null;

    /** Query arg that opens the setup dialog on load (the welcome notice's link). */
    public const SETUP_QUERY_ARG = 'postquality_setup';

    /**
     * Admin menu icon (story 51): the mono mark of assets/icon.svg as a base64
     * SVG. Filled shapes only, so WordPress can recolour it for every admin
     * color scheme (it rewrites fill attributes, never strokes).
     */
    private const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iI2ZmZmZmZiIgZmlsbC1ydWxlPSJldmVub2RkIiBkPSJNIDEzLjEgOS41NSBBIDYuMyA2LjMgMCAxIDEgMC41IDkuNTUgQSA2LjMgNi4zIDAgMSAxIDEzLjEgOS41NSBaIE0gMTAuODUgOS41NSBBIDQuMDUgNC4wNSAwIDEgMCAyLjc1IDkuNTUgQSA0LjA1IDQuMDUgMCAxIDAgMTAuODUgOS41NSBaIi8+PHJlY3QgZmlsbD0iI2ZmZmZmZiIgeD0iMCIgeT0iLTEuMTIiIHdpZHRoPSI0LjgxIiBoZWlnaHQ9IjIuMjQiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDkuNDcgMTIuMjIpIHJvdGF0ZSg0NSkiLz48cmVjdCBmaWxsPSIjZmZmZmZmIiB4PSIwIiB5PSItMS4xMiIgd2lkdGg9IjcuNzgiIGhlaWdodD0iMi4yNCIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTIuODcgMTUuNjIpIHJvdGF0ZSgtNDUpIi8+PGNpcmNsZSBmaWxsPSIjZmZmZmZmIiBjeD0iOS40NyIgY3k9IjEyLjIyIiByPSIxLjEyIi8+PGNpcmNsZSBmaWxsPSIjZmZmZmZmIiBjeD0iMTIuODciIGN5PSIxNS42MiIgcj0iMS4xMiIvPjxjaXJjbGUgZmlsbD0iI2ZmZmZmZiIgY3g9IjE4LjM4IiBjeT0iMTAuMTIiIHI9IjEuMTIiLz48L3N2Zz4=';

    /* ─── Menu Registration ──────────────────────────────────────────── */

    public static function register_pages(): void
    {
        // Story 5.6: the React shell renders the Dashboard.
        self::$dashboard_hook = add_menu_page(
            __('Post Quality', 'post-quality-manager'),
            __('Post Quality', 'post-quality-manager'),
            'postquality_view_reviews',
            'postquality-dashboard',
        [__CLASS__, 'render_dashboard_app'],
            self::MENU_ICON,
            30
        ) ?: null;

        add_submenu_page(
            'postquality-dashboard',
            __('Dashboard', 'post-quality-manager'),
            __('Dashboard', 'post-quality-manager'),
            'postquality_view_reviews',
            'postquality-dashboard',
        [__CLASS__, 'render_dashboard_app']
        );

        // Story 5.1: the React shell renders this page.
        self::$posts_hook = add_submenu_page(
            'postquality-dashboard',
            __('Post Reviews', 'post-quality-manager'),
            __('Post Reviews', 'post-quality-manager'),
            'postquality_view_reviews',
            'postquality-posts',
        [__CLASS__, 'render_posts_app']
        ) ?: null;

        self::$workflow_hook = add_submenu_page(
            'postquality-dashboard',
            __('n8n Workflow', 'post-quality-manager'),
            __('n8n Workflow', 'post-quality-manager'),
            'postquality_manage_settings',
            'postquality-workflow',
        [__CLASS__, 'render_workflow']
        ) ?: null;

        // Editors (postquality_manage_agents) reach the page for the overrides and
        // Automated Reviews sections; the global sections inside stay
        // postquality_manage_settings-only (CAP-3 access matrix). Either capability
        // opens the page, so a role editor can grant access via one or the
        // other with no code change.
        add_submenu_page(
            'postquality-dashboard',
            __('Settings', 'post-quality-manager'),
            __('Settings', 'post-quality-manager'),
            self::settings_page_capability(),
            'postquality-settings',
        [__CLASS__, 'render_settings']
        );
    }

    public static function admin_notices(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'postquality-') === false) {
            return;
        }

        // The n8n Workflow page hosts the setup button — no extra banner there.
        if (strpos($screen->id, 'postquality-workflow') !== false) {
            return;
        }

        // The banner routes to the Admin-only Workflow page — only users who
        // can act on it (postquality_manage_settings) should see it.
        if (!current_user_can('postquality_manage_settings')) {
            return;
        }

        // Shown until the setup is complete (story 36); a site that was
        // already connected and receiving results counts as set up.
        if (!postquality_setup_complete()) {
            $setup_url = admin_url('admin.php?page=postquality-workflow&' . self::SETUP_QUERY_ARG . '=1');
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
        if (strpos($hook, 'postquality-') === false) {
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
            'postquality-admin',
            POSTQUALITY_PLUGIN_URL . 'admin/css/pqm-admin.css',
        [],
            filemtime(POSTQUALITY_PLUGIN_DIR . 'admin/css/pqm-admin.css')
        );

        wp_enqueue_script(
            'postquality-admin',
            POSTQUALITY_PLUGIN_URL . 'admin/js/pqm-admin.js',
        [],
            filemtime(POSTQUALITY_PLUGIN_DIR . 'admin/js/pqm-admin.js'),
            true
        );

        $settings = postquality_get_settings();
        // Story 26: preview texts in the site locale, like the payload sends them.
        [$profile_texts, $voice_texts] = postquality_in_site_locale(static fn() => [
            array_map(static fn($p) => $p['website_context'], self::blog_profiles()),
            array_map(static fn($p) => $p['instruction'], postquality_voice_presets()),
        ]);

        wp_localize_script('postquality-admin', 'postqualityAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pageNonce' => wp_create_nonce('postquality_admin_action'),
            // The page's REST actions (story 67: Update workflow, Rotate token, Save and test, Set up by hand instead).
            'restUrl' => rest_url(POSTQUALITY_REST_NAMESPACE . '/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            // Effective global default model (Custom resolved like
            // postquality_effective_model()) — the model-caps JS falls back to it when
            // the AI Model section is absent (Editor view of the Settings page).
            'defaultModel' => $settings['default_model'] === 'custom'
                ? ($settings['custom_model'] !== '' ? $settings['custom_model'] : 'claude-sonnet-4-6')
                : $settings['default_model'],
            'blogProfiles' => $profile_texts,
            'agentDefaults' => postquality_default_settings()['agent_prompts'],
            'voicePresets' => $voice_texts,
            'modelRegistry' => array_map(
                static fn($m) => ['temperature' => $m['supports_temperature'], 'effortLevels' => $m['effort_levels']],
                postquality_model_registry()
            ),
            'i18n' => [
                'testing' => __('Testing the connection…', 'post-quality-manager'),
                'networkError' => __('The request did not go through. Check your connection and try again.', 'post-quality-manager'),
                'stateLoading' => __('The page is still loading its state. Try again in a moment.', 'post-quality-manager'), // story 76
                'copied' => __('Copied! Paste it in n8n under "Import", then "From URL".', 'post-quality-manager'),
                'copiedValue' => __('Copied.', 'post-quality-manager'),
                'copyFailed' => __('Could not copy automatically. The value is selected — press Ctrl+C.', 'post-quality-manager'),
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
        $asset_path = POSTQUALITY_PLUGIN_DIR . 'admin/build/index.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        $version = $asset['version'] ?? POSTQUALITY_VERSION;

        wp_enqueue_script(
            'postquality-app',
            POSTQUALITY_PLUGIN_URL . 'admin/build/index.js',
            $asset['dependencies'] ?? [],
            $version,
            true
        );
        wp_set_script_translations('postquality-app', 'post-quality-manager', POSTQUALITY_PLUGIN_DIR . 'languages');

        // Command labels come from the PHP registry, the one label source;
        // src/kit/copy.js commandLabel() reads them.
        wp_add_inline_script(
            'postquality-app',
            'window.postqualityCommandLabels = ' . wp_json_encode(array_map(
                static fn(array $entry): string => $entry['label'],
                PostQuality_Features::registry()
            )) . ';',
            'before'
        );

        // @wordpress/components styling is served by core, not the bundle.
        wp_enqueue_style('wp-components');

        // Kit styles (story 5.2): wp-scripts extracts src/kit/style.css
        // into the build. Versioned from the asset manifest, no filemtime.
        if (file_exists(POSTQUALITY_PLUGIN_DIR . 'admin/build/style-index.css')) {
            wp_enqueue_style(
                'postquality-app',
                POSTQUALITY_PLUGIN_URL . 'admin/build/style-index.css',
                ['wp-components'],
                $version
            );
            // wp-scripts also emits style-index-rtl.css; WP swaps it in on RTL locales.
            wp_style_add_data('postquality-app', 'rtl', 'replace');
        }
    }

    /* ─── Blog Profiles (single source of truth) ─────────────────────── */

    /**
     * Niche presets for Website Context (story 26): a selected profile is
     * the setting, resolved at send time by postquality_resolved_context(); 'custom'
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
        register_setting('postquality_settings_group', 'postquality_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);

        // options.php guards the save with manage_options by default; align it
        // with the Settings menu so a remapped role can both open and save.
        // Per-section protection lives in sanitize_settings(): keys of sections
        // the saver could not see carry their stored values.
        add_filter('option_page_capability_postquality_settings_group', [__CLASS__, 'settings_page_capability']);
    }

    /**
     * Capability that opens (and saves) the Settings page for the current
     * user: postquality_manage_settings if held, else postquality_manage_agents if held, else
     * core manage_options — so a site administrator whose role lost both
     * plugin caps still reaches the Roles tab (story 42: never behind a
     * plugin capability). The sections inside follow their own gates.
     */
    public static function settings_page_capability(): string
    {
        if (current_user_can('postquality_manage_settings')) {
            return 'postquality_manage_settings';
        }
        // The agent sections exist in the Pro build only (ADR 0014): on the
        // Free build a postquality_manage_agents holder would get an empty page.
        if ( postquality_fs()->is__premium_only() ) {
            if (current_user_can('postquality_manage_agents')) {
                return 'postquality_manage_agents';
            }
        }
        return 'manage_options';
    }

    public static function sanitize_settings($input): array
    {
        // options.php passes null when a save posts no postquality_settings fields at
        // all (e.g. the free-site Editor view, where every section is hidden
        // or locked) — treat it as an empty POST, not a fatal.
        $input = (array) $input;

        $defaults = postquality_default_settings();
        // Sections the saving request could not have posted — hidden by role or
        // locked by plan (disabled fieldset) — must carry their *stored* values,
        // never the defaults: the disabled-controls pitfall, applied per section.
        $existing = postquality_get_settings();
        $registry_keys = array_keys(postquality_model_registry());
        $clean = [];

        if (current_user_can('postquality_manage_settings')) {
            // Admin-only sections: License, n8n Connection, AI Model,
            // AI Prompts & Context, Monthly Quota.
            // license_key is unused since story 44 (the SDK holds the license):
            // no field posts it, so an admin save clears a stub-era key.
            $clean['license_key'] = trim(sanitize_text_field($input['license_key'] ?? ''));
            // The webhook URL and token live on the n8n Workflow page (story
            // 67: save_webhook_url() and the token rotation, single-key
            // merge-saves). No Settings field posts them, so they carry their
            // stored values whatever the form sends (the disabled-controls
            // pitfall): a forged post cannot change them here either.
            $clean['n8n_webhook_url'] = $existing['n8n_webhook_url'];
            $clean['webhook_token'] = $existing['webhook_token'];
            $clean['notification_url'] = esc_url_raw($input['notification_url'] ?? '');
            $clean['blog_profile'] = array_key_exists($input['blog_profile'] ?? '', self::blog_profiles()) ? $input['blog_profile'] : $defaults['blog_profile'];
            $voice_keys = array_merge(array_keys(postquality_voice_presets()), ['custom']);
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
        // come from the request only when the saver has postquality_manage_agents, the
        // site is pro and the request posted the section; otherwise they carry
        // their stored values wholesale (never per field: an absent
        // cron_enabled must not read as "unchecked") — hidden for a role
        // without postquality_manage_agents, locked on a free site (disabled fieldset),
        // or missing on a pro site from a form rendered while the site was
        // free, or a direct POST. "Posted" means the knobs the pro form always
        // posts — not cron_enabled (the free off-switch posts it too) nor
        // cron_command (ticket 3: one of review/seo/audit, else review).
        // The Free build has neither section (ADR 0014): both always carry.
        $can_edit_agents = current_user_can('postquality_manage_agents') && PostQuality_Features::is_pro();
        $cron_keys = ['cron_enabled', 'cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days', 'cron_command'];
        $cron_posted = false;
        if ( postquality_fs()->is__premium_only() ) {
            $cron_knobs = ['cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days'];
            if ($can_edit_agents && array_intersect_key($input, array_flip($cron_knobs))) {
                $cron_posted = true;
                $clean['cron_enabled'] = !empty($input['cron_enabled']);
                $clean['cron_frequency'] = in_array($input['cron_frequency'] ?? '', ['daily', 'weekly'], true)
                    ? $input['cron_frequency'] : $defaults['cron_frequency'];
                $clean['cron_batch_size'] = absint($input['cron_batch_size'] ?? $defaults['cron_batch_size']);
                $clean['cron_min_score'] = absint($input['cron_min_score'] ?? $defaults['cron_min_score']);
                $clean['cron_max_age_days'] = absint($input['cron_max_age_days'] ?? $defaults['cron_max_age_days']);
                $clean['cron_command'] = in_array($input['cron_command'] ?? '', self::cron_commands__premium_only(), true)
                    ? $input['cron_command'] : $defaults['cron_command'];
            }
        }
        if (!$cron_posted) {
            foreach ($cron_keys as $key) {
                $clean[$key] = $existing[$key];
            }
            // The one exception (ticket 13): on a free site the Enable toggle
            // stays live as an off-switch while stored cron is on, so leftover
            // scheduled reviews can be stopped. It can only switch off — a
            // carried section never sets cron_enabled true, and nothing else is
            // read. Plan-independent, so an off-switch form rendered while free
            // and saved after the site turned pro still switches off.
            if (current_user_can('postquality_manage_agents')
                && !empty($existing['cron_enabled'])
                && isset($input['cron_enabled']) && empty($input['cron_enabled'])) {
                $clean['cron_enabled'] = false;
            }
        }

        $clean['agent_prompts'] = $existing['agent_prompts'];
        if ( postquality_fs()->is__premium_only() ) {
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
                            $patterns ??= PostQuality_Blocks::patterns();
                            $pattern = absint($agent_input['pattern'] ?? 0);
                            $clean['agent_prompts'][$agent]['pattern'] = isset($patterns[$pattern]) ? $pattern : 0;
                        }
                    }
                }
            }
        }

        // Roles tab (story 42): written to the site's roles, never stored in
        // the option (roles_posted, roles_shown and roles never reach $clean).
        // Only for a manage_options saver on a pro site whose request posted
        // the tab — the marker is absent for anyone without the tab, inside
        // the locked (free) fieldset and from programmatic update_option()
        // calls, so none of those touches a role.
        if ( postquality_fs()->is__premium_only() ) {
            if (current_user_can('manage_options') && PostQuality_Features::is_pro() && !empty($input['roles_posted'])) {
                self::save_roles__premium_only((array) ($input['roles'] ?? []), (array) ($input['roles_shown'] ?? []));
            }
        }

        return $clean;
    }

    /**
     * Commands Automated Reviews can schedule (ticket 3). Never rewrite (it
     * changes the post) nor the insert-oriented takeaways/sources.
     */
    private static function cron_commands__premium_only(): array
    {
        return ['review', 'seo', 'audit'];
    }

    /**
     * Plugin capability => its Roles-tab column label, in capabilities()
     * order. Plain actions only: a postquality_ slug never reaches the screen.
     */
    private static function role_cap_labels__premium_only(): array
    {
        return [
            'postquality_view_reviews'    => __('View reviews', 'post-quality-manager'),
            'postquality_run_free_agents' => __('Run Review and Key Takeaways', 'post-quality-manager'),
            'postquality_run_paid_agents' => __('Run paid agents', 'post-quality-manager'),
            'postquality_manage_agents'   => __('Manage agents', 'post-quality-manager'),
            'postquality_manage_settings' => __('Manage settings', 'post-quality-manager'),
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
     * five whatever was posted. Only the five postquality_ caps on roles are touched
     * (never core caps, never per-user grants), and only where the role's
     * state changes — idempotent, so options.php running the sanitizer twice
     * on a first save is harmless.
     *
     * @param array $posted role slug => list of checked capabilities.
     * @param array $shown  role slug => comma-separated capabilities the row rendered checked.
     */
    private static function save_roles__premium_only(array $posted, array $shown): void
    {
        $caps = array_keys(PostQuality_Features::capabilities());
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
        if ($option !== 'postquality_settings') {
            return;
        }
        if ( postquality_fs()->is__premium_only() ) {
            PostQuality_Cron::schedule();
        }

        if ((is_array($old_value) ? ($old_value['n8n_webhook_url'] ?? '') : '')
            !== (is_array($value) ? ($value['n8n_webhook_url'] ?? '') : '')) {
            delete_option('postquality_workflow_version_seen');
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
        if ( postquality_fs()->is__premium_only() ) {
            if (isset($_GET['postquality_dismiss_cron_nudge'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'postquality_dismiss_cron_nudge')
                && current_user_can('postquality_manage_agents')
            ) {
                update_option('postquality_cron_nudge_dismissed', 1, false);
            }
        }

        self::render_app_shell(__('Dashboard', 'post-quality-manager'), 'pqm-dashboard-root');
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

    /** Whether the admin app is built (admin/build/, git-ignored, built by CI). */
    private static function app_built(): bool
    {
        return file_exists(POSTQUALITY_PLUGIN_DIR . 'admin/build/index.asset.php');
    }

    /**
     * The root div the bundle mounts on (src/index.js) — or, on a fresh
     * clone without built assets, the next step instead of a blank spot.
     * Shared by the React pages and the setup island (story 36).
     */
    private static function render_app_root(string $root_id): void
    {
        if (self::app_built()) {
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
        $settings = postquality_get_settings();
        $workflow_page_url = admin_url('admin.php?page=postquality-workflow');
        $registry = postquality_model_registry();
        // Section gates (CAP-3 access matrix): the global sections are hidden
        // without postquality_manage_settings; the agent sections (Agent-Specific
        // Overrides, Automated Reviews) are hidden without postquality_manage_agents
        // and render visible but locked on a free site. Role-hidden beats
        // plan-locked, mirroring PostQuality_Features::why_not().
        $can_manage_settings = current_user_can('postquality_manage_settings');
        $can_manage_agents = current_user_can('postquality_manage_agents');
        $is_pro = PostQuality_Features::is_pro();
        // Story 27: tabs over the one form (pqm-admin.js switches them, so
        // every save still posts every section). A tab exists only when one
        // of its sections renders; the server marks the first one active.
        // The agent sections and Roles exist in the Pro build only (ADR 0014);
        // setting a key again keeps its place, so the order stays fixed.
        $tabs = [
            'connection' => $can_manage_settings ? __('Connection', 'post-quality-manager') : '',
            'ai'         => $can_manage_settings ? __('AI', 'post-quality-manager') : '',
        ];
        if ( postquality_fs()->is__premium_only() ) {
            if ($can_manage_agents) {
                $tabs['ai']         = __('AI', 'post-quality-manager');
                $tabs['automation'] = __('Automation', 'post-quality-manager');
            }
        }
        $tabs['usage'] = $can_manage_settings ? __('Usage & Limits', 'post-quality-manager') : '';
        if ( postquality_fs()->is__premium_only() ) {
            // Story 42: core manage_options, never a pqm_ cap, so the tab can't remove itself.
            if (current_user_can('manage_options')) {
                $tabs['roles'] = __('Roles', 'post-quality-manager');
            }
        }
        $tabs = array_filter($tabs);
        $first_tab = array_key_first($tabs);
        $panel_open = static function (string $slug) use ($first_tab): void {
            printf('<div class="pqm-tab-panel%s" id="pqm-panel-%s">', $slug === $first_tab ? ' is-active' : '', esc_attr($slug));
        };
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Settings', 'post-quality-manager'); ?></h1>

            <?php if (!empty($_GET['settings-updated'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's redirect flag after options.php saves; it only shows a notice. ?>
            <!-- Custom save notice: settings ride the webhook payload, so no n8n re-import is ever needed. -->
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved — they apply on the next command. No n8n re-import needed.', 'post-quality-manager'); ?></p>
            </div>
            <?php
            // Notices the sanitizer raised (e.g. an n8n test URL). Core shows
            // these only under Settings → *, so this page prints its own.
            settings_errors('postquality_settings');
            ?>
            <?php endif; ?>

            <?php if ($can_manage_settings && empty($settings['n8n_webhook_url'])): ?>
            <div class="notice notice-info inline pqm-inline-notice">
                <p>
                    <?php esc_html_e('New here? Set up the n8n workflow first; everything about it lives on one page.', 'post-quality-manager'); ?>
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
                <?php settings_fields('postquality_settings_group'); ?>

                <?php if ($can_manage_settings): $panel_open('connection'); ?>
                <!-- License (story 44): the plan as the Freemius SDK reports it; the
                     key lives on the SDK's own screens, and this is the one upsell. -->
                <h2><?php esc_html_e('License', 'post-quality-manager'); ?></h2>
                <p class="description"><?php
                    // The Free build (ADR 0014) has no Pro code to unlock: a
                    // license activated on it (the SDK accepts one there) points
                    // to the Pro download the Account page offers.
                    if ($is_pro) {
                        $license_line = sprintf(
                            /* translators: %s: link to the Account page */
                            esc_html__('Your Pro license is active. Install the Pro version to use it: %s', 'post-quality-manager'),
                            '<a href="' . esc_url(postquality_fs()->get_account_url()) . '">' . esc_html__('Open the Account page', 'post-quality-manager') . '</a>'
                        );
                    } else {
                        $license_line = sprintf(
                            /* translators: %s: link to the pricing page */
                            esc_html__('Current plan: Free — Review and Key Takeaways are included. %s', 'post-quality-manager'),
                            '<a href="' . esc_url(POSTQUALITY_PRICING_URL) . '">' . esc_html__('Get Pro', 'post-quality-manager') . '</a>'
                        );
                    }
                    if ( postquality_fs()->is__premium_only() ) {
                        if ($is_pro) {
                            $license_line = esc_html__('Current plan: Pro — all agents, per-agent overrides, scheduled reviews and role settings are unlocked.', 'post-quality-manager');
                        } elseif (PostQuality_License::has_lapsed()) {
                            $license_line = sprintf(
                                /* translators: %s: link to the Account page */
                                esc_html__('Current plan: Free — your Pro license has lapsed. Your overrides, schedules and role settings are kept and apply again once you renew. %s', 'post-quality-manager'),
                                '<a href="' . esc_url(postquality_fs()->get_account_url()) . '">' . esc_html__('Renew on the Account page', 'post-quality-manager') . '</a>'
                            );
                        }
                    }
                    echo $license_line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece above.
                ?></p>

                <!-- n8n Connection -->
                <h2 class="pqm-section-gap"><?php esc_html_e('n8n Connection', 'post-quality-manager'); ?></h2>
                <p class="description"><?php
                    printf(
                        /* translators: %s: link to the n8n Workflow page */
                        esc_html__('The workflow itself — the setup, the connection test, the webhook URL and token — lives on the %s page.', 'post-quality-manager'),
                        '<a href="' . esc_url($workflow_page_url) . '">' . esc_html__('n8n Workflow', 'post-quality-manager') . '</a>'
                    );
                ?></p>
                <table class="form-table">
                    <?php // Story 67: read-only here; the fields and their saves are on the n8n Workflow page (no name="postquality_settings[…]", so the save keeps the stored values). ?>
                    <tr>
                        <th><?php esc_html_e('n8n Webhook URL', 'post-quality-manager'); ?></th>
                        <td>
                            <?php if ($settings['n8n_webhook_url'] !== ''): ?>
                            <code class="pqm-settings-readonly"><?php echo esc_html($settings['n8n_webhook_url']); ?></code>
                            <?php else: ?>
                            <span class="pqm-settings-readonly"><?php esc_html_e('Not set.', 'post-quality-manager'); ?></span>
                            <?php endif; ?>
                            <p class="description"><?php
                                printf(
                                    /* translators: %s: link to the n8n Workflow page */
                                    esc_html__('Change it on the %s.', 'post-quality-manager'),
                                    '<a href="' . esc_url($workflow_page_url) . '">' . esc_html__('n8n Workflow page', 'post-quality-manager') . '</a>'
                                );
                            ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Webhook Token', 'post-quality-manager'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($workflow_page_url); ?>"><?php esc_html_e('Shown on the n8n Workflow page', 'post-quality-manager'); ?></a>
                            <p class="description"><?php /* translators: %s: the header name, X-PQM-Token */ echo esc_html(sprintf(__('Shared secret for both directions, always in the %s header: WordPress sends it to start the workflow (your n8n Header Auth credential holds the same value), and n8n sends it back when posting results.', 'post-quality-manager'), PostQuality_N8n::TOKEN_HEADER)); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_notification_url"><?php esc_html_e('Notification URL', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="url" id="pqm_notification_url" name="postquality_settings[notification_url]"
                                   value="<?php echo esc_attr($settings['notification_url']); ?>"
                                   class="regular-text" placeholder="https://your-chat.example.com/hooks/..." />
                            <p class="description"><?php esc_html_e('Optional. kChat/Slack/Teams webhook for formatted reports.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                </div><!-- #pqm-panel-connection -->
                <?php endif; // postquality_manage_settings ?>

                <?php if (isset($tabs['ai'])): $panel_open('ai'); ?>
                <?php if ($can_manage_settings): ?>
                <!-- AI Model -->
                <h2 class="pqm-section-gap"><?php esc_html_e('AI Model', 'post-quality-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Which Claude model runs your commands. Model changes apply on the next command — no n8n re-import needed.', 'post-quality-manager'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_default_model"><?php esc_html_e('Default Model', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_default_model" name="postquality_settings[default_model]">
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
                            <input type="text" id="pqm_custom_model" name="postquality_settings[custom_model]"
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
                $profile_text    = postquality_resolved_context(['blog_profile' => $current_profile, 'website_context' => '']);
                $preset_text     = postquality_resolved_voice(['brand_voice_preset' => $current_preset, 'brand_voice' => '']);
                $preset_labels   = array_map(static fn($p) => $p['label'], postquality_voice_presets());
                $preset_labels['custom'] = __('Custom', 'post-quality-manager');
                // Which commands receive these texts: the Free build has Review only.
                $profile_hint = __('Who you write for — sent as the Website Context with every Review. Custom: describe your site and readers in two or three sentences.', 'post-quality-manager');
                $voice_hint   = __('The voice and rules every post follows: your own if you write alone, the shared ones for a team. Sent with every Review.', 'post-quality-manager');
                if ( postquality_fs()->is__premium_only() ) {
                    $profile_hint = __('Who you write for — sent as the Website Context with every Review, Rewrite, SEO and Audit. Custom: describe your site and readers in two or three sentences.', 'post-quality-manager');
                    $voice_hint   = __('The voice and rules every post follows: your own if you write alone, the shared ones for a team. Sent with every Review and Rewrite.', 'post-quality-manager');
                }
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Blog Profile', 'post-quality-manager'); ?></th>
                        <td>
                            <fieldset class="pqm-profile-fieldset">
                                <legend class="screen-reader-text"><?php esc_html_e('Blog Profile', 'post-quality-manager'); ?></legend>
                                <?php foreach ($profiles as $val => $profile): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="postquality_settings[blog_profile]" value="<?php echo esc_attr($val); ?>" <?php checked($current_profile, $val); ?> class="pqm-blog-profile" />
                                        <?php echo esc_html($profile['label']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php echo esc_html($profile_hint); ?></p>
                            <p class="description pqm-preset-text" id="pqm_profile_text" aria-live="polite"<?php echo $profile_text === '' ? ' hidden' : ''; ?>><?php echo esc_html($profile_text); ?></p>
                            <div class="pqm-custom-field" id="pqm_context_field"<?php echo $profile_text === '' ? '' : ' hidden'; ?>>
                                <textarea id="pqm_website_context" name="postquality_settings[website_context]" rows="4" class="large-text"
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
                                        <input type="radio" name="postquality_settings[brand_voice_preset]" value="<?php echo esc_attr($val); ?>" <?php checked($current_preset, $val); ?> class="pqm-voice-preset" />
                                        <?php echo esc_html($lbl); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php echo esc_html($voice_hint); ?></p>
                            <p class="description pqm-preset-text" id="pqm_voice_text" aria-live="polite"<?php echo $preset_text === '' ? ' hidden' : ''; ?>><?php echo esc_html($preset_text); ?></p>
                            <div class="pqm-custom-field" id="pqm_voice_field"<?php echo $preset_text === '' ? '' : ' hidden'; ?>>
                                <textarea id="pqm_brand_voice" name="postquality_settings[brand_voice]" rows="4" class="large-text"
                                          aria-label="<?php esc_attr_e('Editorial Standards', 'post-quality-manager'); ?>" aria-describedby="pqm_brand_voice_empty"
                                          placeholder="<?php esc_attr_e('e.g. Active voice, short paragraphs, no buzzwords. Back every statistic with a source.', 'post-quality-manager'); ?>"><?php echo esc_textarea($settings['brand_voice']); ?></textarea>
                                <p class="description" id="pqm_brand_voice_empty"><?php esc_html_e('Empty: the AI falls back to "professional yet accessible, active voice".', 'post-quality-manager'); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
                <?php self::render_agent_table($settings, $registry); // story 67: beside the values it reads ?>
                <?php endif; // postquality_manage_settings ?>

                <?php
                if ( postquality_fs()->is__premium_only() ) {
                    if ($can_manage_agents) {
                        self::render_overrides__premium_only($settings, $registry, $can_manage_settings, $is_pro);
                    }
                }
                ?>
                </div><!-- #pqm-panel-ai -->
                <?php endif; // AI tab ?>

                <?php
                if ( postquality_fs()->is__premium_only() ) {
                    if ($can_manage_agents) {
                        self::render_automation__premium_only($panel_open, $settings, $is_pro);
                    }
                }
                ?>

                <?php if ($can_manage_settings): $panel_open('usage'); ?>
                <!-- Author Quota -->
                <h2 id="pqm-quota-settings" class="pqm-section-gap"><?php esc_html_e('Monthly Quota', 'post-quality-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_author_quota"><?php esc_html_e('AI Actions per User', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_author_quota" name="postquality_settings[author_quota]"
                                   value="<?php echo esc_attr($settings['author_quota']); ?>"
                                   min="0" step="1" class="small-text" />
                            <p class="description"><?php
                                // The Free build has no paid agents or schedules to name.
                                $quota_hint = __('Monthly limit of manual AI actions for each Author; Editors and Administrators have no limit. Set 0 for unlimited.', 'post-quality-manager');
                                if ( postquality_fs()->is__premium_only() ) {
                                    $quota_hint = __('Monthly limit of manual AI actions for users without the paid-agents capability. Set 0 for unlimited. Scheduled reviews are not counted.', 'post-quality-manager');
                                }
                                echo esc_html($quota_hint);
                            ?></p>
                        </td>
                    </tr>
                </table>
                </div><!-- #pqm-panel-usage -->
                <?php endif; // postquality_manage_settings ?>

                <?php
                if ( postquality_fs()->is__premium_only() ) {
                    if (isset($tabs['roles'])) {
                        self::render_roles__premium_only($panel_open, $is_pro);
                    }
                }
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Agent-Specific Overrides, in the AI tab (Pro build only, ADR 0014;
     * locked, not hidden, while the site has no Pro license).
     */
    private static function render_overrides__premium_only(array $settings, array $registry, bool $can_manage_settings, bool $is_pro): void
    {
?>
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
                    $caps       = postquality_model_caps(postquality_effective_model($key, $settings));
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
                                            name="postquality_settings[agent_prompts][<?php echo esc_attr($key); ?>][model]"
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
                                            name="postquality_settings[agent_prompts][<?php echo esc_attr($key); ?>][effort]"
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
                                               name="postquality_settings[agent_prompts][<?php echo esc_attr($key); ?>][temperature]"
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
                                              name="postquality_settings[agent_prompts][<?php echo esc_attr($key); ?>][instructions]"
                                              rows="4" class="large-text"><?php echo esc_textarea($agent_data['instructions']); ?></textarea>
                                    <p class="description"><?php esc_html_e('Added to the agent\'s core JSON instructions.', 'post-quality-manager'); ?></p>
                                </td>
                            </tr>
                            <?php if (isset($agent_data['pattern'])): $patterns ??= PostQuality_Blocks::patterns();
                                // A stored pattern that no longer qualifies is the plain list here, as in insert().
                                $pattern_id = isset($patterns[(int) $agent_data['pattern']]) ? (int) $agent_data['pattern'] : 0; ?>
                            <tr>
                                <th><label for="pqm_agent_pattern_<?php echo esc_attr($key); ?>"><?php esc_html_e('Pattern to use', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <div class="pqm-pattern-row">
                                        <select id="pqm_agent_pattern_<?php echo esc_attr($key); ?>"
                                                name="postquality_settings[agent_prompts][<?php echo esc_attr($key); ?>][pattern]"
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
<?php
    }

    /**
     * The Automation tab: Automated Reviews (Pro build only, ADR 0014;
     * locked, not hidden, while the site has no Pro license).
     */
    private static function render_automation__premium_only(callable $panel_open, array $settings, bool $is_pro): void
    {
        $panel_open('automation');
?>
                <!-- Automated Reviews (paid: locked, not hidden, on a free site) -->
                <?php
                // Ticket 13: while stored cron is on, a free site keeps the
                // Enable toggle live, outside the lock, as an off-switch. The
                // hidden 0 makes the unchecked state post; the sanitizer only
                // ever reads it to switch off.
                // Only while the stored command still runs on free (ticket 3):
                // a paid seo/audit schedule is skipped by PostQuality_Cron, so nothing
                // "keeps running" and the section stays fully locked.
                $cron_off_switch = !$is_pro && !empty($settings['cron_enabled'])
                    && PostQuality_Features::plan_allows((string) $settings['cron_command']);
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
                            <input type="hidden" name="postquality_settings[cron_enabled]" value="0" />
                            <label>
                                <input type="checkbox" name="postquality_settings[cron_enabled]" value="1" checked />
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
                                <input type="checkbox" name="postquality_settings[cron_enabled]" value="1"
                                       <?php checked($settings['cron_enabled']); ?> />
                                <?php esc_html_e('Automatically run an agent on a schedule', 'post-quality-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="pqm_cron_frequency"><?php esc_html_e('Frequency', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_cron_frequency" name="postquality_settings[cron_frequency]">
                                <option value="daily" <?php selected($settings['cron_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'post-quality-manager'); ?></option>
                                <option value="weekly" <?php selected($settings['cron_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'post-quality-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_batch_size"><?php esc_html_e('Batch Size', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_batch_size" name="postquality_settings[cron_batch_size]"
                                   value="<?php echo esc_attr($settings['cron_batch_size']); ?>"
                                   min="1" max="20" class="small-text" />
                            <p class="description"><?php esc_html_e('Number of posts per scheduled run.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_command"><?php esc_html_e('Command', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_cron_command" name="postquality_settings[cron_command]">
                                <?php foreach (self::cron_commands__premium_only() as $cron_cmd): ?>
                                <option value="<?php echo esc_attr($cron_cmd); ?>" <?php selected($settings['cron_command'], $cron_cmd); ?>><?php echo esc_html(PostQuality_Features::label($cron_cmd)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('The agent that runs on each schedule. Posts are picked by their latest result from that agent.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_min_score"><?php esc_html_e('Min Score Threshold', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_min_score" name="postquality_settings[cron_min_score]"
                                   value="<?php echo esc_attr($settings['cron_min_score']); ?>"
                                   min="0" max="100" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-run posts whose result from this agent scores below this value.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_max_age_days"><?php esc_html_e('Max Result Age (days)', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_max_age_days" name="postquality_settings[cron_max_age_days]"
                                   value="<?php echo esc_attr($settings['cron_max_age_days']); ?>"
                                   min="1" max="365" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-run posts whose latest result from this agent is older than this.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>
                </div><!-- #pqm-panel-automation -->
<?php
    }

    /**
     * The Roles tab (Pro build only, ADR 0014; locked, not hidden, while
     * the site has no Pro license).
     */
    private static function render_roles__premium_only(callable $panel_open, bool $is_pro): void
    {
        $panel_open('roles');
?>
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
                <input type="hidden" name="postquality_settings[roles_posted]" value="1" />
                <table class="widefat striped pqm-roles-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Role', 'post-quality-manager'); ?></th>
                            <?php foreach (self::role_cap_labels__premium_only() as $cap_label): ?>
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
                            array_keys(self::role_cap_labels__premium_only()),
                            static fn($cap) => ($pinned && $is_pro) || $role->has_cap($cap)
                        ));
                        $hint_id = 'pqm-role-hint-' . $role_slug;
                    ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($role_label);
                                /* What this row rendered checked: Save writes only the cells that differ.
                                   None for a pinned row, so one that loses manage_options before Save is skipped. */
                                if (!$pinned): ?>
                                <input type="hidden" name="postquality_settings[roles_shown][<?php echo esc_attr($role_slug); ?>]" value="<?php echo esc_attr(implode(',', $row_checked)); ?>" /><?php endif; ?></th>
                            <?php foreach (self::role_cap_labels__premium_only() as $cap => $cap_label): ?>
                            <td><input type="checkbox" name="postquality_settings[roles][<?php echo esc_attr($role_slug); ?>][]" value="<?php echo esc_attr($cap); ?>"
                                       aria-label="<?php echo esc_attr($role_label . ': ' . $cap_label); ?>"<?php
                                if ($cap === 'postquality_view_reviews'): ?> aria-describedby="<?php echo esc_attr($hint_id); ?>"<?php endif;
                                checked(in_array($cap, $row_checked, true)); disabled($pinned); ?> /><?php
                                if ($cap === 'postquality_view_reviews'): ?><span class="pqm-role-hint" id="<?php echo esc_attr($hint_id); ?>"><?php esc_html_e('Without this, the role does not see Dashboard or Post Reviews.', 'post-quality-manager'); ?></span><?php endif; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('A role with Run paid agents is not subject to the monthly quota (Usage & Limits).', 'post-quality-manager'); ?></p>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>
                </div><!-- #pqm-panel-roles -->
<?php
    }

    /* ─── Page: n8n Workflow ─────────────────────────────────────────── */

    /**
     * Agent keys => human labels, shared by the Settings page and the
     * n8n Workflow page's configuration preview.
     */
    private static function agent_labels(): array
    {
        $labels = [
            'review'    => __('Review Agent', 'post-quality-manager'),
            'takeaways' => __('Key Takeaways Agent', 'post-quality-manager'),
        ];
        if ( postquality_fs()->is__premium_only() ) {
            $labels += [
                'sources'   => __('Sources Agent', 'post-quality-manager'),
                'seo'       => __('SEO Agent', 'post-quality-manager'),
                'audit'     => __('Audit Agent', 'post-quality-manager'),
                'rewrite'   => __('Rewrite Agent', 'post-quality-manager'),
            ];
        }

        return $labels;
    }

    /**
     * The n8n "Import from URL" link: the workflow route with the webhook
     * token. Shared by this page and the setup dialog (GET /setup).
     */
    public static function workflow_import_url(): string
    {
        return add_query_arg('token', rawurlencode(postquality_get_settings()['webhook_token']), rest_url(POSTQUALITY_REST_NAMESPACE . '/workflow'));
    }

    /**
     * The n8n Workflow page (story 67): the H1, the setup dialog's mount
     * point (story 36) and one body, #pqm-workflow-body, rendered by
     * render_workflow_body() from the site's state. Every reply that
     * carries `status_html` carries that whole body and pqm-admin.js swaps
     * the body's content (the aria-live region stays); the n8n connection
     * island inside it remounts on each swap (src/index.js).
     */
    public static function render_workflow(): void
    {
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('n8n Workflow', 'post-quality-manager'); ?></h1>

            <?php
            // Story 36: the setup dialog (src/setup/) mounts here — the Modal
            // only; the status block's primary action opens it (.pqm-open-setup).
            self::render_app_root('pqm-setup-root');
            ?>
            <div id="pqm-workflow-body" aria-live="polite"><?php echo self::render_workflow_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
        </div>
<?php
    }

    /**
     * Where the site stands (story 67, EXPERIENCE.md → States), resolved
     * from the top, first match wins:
     *   needs-attention — a URL is saved and the last test failed, found
     *                     the workflow mis-wired, older than the template,
     *                     or the Free variant on Pro (`problem`, `fix`);
     *   set-up          — a URL is saved and the last test passed
     *                     (`checked` false: a URL but no report yet);
     *   manual          — no URL, and the owner chose the manual path or
     *                     the last connection check failed with a cause
     *                     that offers it (`reason`);
     *   connected       — a key with a passing check, no URL;
     *   not-set-up      — none of the above.
     * `managed`: PostQuality_N8n::managed_blocked() null (Update and Rotate are
     * offered); `has_map`: the plugin set the workflow up or manages it
     * (no map = made by hand: Set up by hand stays reachable); `adopted`:
     * a map adopt() took over (story 76: Update asks before replacing it).
     *
     * @return array{state: string, problem: string, fix: string, checked: bool, url: string, report: ?array, reason: ?array, managed: bool, has_map: bool, adopted: bool, has_result: bool, leftovers: bool, host: string}
     */
    private static function workflow_page_state(): array
    {
        $url        = (string) postquality_get_settings()['n8n_webhook_url'];
        $report     = postquality_workflow_report();
        $connection = PostQuality_N8n::connection();
        $managed    = PostQuality_N8n::managed_blocked() === null;
        $map        = get_option(PostQuality_N8n::SETUP_OPTION, false);
        $has_map    = is_array($map);
        $base       = [
            'url'        => $url,
            'report'     => $report,
            'reason'     => null,
            'also'       => '',
            'managed'    => $managed,
            'has_map'    => $has_map,
            'adopted'    => $has_map && empty($map['wizard_created']),
            'has_result' => false,
            'leftovers'  => false,
            'host'       => preg_replace('#^https?://#', '', (string) $connection['base_url']),
            'key_set'    => $connection['api_key'] !== '', // story 74: the island (its refusal Notice, Forget key) stays reachable
            'problem'    => '',
            'fix'        => '',
            'checked'    => $report !== null,
        ];

        if ($url !== '') {
            $problem = '';
            if ($report !== null) {
                if (isset($report['failed'])) {
                    $problem = 'failed';
                } elseif (($report['miswired'] ?? '') !== '') {
                    $problem = 'miswired';
                } elseif ($report['version'] < POSTQUALITY_WORKFLOW_VERSION) {
                    $problem = 'outdated';
                }
                if ( postquality_fs()->is__premium_only() ) {
                    if ($problem === '' && ($report['variant'] ?? '') === 'free') {
                        $problem = 'free';
                    }
                }
            }
            if ($problem !== '') {
                // The fixes in EXPERIENCE.md's order: the test for a failed or mis-wired
                // check; Update in place on a managed site, else the re-import steps.
                // A mis-wired workflow that is also outdated (or the Free variant on
                // Pro) keeps that fix as a secondary beside Test again (`also`).
                $fix  = in_array($problem, ['failed', 'miswired'], true) ? 'test' : ($managed ? 'update' : 'reimport');
                $also = '';
                if ($problem === 'miswired' && $report['version'] < POSTQUALITY_WORKFLOW_VERSION) {
                    $also = $managed ? 'update' : 'reimport';
                }
                if ( postquality_fs()->is__premium_only() ) {
                    if ($problem === 'miswired' && ($report['variant'] ?? '') === 'free') {
                        $also = $managed ? 'update' : 'reimport';
                    }
                }

                return ['state' => 'needs-attention', 'problem' => $problem, 'fix' => $fix, 'also' => $also] + $base;
            }

            return ['state' => 'set-up', 'has_result' => PostQuality_DB::has_result()] + $base;
        }

        // postquality_manual_reason() hides the stored choice behind a passing key (the
        // wizard's rule); on this page the choice wins: "Set up by hand instead"
        // on a connected site must land on the open steps, not back on state 2.
        $reason = postquality_manual_reason() ?? (!empty(get_option(POSTQUALITY_SETUP_OPTION, [])['manual']) ? ['kind' => 'choice'] : null);
        if ($reason !== null) {
            return ['state' => 'manual', 'reason' => $reason] + $base;
        }
        if (PostQuality_N8n::connected()) {
            // Story 71: only a record of the connected instance is removed by the next setup (as GET /setup reads it).
            $leftovers = get_option(PostQuality_N8n::LEFTOVERS_OPTION, false);

            return ['state' => 'connected', 'leftovers' => is_array($leftovers) && ($leftovers['base_url'] ?? '') === $connection['base_url']] + $base;
        }

        return ['state' => 'not-set-up'] + $base;
    }

    /**
     * The page's whole body under the H1 (story 67), the one renderer
     * behind the page load and every `status_html` (AJAX ping and status
     * read, POST /setup/connect, /n8n/provision, /n8n/token, /n8n/workflow):
     * the status block (an action's outcome line first, the state sentence,
     * the version and last check, the one primary action and Test again
     * once a URL exists), a Pro warning under it, the state's body, then
     * the collapsed disclosures the state allows — Set up by hand (the
     * manual steps), n8n connection (the island's root), Webhook (URL,
     * token, rotation). Exactly one .button-primary in every state.
     *
     * @param string $outcome An action's outcome sentence, '' on a plain render.
     * @param string $tone    'success' | 'warning' | 'error' for the outcome line.
     */
    public static function render_workflow_body(string $outcome = '', string $tone = 'success'): string
    {
        $settings = postquality_get_settings();
        $state    = self::workflow_page_state();
        $report   = $state['report'];
        $kind     = $state['state'];

        // The accent (DESIGN.md): line, info, success, warning or error.
        $accent = [
            'not-set-up' => 'not-set-up', 'connected' => 'connected', 'manual' => 'manual',
            'set-up'     => $state['checked'] ? 'set-up' : 'not-checked',
        ][$kind] ?? (in_array($state['problem'], ['failed', 'miswired'], true) ? 'failing' : 'outdated');

        // The state sentence, and the version line when a check is on record.
        $sentence = '';
        $details  = []; // sentences under the main one, each its own <p>
        switch ($kind) {
            case 'not-set-up':
                $sentence = __('Not connected to n8n yet.', 'post-quality-manager');
                break;
            case 'connected':
                /* translators: %s: the n8n instance's host, e.g. "n8n.example.com" */
                $sentence = sprintf(__('Connected to n8n at %s. The workflow is not set up yet.', 'post-quality-manager'), $state['host']);
                break;
            case 'manual':
                $sentence  = __('Setting up by hand.', 'post-quality-manager');
                $details[] = ($state['reason']['kind'] ?? '') === 'probe'
                    ? sprintf(
                        /* translators: 1: date of the check, 2: why it did not pass, e.g. "n8n answered 404: the public API is turned off…" */
                        __('On %1$s, the n8n connection check did not pass: %2$s', 'post-quality-manager'),
                        $state['reason']['at'],
                        $state['reason']['message']
                    )
                    : __('You chose to set up n8n by hand.', 'post-quality-manager');
                break;
            case 'set-up':
                $sentence = $state['checked']
                    ? __('Your workflow answers.', 'post-quality-manager')
                    : __('Saved, not checked yet.', 'post-quality-manager');
                break;
            default: // needs-attention
                switch ($state['problem']) {
                    case 'failed':
                        $sentence = sprintf(
                            /* translators: 1: when, e.g. "just now" or "3 hours ago", 2: why the test failed, e.g. "n8n responded with 404…" */
                            __('The last test %1$s did not pass: %2$s', 'post-quality-manager'),
                            self::when($report['failed']['at']),
                            $report['failed']['message']
                        );
                        break;
                    case 'miswired':
                        $sentence = $report['miswired']; // raw (n8n supplies the names): escaped below, once
                        break;
                    case 'outdated':
                        $sentence = $report['version'] > 0
                            /* translators: 1: the workflow's version, 2: the version this plugin needs */
                            ? sprintf(__('Your workflow is version %1$d; this plugin needs %2$d.', 'post-quality-manager'), $report['version'], POSTQUALITY_WORKFLOW_VERSION)
                            /* translators: %d: the version this plugin needs */
                            : sprintf(__('Your workflow is an older version that does not report its number; this plugin needs %d.', 'post-quality-manager'), POSTQUALITY_WORKFLOW_VERSION);
                        break;
                    default: // free
                        $sentence = __('Your workflow is the Free variant: it lacks the Sources, SEO, Audit and Rewrite branches.', 'post-quality-manager');
                }
                if (in_array($state['problem'], ['outdated', 'free'], true)) {
                    // 6 = the first version with a per-site webhook path (postquality_webhook_path()):
                    // only an older workflow gets a new production URL on re-import; an
                    // in-place update (entry 9) keeps the URL whatever the version.
                    if ($state['managed']) {
                        $details[] = __('The plugin can update it in place: the workflow\'s nodes are replaced; its credentials, webhook path and name stay.', 'post-quality-manager');
                    } elseif ($state['problem'] === 'outdated' && $report['version'] < 6) {
                        $details[] = __('A re-import gives it a new production URL.', 'post-quality-manager');
                    }
                }
        }
        $version_line = '';
        if ($report !== null && ($report['version'] > 0 || $report['at'] > 0)) {
            $version = $report['version'] > 0
                /* translators: %d: workflow version number */
                ? sprintf(__('Version %d', 'post-quality-manager'), $report['version'])
                : __('An older version that does not report its number', 'post-quality-manager');
            if ($report['at'] === 0) {
                // Stored by a pre-v5 plugin: a command reported it, time unknown.
                $how = __('reported by a command', 'post-quality-manager');
            } else {
                $how = $report['via'] === 'test'
                    /* translators: %s: when, e.g. "just now" or "3 hours ago" */
                    ? sprintf(__('checked %s by a test', 'post-quality-manager'), self::when($report['at']))
                    /* translators: %s: when, e.g. "just now" or "3 hours ago" */
                    : sprintf(__('checked %s by a command', 'post-quality-manager'), self::when($report['at']));
            }
            /* translators: 1: "Version 6" or "An older version…", 2: "checked just now by a test" */
            $version_line = sprintf(__('%1$s · %2$s.', 'post-quality-manager'), $version, $how);
        }

        // The one primary action, and whether Test again joins it as a secondary.
        // Without the built assets no dialog can open: no button (the build warning says why).
        $setup_button = static fn(string $label, string $step = ''): string => !self::app_built() ? '' : '<button type="button" class="button button-primary pqm-open-setup"'
            . ($step !== '' ? ' data-step="' . esc_attr($step) . '"' : '') . '>' . esc_html($label) . '</button>';
        $test_button  = static fn(bool $primary): string => '<button type="button" class="button' . ($primary ? ' button-primary' : '') . ' pqm-test-connection">'
            . esc_html($primary && $kind === 'set-up' ? __('Test the connection', 'post-quality-manager') : __('Test again', 'post-quality-manager')) . '</button>';
        $fix_button   = static fn(string $fix, bool $primary): string => $fix === 'update'
            ? '<button type="button" class="button' . ($primary ? ' button-primary' : '') . ' pqm-update-workflow" data-pqm-post="n8n/workflow"'
                . ($state['adopted'] ? ' data-pqm-confirm="update"' : '') . ' data-pqm-busy="' // story 76: the island asks first
                . esc_attr__('Updating the workflow…', 'post-quality-manager') . '">' . esc_html__('Update workflow', 'post-quality-manager') . '</button>'
            : '<a class="button' . ($primary ? ' button-primary' : '') . ' pqm-reimport" href="#pqm-by-hand">' . esc_html__('Re-import the workflow', 'post-quality-manager') . '</a>';
        $primary = '';
        switch ($kind) {
            case 'not-set-up':
            case 'connected':
                $primary = $setup_button(__('Set up n8n', 'post-quality-manager'));
                break;
            case 'manual':
                $primary = ''; // Save and test, under step 4 of the open Set up by hand
                break;
            case 'set-up':
                if (!$state['checked']) {
                    $primary = $test_button(true);
                } elseif ($state['has_result']) {
                    $primary = '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=postquality-posts')) . '">' . esc_html__('Open Post Reviews', 'post-quality-manager') . '</a>';
                } else {
                    $primary = $setup_button(__('Run your first review', 'post-quality-manager'), 'review');
                }
                break;
            default:
                $primary = $state['fix'] === 'test' ? $test_button(true) : $fix_button($state['fix'], true);
        }
        // The secondaries: Test again once a URL exists, and the outdated fix behind a mis-wiring.
        $secondary = $state['url'] !== '' && !str_contains($primary, 'pqm-test-connection') ? $test_button(false) : '';
        // Set up (story 67): the configuration steps still open (story 55: saved
        // or skipped ticks them) as one secondary that opens the dialog there; gone
        // once every one is ticked. The wizard resumes at the first open step itself.
        if ($kind === 'set-up' && $state['checked'] && !str_contains($primary, 'pqm-open-setup') && self::app_built()) {
            $open = self::open_setup_steps();
            if ($open !== []) {
                $secondary .= ($secondary !== '' ? ' ' : '') . '<button type="button" class="button-link pqm-open-setup">'
                    /* translators: %s: the open steps, e.g. "Blog Profile, scheduled reviews" */
                    . esc_html(sprintf(__('Finish setting up: %s', 'post-quality-manager'), implode(__(', ', 'post-quality-manager'), $open))) . '</button>';
            }
        }
        if ($state['also'] !== '') {
            $secondary .= ($secondary !== '' ? ' ' : '') . $fix_button($state['also'], false);
        }

        // The disclosures this state allows, in the spine's order. The re-import
        // steps are where the Re-import link leads: shown whenever it renders.
        $reimports   = in_array('reimport', [$state['fix'], $state['also']], true);
        $disclosures = [
            'by-hand'    => in_array($kind, ['manual'], true) || (in_array($kind, ['set-up', 'needs-attention'], true) && (!$state['has_map'] || $reimports)),
            'connection' => $kind !== 'not-set-up' || $state['key_set'],
            'webhook'    => in_array($kind, ['set-up', 'needs-attention'], true),
        ];
        $by_hand_link = '<p><a href="#pqm-by-hand" class="pqm-by-hand-link" data-pqm-post="setup" data-pqm-body="' . esc_attr((string) wp_json_encode(['manual' => true]))
            . '" data-pqm-focus="pqm-by-hand">' . esc_html__('Set up by hand instead', 'post-quality-manager') . '</a></p>';

        ob_start();
?>
        <div class="pqm-workflow-status" data-state="<?php echo esc_attr($accent); ?>">
            <?php if ($outcome !== ''): ?>
            <p class="pqm-workflow-outcome is-<?php echo esc_attr(in_array($tone, ['success', 'warning', 'error'], true) ? $tone : 'success'); ?>"><?php echo esc_html($outcome); ?></p>
            <?php endif; ?>
            <p class="pqm-workflow-sentence"><?php echo esc_html($sentence); ?></p>
            <?php foreach ($details as $detail): ?>
            <p><?php echo esc_html($detail); ?></p>
            <?php endforeach; ?>
            <?php if ($version_line !== ''): ?>
            <p class="description pqm-workflow-version"><?php echo esc_html($version_line); ?></p>
            <?php endif; ?>
            <?php if ($primary !== '' || $secondary !== ''): ?>
            <p class="pqm-workflow-actions"><?php echo $primary . ($primary !== '' && $secondary !== '' ? ' ' : '') . $secondary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
            <?php endif; ?>
        </div>
        <?php
        if ( postquality_fs()->is__premium_only() ) {
            self::render_rewrite_warning__premium_only($kind);
        }
        ?>
        <?php if ($kind === 'not-set-up'): ?>
        <p><?php esc_html_e('The setup asks for three values: your n8n address, an n8n API key with an expiry date, and an Anthropic API key. The plugin then creates everything in n8n for you: the credentials, the workflow and its publication.', 'post-quality-manager'); ?></p>
        <?php echo $by_hand_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
        <?php elseif ($kind === 'connected'): ?>
        <?php if ($state['leftovers']): ?>
        <div class="notice notice-info inline pqm-inline-notice"><p><?php esc_html_e('An earlier setup left a workflow or credentials in n8n. Setting up removes them first.', 'post-quality-manager'); ?></p></div>
        <?php endif; ?>
        <?php echo $by_hand_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
        <?php endif; ?>

        <?php if ($disclosures['by-hand']): ?>
        <details class="pqm-disclosure" id="pqm-by-hand"<?php echo $kind === 'manual' ? ' open' : ''; ?>>
            <summary><h2><?php esc_html_e('Set up by hand', 'post-quality-manager'); ?></h2></summary>
            <?php self::render_workflow_file($settings, $kind === 'manual'); ?>
        </details>
        <?php endif; ?>
        <?php if ($disclosures['connection']): ?>
        <details class="pqm-disclosure" id="pqm-n8n-connection">
            <summary><h2><?php esc_html_e('n8n connection', 'post-quality-manager'); ?></h2></summary>
            <?php // n8n provisioning, entry 1: src/n8n-connection/ over GET|POST|DELETE /n8n/connection; adoption (entry 8) inside it. ?>
            <?php self::render_app_root('pqm-n8n-connection-root'); ?>
        </details>
        <?php endif; ?>
        <?php if ($disclosures['webhook']): ?>
        <details class="pqm-disclosure" id="pqm-webhook">
            <summary><h2><?php esc_html_e('Webhook', 'post-quality-manager'); ?></h2></summary>
            <?php self::render_webhook_form($settings, false, 'disclosure'); ?>
            <?php self::render_copy_value(__('Webhook token', 'post-quality-manager'), (string) $settings['webhook_token']); ?>
            <p class="description"><?php /* translators: %s: the header name, X-PQM-Token */ echo esc_html(sprintf(__('Shared secret for both directions, always in the %s header: this site sends it to start the workflow (the webhook credential in n8n holds the same value), and n8n sends it back with the results.', 'post-quality-manager'), PostQuality_N8n::TOKEN_HEADER)); ?></p>
            <?php if ($state['managed']): ?>
            <p><button type="button" class="button pqm-rotate-token" data-pqm-post="n8n/token" data-pqm-busy="<?php esc_attr_e('Rotating the token…', 'post-quality-manager'); ?>"><?php esc_html_e('Rotate token', 'post-quality-manager'); ?></button></p>
            <p class="description"><?php esc_html_e('Writes a new token into the webhook credential in n8n and here, then tests the connection: the old token stops working at once.', 'post-quality-manager'); ?></p>
            <?php endif; ?>
        </details>
        <?php endif; ?>
<?php
        return (string) ob_get_clean();
    }

    /**
     * The setup dialog's configuration steps not yet saved or skipped (story
     * 55 ticks them in the setup progress), by name, in the dialog's order;
     * scheduled reviews only on the Pro build (the Free dialog has no such step).
     *
     * @return string[]
     */
    private static function open_setup_steps(): array
    {
        $steps = (array) (get_option(POSTQUALITY_SETUP_OPTION, [])['steps'] ?? []);
        $names = [
            'profile'   => __('Blog Profile', 'post-quality-manager'),
            'standards' => __('Editorial Standards', 'post-quality-manager'),
        ];
        if ( postquality_fs()->is__premium_only() ) {
            $names['schedule'] = __('scheduled reviews', 'post-quality-manager');
        }

        return array_values(array_diff_key($names, array_filter($steps)));
    }

    /** "just now" or "3 hours ago", for a timestamp. */
    private static function when(int $at): string
    {
        return time() - $at < MINUTE_IN_SECONDS
            ? __('just now', 'post-quality-manager')
            /* translators: %s: time span, e.g. "3 hours" */
            : sprintf(__('%s ago', 'post-quality-manager'), human_time_diff($at));
    }

    /**
     * Pro, state Set up (story 67, 4b; entry 4's D1): Application Passwords
     * off for the current user means no WordPress credential, so Rewrite
     * stays off — one warning line under the status block.
     */
    private static function render_rewrite_warning__premium_only(string $kind): void
    {
        if ($kind !== 'set-up' || wp_is_application_passwords_available_for_user(wp_get_current_user())) {
            return;
        }
?>
        <div class="notice notice-warning inline pqm-inline-notice"><p><?php esc_html_e('Application Passwords are turned off on this site, so no WordPress credential can be created for this user. Rewrite stays off until they are available and a WordPress credential is set in n8n on the Update WP Post (Rewrite) node.', 'post-quality-manager'); ?></p></div>
<?php
    }

    /**
     * The webhook URL field and Save and test (story 67): step 4 of Set up
     * by hand (primary, the manual path's one action) and the Webhook
     * disclosure (secondary). pqm-admin.js posts the form's field to
     * POST /setup/connect (save_webhook_url(), then the ping) and swaps the
     * body its reply carries. No name="postquality_settings[…]": never a second
     * Settings form.
     */
    private static function render_webhook_form(array $settings, bool $primary, string $place): void
    {
        $id = 'pqm_webhook_url_' . ($place === 'step' ? 'step' : 'disclosure'); // both forms may share a page
?>
        <form class="pqm-webhook-form" data-pqm-post="setup/connect">
            <label class="pqm-copy-label" for="<?php echo esc_attr($id); ?>"><?php esc_html_e('n8n Webhook URL', 'post-quality-manager'); ?></label>
            <span class="pqm-copy-controls">
                <input type="url" id="<?php echo esc_attr($id); ?>" name="webhook_url" class="regular-text code pqm-webhook-url-field"
                       value="<?php echo esc_attr((string) $settings['n8n_webhook_url']); ?>"
                       placeholder="<?php echo esc_attr('https://your-n8n.example.com/webhook/' . postquality_webhook_path()); ?>" />
                <button type="submit" class="button<?php echo $primary ? ' button-primary' : ''; ?> pqm-save-webhook" data-pqm-busy="<?php esc_attr_e('Saving and testing…', 'post-quality-manager'); ?>"><?php esc_html_e('Save and test', 'post-quality-manager'); ?></button>
            </span>
        </form>
<?php
    }

    /**
     * What each agent sends to Claude (story 67: on Settings → AI, beside
     * the values it reads): the effective model, effort, temperature and
     * output budget per agent, as postquality_llm_params() resolves them.
     */
    private static function render_agent_table(array $settings, array $registry): void
    {
?>
                <h2 class="pqm-section-gap"><?php esc_html_e('What Each Agent Sends to Claude', 'post-quality-manager'); ?></h2>
                <p class="description"><?php esc_html_e('These values travel with every command, so a model change here applies on the next command — no n8n re-import needed. Only plugin updates that change the workflow itself need an update in n8n.', 'post-quality-manager'); ?></p>
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
                            $params = postquality_llm_params($key, $settings);
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
<?php
    }

    /* ─── Workflow Generator (admin-post download) ───────────────────── */

    /**
     * The Set up by hand steps (n8n provisioning, entry 2; CAP-8, story 40):
     * the manual setup as four steps in the order the work happens — import,
     * credentials, each flagged node, publish and connect — with a Copy row
     * for every value typed into n8n and links only to addressable places.
     * Rendered inside the page's Set up by hand disclosure (story 67; why
     * the reader is on the manual path is the status block's sentence).
     * The one place for these steps: the setup dialog's "Set up by hand"
     * step links here (`#pqm-by-hand`, n8n provisioning entry 6). Step 4
     * holds the webhook URL field and Save and test: the manual path's one
     * action (`$primary`), a secondary save on a set-up site re-importing.
     */
    private static function render_workflow_file(array $settings, bool $primary): void
    {
        $host     = postquality_site_host(); // the template's credential names (__PQM_SITE_HOST__)
        $names    = PostQuality_N8n::credential_names($host);
        $webhook  = $names['webhook'];
        $claude   = $names['anthropic'];
        $download = wp_nonce_url(admin_url('admin-post.php?action=postquality_download_workflow'), 'postquality_download_workflow');
        $n8n_url  = self::n8n_editor_url((string) $settings['n8n_webhook_url']);
        $n8n      = $n8n_url !== ''
            ? '<a href="' . esc_url($n8n_url) . '">' . esc_html__('your n8n', 'post-quality-manager') . '</a>'
            : esc_html__('n8n', 'post-quality-manager');
        $name     = __('Credential name', 'post-quality-manager');

        // The Free template has no Rewrite branch: two credentials, two Claude nodes.
        $claude_type = __('Type: Header Auth. Goes in the two Claude nodes.', 'post-quality-manager');
        $nodes_hint  = __('n8n marks the imported nodes "Credentials … are not set", or has already filled some (step 1). Do the Claude nodes first: double-click one, pick the Anthropic credential in its credential field and close it; n8n adds it to the other Claude nodes that have none. Then double-click the Webhook Trigger and pick the webhook credential, even if one is already set. Last, open each node — the Webhook Trigger and the two Claude nodes — and check the credential name.', 'post-quality-manager');
        $nodes_pick  = sprintf(
            /* translators: 1: credential name, e.g. "Post Quality webhook - example.com", 2: credential name "Anthropic - Claude" */
            esc_html__('Pick %1$s in the Webhook Trigger and %2$s in the two Claude nodes.', 'post-quality-manager'),
            '<strong>' . esc_html($webhook) . '</strong>',
            '<strong>' . esc_html($claude) . '</strong>'
        );
        if ( postquality_fs()->is__premium_only() ) {
            $claude_type = __('Type: Header Auth. Goes in the six Claude nodes.', 'post-quality-manager');
            $nodes_hint  = __('n8n marks the imported nodes "Credentials … are not set", or has already filled some (step 1). Do the Claude nodes first: double-click one, pick the Anthropic credential in its credential field and close it; n8n adds it to the other Claude nodes that have none. Then double-click the Webhook Trigger and pick the webhook credential, and Update WP Post (Rewrite) and pick the WordPress one, even if one is already set. Last, open each node — the Webhook Trigger, the six Claude nodes and Update WP Post (Rewrite) — and check the credential name.', 'post-quality-manager');
            $nodes_pick  = sprintf(
                /* translators: 1: credential name, e.g. "Post Quality webhook - example.com", 2: credential name "Anthropic - Claude", 3: credential name, e.g. "WordPress - example.com" */
                esc_html__('Pick %1$s in the Webhook Trigger, %2$s in the six Claude nodes and %3$s in Update WP Post (Rewrite).', 'post-quality-manager'),
                '<strong>' . esc_html($webhook) . '</strong>',
                '<strong>' . esc_html($claude) . '</strong>',
                '<strong>' . esc_html($names['wordpress']) . '</strong>'
            );
        }
?>
        <p class="description"><?php esc_html_e('The steps to set up n8n by hand, and to re-import the workflow after a plugin update. To have the plugin set n8n up for you instead, connect your n8n with an API key under "n8n connection".', 'post-quality-manager'); ?></p>

        <ol class="pqm-workflow-steps">
            <li>
                <h3><?php esc_html_e('Import the workflow', 'post-quality-manager'); ?></h3>
                <div class="notice notice-warning inline pqm-inline-notice"><p><?php esc_html_e('Before you import: n8n fills in credentials by itself. A node gets the credential of its type you saved most recently, even one made for another workflow, when it is imported or opened; and a credential you pick in one node is added to every other node of that type that has none. Both Header Auth credentials share a type, so follow the order in step 3 and check the name in each node.', 'post-quality-manager'); ?></p></div>
                <p><?php
                    printf(
                        /* translators: %s: "your n8n", linked to the n8n instance when its address is known, else "n8n" */
                        esc_html__('In %s, open a new, empty workflow, open the workflow menu (the three dots at the top right), choose "Import", then "From URL", and paste this link:', 'post-quality-manager'),
                        $n8n // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
                    );
                ?></p>
                <div class="pqm-import-url">
                    <?php self::render_copy_value(__('Workflow import URL', 'post-quality-manager'), self::workflow_import_url()); ?>
                    <p class="description"><?php esc_html_e('It includes your webhook token, which also opens your n8n workflow\'s trigger — keep it private.', 'post-quality-manager'); ?></p>
                </div>
                <p><?php esc_html_e('Importing from the URL not working (for example, n8n cannot reach this site)? Download the file and choose "Import", then "From file", in the same menu.', 'post-quality-manager'); ?></p>
                <p><a href="<?php echo esc_url($download); ?>" class="button"><?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?></a></p>
            </li>
            <li>
                <h3><?php esc_html_e('Create the credentials', 'post-quality-manager'); ?></h3>
                <p><?php esc_html_e('n8n keeps secrets in credentials. To add one, open the arrow beside "Create workflow" on the n8n Overview page, choose "Create credential", search for the type and fill in the fields below. n8n names a new credential after its type: rename each one to the credential name shown, the name that tells the two Header Auth credentials apart in step 3.', 'post-quality-manager'); ?></p>
                <div class="pqm-workflow-credential">
                    <h4><?php echo esc_html($webhook); ?></h4>
                    <?php self::render_copy_value($name, $webhook, $webhook); ?>
                    <p><?php esc_html_e('Type: Header Auth. Goes in the Webhook Trigger only, so only this site can start the workflow.', 'post-quality-manager'); ?></p>
                    <?php self::render_copy_value(__('Name', 'post-quality-manager'), PostQuality_N8n::TOKEN_HEADER, $webhook); ?>
                    <?php self::render_copy_value(__('Value', 'post-quality-manager'), (string) $settings['webhook_token'], $webhook); ?>
                </div>
                <div class="pqm-workflow-credential">
                    <h4><?php echo esc_html($claude); ?></h4>
                    <?php self::render_copy_value($name, $claude, $claude); ?>
                    <p><?php echo esc_html($claude_type); ?></p>
                    <?php self::render_copy_value(__('Name', 'post-quality-manager'), 'x-api-key', $claude); ?>
                    <p><?php
                        printf(
                            /* translators: %s: link to the API keys page of the Anthropic Console */
                            esc_html__('Value: your Anthropic API key. Create one on the %s.', 'post-quality-manager'),
                            '<a href="' . esc_url('https://platform.claude.com/settings/keys') . '">' . esc_html__('API keys page of the Anthropic Console', 'post-quality-manager') . '</a>'
                        );
                    ?></p>
                </div>
                <?php
                if ( postquality_fs()->is__premium_only() ) {
                    self::render_wordpress_credential__premium_only($host, $name);
                }
                ?>
            </li>
            <li>
                <h3><?php esc_html_e('Check the credential in each node', 'post-quality-manager'); ?></h3>
                <p><?php echo esc_html($nodes_hint); ?></p>
                <p><?php echo $nodes_pick; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
            </li>
            <li>
                <h3><?php esc_html_e('Publish and connect', 'post-quality-manager'); ?></h3>
                <p><?php esc_html_e('Save the workflow, then click "Publish" in the header above the canvas (older n8n versions: switch the "Active" toggle on).', 'post-quality-manager'); ?></p>
                <p><?php esc_html_e('Re-importing over a workflow of version 6 or later? It uses this site\'s webhook path too, so n8n publishes the new one only once the old one is unpublished: open the old one, choose "Unpublish" from the arrow beside "Publish" (older versions: switch its "Active" toggle off), then publish the new one.', 'post-quality-manager'); ?></p>
                <p><?php
                    printf(
                        /* translators: %s: the end of the production URL, e.g. "/webhook/pqm-execute-example-com" */
                        esc_html__('Double-click the Webhook Trigger, choose "Production URL" and copy the address: it ends in %s.', 'post-quality-manager'),
                        '<code>' . esc_html('/webhook/' . postquality_webhook_path()) . '</code>'
                    );
                ?></p>
                <p class="description"><?php esc_html_e('Not the Test URL: an address with /webhook-test/ only listens while the n8n editor is open, so this site refuses it.', 'post-quality-manager'); ?></p>
                <p><?php esc_html_e('Paste it here: the plugin saves it and tests the connection.', 'post-quality-manager'); ?></p>
                <?php self::render_webhook_form($settings, $primary, 'step'); ?>
            </li>
        </ol>
<?php
    }

    /**
     * The WordPress credential's block in step 2 (Pro: only Rewrite needs
     * it): the current user's login and where an Application Password of
     * theirs is created, their own profile page.
     */
    private static function render_wordpress_credential__premium_only(string $host, string $name): void
    {
        $credential = PostQuality_N8n::credential_names($host)['wordpress'];
        $user       = wp_get_current_user();
?>
                <div class="pqm-workflow-credential">
                    <h4><?php echo esc_html($credential); ?></h4>
                    <?php self::render_copy_value($name, $credential, $credential); ?>
                    <p><?php esc_html_e('Type: Basic Auth. Goes in Update WP Post (Rewrite).', 'post-quality-manager'); ?></p>
                    <?php self::render_copy_value(__('User', 'post-quality-manager'), (string) $user->user_login, $credential); ?>
                    <?php if (wp_is_application_passwords_available_for_user($user)): // core prints the profile section only then ?>
                    <p><?php
                        printf(
                            /* translators: %s: link to the Application Passwords section of the user's profile page */
                            esc_html__('Password: an Application Password for this user, created in %s. n8n edits posts with it when you run Rewrite, so the user must be allowed to edit posts.', 'post-quality-manager'),
                            '<a href="' . esc_url(admin_url('profile.php#application-passwords-section')) . '">' . esc_html__('Application Passwords on your profile page', 'post-quality-manager') . '</a>'
                        );
                    ?></p>
                    <?php else: ?>
                    <p><?php esc_html_e('Password: Application Passwords are turned off on this site, so none can be created for this user. Usually the site does not run on HTTPS; sometimes a security plugin turns them off. Only Rewrite needs this credential.', 'post-quality-manager'); ?></p>
                    <?php endif; ?>
                </div>
<?php
    }

    /**
     * One value to enter in n8n: a visible label, the value read-only, a
     * Copy button and its aria-live feedback. Classes only, no IDs
     * (pqm-admin.js binds every .pqm-copy-row by class). Rows inside a
     * credential block pass `$credential`: the field and button are then
     * named "<credential>: <label>" for a screen reader, so rows that share
     * a visible label ("Name", "Value") differ.
     */
    private static function render_copy_value(string $label, string $value, string $credential = ''): void
    {
        $a11y_label = $credential === ''
            ? $label
            /* translators: 1: credential name, e.g. "Anthropic - Claude", 2: the field, e.g. "Credential name" or "Value" */
            : sprintf(__('%1$s: %2$s', 'post-quality-manager'), $credential, $label);
?>
        <div class="pqm-copy-row">
            <span class="pqm-copy-label"><?php echo esc_html($label); ?></span>
            <span class="pqm-copy-controls">
                <input type="text" class="regular-text code pqm-copy-field" value="<?php echo esc_attr($value); ?>" readonly
                       aria-label="<?php echo esc_attr($a11y_label); ?>" />
                <button type="button" class="button pqm-copy-button" aria-label="<?php
                    /* translators: %s: what the button copies, e.g. "Anthropic - Claude: Credential name" */
                    echo esc_attr(sprintf(__('Copy %s', 'post-quality-manager'), $a11y_label));
                ?>"><?php esc_html_e('Copy', 'post-quality-manager'); ?></button>
            </span>
            <p class="pqm-copy-feedback" aria-live="polite"></p>
        </div>
<?php
    }

    /**
     * Where the owner opens n8n (decision D3): the stored n8n Instance
     * address, else the saved webhook URL's origin, else '' (no link).
     */
    private static function n8n_editor_url(string $webhook_url): string
    {
        $base = PostQuality_N8n::connection()['base_url'];
        if ($base !== '') {
            return $base;
        }

        return PostQuality_N8n::origin_of($webhook_url);
    }

    /**
     * Serve the bundled n8n workflow template with this site's values injected.
     */
    public static function download_workflow(): void
    {
        if (!current_user_can('postquality_manage_settings')) {
            wp_die(esc_html__('Insufficient permissions.', 'post-quality-manager'));
        }
        check_admin_referer('postquality_download_workflow');

        $json = postquality_workflow_json();
        if ($json === null) {
            wp_die(esc_html__('Workflow template not found. Please reinstall the plugin.', 'post-quality-manager'));
        }

        // The user is about to re-import: the last report no longer says
        // what runs in n8n, so the page shows "not checked yet" until a ping.
        delete_option('postquality_workflow_version_seen');

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
        $warning = postquality_webhook_url_warning($url);
        if ($warning !== '') {
            return new WP_Error('pqm_webhook_test_url', $warning, ['status' => 400]);
        }

        $settings = get_option('postquality_settings', []);
        $settings['n8n_webhook_url'] = $url;
        update_option('postquality_settings', $settings); // updated_option hook reschedules cron.

        return true;
    }

    /**
     * Merge-save only the webhook token, as save_webhook_url() saves the
     * URL: one write of postquality_settings, every other key as stored. Used by the
     * token rotation (n8n provisioning, entry 10) once n8n holds the token.
     */
    public static function save_webhook_token(string $token): void
    {
        $settings = get_option('postquality_settings', []);
        $settings['webhook_token'] = $token;
        update_option('postquality_settings', $settings);
    }

    /**
     * Send a lightweight "ping" command to the n8n webhook. A v5+ Webhook
     * Trigger answers `{"ok":true,"workflow_version":N}`; with an up-to-date
     * import a 2xx then proves that this site reaches n8n and that the
     * trigger accepted the X-PQM-Token header (story 35). An older import
     * answers without a version (n8n's default body): reachability only.
     * Story 2: with an up-to-date import the ping also proves the way
     * back — the workflow's Ping OK sends the item through POST Result to
     * WordPress, which calls `/ping/{id}` back with the token, and this
     * request waits for that call: `ok` only once it landed (a refused or
     * missing callback is a failed test, with the fix in its message).
     * Shared by the AJAX handler and the setup dialog's connect route
     * (story 36); the callers decide whether to record the version.
     *
     * @param bool $diagnose   False: no diagnosis read on n8n, `miswired` null
     *                         (the token rotation's ping, n8n provisioning
     *                         entry 10: its PATCH is the one n8n API call).
     * @param bool $check_gone False: a 404 of the saved URL is never read as a
     *                         gone workflow (story 71, D4) — the pings right
     *                         after provisioning and after an update, which
     *                         just wrote the workflow; a test ping asks.
     * @return array{ok: bool, message: string, version: ?int, outdated: bool, variant?: string, miswired?: ?string, refused?: bool, code?: int, callback?: string}
     *         `version` is the replied workflow version (0 = an older
     *         workflow that does not report it), null when the ping failed.
     *         `outdated`: a 2xx from a workflow older than the bundled
     *         template — connected, but shown as a warning (D2); on the Pro
     *         build also the Free template. `variant` ('free' or 'pro', a
     *         reply without one is the Pro template) and `miswired`
     *         (diagnosed()) come with every 2xx; `refused` (true) with a
     *         401/403, n8n refusing the token; `callback` ('refused' or
     *         'none', story 2) when the ping got through but its callback
     *         did not — the address was right, so no address hint applies.
     */
    public static function ping_webhook(string $url, bool $diagnose = true, bool $check_gone = true): array
    {
        // A test URL would answer while the n8n editor listens and then miss
        // every real run — refuse before sending.
        $warning = postquality_webhook_url_warning($url);
        if ($warning !== '') {
            return ['ok' => false, 'message' => $warning, 'version' => null, 'outdated' => false];
        }

        $ping = self::sent_ping($url, $diagnose);
        // Story 67: a failed test of the saved URL is on record (the page's
        // "needs attention"); a passing one is recorded by the callers, which
        // clears it.
        if (!$ping['ok'] && $url === postquality_get_settings()['n8n_webhook_url']) {
            // Story 71: a 404 of the URL the plugin's own workflow listened on may
            // mean that workflow was deleted in n8n — one read by its id says so.
            if ($check_gone && $diagnose && ($ping['code'] ?? 0) === 404 && PostQuality_N8n::workflow_gone($url)) {
                $ping['message'] = __('The workflow the plugin set up is no longer in n8n. Choose Start over in the n8n connection section, then set n8n up again.', 'post-quality-manager');
            }
            postquality_record_workflow_failure($ping['message']);
        }

        return $ping;
    }

    /**
     * The test ping waiting for n8n's callback (story 2): `{id, at}`,
     * `state` added by PostQuality_REST_API's /ping/{id} route (`received` with the
     * right token, `refused` with another); written before the request,
     * deleted after it on every path. Autoload off.
     */
    public const PING_OPTION = 'postquality_ping';
    /** Seconds the pending ping stays claimable by its callback. */
    public const PING_LIVE = 60;
    /** Seconds sent_ping() waits for the callback (filter `postquality_ping_callback_wait`). */
    public const PING_WAIT = 8;

    /** ping_webhook()'s request and its reading of the answer, around the pending ping its callback may claim. */
    private static function sent_ping(string $url, bool $diagnose = true): array
    {
        $pending = ['id' => wp_generate_password(20, false), 'at' => time()];
        update_option(self::PING_OPTION, $pending, false);
        try {
            return self::ping_reply($url, $pending, $diagnose);
        } finally {
            delete_option(self::PING_OPTION);
        }
    }

    /** The ping's request, carrying the callback's address and token as a review payload does, and its reading of the answer. */
    private static function ping_reply(string $url, array $pending, bool $diagnose): array
    {
        $settings = postquality_get_settings();
        $response = wp_remote_post($url, [
            'headers' => postquality_n8n_headers($settings),
            'body'    => wp_json_encode([
                'command'      => 'ping',
                'source'       => 'pqm-test-connection',
                'callback_url' => rest_url(POSTQUALITY_REST_NAMESPACE . '/ping/' . $pending['id']),
                'auth_token'   => $settings['webhook_token'],
            ]),
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

        if (PostQuality_N8n::is_2xx((int) $code)) {
            // The reply says which workflow answered. Without a version (or
            // not JSON) it is a v1–v4 import, which may ignore the header:
            // its 2xx proves only the reachability.
            $body    = json_decode((string) wp_remote_retrieve_body($response), true);
            $version = is_array($body) ? absint($body['workflow_version'] ?? 0) : 0;
            $variant = is_array($body) && ($body['workflow_variant'] ?? '') === 'free' ? 'free' : 'pro';
            if ($version >= POSTQUALITY_WORKFLOW_VERSION) {
                // Story 2: a current workflow calls this site back; the wait lets the
                // callback claim the pending id (a v7 Free template calls back too,
                // so the wait comes before the variant check: its callback must not
                // meet a deleted id and fail the execution in n8n).
                $wait     = max(0, (int) apply_filters('postquality_ping_callback_wait', self::PING_WAIT));
                $callback = self::awaited_callback($pending, $wait);
                if ( postquality_fs()->is__premium_only() ) {
                    if ($variant === 'free') {
                        return self::diagnosed($diagnose ? $url : null, ['ok' => true, 'message' => __('Ping OK — this site reaches n8n, but your n8n workflow is the Free variant, without the Sources, SEO, Audit and Rewrite branches: download or re-import it on the n8n Workflow page, paste the re-imported workflow\'s production URL, from its Webhook Trigger, as the n8n Webhook URL in Settings, then send another ping.', 'post-quality-manager'), 'version' => $version, 'outdated' => true, 'variant' => $variant]);
                    }
                }
                // What landed decides.
                if ($callback === 'received') {
                    return self::diagnosed($diagnose ? $url : null, ['ok' => true, 'message' => __('Ping OK — this site reaches n8n, the webhook credential is right, your workflow is up to date and n8n called this site back with the right token: the round trip works.', 'post-quality-manager'), 'version' => $version, 'outdated' => false, 'variant' => $variant]);
                }
                if ($callback === 'refused') {
                    return ['ok' => false, 'message' => sprintf(
                        /* translators: %s: the header name, X-PQM-Token */
                        __('n8n reached this site, but the callback was refused: it carried a token that is not this site\'s webhook token. In n8n, open the POST Result to WordPress node and set its %s header to {{ $json.auth_token }}, then test again.', 'post-quality-manager'),
                        PostQuality_N8n::TOKEN_HEADER
                    ), 'version' => null, 'outdated' => false, 'callback' => 'refused'];
                }
                return ['ok' => false, 'message' => sprintf(
                    /* translators: 1: seconds waited, 2: this site's REST API address, e.g. "https://example.com/wp-json/" */
                    __('n8n accepted the ping but did not call this site back within %1$d seconds. n8n must reach %2$s (this site\'s REST API): a local, private or password-protected site cannot be called back. Check the ping\'s execution in n8n, then test again.', 'post-quality-manager'),
                    $wait,
                    rest_url()
                ), 'version' => null, 'outdated' => false, 'callback' => 'none'];
            }
            return self::diagnosed($diagnose ? $url : null, ['ok' => true, 'message' => __('Ping OK — this site reaches n8n, but your n8n workflow is an older version: download or re-import it on the n8n Workflow page, paste the re-imported workflow\'s production URL, from its Webhook Trigger, as the n8n Webhook URL in Settings, then send another ping. A workflow older than version 6 gets a new production URL: from version 6, each site\'s workflow has its own.', 'post-quality-manager'), 'version' => $version, 'outdated' => true, 'variant' => $variant]);
        }

        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => sprintf(
                /* translators: 1: HTTP status code (401 or 403), 2: the header name, X-PQM-Token */
                __('n8n refused the webhook credential (HTTP %1$d). In n8n, open the Webhook Trigger\'s Header Auth credential: its Name must be %2$s and its Value this site\'s webhook token. Then try again.', 'post-quality-manager'),
                $code,
                PostQuality_N8n::TOKEN_HEADER
            ), 'version' => null, 'outdated' => false, 'refused' => true, 'code' => $code];
        }

        if ($code === 404) {
            return ['ok' => false, 'message' => __('n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'post-quality-manager'), 'version' => null, 'outdated' => false, 'code' => $code];
        }

        return ['ok' => false, 'message' => sprintf(
            /* translators: %d: HTTP status code */
            __('n8n responded with HTTP %d. Check your workflow configuration.', 'post-quality-manager'),
            $code
        ), 'version' => null, 'outdated' => false, 'code' => $code];
    }

    /**
     * What the pending ping's callback did while this request waited
     * (story 2): `received`, `refused`, or `none` once $wait seconds are
     * over. One read per 500 ms, the Options cache bypassed as
     * PostQuality_N8n::cache_lock() does: the callback writes the option from
     * n8n's own request, and get_option() would answer this request's
     * cached copy (a miss stays in `notoptions` for the whole request).
     */
    private static function awaited_callback(array $pending, int $wait): string
    {
        $deadline = microtime(true) + $wait;
        while (true) {
            wp_cache_delete(self::PING_OPTION, 'options');
            $notoptions = wp_cache_get('notoptions', 'options');
            if (is_array($notoptions) && isset($notoptions[self::PING_OPTION])) {
                unset($notoptions[self::PING_OPTION]);
                wp_cache_set('notoptions', $notoptions, 'options');
            }
            $stored = get_option(self::PING_OPTION, null);
            $state  = is_array($stored) && ($stored['id'] ?? '') === $pending['id'] ? (string) ($stored['state'] ?? '') : '';
            if ($state === 'received' || $state === 'refused') {
                return $state;
            }
            if (microtime(true) >= $deadline) {
                return 'none';
            }
            usleep(500000);
        }
    }

    /**
     * A passing ping, plus `miswired` (n8n provisioning, entry 7): for the
     * saved webhook URL, PostQuality_N8n::diagnose()'s answer — a sentence, '' or
     * null when the check could not run; null for any other URL (with a
     * setup map, the read would describe the provisioned workflow, not the
     * URL pinged), or for null (no diagnosis asked, entry 10). A sentence
     * becomes the message and the reply a warning (D2); anything else
     * leaves the ping's own reply as it is.
     */
    private static function diagnosed(?string $url, array $ping): array
    {
        $ping['miswired'] = $url === postquality_get_settings()['n8n_webhook_url'] ? PostQuality_N8n::diagnose($url) : null;
        if ($ping['miswired'] !== null && $ping['miswired'] !== '') {
            $ping['message']  = $ping['miswired'];
            $ping['outdated'] = true;
        }

        return $ping;
    }

    /* ─── AJAX: Test Connection ──────────────────────────────────────── */

    /**
     * Ping the n8n webhook and report the outcome. The n8n Workflow page
     * sends no URL (story 67: the field lives on the page and saves through
     * POST /setup/connect), so the saved URL is pinged; a posted URL is
     * still tested as is. Success answers `{message, outdated}`; a failed
     * ping answers an error `{message}`. Both carry `status_html`, the
     * page's whole body with the outcome as its first line, when the saved
     * URL was pinged: only then is the replied version, or the failure,
     * recorded (an unsaved URL may be another workflow). No URL at all:
     * an error string.
     */
    public static function ajax_test_connection(): void
    {
        check_ajax_referer('postquality_admin_action');

        if (!current_user_can('postquality_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        $saved_url = postquality_get_settings()['n8n_webhook_url'];
        $url       = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        if (empty($url)) {
            $url = $saved_url;
        }
        if (empty($url)) {
            wp_send_json_error(__('Save your n8n webhook URL first.', 'post-quality-manager'));
        }

        $ping = self::ping_webhook($url);
        if ($url !== $saved_url) {
            $ping['ok'] ? wp_send_json_success(['message' => $ping['message'], 'outdated' => $ping['outdated']]) : wp_send_json_error($ping['message']);
        }
        if (!$ping['ok']) {
            // The failure is on record (ping_webhook()): the body shows it.
            wp_send_json_error(['message' => $ping['message'], 'status_html' => self::render_workflow_body($ping['message'], 'error')]);
        }

        postquality_record_workflow_version($ping['version'], 'test', $ping['variant'], $ping['miswired'] ?? null);
        wp_send_json_success([
            'message'     => $ping['message'],
            'outdated'    => $ping['outdated'],
            'status_html' => self::render_workflow_body($ping['message'], $ping['outdated'] ? 'warning' : 'success'),
        ]);
    }

    /* ─── AJAX: Workflow status (re-read on tab focus, dialog close) ─── */

    /**
     * Answer the n8n Workflow page's body, `{status_html}`, as it renders
     * now. The open page asks whenever its tab regains focus and whenever
     * the setup dialog closes (story 67), so a download, an import from
     * URL, a command's report or what the dialog did shows without a
     * reload (D1). Reads only — never pings n8n.
     */
    public static function ajax_workflow_status(): void
    {
        check_ajax_referer('postquality_admin_action');

        if (!current_user_can('postquality_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        wp_send_json_success(['status_html' => self::render_workflow_body()]);
    }
}
