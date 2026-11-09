<?php
/**
 * CLI harness — story 109, the Post Quality meta box on the post edit
 * screen (PostQuality_Meta_Box). Rows of the plan's I/O matrix the server
 * decides: the box is registered for posts only (add_meta_boxes_post) and
 * only for users with postquality_view_reviews; its script loads only on
 * the edit screens of posts for those users; no second way to start a run
 * (no save_post hook); the box's data rides an inline script, never
 * wp_localize_script. The enqueue rows that need the built entry
 * (admin/build/meta-box.asset.php) run when it is built and say so when it
 * is not (CI's php job runs without the build).
 * Run: php meta-box-harness.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = [
    'options'   => [],
    'caps'      => [],
    'actions'   => [], // [tag, callback, priority]
    'boxes'     => [], // add_meta_box() calls
    'scripts'   => [], // handle => [src, deps, ver, in_footer]
    'styles'    => [], // handle => [src, deps, ver]
    'inline'    => [], // [handle, data, position]
    'localized' => [],
    'screen'    => null,
    'post'      => null,
];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
function is_admin() { return true; } // the admin hooks register
function add_action($tag, $cb, $priority = 10, $args = 1) { $GLOBALS['tw']['actions'][] = [$tag, $cb, $priority]; return true; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/post-quality-manager/'; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null) {
    $GLOBALS['tw']['boxes'][] = compact('id', 'title', 'callback', 'screen', 'context', 'priority');
}
function get_current_screen() { return $GLOBALS['tw']['screen']; }
function get_post($post = null, $output = 'OBJECT', $filter = 'raw') { return $GLOBALS['tw']['post']; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg(...$args) { // WP's two call shapes: (key, value, url) and (array, url)
    [$pairs, $url] = is_array($args[0]) ? [$args[0], $args[1]] : [[$args[0] => $args[1]], $args[2]];
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($pairs);
}
function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) { $GLOBALS['tw']['scripts'][$handle] = [$src, $deps, $ver, $in_footer]; }
function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') { $GLOBALS['tw']['styles'][$handle] = [$src, $deps, $ver]; }
function wp_add_inline_script($handle, $data, $position = 'after') { $GLOBALS['tw']['inline'][] = [$handle, $data, $position]; return true; }
function wp_localize_script($handle, $name, $data) { $GLOBALS['tw']['localized'][] = $handle; return true; }
function wp_set_script_translations(...$a) { return true; }
function wp_style_add_data(...$a) { return true; }

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */

/** An edit screen of $post_type in the block editor or the Classic Editor. */
function screen(string $post_type, bool $block = true): object {
    return new class($post_type, $block) {
        public function __construct(public string $post_type, private bool $block) {}
        public function is_block_editor($set = null) { return $this->block; }
    };
}

/** Reset the enqueue records, set the user and the screen, run the enqueue on $hook. */
function enqueue_on(string $hook, array $caps, ?object $screen, ?object $post = null): void {
    $GLOBALS['tw'] = array_merge($GLOBALS['tw'], ['scripts' => [], 'styles' => [], 'inline' => [], 'localized' => [], 'caps' => $caps, 'screen' => $screen, 'post' => $post]);
    PostQuality_Meta_Box::enqueue($hook);
}

$plugin = dirname(__DIR__, 2) . '/post-quality-manager';
$built  = file_exists($plugin . '/admin/build/meta-box.asset.php');
$view   = ['postquality_view_reviews'];
$author = ['postquality_view_reviews', 'postquality_run_free_agents'];
$post   = (object) ['ID' => 7, 'post_status' => 'publish'];

/* ─── Registration: posts only, the view capability ─────────────────── */
echo "Registration (posts only)\n";
$hooked = static fn(string $tag, array $cb) => in_array([$tag, $cb, 10], $GLOBALS['tw']['actions'], true);
check('the box registers on add_meta_boxes_post — the post type\'s own hook, so pages and other types never see it',
    $hooked('add_meta_boxes_post', ['PostQuality_Meta_Box', 'register']));
check('no hook on add_meta_boxes for every type, nor on another type\'s hook',
    !array_filter($GLOBALS['tw']['actions'], static fn($a) => $a[0] === 'add_meta_boxes' || (str_starts_with($a[0], 'add_meta_boxes_') && $a[0] !== 'add_meta_boxes_post')));
