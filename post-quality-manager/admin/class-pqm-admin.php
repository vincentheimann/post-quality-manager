<?php
/**
 * Admin pages for the Post Quality Manager plugin.
 *
 * Registers four sub-pages under a "Content Quality" top-level menu:
 * 1. Dashboard — stat cards linking into Post Reviews, state-aware CTAs
 * 2. Post Reviews — post table with scores, filters, per-post actions
 * 3. n8n Workflow — setup wizard, download/import, request preview, ping
 * 4. Settings — connection, AI model, prompts & context, cron
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQM_Admin
{

    /**
     * Hook suffix of the Post Reviews submenu page, captured at registration
     * so the enqueue gate never depends on the translated menu title
     * (e.g. 'content-quality_page_pqm-posts' on an English site).
     */
    private static ?string $posts_hook = null;

    /* ─── Menu Registration ──────────────────────────────────────────── */

    public static function register_pages(): void
    {
        add_menu_page(
            __('Content Quality', 'post-quality-manager'),
            __('Content Quality', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard'],
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'pqm-dashboard',
            __('Dashboard', 'post-quality-manager'),
            __('Dashboard', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard']
        );

        // Story 5.1: the React shell renders this page; the legacy
        // render_posts() stays in place but unreachable until story 5.7.
        self::$posts_hook = add_submenu_page(
            'pqm-dashboard',
            __('Post Reviews', 'post-quality-manager'),
            __('Post Reviews', 'post-quality-manager'),
            'pqm_view_reviews',
            'pqm-posts',
        [__CLASS__, 'render_posts_app']
        ) ?: null;

        add_submenu_page(
            'pqm-dashboard',
            __('n8n Workflow', 'post-quality-manager'),
            __('n8n Workflow', 'post-quality-manager'),
            'pqm_manage_settings',
            'pqm-workflow',
        [__CLASS__, 'render_workflow']
        );

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

        // The n8n Workflow page hosts the setup wizard — no extra banner there.
        if (strpos($screen->id, 'pqm-workflow') !== false) {
            return;
        }

        // The banner routes to the Admin-only Workflow page — only users who
        // can act on it (pqm_manage_settings) should see it.
        if (!current_user_can('pqm_manage_settings')) {
            return;
        }

        $settings = pqm_get_settings();
        if (empty($settings['n8n_webhook_url'])) {
            $workflow_url = admin_url('admin.php?page=pqm-workflow');
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Welcome to Post Quality Manager! To start reviewing posts, set up the n8n workflow first.', 'post-quality-manager'),
                esc_url($workflow_url),
                esc_html__('Open the n8n Workflow page', 'post-quality-manager')
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

        wp_enqueue_style(
            'pqm-admin',
            PQM_PLUGIN_URL . 'admin/css/pqm-admin.css',
        [],
            filemtime(PQM_PLUGIN_DIR . 'admin/css/pqm-admin.css')
        );

        // Post Reviews runs the React app (story 5.1): built bundle with
        // core-served dependencies. The legacy pqm-admin JS stays off this
        // hook so the old string-built render path cannot run; the shared
        // CSS above still applies (badges, pills).
        if ($hook === self::$posts_hook) {
            self::enqueue_posts_app();
            return;
        }

        wp_enqueue_script(
            'pqm-admin',
            PQM_PLUGIN_URL . 'admin/js/pqm-admin.js',
        [],
            filemtime(PQM_PLUGIN_DIR . 'admin/js/pqm-admin.js'),
            true
        );

        $settings = pqm_get_settings();

        wp_localize_script('pqm-admin', 'pqmAdmin', [
            'restUrl' => rest_url(PQM_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
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
            'gatedCommands' => PQM_Features::gated_commands(),
            'hiddenCommands' => PQM_Features::hidden_commands(),
            'quota' => PQM_Features::quota_state(),
            'i18n' => [
                'lockedTitle' => __('Available with Pro. Upgrade to unlock this agent.', 'post-quality-manager'),
                /* translators: 1: remaining AI actions, 2: monthly limit */
                'quotaNote' => __('%1$d of %2$d AI actions left this month', 'post-quality-manager'),
                'enterWebhookUrl' => __('Enter your n8n webhook URL first.', 'post-quality-manager'),
                'testing' => __('Sending test ping…', 'post-quality-manager'),
                'saving' => __('Saving…', 'post-quality-manager'),
                'savedPingFailed' => __('URL saved, but the ping did not get through.', 'post-quality-manager'),
                'networkError' => __('Network error. Please try again.', 'post-quality-manager'),
                'copied' => __('Copied! Paste it in n8n via "Import from URL…".', 'post-quality-manager'),
                'copyFailed' => __('Could not copy automatically. The URL is selected — press Ctrl+C.', 'post-quality-manager'),
            ],
        ]);
    }

    /**
     * Enqueue the built Post Reviews app. Dependencies and version come from
     * the dependency-extraction manifest (build/index.asset.php): React,
     * @wordpress/components, api-fetch and i18n all load from WordPress core
     * — the bundle ships no runtime. Missing build output is handled by
     * render_posts_app(), which shows the build instructions instead.
     */
    private static function enqueue_posts_app(): void
    {
        $asset_path = PQM_PLUGIN_DIR . 'admin/build/index.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        $version = $asset['version'] ?? PQM_VERSION;

        wp_enqueue_script(
            'pqm-post-reviews',
            PQM_PLUGIN_URL . 'admin/build/index.js',
            $asset['dependencies'] ?? [],
            $version,
            true
        );
        wp_set_script_translations('pqm-post-reviews', 'post-quality-manager', PQM_PLUGIN_DIR . 'languages');

        // @wordpress/components styling is served by core, not the bundle.
        wp_enqueue_style('wp-components');

        // Kit styles (story 5.2): wp-scripts extracts src/kit/style.css
        // into the build. Versioned from the asset manifest, no filemtime.
        if (file_exists(PQM_PLUGIN_DIR . 'admin/build/style-index.css')) {
            wp_enqueue_style(
                'pqm-post-reviews',
                PQM_PLUGIN_URL . 'admin/build/style-index.css',
                ['wp-components'],
                $version
            );
            // wp-scripts also emits style-index-rtl.css; WP swaps it in on RTL locales.
            wp_style_add_data('pqm-post-reviews', 'rtl', 'replace');
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

        if (PQM_Features::is_pro()) {
            // Plan-gated sections: Automated Reviews + Agent-Specific Overrides.
            $clean['cron_enabled'] = !empty($input['cron_enabled']);
            $clean['cron_frequency'] = in_array($input['cron_frequency'] ?? '', ['daily', 'weekly'], true)
                ? $input['cron_frequency'] : $defaults['cron_frequency'];
            $clean['cron_batch_size'] = absint($input['cron_batch_size'] ?? $defaults['cron_batch_size']);
            $clean['cron_min_score'] = absint($input['cron_min_score'] ?? $defaults['cron_min_score']);
            $clean['cron_max_age_days'] = absint($input['cron_max_age_days'] ?? $defaults['cron_max_age_days']);
            $clean['cron_command'] = $defaults['cron_command'];

            $effort_levels = ['low', 'medium', 'high', 'xhigh', 'max'];
            // Locked knobs (model doesn't support them) render disabled and are absent
            // from the POST — fall back to the stored value, not the default.
            $clean['agent_prompts'] = [];
            foreach (array_keys($defaults['agent_prompts']) as $agent) {
                if (isset($input['agent_prompts'][$agent])) {
                    $agent_input = $input['agent_prompts'][$agent];
                    $stored      = $existing['agent_prompts'][$agent] ?? $defaults['agent_prompts'][$agent];
                    $clean['agent_prompts'][$agent] = [
                        'instructions' => sanitize_textarea_field($agent_input['instructions'] ?? ''),
                        'temperature'  => (float) ($agent_input['temperature'] ?? $stored['temperature']),
                        'model'        => in_array($agent_input['model'] ?? '', $registry_keys, true) ? $agent_input['model'] : '',
                        'effort'       => in_array($agent_input['effort'] ?? '', $effort_levels, true)
                            ? $agent_input['effort'] : $stored['effort'],
                    ];
                }
            }
        }
        else {
            // On a free site both sections render inside a disabled fieldset, so
            // nothing of them POSTs — carry the stored values wholesale (never
            // per field: an absent cron_enabled must not read as "unchecked").
            $cron_keys = ['cron_enabled', 'cron_frequency', 'cron_batch_size', 'cron_min_score', 'cron_max_age_days', 'cron_command'];
            foreach ($cron_keys as $key) {
                $clean[$key] = $existing[$key];
            }
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

    /* ─── Page: Dashboard ────────────────────────────────────────────── */

    public static function render_dashboard(): void
    {
        $settings  = pqm_get_settings();
        $stats     = PQM_DB::get_dashboard_stats();
        $posts_url = admin_url('admin.php?page=pqm-posts');

        // One-click dismissal of the scheduled-reviews nudge (persisted).
        if (isset($_GET['pqm_dismiss_cron_nudge'])
            && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'pqm_dismiss_cron_nudge')
            && current_user_can('pqm_manage_settings')
        ) {
            update_option('pqm_cron_nudge_dismissed', 1, false);
        }
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Dashboard', 'post-quality-manager'); ?></h1>

            <?php if (empty($settings['n8n_webhook_url'])): ?>
            <!-- State: not connected yet — route to setup, hide meaningless zeros. -->
            <div class="pqm-dashboard-cta">
                <h2><?php esc_html_e('Connect n8n to start reviewing', 'post-quality-manager'); ?></h2>
                <p><?php esc_html_e('Import the pre-configured workflow into n8n and connect it to this site — about 10 minutes, one time.', 'post-quality-manager'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pqm-workflow')); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Set up the n8n workflow', 'post-quality-manager'); ?>
                </a>
            </div>
        </div>
            <?php
                return; // Stat cards would only show zeros.
            endif;

            $needs_attention = ($stats['distribution']['needs_revision'] ?? 0) + ($stats['distribution']['major_revision'] ?? 0);
            $ready_to_ship   = $stats['distribution']['ship'] ?? 0;

            if ((int) $stats['reviewed'] === 0):
            ?>
            <!-- State: connected, nothing reviewed yet — prompt the first review. -->
            <div class="pqm-dashboard-cta">
                <h2><?php esc_html_e('You are all set — run your first review', 'post-quality-manager'); ?></h2>
                <p><?php esc_html_e('Pick a post and trigger a review. The score, issues, and suggested edits land back here in about a minute.', 'post-quality-manager'); ?></p>
                <a href="<?php echo esc_url($posts_url); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Run your first review', 'post-quality-manager'); ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Stat Cards — each links to the posts they count. -->
            <div class="pqm-stats-grid">
                <a class="pqm-stat-card" href="<?php echo esc_url($posts_url); ?>">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['avg_score']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('The overall average quality score across all analyzed posts.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Average Score', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </a>
                <a class="pqm-stat-card" href="<?php echo esc_url($posts_url); ?>">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['reviewed']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total posts that have been processed by the quality manager at least once.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Analyzed Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </a>
                <a class="pqm-stat-card" href="<?php echo esc_url(add_query_arg('filter', 'needs_review', $posts_url)); ?>">
                    <span class="pqm-stat-number"><?php echo esc_html($needs_attention); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Posts requiring manual intervention or major revisions to meet quality standards.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Needs Attention', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </a>
                <a class="pqm-stat-card" href="<?php echo esc_url($posts_url); ?>">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['total_posts']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total published and pending posts on your site.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Total Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </a>
            </div>

            <?php
            // Nudge toward the habit loop: first result is in, cron still off.
            // Pro only — on a free site the Automated Reviews section is locked.
            $show_cron_nudge = (int) $stats['reviewed'] > 0
                && empty($settings['cron_enabled'])
                && !get_option('pqm_cron_nudge_dismissed')
                && current_user_can('pqm_manage_settings')
                && PQM_Features::is_pro();
            if ($show_cron_nudge):
                $cron_url    = admin_url('admin.php?page=pqm-settings#pqm-cron-settings');
                $dismiss_url = wp_nonce_url(add_query_arg('pqm_dismiss_cron_nudge', '1'), 'pqm_dismiss_cron_nudge');
            ?>
            <div class="notice notice-info inline pqm-inline-notice">
                <p>
                    <strong><?php esc_html_e('Let reviews run themselves.', 'post-quality-manager'); ?></strong>
                    <?php esc_html_e('Scheduled reviews keep scoring your back catalog automatically — you pick the frequency and batch size.', 'post-quality-manager'); ?>
                    <a href="<?php echo esc_url($cron_url); ?>"><?php esc_html_e('Enable scheduled reviews', 'post-quality-manager'); ?></a>
                    &nbsp;·&nbsp;
                    <a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'post-quality-manager'); ?></a>
                </p>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /* ─── Page: Post Reviews (React shell, story 5.1) ────────────────── */

    /**
     * PHP shell for the React Post Reviews app: heading + root div only —
     * every row, badge and pill renders client-side from REST data
     * (ADR 0005, one render source). On a fresh clone without built assets
     * the page explains the build commands instead of staying blank.
     */
    public static function render_posts_app(): void
    {
        $built = file_exists(PQM_PLUGIN_DIR . 'admin/build/index.asset.php');
        ?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Post Reviews', 'post-quality-manager'); ?></h1>
            <?php if (!$built): ?>
                <div class="notice notice-warning">
                    <p><?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: npm install command, 2: npm run build command */
                            __('The Post Reviews app has not been built yet. Run %1$s and then %2$s in the repository root, then reload this page.', 'post-quality-manager'),
                            '<code>npm install</code>',
                            '<code>npm run build</code>'
                        ),
                        ['code' => []]
                    );
                    ?></p>
                </div>
            <?php elseif (!wp_script_is('react-jsx-runtime', 'registered')): ?>
                <?php // The bundle depends on core's react-jsx-runtime handle (WP 6.6+);
                      // older cores silently drop the script, so explain instead of a blank div. ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Post Reviews needs WordPress 6.6 or newer to render. Update WordPress, then reload this page.', 'post-quality-manager'); ?></p>
                </div>
            <?php else: ?>
                <div id="pqm-post-reviews-root"></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ─── Page: Post Reviews (legacy, unreachable — removal is story 5.7) ── */

    public static function render_posts(): void
    {
        // Handle trigger-review action.
        if (isset($_GET['pqm_action'], $_GET['post_id'], $_GET['_wpnonce'])
        && $_GET['pqm_action'] === 'trigger'
        && wp_verify_nonce($_GET['_wpnonce'], 'pqm_trigger_' . $_GET['post_id'])
        ) {
            $post_id = absint($_GET['post_id']);
            $command = sanitize_key($_GET['command'] ?? 'review');
            $settings = pqm_get_settings();

            if (!empty($settings['n8n_webhook_url'])) {
                // Re-use the REST trigger logic.
                $request = new WP_REST_Request('POST', '/' . PQM_REST_NAMESPACE . '/trigger-review');
                $request->set_param('post_id', $post_id);
                $request->set_param('command', $command);
                $response = PQM_REST_API::trigger_review_callback($request);

                if (is_wp_error($response)) {
                    $notice_class = 'notice-error';
                    $notice_message = $response->get_error_message();
                }
                else {
                    $notice_class = 'notice-success';
                    $notice_message = sprintf(
                        /* translators: %s: post title */
                        __('Review triggered for "%s". Results will appear here shortly.', 'post-quality-manager'),
                        get_the_title($post_id)
                    );
                    // The localized pqmAdmin.quota snapshot was captured on
                    // admin_enqueue_scripts, before this trigger consumed an
                    // action — refresh it so JS-rendered notes match the PHP
                    // one (the script prints in the footer, so inline data
                    // added here still lands).
                    wp_add_inline_script(
                        'pqm-admin',
                        'pqmAdmin.quota = ' . wp_json_encode(PQM_Features::quota_state()) . ';',
                        'after'
                    );
                }
            }
            else {
                $notice_class = 'notice-error';
                $notice_message = sprintf(
                    /* translators: %s: link to the n8n Workflow page */
                    __('n8n is not connected yet. Set it up on the %s page.', 'post-quality-manager'),
                    '<a href="' . esc_url(admin_url('admin.php?page=pqm-workflow')) . '">' . esc_html__('n8n Workflow', 'post-quality-manager') . '</a>'
                );
            }

            echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . wp_kses_post($notice_message) . '</p></div>';
        }

        // Pagination & filters.
        $current_page = absint($_GET['paged'] ?? 1);
        $status_filter = sanitize_key($_GET['review_status'] ?? '');
        $filter = sanitize_key($_GET['filter'] ?? 'all');
        $orderby = sanitize_key($_GET['orderby'] ?? 'reviewed_at');
        $order = strtoupper(sanitize_key($_GET['order'] ?? 'DESC'));

        $result = PQM_DB::get_all_posts_with_scores([
            'per_page' => 20,
            'page' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'status' => $status_filter,
            'filter' => $filter,
        ]);

        $items = $result['items'];
        $total = $result['total'];
        $pages = $result['pages'];

        // Get command states for all posts on this page.
        $post_ids = array_column($items, 'post_id');
        $commands = PQM_DB::get_commands_for_posts($post_ids);

        // Yoast column only exists while Yoast SEO is active.
        $yoast_active = defined('WPSEO_VERSION');
        if ($yoast_active && !empty($post_ids)) {
            update_postmeta_cache($post_ids); // one query for all rows instead of one per badge
        }
        $colspan = $yoast_active ? 7 : 6;

        // Role-gated actions are hidden, not locked (review is free, so only the role can refuse it).
        $can_review = PQM_Features::can('review');

        // Progress chips: canonical order + labels; only commands that ran get a chip.
        $cmd_order  = ['review', 'takeaways', 'sources', 'seo', 'audit', 'rewrite'];
        $cmd_labels = [
            'review'    => __('Review', 'post-quality-manager'),
            'takeaways' => __('Takeaways', 'post-quality-manager'),
            'sources'   => __('Sources', 'post-quality-manager'),
            'seo'       => __('SEO', 'post-quality-manager'),
            'audit'     => __('Audit', 'post-quality-manager'),
            'rewrite'   => __('Rewrite', 'post-quality-manager'),
        ];

?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Post Reviews', 'post-quality-manager'); ?></h1>

            <!-- Filters -->
            <div class="pqm-filters">
                <a href="<?php echo esc_url(admin_url('admin.php?page=pqm-posts&filter=all')); ?>"
                   class="button <?php echo $filter !== 'needs_review' ? 'button-primary' : ''; ?>">
                    <?php esc_html_e('All Posts', 'post-quality-manager'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pqm-posts&filter=needs_review')); ?>"
                   class="button <?php echo $filter === 'needs_review' ? 'button-primary' : ''; ?>">
                    <?php esc_html_e('Needs Review', 'post-quality-manager'); ?>
                </a>
                <?php $quota = PQM_Features::quota_state(); ?>
                <?php if ($quota !== null): ?>
                    <span class="pqm-quota-note"><?php echo esc_html(sprintf(
                        /* translators: 1: remaining AI actions, 2: monthly limit */
                        __('%1$d of %2$d AI actions left this month', 'post-quality-manager'),
                        $quota['remaining'],
                        $quota['limit']
                    )); ?></span>
                <?php endif; ?>
            </div>

            <table class="widefat fixed striped pqm-posts-table">
                <thead>
                    <tr>
                        <th class="pqm-col-title">
                            <?php echo self::sortable_header('title', __('Post', 'post-quality-manager'), $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </th>
                        <th class="pqm-col-score">
                            <?php echo self::sortable_header('score', __('Score', 'post-quality-manager'), $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </th>
                        <?php if ($yoast_active): ?>
                        <th class="pqm-col-yoast"><?php esc_html_e('Yoast SEO', 'post-quality-manager'); ?></th>
                        <?php endif; ?>
                        <th class="pqm-col-status"><?php esc_html_e('Status', 'post-quality-manager'); ?></th>
                        <th class="pqm-col-progress"><?php esc_html_e('Progress', 'post-quality-manager'); ?></th>
                        <th class="pqm-col-date">
                            <?php echo self::sortable_header('reviewed_at', __('Last Reviewed', 'post-quality-manager'), $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </th>
                        <th class="pqm-col-actions"><?php esc_html_e('Actions', 'post-quality-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="<?php echo esc_attr($colspan); ?>"><?php esc_html_e('No posts found.', 'post-quality-manager'); ?></td></tr>
                    <?php
        else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr class="pqm-post-row" data-post-id="<?php echo esc_attr($item['post_id']); ?>">
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($item['post_id'])); ?>">
                                        <?php echo esc_html($item['title']); ?>
                                    </a>
                                    <?php if (($item['wp_post_status'] ?? '') === 'pending'): ?>
                                        — <span class="post-state pqm-post-state"><?php esc_html_e('Pending', 'post-quality-manager'); ?></span>
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td class="pqm-cell-score">
                                <?php if ($item['latest_score'] !== null): ?>
                                    <span class="pqm-score-badge pqm-score-<?php echo esc_attr(self::score_class((int)$item['latest_score'])); ?>">
                                        <?php echo esc_html($item['latest_score']); ?>
                                    </span>
                                <?php
                else: ?>
                                    <span class="pqm-score-badge pqm-score-none">—</span>
                                <?php
                endif; ?>
                            </td>
                            <?php if ($yoast_active): ?>
                            <td class="pqm-cell-yoast">
                                <?php self::render_yoast_badges((int)$item['post_id']); ?>
                            </td>
                            <?php endif; ?>
                            <td class="pqm-cell-status">
                                <?php echo esc_html(self::format_status($item['latest_status'] ?? '')); ?>
                            </td>
                            <td class="pqm-cell-progress">
                                <?php
                $cmds = $commands[$item['post_id']] ?? [];
                $has_rev = in_array('review', $cmds, true);
                // Chips show what actually ran (canonical order, unknown commands appended).
                $ran = array_merge(array_values(array_intersect($cmd_order, $cmds)), array_values(array_diff($cmds, $cmd_order)));
?>
                                <div class="pqm-pills">
                                    <?php if (empty($ran)): ?>
                                        <span class="pqm-pill pqm-pill-pending">— <?php esc_html_e('Not analyzed', 'post-quality-manager'); ?></span>
                                    <?php
                else: foreach ($ran as $cmd): ?>
                                        <span class="pqm-pill pqm-pill-done">✓ <?php echo esc_html($cmd_labels[$cmd] ?? ucfirst($cmd)); ?></span>
                                    <?php
                    endforeach;
                endif; ?>
                                </div>
                            </td>
                            <td class="pqm-cell-date">
                                <?php echo $item['latest_reviewed'] ? esc_html(self::format_date($item['latest_reviewed'])) : '<em>' . esc_html__('Never', 'post-quality-manager') . '</em>'; ?>
                            </td>
                            <td class="pqm-cell-actions">
                                <?php if (!$has_rev): ?>
                                    <?php if ($can_review): ?>
                                    <button type="button" class="button button-primary button-small"
                                            data-post-id="<?php echo esc_attr($item['post_id']); ?>" data-pqm-cmd="review">
                                        <?php esc_html_e('Run Review', 'post-quality-manager'); ?>
                                    </button>
                                    <?php endif; ?>
                                <?php
                else: ?>
                                    <button type="button" class="button button-small pqm-detail-toggle"
                                            data-post-id="<?php echo esc_attr($item['post_id']); ?>">
                                        <?php esc_html_e('Details', 'post-quality-manager'); ?>
                                    </button>
                                <?php
                endif; ?>
                            </td>
                        </tr>
                        <!-- Expandable detail row -->
                        <tr class="pqm-detail-row pqm-hidden" id="pqm-detail-<?php echo esc_attr($item['post_id']); ?>">
                            <td colspan="<?php echo esc_attr($colspan); ?>">
                                <div class="pqm-detail-content" data-post-id="<?php echo esc_attr($item['post_id']); ?>">
                                    <p class="description"><?php esc_html_e('Loading...', 'post-quality-manager'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <?php
            endforeach; ?>
                    <?php
        endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
            echo paginate_links([ // phpcs:ignore WordPress.Security.EscapeOutput
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'total' => $pages,
                'current' => $current_page,
            ]);
?>
                </div>
            </div>
            <?php
        endif; ?>
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
        // without pqm_manage_settings; the paid sections (Agent-Specific
        // Overrides, Automated Reviews) render visible but locked on a free
        // site. Role-hidden beats plan-locked, mirroring PQM_Features::why_not().
        $can_manage_settings = current_user_can('pqm_manage_settings');
        $is_pro = PQM_Features::is_pro();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Settings', 'post-quality-manager'); ?></h1>

            <?php if (!empty($_GET['settings-updated'])): ?>
            <!-- Custom save notice: settings ride the webhook payload, so no n8n re-import is ever needed. -->
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved — they apply on the next command. No n8n re-import needed.', 'post-quality-manager'); ?></p>
            </div>
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
                            <p class="description"><?php esc_html_e('Shared secret — n8n must send this in the X-PQM-Token header when posting results.', 'post-quality-manager'); ?></p>
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
                <h2 id="pqm-cron-settings" class="pqm-section-gap"><?php
                    esc_html_e('Automated Reviews (Cron)', 'post-quality-manager');
                    if (!$is_pro): ?> <span class="dashicons dashicons-lock pqm-section-lock" aria-hidden="true"></span><?php endif;
                ?></h2>
                <?php if (!$is_pro): ?>
                <p class="description pqm-locked-note"><?php esc_html_e('Available with Pro. Upgrade to let reviews run on a schedule.', 'post-quality-manager'); ?></p>
                <fieldset disabled class="pqm-locked-section">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Enable', 'post-quality-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pqm_settings[cron_enabled]" value="1"
                                       <?php checked($settings['cron_enabled']); ?> />
                                <?php esc_html_e('Automatically review posts on a schedule', 'post-quality-manager'); ?>
                            </label>
                        </td>
                    </tr>
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
                            <p class="description"><?php esc_html_e('Number of posts to review per cron run.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_command"><?php esc_html_e('Command', 'post-quality-manager'); ?></label></th>
                        <td>
                            <select id="pqm_cron_command" name="pqm_settings[cron_command]" disabled>
                                <option value="review" selected>review</option>
                            </select>
                            <p class="description"><?php esc_html_e('Only "review" is available in this version.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_min_score"><?php esc_html_e('Min Score Threshold', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_min_score" name="pqm_settings[cron_min_score]"
                                   value="<?php echo esc_attr($settings['cron_min_score']); ?>"
                                   min="0" max="100" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-review posts scoring below this value.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_cron_max_age_days"><?php esc_html_e('Max Review Age (days)', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="number" id="pqm_cron_max_age_days" name="pqm_settings[cron_max_age_days]"
                                   value="<?php echo esc_attr($settings['cron_max_age_days']); ?>"
                                   min="1" max="365" class="small-text" />
                            <p class="description"><?php esc_html_e('Re-review posts whose last review is older than this.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php if (!$is_pro): ?></fieldset><?php endif; ?>

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
     * Agent keys => human labels, shared by the settings page and the
     * workflow configuration preview.
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
     * Everything about generating and connecting the n8n workflow:
     * download / import URL, per-agent request preview, template version
     * status, and the connection test.
     */
    public static function render_workflow(): void
    {
        $settings     = pqm_get_settings();
        $download_url = wp_nonce_url(admin_url('admin-post.php?action=pqm_download_workflow'), 'pqm_download_workflow');
        $import_url   = add_query_arg('token', rawurlencode($settings['webhook_token']), rest_url(PQM_REST_NAMESPACE . '/workflow'));
        $settings_url = admin_url('admin.php?page=pqm-settings');
        $seen_version = (int) get_option('pqm_workflow_version_seen', 0);
        $registry     = pqm_model_registry();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('n8n Workflow', 'post-quality-manager'); ?></h1>

            <?php if (empty($settings['n8n_webhook_url'])): ?>
            <!-- Setup wizard: shown only until the connection is configured. -->
            <div class="pqm-wizard" id="pqm-wizard">
                <h2 class="pqm-wizard-title"><?php esc_html_e('Set up your n8n connection', 'post-quality-manager'); ?></h2>
                <ol class="pqm-wizard-steps">
                    <li class="pqm-wizard-dot is-active" data-step="1"><span>1</span> <?php esc_html_e('Download', 'post-quality-manager'); ?></li>
                    <li class="pqm-wizard-dot" data-step="2"><span>2</span> <?php esc_html_e('Import', 'post-quality-manager'); ?></li>
                    <li class="pqm-wizard-dot" data-step="3"><span>3</span> <?php esc_html_e('Connect & test', 'post-quality-manager'); ?></li>
                    <li class="pqm-wizard-dot" data-step="4"><span>4</span> <?php esc_html_e('First review', 'post-quality-manager'); ?></li>
                </ol>

                <div class="pqm-wizard-step is-active" data-step="1">
                    <p><?php esc_html_e('Download the review workflow pre-configured for this site.', 'post-quality-manager'); ?></p>
                    <a href="<?php echo esc_url($download_url); ?>" class="button button-primary">
                        <?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?>
                    </a>
                    <?php self::render_import_url($import_url); ?>
                </div>

                <div class="pqm-wizard-step" data-step="2">
                    <p><?php esc_html_e('In your n8n instance, import the downloaded file and connect two credentials:', 'post-quality-manager'); ?></p>
                    <ul class="pqm-wizard-list">
                        <li><?php esc_html_e('WordPress — an Application Password for a user who can edit posts.', 'post-quality-manager'); ?></li>
                        <li><?php esc_html_e('Claude — your Anthropic API key.', 'post-quality-manager'); ?></li>
                    </ul>
                    <p><?php esc_html_e('Then activate the workflow in n8n.', 'post-quality-manager'); ?></p>
                </div>

                <div class="pqm-wizard-step" data-step="3">
                    <p><?php esc_html_e('In n8n, copy the workflow\'s production webhook URL and paste it here:', 'post-quality-manager'); ?></p>
                    <div class="pqm-wizard-connect">
                        <input type="url" id="pqm_wizard_webhook_url" class="regular-text"
                               placeholder="https://your-n8n.example.com/webhook/pqm-execute"
                               aria-label="<?php esc_attr_e('n8n webhook URL', 'post-quality-manager'); ?>" />
                        <button type="button" class="button button-primary pqm-wizard-save-test">
                            <?php esc_html_e('Save & test', 'post-quality-manager'); ?>
                        </button>
                    </div>
                    <p class="description"><?php esc_html_e('Saves the URL and sends a test ping. The ping verifies WordPress → n8n; your first review confirms the return path.', 'post-quality-manager'); ?></p>
                    <span class="pqm-test-result" aria-live="polite"></span>
                </div>

                <div class="pqm-wizard-step" data-step="4">
                    <p><?php esc_html_e('Setup complete. Run your first review on a real post — the score, issues, and suggested edits land right in the list.', 'post-quality-manager'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pqm-posts')); ?>" class="button button-primary">
                        <?php esc_html_e('Run your first review', 'post-quality-manager'); ?>
                    </a>
                </div>

                <div class="pqm-wizard-nav">
                    <button type="button" class="button pqm-wizard-back" disabled><?php esc_html_e('Back', 'post-quality-manager'); ?></button>
                    <button type="button" class="button button-primary pqm-wizard-next"><?php esc_html_e('Next', 'post-quality-manager'); ?></button>
                </div>
            </div>
            <?php else: // Connected — show the maintenance sections the wizard already covers. ?>

            <?php if ($seen_version > 0 && $seen_version < PQM_WORKFLOW_VERSION): ?>
            <div class="notice notice-warning inline pqm-inline-notice">
                <p><strong><?php esc_html_e('Workflow update available.', 'post-quality-manager'); ?></strong>
                <?php esc_html_e('Your n8n instance runs an older version of the workflow. Download or re-import it below to get the latest pipeline — your settings and credentials are untouched.', 'post-quality-manager'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Workflow file -->
            <h2><?php esc_html_e('Workflow File', 'post-quality-manager'); ?></h2>
            <p class="description"><?php esc_html_e('The review workflow tailored to this site, ready to import into n8n. After importing, connect your WordPress and Claude credentials.', 'post-quality-manager'); ?></p>
            <p>
                <a href="<?php echo esc_url($download_url); ?>" class="button button-primary">
                    <?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?>
                </a>
            </p>
            <?php self::render_import_url($import_url); ?>
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
            <?php endif; ?>
        </div>
<?php
    }

    /* ─── Workflow Generator (admin-post download) ───────────────────── */

    /**
     * "Or import from URL" block shown under each workflow download button.
     * Rendered twice on the settings page, so classes only — no IDs.
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
            <p class="description"><?php esc_html_e('In n8n, open the workflow menu, choose "Import from URL…" and paste this link. It includes your webhook token — keep it private.', 'post-quality-manager'); ?></p>
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

    /* ─── AJAX: Save Webhook URL (wizard) ────────────────────────────── */

    /**
     * Merge-save only the webhook URL so wizard step 3 completes on one page.
     * Reads the stored option and updates a single key — the Settings form's
     * full-array sanitize contract and the form-free Workflow page both stay intact.
     */
    public static function ajax_save_webhook_url(): void
    {
        check_ajax_referer('pqm_admin_action');

        if (!current_user_can('pqm_manage_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        $url = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        if (empty($url)) {
            wp_send_json_error(__('Enter your n8n webhook URL first.', 'post-quality-manager'));
        }

        $settings = get_option('pqm_settings', []);
        $settings['n8n_webhook_url'] = $url;
        update_option('pqm_settings', $settings); // updated_option hook reschedules cron.

        wp_send_json_success();
    }

    /* ─── AJAX: Test Connection ──────────────────────────────────────── */

    /**
     * Send a lightweight "ping" command to the n8n webhook and report the outcome.
     * Tests the URL from the settings field so it works before saving.
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

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['command' => 'ping', 'source' => 'pqm-test-connection']),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(sprintf(
                /* translators: %s: connection error message */
                __('Could not connect to n8n: %s', 'post-quality-manager'),
                $response->get_error_message()
            ));
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            wp_send_json_success(__('Ping OK — WordPress can reach n8n. Your first review confirms the return path.', 'post-quality-manager'));
        }

        if ($code === 404) {
            wp_send_json_error(__('n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'post-quality-manager'));
        }

        wp_send_json_error(sprintf(
            /* translators: %d: HTTP status code */
            __('n8n responded with HTTP %d. Check your workflow configuration.', 'post-quality-manager'),
            $code
        ));
    }

    /* ─── AJAX: Trigger Command ──────────────────────────────────────── */

    public static function ajax_trigger_command(): void
    {
        check_ajax_referer('pqm_admin_action');

        // Same route gate as POST /trigger-review; the per-command decision
        // happens in trigger_review_callback() below.
        if (!PQM_REST_API::check_can_trigger()) {
            wp_send_json_error(__('Insufficient permissions.', 'post-quality-manager'), 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $command = sanitize_key($_POST['command'] ?? 'review');

        if (!$post_id) {
            wp_send_json_error('Invalid post ID.');
        }

        $request = new WP_REST_Request('POST', '/' . PQM_REST_NAMESPACE . '/trigger-review');
        $request->set_param('post_id', $post_id);
        $request->set_param('command', $command);
        $response = PQM_REST_API::trigger_review_callback($request);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message(), (int) ($response->get_error_data()['status'] ?? 400));
        }

        // The 'processing' placeholder row is written by the REST callback
        // itself on an n8n 2xx (story 5.4) — one write path for every caller.
        wp_send_json_success([
            'message' => __('Command triggered in n8n.', 'post-quality-manager'),
            // Post-trigger remaining count from the REST callback; null for exempt/unlimited.
            'quota_remaining' => $response->get_data()['quota_remaining'] ?? null,
        ]);
    }

    /* ─── Helpers ─────────────────────────────────────────────────────── */

    /**
     * Yoast SEO + readability badges for one Post Reviews row.
     * Yoast's own quality buckets (>70 good, >40 ok) line up with
     * score_class(), so the badges reuse the existing score colors.
     */
    private static function render_yoast_badges(int $post_id): void
    {
        $seo  = get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
        $read = get_post_meta($post_id, '_yoast_wpseo_content_score', true);

        if ($seo === '' && $read === '') {
            echo '<span class="pqm-score-badge pqm-score-none" title="'
                . esc_attr__('No Yoast analysis yet. Open the post in the editor to generate one.', 'post-quality-manager')
                . '">—</span>';
            return;
        }

        self::yoast_badge(__('SEO', 'post-quality-manager'), $seo, __('Yoast SEO score', 'post-quality-manager'));
        self::yoast_badge(__('Readability', 'post-quality-manager'), $read, __('Yoast readability score', 'post-quality-manager'));
    }

    private static function yoast_badge(string $label, string $value, string $title): void
    {
        $class = $value === '' ? 'none' : self::score_class((int)$value);
        printf(
            '<span class="pqm-yoast-badge pqm-score-%s" title="%s">%s %s</span>',
            esc_attr($class),
            esc_attr($title),
            esc_html($label),
            esc_html($value === '' ? '—' : $value)
        );
    }

    private static function score_class(int $score): string
    {
        if ($score >= 71)
            return 'green';
        if ($score >= 41)
            return 'orange';
        if ($score > 0)
            return 'red';
        return 'none';
    }

    private static function format_status(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'processing') return __('⚙️ Processing...', 'post-quality-manager');
        
        return match ($status) {
                'ship'           => '🟢 ' . __('Ready to Ship', 'post-quality-manager'),
                'needs_revision' => '🟠 ' . __('Needs Revision', 'post-quality-manager'),
                'major_revision' => '🔴 ' . __('Major Revision', 'post-quality-manager'),
                default          => $status ? $status : '—',
            };
    }

    private static function format_date(string $date): string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '—';
        }
        $timestamp = strtotime($date);
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private static function sortable_header(string $col, string $label, string $current_orderby, string $current_order): string
    {
        $is_active = $current_orderby === $col;
        $next_order = ($is_active && $current_order === 'ASC') ? 'DESC' : 'ASC';
        $arrow = $is_active ? ($current_order === 'ASC' ? ' ▲' : ' ▼') : '';

        $url = add_query_arg([
            'orderby' => $col,
            'order' => $next_order,
        ]);

        return '<a href="' . esc_url($url) . '">' . esc_html($label . $arrow) . '</a>';
    }
}
