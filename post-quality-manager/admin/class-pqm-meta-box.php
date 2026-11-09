<?php
/**
 * The Post Quality meta box on the post edit screen (story 109).
 *
 * A classic meta box under the content of posts, in the block editor and
 * the Classic Editor, for users who may view reviews. PHP prints only the
 * root div; the box renders client-side from the second build entry
 * (src/meta-box/), which reads the user's gating, quota and connection from
 * GET /posts?post_id and the post's reviews from GET /reviews/<post_id>, and
 * runs Review through POST /trigger-review. The WordPress facts it relies
 * on (both editors, 6.6 to 7.1): memory/admin-ui-rebuild.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PostQuality_Meta_Box
{
    /** The meta box id (add_meta_box(), Screen Options). */
    public const BOX_ID = 'postquality-review';

    /** The root div src/meta-box/index.js mounts on. */
    public const ROOT_ID = 'pqm-meta-box';

    /** Post Reviews' one-post link (src/post-reviews-table.js POST_PARAM). */
    public const POST_PARAM = 'postquality_post';

    /** The post type the box shows on: Post Reviews lists posts only. */
    private const POST_TYPE = 'post';

    /** Fired by add_meta_boxes_post: posts only, users who may view reviews. */
    public static function register(): void
    {
        if (!current_user_can('postquality_view_reviews')) {
            return;
        }

        add_meta_box(
            self::BOX_ID,
            __('Post Quality', 'post-quality-manager'),
            [__CLASS__, 'render'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function render(): void
    {
        PostQuality_Admin::render_app_root(self::ROOT_ID);
    }

    /**
     * Whether the box's script loads on this admin screen: the edit screen
     * of a post (both editors run on post.php and post-new.php), for a user
     * who may view reviews — the same gate as the box.
     */
    public static function loads_on(string $hook): bool
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !current_user_can('postquality_view_reviews')) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && self::POST_TYPE === $screen->post_type;
    }

    /**
     * The box's script and styles where loads_on() says. The box's data
     * rides an inline script before the bundle (no wp_localize_script): the
     * post, its saved status, the editor and the Post Reviews link.
     */
    public static function enqueue(string $hook): void
    {
        if (!self::loads_on($hook)) {
            return;
        }
        $post   = get_post();
        $screen = get_current_screen();
        if (!$post) {
            return;
        }

        $asset_path = POSTQUALITY_PLUGIN_DIR . 'admin/build/meta-box.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset   = require $asset_path;
        $version = $asset['version'] ?? POSTQUALITY_VERSION;

        wp_enqueue_script(
            'postquality-meta-box',
            POSTQUALITY_PLUGIN_URL . 'admin/build/meta-box.js',
            $asset['dependencies'] ?? [],
            $version,
            true
        );
        // No path: the JSON comes from the language packs in WP_LANG_DIR/plugins (story 95).
        wp_set_script_translations('postquality-meta-box', 'post-quality-manager');
        PostQuality_Admin::add_command_labels('postquality-meta-box');
        wp_add_inline_script(
            'postquality-meta-box',
            'window.postqualityMetaBox = ' . wp_json_encode([
                'postId'        => (int) $post->ID,
                'status'        => (string) $post->post_status,
                'isBlockEditor' => (bool) $screen->is_block_editor(),
                'reviewsUrl'    => add_query_arg(self::POST_PARAM, (int) $post->ID, admin_url('admin.php?page=postquality-posts')),
            ]) . ';',
            'before'
        );

        // The kit's rules ship in style-index.css (see enqueue_app_style()); the box's own beside them.
        PostQuality_Admin::enqueue_app_style($version);
        if (file_exists(POSTQUALITY_PLUGIN_DIR . 'admin/build/style-meta-box.css')) {
            wp_enqueue_style(
                'postquality-meta-box',
                POSTQUALITY_PLUGIN_URL . 'admin/build/style-meta-box.css',
                ['wp-components'],
                $version
            );
            wp_style_add_data('postquality-meta-box', 'rtl', 'replace');
        }
    }
}
