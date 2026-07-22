<?php
/**
 * Admin pages for the Post Quality Manager plugin.
 *
 * Registers three sub-pages under a "Content Quality" top-level menu:
 * 1. Dashboard — stat cards, status distribution, recent reviews
 * 2. Post Reviews — WP_List_Table with scores, filters, actions
 * 3. Settings — n8n webhook URL, token, cron configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQM_Admin
{

    /* ─── Menu Registration ──────────────────────────────────────────── */

    public static function register_pages(): void
    {
        add_menu_page(
            __('Content Quality', 'post-quality-manager'),
            __('Content Quality', 'post-quality-manager'),
            'edit_posts',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard'],
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'pqm-dashboard',
            __('Dashboard', 'post-quality-manager'),
            __('Dashboard', 'post-quality-manager'),
            'edit_posts',
            'pqm-dashboard',
        [__CLASS__, 'render_dashboard']
        );

        add_submenu_page(
            'pqm-dashboard',
            __('Post Reviews', 'post-quality-manager'),
            __('Post Reviews', 'post-quality-manager'),
            'edit_posts',
            'pqm-posts',
        [__CLASS__, 'render_posts']
        );

        add_submenu_page(
            'pqm-dashboard',
            __('Settings', 'post-quality-manager'),
            __('Settings', 'post-quality-manager'),
            'manage_options',
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

        $settings = pqm_get_settings();
        if (empty($settings['n8n_webhook_url'])) {
            $settings_url = admin_url('admin.php?page=pqm-settings');
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Welcome to Post Quality Manager! To start reviewing posts, please configure your n8n Webhook URL.', 'post-quality-manager'),
                esc_url($settings_url),
                esc_html__('Go to Settings', 'post-quality-manager')
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

        wp_enqueue_script(
            'pqm-admin',
            PQM_PLUGIN_URL . 'admin/js/pqm-admin.js',
        [],
            filemtime(PQM_PLUGIN_DIR . 'admin/js/pqm-admin.js'),
            true
        );

        wp_localize_script('pqm-admin', 'pqmAdmin', [
            'restUrl' => rest_url(PQM_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pageNonce' => wp_create_nonce('pqm_admin_action'),
            'blogProfiles' => array_map(
                static fn($p) => ['website_context' => $p['website_context'], 'brand_voice_preset' => $p['brand_voice_preset']],
                self::blog_profiles()
            ),
            'agentDefaults' => pqm_default_settings()['agent_prompts'],
            'i18n' => [
                'replaceContext' => __('Replace the current Website Context with the profile default? Your existing text will be overwritten.', 'post-quality-manager'),
                'resetAgent' => __('Are you sure you want to reset this agent to default settings?', 'post-quality-manager'),
                'enterWebhookUrl' => __('Enter your n8n webhook URL first.', 'post-quality-manager'),
                'testing' => __('Sending test ping…', 'post-quality-manager'),
                'networkError' => __('Network error. Please try again.', 'post-quality-manager'),
            ],
        ]);
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
    }

    public static function sanitize_settings(array $input): array
    {
        $defaults = pqm_default_settings();
        $clean = [];

        $clean['n8n_webhook_url'] = esc_url_raw($input['n8n_webhook_url'] ?? '');
        $clean['webhook_token'] = sanitize_text_field($input['webhook_token'] ?? '');
        $clean['notification_url'] = esc_url_raw($input['notification_url'] ?? '');
        $clean['blog_profile'] = array_key_exists($input['blog_profile'] ?? '', self::blog_profiles()) ? $input['blog_profile'] : $defaults['blog_profile'];
        $clean['brand_voice_preset'] = in_array($input['brand_voice_preset'] ?? '', ['professional', 'casual', 'direct', 'custom'], true) ? $input['brand_voice_preset'] : $defaults['brand_voice_preset'];
        $clean['brand_voice'] = sanitize_textarea_field($input['brand_voice'] ?? '');
        $clean['website_context'] = sanitize_textarea_field($input['website_context'] ?? '');
        $clean['cron_enabled'] = !empty($input['cron_enabled']);
        $clean['cron_frequency'] = in_array($input['cron_frequency'] ?? '', ['daily', 'weekly'], true)
            ? $input['cron_frequency'] : $defaults['cron_frequency'];
        $clean['cron_batch_size'] = absint($input['cron_batch_size'] ?? $defaults['cron_batch_size']);
        $clean['cron_min_score'] = absint($input['cron_min_score'] ?? $defaults['cron_min_score']);
        $clean['cron_max_age_days'] = absint($input['cron_max_age_days'] ?? $defaults['cron_max_age_days']);
        $clean['cron_command'] = $defaults['cron_command'];

        $clean['agent_prompts'] = [];
        foreach (array_keys($defaults['agent_prompts']) as $agent) {
            if (isset($input['agent_prompts'][$agent])) {
                $clean['agent_prompts'][$agent] = [
                    'instructions' => sanitize_textarea_field($input['agent_prompts'][$agent]['instructions'] ?? ''),
                    'temperature'  => (float) ($input['agent_prompts'][$agent]['temperature'] ?? $defaults['agent_prompts'][$agent]['temperature']),
                ];
            }
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
        $stats = PQM_DB::get_dashboard_stats();
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Dashboard', 'post-quality-manager'); ?></h1>

            <!-- Stat Cards -->
            <?php
            $needs_attention = ($stats['distribution']['needs_revision'] ?? 0) + ($stats['distribution']['major_revision'] ?? 0);
            $ready_to_ship   = $stats['distribution']['ship'] ?? 0;
            ?>
            <div class="pqm-stats-grid">
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['avg_score']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('The overall average quality score across all analyzed posts.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Average Score', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['reviewed']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total posts that have been processed by the quality manager at least once.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Analyzed Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($needs_attention); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Posts requiring manual intervention or major revisions to meet quality standards.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Needs Attention', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['total_posts']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total published and pending posts on your site.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Total Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help pqm-help-icon"></span>
                    </span>
                </div>
            </div>

        </div>
        <?php
    }

    /* ─── Page: Post Reviews ─────────────────────────────────────────── */

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
                }
            }
            else {
                $notice_class = 'notice-error';
                $notice_message = __('n8n webhook URL is not configured. Go to Settings.', 'post-quality-manager');
            }

            echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($notice_message) . '</p></div>';
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
                        <tr><td colspan="6"><?php esc_html_e('No posts found.', 'post-quality-manager'); ?></td></tr>
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
                            <td class="pqm-cell-status">
                                <?php echo esc_html(self::format_status($item['latest_status'] ?? '')); ?>
                            </td>
                            <td class="pqm-cell-progress">
                                <?php
                $cmds = $commands[$item['post_id']] ?? [];
                $has_rev = in_array('review', $cmds, true);
                $has_rew = in_array('rewrite', $cmds, true);
?>
                                <div class="pqm-pills">
                                    <span class="pqm-pill <?php echo $has_rev ? 'pqm-pill-done' : 'pqm-pill-pending'; ?>">
                                        <?php echo $has_rev ? '✓ ' : '— '; ?><?php esc_html_e('Review', 'post-quality-manager'); ?>
                                    </span>
                                    <span class="pqm-pill <?php echo $has_rew ? 'pqm-pill-done' : 'pqm-pill-pending'; ?>">
                                        <?php echo $has_rew ? '✓ ' : '— '; ?><?php esc_html_e('Rewrite', 'post-quality-manager'); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="pqm-cell-date">
                                <?php echo $item['latest_reviewed'] ? esc_html(self::format_date($item['latest_reviewed'])) : '<em>' . esc_html__('Never', 'post-quality-manager') . '</em>'; ?>
                            </td>
                            <td class="pqm-cell-actions">
                                <?php if (!$has_rev): ?>
                                    <button type="button" class="button button-primary button-small"
                                            data-post-id="<?php echo esc_attr($item['post_id']); ?>" data-pqm-cmd="review">
                                        <?php esc_html_e('Run Review', 'post-quality-manager'); ?>
                                    </button>
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
                            <td colspan="6">
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
        $download_url = wp_nonce_url(admin_url('admin-post.php?action=pqm_download_workflow'), 'pqm_download_workflow');
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Settings', 'post-quality-manager'); ?></h1>

            <?php if (empty($settings['n8n_webhook_url'])): ?>
            <!-- Setup wizard: shown only until the connection is configured. -->
            <div class="pqm-wizard" id="pqm-wizard">
                <h2 class="pqm-wizard-title"><?php esc_html_e('Set up your n8n connection', 'post-quality-manager'); ?></h2>
                <ol class="pqm-wizard-steps">
                    <li class="pqm-wizard-dot is-active" data-step="1"><span>1</span> <?php esc_html_e('Download', 'post-quality-manager'); ?></li>
                    <li class="pqm-wizard-dot" data-step="2"><span>2</span> <?php esc_html_e('Import', 'post-quality-manager'); ?></li>
                    <li class="pqm-wizard-dot" data-step="3"><span>3</span> <?php esc_html_e('Connect & test', 'post-quality-manager'); ?></li>
                </ol>

                <div class="pqm-wizard-step is-active" data-step="1">
                    <p><?php esc_html_e('Download the review workflow pre-configured for this site.', 'post-quality-manager'); ?></p>
                    <a href="<?php echo esc_url($download_url); ?>" class="button button-primary">
                        <?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?>
                    </a>
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
                    <p><?php esc_html_e('Paste the workflow\'s production webhook URL into the field below, then send a test ping to verify the connection. Save the settings when the test passes.', 'post-quality-manager'); ?></p>
                    <button type="button" class="button button-primary pqm-test-connection">
                        <?php esc_html_e('Send test ping', 'post-quality-manager'); ?>
                    </button>
                    <span class="pqm-test-result" aria-live="polite"></span>
                </div>

                <div class="pqm-wizard-nav">
                    <button type="button" class="button pqm-wizard-back" disabled><?php esc_html_e('Back', 'post-quality-manager'); ?></button>
                    <button type="button" class="button button-primary pqm-wizard-next"><?php esc_html_e('Next', 'post-quality-manager'); ?></button>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('pqm_settings_group'); ?>

                <!-- n8n Connection -->
                <h2><?php esc_html_e('n8n Connection', 'post-quality-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Workflow File', 'post-quality-manager'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($download_url); ?>" class="button button-secondary">
                                <?php esc_html_e('Download n8n workflow', 'post-quality-manager'); ?>
                            </a>
                            <p class="description"><?php esc_html_e('The review workflow tailored to this site, ready to import into n8n. After importing, connect your WordPress and Claude credentials.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
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

                <!-- AI Prompts & Context -->
                <h2 class="pqm-section-gap"><?php esc_html_e('AI Prompts & Context', 'post-quality-manager'); ?></h2>
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
                            <fieldset>
                                <?php foreach ($profiles as $val => $profile): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[blog_profile]" value="<?php echo esc_attr($val); ?>" <?php checked($current_profile, $val); ?> class="pqm-blog-profile" />
                                        <?php echo esc_html($profile['label']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Picking a profile pre-fills the voice and context fields below with sensible defaults for your niche. Everything stays editable.', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_brand_voice"><?php esc_html_e('Brand Voice & Tone', 'post-quality-manager'); ?></label></th>
                        <td>
                            <?php 
                            $presets = [
                                'professional' => __('Professional & Authoritative', 'post-quality-manager'),
                                'casual'       => __('Casual & Conversational', 'post-quality-manager'),
                                'direct'       => __('Direct & Action-Oriented', 'post-quality-manager'),
                                'custom'       => __('Custom', 'post-quality-manager'),
                            ];
                            $current_preset = $settings['brand_voice_preset'];
                            ?>
                            <fieldset class="pqm-voice-fieldset">
                                <?php foreach ($presets as $val => $lbl): ?>
                                    <label class="pqm-radio-label">
                                        <input type="radio" name="pqm_settings[brand_voice_preset]" value="<?php echo esc_attr($val); ?>" <?php checked($current_preset, $val); ?> class="pqm-voice-preset" />
                                        <?php echo esc_html($lbl); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <textarea id="pqm_brand_voice" name="pqm_settings[brand_voice]" rows="4" class="large-text<?php echo $current_preset !== 'custom' ? ' pqm-hidden' : ''; ?>"><?php echo esc_textarea($settings['brand_voice']); ?></textarea>
                            <p class="description pqm-voice-desc<?php echo $current_preset !== 'custom' ? ' pqm-hidden' : ''; ?>"><?php esc_html_e('Instructions on how the AI should sound (e.g., "Professional yet accessible, active voice").', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_website_context"><?php esc_html_e('Website Context', 'post-quality-manager'); ?></label></th>
                        <td>
                            <textarea id="pqm_website_context" name="pqm_settings[website_context]" rows="4" class="large-text"><?php echo esc_textarea($settings['website_context']); ?></textarea>
                            <p class="description"><?php esc_html_e('Context about the website and audience (e.g., "A blog for professional software developers").', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3 class="pqm-section-gap"><?php esc_html_e('Agent-Specific Overrides', 'post-quality-manager'); ?></h3>
                <p class="description pqm-agents-intro"><?php esc_html_e('Optionally override the temperature and provide additional system instructions for each AI agent.', 'post-quality-manager'); ?></p>

                <?php
                $agent_defaults = pqm_default_settings()['agent_prompts'];
                $agents = [
                    'review'    => __('Review Agent', 'post-quality-manager'),
                    'takeaways' => __('Key Takeaways Agent', 'post-quality-manager'),
                    'sources'   => __('Sources Agent', 'post-quality-manager'),
                    'seo'       => __('SEO Agent', 'post-quality-manager'),
                    'audit'     => __('Audit Agent', 'post-quality-manager'),
                    'rewrite'   => __('Rewrite Agent', 'post-quality-manager'),
                ];
                foreach ($agents as $key => $label):
                    $agent_data = $settings['agent_prompts'][$key];
                ?>
                    <details class="pqm-agent-box">
                        <summary>
                            <span class="dashicons dashicons-admin-generic pqm-agent-icon"></span>
                            <?php echo esc_html($label); ?>
                        </summary>
                        <table class="form-table">
                            <tr>
                                <th><label for="pqm_agent_temp_<?php echo esc_attr($key); ?>"><?php esc_html_e('Temperature', 'post-quality-manager'); ?></label></th>
                                <td>
                                    <div class="pqm-temp-row">
                                        <input type="range" step="0.1" min="0" max="1"
                                               id="pqm_agent_temp_<?php echo esc_attr($key); ?>"
                                               name="pqm_settings[agent_prompts][<?php echo esc_attr($key); ?>][temperature]"
                                               value="<?php echo esc_attr(number_format((float)$agent_data['temperature'], 1)); ?>"
                                               class="pqm-temp-range" />
                                        <span class="pqm-temp-value"><?php echo esc_html(number_format((float)$agent_data['temperature'], 1)); ?></span>
                                    </div>
                                    <p class="description"><?php esc_html_e('0.0 (Strict/Analytical) to 1.0 (Creative & Loose).', 'post-quality-manager'); ?></p>
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
                                    <button type="button" class="button pqm-reset-agent" data-agent="<?php echo esc_attr($key); ?>" data-temp="<?php echo esc_attr(number_format((float)$agent_defaults[$key]['temperature'], 1)); ?>">
                                        <?php esc_html_e('Reset to Defaults', 'post-quality-manager'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </details>
                <?php endforeach; ?>

                <!-- Cron Settings -->
                <h2><?php esc_html_e('Automated Reviews (Cron)', 'post-quality-manager'); ?></h2>
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

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ─── Workflow Generator (admin-post download) ───────────────────── */

    /**
     * Serve the bundled n8n workflow template with this site's values injected.
     */
    public static function download_workflow(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'post-quality-manager'));
        }
        check_admin_referer('pqm_download_workflow');

        $template_path = PQM_PLUGIN_DIR . 'templates/n8n-workflow.json';
        if (!file_exists($template_path)) {
            wp_die(esc_html__('Workflow template not found. Please reinstall the plugin.', 'post-quality-manager'));
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'my-site';
        $json = str_replace('__PQM_SITE_HOST__', $host, file_get_contents($template_path));

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="post-quality-workflow-' . sanitize_file_name($host) . '.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download, not HTML.
        exit;
    }

    /* ─── AJAX: Test Connection ──────────────────────────────────────── */

    /**
     * Send a lightweight "ping" command to the n8n webhook and report the outcome.
     * Tests the URL from the settings field so it works before saving.
     */
    public static function ajax_test_connection(): void
    {
        check_ajax_referer('pqm_admin_action');

        if (!current_user_can('manage_options')) {
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
            wp_send_json_success(__('Connection successful — n8n received the test ping.', 'post-quality-manager'));
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

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions.', 403);
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
            wp_send_json_error($response->get_error_message());
        }

        // If the REST API call was successful, record a 'processing' status immediately.
        // The actual review results will come back via the webhook.
        PQM_DB::save_review($post_id, $command, 0, 'processing', ['summary' => __('Running asynchronously in n8n...', 'post-quality-manager')]);
        wp_send_json_success(['message' => __('Command triggered in n8n.', 'post-quality-manager')]);
    }

    /* ─── Helpers ─────────────────────────────────────────────────────── */

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
