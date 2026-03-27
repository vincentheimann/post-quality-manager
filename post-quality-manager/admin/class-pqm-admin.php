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
        ]);
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
        $clean = [];

        $clean['n8n_webhook_url'] = esc_url_raw($input['n8n_webhook_url'] ?? '');
        $clean['webhook_token'] = sanitize_text_field($input['webhook_token'] ?? '');
        $clean['notification_url'] = esc_url_raw($input['notification_url'] ?? '');
        $clean['brand_voice'] = sanitize_textarea_field($input['brand_voice'] ?? '');
        $clean['website_context'] = sanitize_textarea_field($input['website_context'] ?? '');
        $clean['cron_enabled'] = !empty($input['cron_enabled']);
        $clean['cron_frequency'] = in_array($input['cron_frequency'] ?? '', ['daily', 'weekly'], true)
            ? $input['cron_frequency'] : 'daily';
        $clean['cron_batch_size'] = absint($input['cron_batch_size'] ?? 5);
        $clean['cron_min_score'] = absint($input['cron_min_score'] ?? 60);
        $clean['cron_max_age_days'] = absint($input['cron_max_age_days'] ?? 30);
        $clean['cron_command'] = 'review';

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
                        <?php esc_html_e('Average Score', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help" style="font-size: 14px; line-height: 1.5; color: #a7a7a7; cursor: help;"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['reviewed']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total posts that have been processed by the quality manager at least once.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Analyzed Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help" style="font-size: 14px; line-height: 1.5; color: #a7a7a7; cursor: help;"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($needs_attention); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Posts requiring manual intervention or major revisions to meet quality standards.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Needs Attention', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help" style="font-size: 14px; line-height: 1.5; color: #a7a7a7; cursor: help;"></span>
                    </span>
                </div>
                <div class="pqm-stat-card">
                    <span class="pqm-stat-number"><?php echo esc_html($stats['total_posts']); ?></span>
                    <span class="pqm-stat-label" title="<?php esc_attr_e('Total published and pending posts on your site.', 'post-quality-manager'); ?>">
                        <?php esc_html_e('Total Posts', 'post-quality-manager'); ?> <span class="dashicons dashicons-editor-help" style="font-size: 14px; line-height: 1.5; color: #a7a7a7; cursor: help;"></span>
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
                                        — <span class="post-state" style="font-weight: normal; color: #50575e;"><?php esc_html_e('Pending', 'post-quality-manager'); ?></span>
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
                        <tr class="pqm-detail-row" id="pqm-detail-<?php echo esc_attr($item['post_id']); ?>" style="display:none">
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
?>
        <div class="wrap pqm-wrap">
            <h1><?php esc_html_e('Content Quality Settings', 'post-quality-manager'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('pqm_settings_group'); ?>

                <!-- n8n Connection -->
                <h2><?php esc_html_e('n8n Connection', 'post-quality-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_n8n_webhook_url"><?php esc_html_e('n8n Webhook URL', 'post-quality-manager'); ?></label></th>
                        <td>
                            <input type="url" id="pqm_n8n_webhook_url" name="pqm_settings[n8n_webhook_url]"
                                   value="<?php echo esc_attr($settings['n8n_webhook_url']); ?>"
                                   class="regular-text" placeholder="https://your-n8n.example.com/webhook/pqm-execute" />
                            <p class="description"><?php esc_html_e('The webhook URL of your n8n workflow.', 'post-quality-manager'); ?></p>
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
                <h2><?php esc_html_e('AI Prompts & Context', 'post-quality-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="pqm_brand_voice"><?php esc_html_e('Brand Voice & Tone', 'post-quality-manager'); ?></label></th>
                        <td>
                            <textarea id="pqm_brand_voice" name="pqm_settings[brand_voice]" rows="4" class="large-text"><?php echo esc_textarea($settings['brand_voice'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Instructions on how the AI should sound (e.g., "Professional yet accessible, active voice").', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pqm_website_context"><?php esc_html_e('Website Context', 'post-quality-manager'); ?></label></th>
                        <td>
                            <textarea id="pqm_website_context" name="pqm_settings[website_context]" rows="4" class="large-text"><?php echo esc_textarea($settings['website_context'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Context about the website and audience (e.g., "A blog for professional software developers").', 'post-quality-manager'); ?></p>
                        </td>
                    </tr>
                </table>

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