check('the script hooks admin_enqueue_scripts', $hooked('admin_enqueue_scripts', ['PostQuality_Meta_Box', 'enqueue']));
check('no second way to start a run: the plugin hooks no save_post, publish or status transition',
    !array_filter($GLOBALS['tw']['actions'], static fn($a) => preg_match('/^(save_post|wp_insert_post|transition_post_status|publish_)/', $a[0])));

echo "Registration (capability)\n";
$GLOBALS['tw']['caps'] = $author;
PostQuality_Meta_Box::register();
$box = $GLOBALS['tw']['boxes'][0] ?? [];
check('with postquality_view_reviews: one box, id postquality-review, titled "Post Quality"',
    count($GLOBALS['tw']['boxes']) === 1 && ($box['id'] ?? '') === 'postquality-review' && ($box['title'] ?? '') === 'Post Quality');
check('…on posts, under the content (normal), default priority', ($box['screen'] ?? '') === 'post' && ($box['context'] ?? '') === 'normal' && ($box['priority'] ?? '') === 'default');
check('…rendered by PostQuality_Meta_Box::render', ($box['callback'] ?? null) === ['PostQuality_Meta_Box', 'render']);
foreach (['no capability' => [], 'edit_posts only' => ['edit_posts'], 'run without view' => ['postquality_run_free_agents']] as $label => $caps) {
    $GLOBALS['tw']['boxes'] = [];
    $GLOBALS['tw']['caps']  = $caps;
    PostQuality_Meta_Box::register();
    check("$label: no box", $GLOBALS['tw']['boxes'] === []);
}
$GLOBALS['tw']['caps'] = $view;
$GLOBALS['tw']['boxes'] = [];
PostQuality_Meta_Box::register();
check('view without run: the box is there (the script hides the action)', count($GLOBALS['tw']['boxes']) === 1);

echo "Render\n";
ob_start();
PostQuality_Meta_Box::render();
$html = ob_get_clean();
$app_built = file_exists($plugin . '/admin/build/index.asset.php');
check('render prints the root div the entry mounts on (#pqm-meta-box)' . ($app_built ? '' : ' — unbuilt: the build notice instead'),
    $app_built ? $html === '<div id="pqm-meta-box"></div>' : str_contains($html, 'npm run build'));

/* ─── The enqueue gate ──────────────────────────────────────────────── */
echo "Enqueue gate\n";
foreach ([
    'post.php, a post, view cap'        => ['post.php', $view, screen('post'), true],
    'post-new.php, a post, view cap'    => ['post-new.php', $view, screen('post'), true],
    'post.php, Classic Editor'          => ['post.php', $view, screen('post', false), true],
    'post.php, a page'                  => ['post.php', $view, screen('page'), false],
    'post.php, a custom type'           => ['post.php', $view, screen('product'), false],
    'edit.php (the Posts list)'         => ['edit.php', $view, screen('post'), false],
    'Post Reviews'                      => ['post-quality_page_postquality-posts', $view, screen(''), false],
    'post.php, a post, no capability'   => ['post.php', [], screen('post'), false],
    'post.php, a post, edit_posts only' => ['post.php', ['edit_posts'], screen('post'), false],
    'post.php, no screen'               => ['post.php', $view, null, false],
] as $label => [$hook, $caps, $screen, $expected]) {
    $GLOBALS['tw']['caps']   = $caps;
    $GLOBALS['tw']['screen'] = $screen;
    check("$label → " . ($expected ? 'loads' : 'nothing'), PostQuality_Meta_Box::loads_on($hook) === $expected);
    if (!$expected) {
        enqueue_on($hook, $caps, $screen, $post);
        check("$label → enqueue adds no script, style or inline data",
            $GLOBALS['tw']['scripts'] === [] && $GLOBALS['tw']['styles'] === [] && $GLOBALS['tw']['inline'] === []);
    }
}

/* ─── What the enqueue adds (built entry only) ──────────────────────── */
echo "Enqueue on a post's edit screen" . ($built ? '' : ' — skipped: admin/build/meta-box.asset.php is not built (npm run build)') . "\n";
if ($built) {
    $asset = require $plugin . '/admin/build/meta-box.asset.php';
    enqueue_on('post.php', $author, screen('post'), $post);
    $script = $GLOBALS['tw']['scripts']['postquality-meta-box'] ?? null;
    check('script postquality-meta-box: admin/build/meta-box.js, in the footer, deps and version from its asset manifest',
        $script !== null && str_ends_with($script[0], 'admin/build/meta-box.js') && $script[3] === true
        && $script[1] === $asset['dependencies'] && $script[2] === $asset['version']);
    check('the entry depends on wp-data and never on wp-editor (core/editor is read by its store name)',
        in_array('wp-data', $asset['dependencies'], true) && !in_array('wp-editor', $asset['dependencies'], true));
    check('only the box\'s script: the app bundle (postquality-app) is not loaded as a script', array_keys($GLOBALS['tw']['scripts']) === ['postquality-meta-box']);
    $inline = array_column(array_filter($GLOBALS['tw']['inline'], static fn($i) => $i[0] === 'postquality-meta-box' && $i[2] === 'before'), 1);
    $labels = array_values(array_filter($inline, static fn($d) => str_starts_with($d, 'window.postqualityCommandLabels = ')));
    check('the command labels print before the bundle (the shared helper)', count($labels) === 1 && str_contains($labels[0], '"review":"Review"'));
    $data = null;
    foreach ($inline as $d) {
        if (preg_match('/^window\.postqualityMetaBox = (.*);$/s', $d, $m)) { $data = json_decode($m[1], true); }
    }
    check('window.postqualityMetaBox = {postId, status, isBlockEditor, reviewsUrl}, printed before the bundle',
        is_array($data) && array_keys($data) === ['postId', 'status', 'isBlockEditor', 'reviewsUrl']);
    check('…the post (7), its saved status (publish), the block editor (true)',
        ($data['postId'] ?? null) === 7 && ($data['status'] ?? null) === 'publish' && ($data['isBlockEditor'] ?? null) === true);
    check('…reviewsUrl = Post Reviews narrowed to the post (postquality_post=7)',
        ($data['reviewsUrl'] ?? null) === 'https://example.test/wp-admin/admin.php?page=postquality-posts&postquality_post=7');
    check('no wp_localize_script', $GLOBALS['tw']['localized'] === []);
    check('styles: wp-components, the kit\'s style-index.css (postquality-app) and the box\'s style-meta-box.css',
        isset($GLOBALS['tw']['styles']['wp-components'])
        && str_ends_with($GLOBALS['tw']['styles']['postquality-app'][0] ?? '', 'admin/build/style-index.css')
        && str_ends_with($GLOBALS['tw']['styles']['postquality-meta-box'][0] ?? '', 'admin/build/style-meta-box.css'));
    enqueue_on('post-new.php', $view, screen('post', false), (object) ['ID' => 12, 'post_status' => 'auto-draft']);
    $data = null;
    foreach ($GLOBALS['tw']['inline'] as [$handle, $d]) {
        if (preg_match('/^window\.postqualityMetaBox = (.*);$/s', $d, $m)) { $data = json_decode($m[1], true); }
    }
    check('a new post in the Classic Editor: postId 12, status auto-draft, isBlockEditor false',
        ($data['postId'] ?? null) === 12 && ($data['status'] ?? null) === 'auto-draft' && ($data['isBlockEditor'] ?? null) === false);
    enqueue_on('post.php', $view, screen('post'), null);
    check('no post object: nothing enqueued', $GLOBALS['tw']['scripts'] === []);
}

/* ─── Static: the box's sources ─────────────────────────────────────── */
echo "Sources\n";
$php = (string) file_get_contents($plugin . '/admin/class-pqm-meta-box.php');
check('class-pqm-meta-box.php calls no wp_localize_script', !str_contains($php, 'wp_localize_script('));
$js = '';
foreach (glob(dirname(__DIR__, 2) . '/src/meta-box/*.js') ?: [] as $f) { $js .= (string) file_get_contents($f); }
check('src/meta-box/ never imports @wordpress/editor', $js !== '' && !str_contains($js, "'@wordpress/editor'"));
check('src/meta-box/ starts runs only through the kit\'s requestRun() (POST /trigger-review); its own requests are reads (no method:)',
    str_contains($js, 'requestRun( postId, \'review\' )') && !str_contains($js, 'method:'));

harness_exit();
