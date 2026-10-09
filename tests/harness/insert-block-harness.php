<?php
/**
 * Insert-block harness for story 23 (Takeaways / Sources into the post
 * through a pattern the site owner picks) — every I/O & Edge-Case Matrix
 * row: PQM_Blocks::place() output serialized with core's own serializer,
 * PQM_Blocks::patterns(), and the POST /insert-block route (permission,
 * gate, 409, pattern resolution, save). WP stubs, no WP install. The
 * serialize_block* functions are copied verbatim from WordPress 7.1.2
 * (wp-includes/blocks.php) so the markup checked here is the markup
 * WordPress writes. Parsed-block fixtures mirror WP_Block_Parser output
 * (whitespace between blocks = null-name blocks). The fixture pattern is
 * the owner's from the ticket conversation: Group › Heading + List, then a
 * Spacer. The "Bad setting" row lives in sanitize-matrix-harness.php
 * (Case 7), the dropdown in render-matrix-harness.php.
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['tw'] = [
    'caps'     => [],
    'can_edit' => [],   // post ids the user may edit_post
    'pro'      => true,
    'settings' => [],
    'posts'    => [],   // id => post_content
    'parsed'   => [],   // post_content => parse_blocks() fixture
    'updates'  => [],   // wp_update_post() calls (raw, slashed)
    'patterns' => [],   // wp_block posts: id => [title, status, content]
    'query'    => null, // last get_posts() args
    'meta'     => 0,    // update_user_meta() calls (quota)
];
$GLOBALS['pqm_routes'] = [];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function add_action(...$a) {}
function add_filter(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return ''; }
function is_admin() { return false; }
function wp_generate_uuid4() { return 'stub-uuid'; }
function __($s, $d = null) { return $s; }
function get_option($k, $d = false) { return $k === 'pqm_settings' ? $GLOBALS['tw']['settings'] : $d; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function apply_filters($tag, $value, ...$a) { return $tag === 'pqm_is_pro' ? $GLOBALS['tw']['pro'] : $value; }
function current_user_can($cap, ...$args) {
    if ($cap === 'edit_post') {
        return in_array((int) ($args[0] ?? 0), $GLOBALS['tw']['can_edit'], true);
    }
    return in_array($cap, $GLOBALS['tw']['caps'], true);
}
function get_current_user_id() { return 42; }
function get_user_meta($u, $k, $s) { return ''; }
function update_user_meta(...$a) { $GLOBALS['tw']['meta']++; return true; }
function wp_date($f, $ts = null) { return gmdate($f, $ts ?? time()); }
function wp_timezone() { return new DateTimeZone('UTC'); }
/* WP's esc_html/esc_attr: specialchars, quotes included, no double-encoding. */
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $s)); }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
function get_post($id) {
    $content = $GLOBALS['tw']['posts'][$id] ?? $GLOBALS['tw']['patterns'][$id][2] ?? null;
    return $content === null ? null : (object) ['ID' => $id, 'post_content' => $content];
}
/* WP_Query's filtering and ordering for the args patterns() passes. */
function get_posts($args) {
    $GLOBALS['tw']['query'] = $args;
    $out = [];
    foreach ($GLOBALS['tw']['patterns'] as $id => [$title, $status, $content]) {
        if ($args['post_type'] === 'wp_block' && $status === $args['post_status']) {
            $out[] = (object) ['ID' => $id, 'post_title' => $title, 'post_content' => $content];
        }
    }
    usort($out, static fn($a, $b) => strcmp($a->post_title, $b->post_title));
    return $args['numberposts'] === -1 ? $out : array_slice($out, 0, $args['numberposts']);
}
function parse_blocks($content) { return $GLOBALS['tw']['parsed'][$content] ?? []; }
function wp_update_post($postarr, $wp_error = false) {
    $GLOBALS['tw']['updates'][] = $postarr;
    return $postarr['ID'];
}

/* ─── Core serializer, verbatim from WordPress 7.1.2 wp-includes/blocks.php ── */
function serialize_block_attributes( $block_attributes ) {
	$encoded_attributes = wp_json_encode( $block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return strtr(
		$encoded_attributes,
		array(
			'\\\\' => '\\u005c',
			'--'   => '\\u002d\\u002d',
			'<'    => '\\u003c',
			'>'    => '\\u003e',
			'&'    => '\\u0026',
			'\\"'  => '\\u0022',
		)
	);
}

function strip_core_block_namespace( $block_name = null ) {
	if ( is_string( $block_name ) && str_starts_with( $block_name, 'core/' ) ) {
		return substr( $block_name, 5 );
	}

	return $block_name;
}

function get_comment_delimited_block_content( $block_name, $block_attributes, $block_content ) {
	if ( is_null( $block_name ) ) {
		return $block_content;
	}

	$serialized_block_name = strip_core_block_namespace( $block_name );
	$serialized_attributes = empty( $block_attributes ) ? '' : serialize_block_attributes( $block_attributes ) . ' ';

	if ( empty( $block_content ) ) {
		return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes );
	}

	return sprintf(
		'<!-- wp:%s %s-->%s<!-- /wp:%s -->',
		$serialized_block_name,
		$serialized_attributes,
		$block_content,
		$serialized_block_name
	);
}

function serialize_block( $block ) {
	$block_content = '';

	$index = 0;
	foreach ( $block['innerContent'] as $chunk ) {
		$block_content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $index++ ] );
	}

	if ( ! is_array( $block['attrs'] ) ) {
		$block['attrs'] = array();
	}

	return get_comment_delimited_block_content(
		$block['blockName'],
		$block['attrs'],
		$block_content
	);
}

function serialize_blocks( $blocks ) {
	return implode( '', array_map( 'serialize_block', $blocks ) );
}

/* ─── WP class stubs ─────────────────────────────────────────────────── */
class WP_REST_Server {
    const READABLE  = 'GET';
    const CREATABLE = 'POST';
}
class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_header($k) { return null; }
}
class WP_REST_Response {
    public function __construct(public $data = null, public $status = 200) {}
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}
class WP_Error {
    public function __construct(public $code = '', public $message = '', public $error_data = null) {}
}
class PQM_Stub_WPDB {
    public $prefix = 'wp_';
    public $rows = [];     // get_reviews_for_post() rows, newest first
    public $inserts = 0;   // History rows written
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $output = ARRAY_A) { return $this->rows; }
    public function insert(...$a) { $this->inserts++; return 1; }
}
$wpdb = new PQM_Stub_WPDB();

require __DIR__ . '/fs-stub.php'; // the Freemius SDK stand-in (story 44)
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Runner + fixtures ──────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/** A parsed block as WP_Block_Parser returns it. */
function blk(?string $name, array $attrs = [], array $inner = [], array $content = []): array {
    return [
        'blockName'    => $name,
        'attrs'        => $attrs,
        'innerBlocks'  => $inner,
        'innerHTML'    => implode('', array_filter($content, 'is_string')),
        'innerContent' => $content,
    ];
}
function ws(string $s = "\n\n"): array { return blk(null, [], [], [$s]); }
function para(string $text, array $attrs = []): array { return blk('core/paragraph', $attrs, [], ["\n<p>$text</p>\n"]); }
function li_html(array $items): string {
    return implode('', array_map(static fn($i) => "<!-- wp:list-item --><li>$i</li><!-- /wp:list-item -->", $items));
}
/** The plain-list copy as serialized. */
function list_html(array $items, string $command = 'takeaways'): string {
    return '<!-- wp:list {"metadata":{"pqmInsert":"' . $command . '"}} --><ul class="wp-block-list">' . li_html($items) . '</ul><!-- /wp:list -->';
}
/** An editor-saved core/list (items between newlines), as parsed. */
function parsed_list(array $items, array $attrs = [], string $tag = 'ul'): array {
    $lis = array_map(static fn($i) => blk('core/list-item', [], [], ["\n<li>$i</li>\n"]), $items);
    $content = ["\n<$tag class=\"wp-block-list\">"];
    foreach ($lis as $k => $li) { if ($k) { $content[] = "\n\n"; } $content[] = null; }
    $content[] = "</$tag>\n";
    return blk('core/list', $attrs, $lis, $content);
}
function marked(array $block, string $command = 'takeaways'): array {
    $block['attrs']['metadata']['pqmInsert'] = $command;
    return $block;
}
function place(array $blocks, array $items, array $copy, string $command): string {
    return serialize_blocks(PQM_Blocks::place($blocks, $items, $copy, $command));
}

$four  = ['Sleep 8 hours.', 'Walk daily.', 'Eat fiber.', 'Drink water.'];
$three = ['https://a.example', 'https://b.example', 'https://c.example'];
$intro = para('Intro');
$outro = para('Outro');

/* The owner's pattern (Appearance → Patterns): Group › Heading + List of 4
 * sample items, then a Spacer — parsed as WP_Block_Parser returns it. */
$heading = blk('core/heading', [], [], ["\n<h2 class=\"wp-block-heading\">Key Takeaways</h2>\n"]);
$group_attrs = ['metadata' => ['name' => 'Key Takeaways'], 'backgroundColor' => 'accent-5', 'layout' => ['type' => 'constrained']];
$group_open  = "\n<div class=\"wp-block-group has-accent-5-background-color has-background\">";
function owner_group(array $list, array $attrs, array $heading, string $open): array {
    return blk('core/group', $attrs, [$heading, $list], [$open, null, "\n\n", null, "</div>\n"]);
}
$sample  = ['Item 1', 'Item 2', 'Item 3', 'Item 4'];
$spacer  = blk('core/spacer', ['height' => '60px'], [], ["\n<div style=\"height:60px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n"]);
$pattern = [owner_group(parsed_list($sample), $group_attrs, $heading, $group_open), ws(), $spacer];

/** The owner's pattern after an insert of $items for $command, serialized. */
function owner_html(array $items, string $command = 'takeaways'): string {
    return '<!-- wp:group {"metadata":{"name":"Key Takeaways","pqmInsert":"' . $command . '"},"backgroundColor":"accent-5","layout":{"type":"constrained"}} -->'
        . "\n<div class=\"wp-block-group has-accent-5-background-color has-background\">"
        . "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Key Takeaways</h2>\n<!-- /wp:heading -->"
        . "\n\n"
        . "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . li_html($items) . "</ul>\n<!-- /wp:list -->"
        . "</div>\n<!-- /wp:group -->"
        . '<!-- wp:spacer {"height":"60px","metadata":{"pqmInsert":"' . $command . '"}} -->'
        . "\n<div style=\"height:60px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->";
}

/* Row: plain list, new */
echo "Plain list, new\n";
$out = place([$intro, ws(), $outro], $four, [], 'takeaways');
check('wp:list marked pqmInsert "takeaways" with 4 list items, prepended, whitespace, then the post untouched',
    $out === list_html($four) . "\n\n" . serialize_blocks([$intro, ws(), $outro]));
check('opens with <!-- wp:list {"metadata":{"pqmInsert":"takeaways"}} -->', str_starts_with($out, '<!-- wp:list {"metadata":{"pqmInsert":"takeaways"}} -->'));
check('4 wp:list-item children', substr_count($out, '<!-- wp:list-item -->') === 4);
check('marker in the comment attributes only, never in the HTML', !str_contains($out, 'class="wp-block-list pqm') && !str_contains($out, 'pqm-takeaways'));
check('empty post → the list alone', place([], $four, [], 'takeaways') === list_html($four));

/* Row: re-insert */
echo "Re-insert\n";
$old  = marked(parsed_list(['Old']));
$post = [$intro, ws(), $old, ws(), $outro];
check('marked block mid-content replaced in place, nothing else changes',
    place($post, $four, [], 'takeaways') === serialize_blocks([$intro, ws()]) . list_html($four) . serialize_blocks([ws(), $outro]));
check('marker found among other metadata keys',
    place([marked(parsed_list(['Old'], ['metadata' => ['name' => 'Mine']]))], $four, [], 'takeaways') === list_html($four));
$hand = parsed_list(['Mine']);
check('hand-made core/list at the top is never replaced (plain list has no block name to match)',
    place([$hand, ws(), $outro], $four, [], 'takeaways') === list_html($four) . "\n\n" . serialize_blocks([$hand, ws(), $outro]));
check('stray parts of an earlier copy elsewhere are removed too',
    place([marked(para('A')), ws(), $intro, ws(), marked(para('B'))], $four, [], 'takeaways')
    === list_html($four) . serialize_blocks([ws(), $intro, ws()]));

/* Row: pattern, new */
echo "Pattern, new\n";
$out = place([$intro, ws(), $outro], $three, $pattern, 'takeaways');
check('Group (attrs + wrapper kept, metadata.pqmInsert added), Heading untouched, List with the 3 items only, Spacer marked; prepended in pattern order',
    $out === owner_html($three) . "\n\n" . serialize_blocks([$intro, ws(), $outro]));
check('no sample item left', !str_contains($out, 'Item 1'));
check('the pattern itself is not edited (fixture still holds its sample items)',
    serialize_blocks($pattern) === serialize_blocks([owner_group(parsed_list($sample), $group_attrs, $heading, $group_open), ws(), $spacer]));
$ol = [blk('core/group', [], [para('Lead'), parsed_list(['x'], ['ordered' => true, 'className' => 'is-style-checks'], 'ol'), parsed_list(['keep me'])],
    ["<div class=\"wp-block-group\">", null, "\n", null, "\n", null, "</div>"])];
$out = place([], ['A', 'B'], $ol, 'takeaways');
check('only the first List (depth-first) gets the items; its attrs and <ol> wrapper are kept; a second List is untouched',
    str_contains($out, '<!-- wp:list {"ordered":true,"className":"is-style-checks"} -->' . "\n<ol class=\"wp-block-list\">" . li_html(['A', 'B']) . "</ol>\n<!-- /wp:list -->")
    && str_contains($out, '<li>keep me</li>') && !str_contains($out, '<li>x</li>'));
check('a top-level List pattern works the same',
    place([], ['A'], [parsed_list(['x'], ['className' => 'is-style-x'])], 'takeaways')
    === '<!-- wp:list {"className":"is-style-x","metadata":{"pqmInsert":"takeaways"}} -->' . "\n<ul class=\"wp-block-list\">" . li_html(['A']) . "</ul>\n<!-- /wp:list -->");
check('a copy without a List → plain list', place([], ['A'], [para('No list')], 'takeaways') === list_html(['A']));

/* Row: pattern re-insert */
echo "Pattern re-insert\n";
$first = PQM_Blocks::place([$intro, ws(), $outro], $three, $pattern, 'takeaways');
$again = place($first, ['New one.'], $pattern, 'takeaways');
check('earlier Group + Spacer replaced by one new copy, no duplicate Spacer',
    $again === owner_html(['New one.']) . "\n\n" . serialize_blocks([$intro, ws(), $outro]) && substr_count($again, '<!-- wp:spacer') === 1);
// After an editor save the copy's blocks are separated by whitespace.
$saved = [$first[0], ws(), $first[1], ws(), $intro, ws(), $outro];
$again = place($saved, ['New one.'], $pattern, 'takeaways');
check('editor-saved copy (whitespace between Group and Spacer) → one copy, the whitespace between them goes with it',
    $again === owner_html(['New one.']) . serialize_blocks([ws(), $intro, ws(), $outro]));

/* Row: setting changed */
echo "Setting changed\n";
check('marked plain list in the post, now a pattern → replaced in place by the pattern copy',
    place([$intro, ws(), marked(parsed_list(['Old'])), ws(), $outro], $three, $pattern, 'takeaways')
    === serialize_blocks([$intro, ws()]) . owner_html($three) . serialize_blocks([ws(), $outro]));
check('…and back: the marked pattern copy becomes a plain list',
    place($first, $four, [], 'takeaways') === list_html($four) . "\n\n" . serialize_blocks([$intro, ws(), $outro]));

/* Row: hand-made copy */
echo "Hand-made copy\n";
$hand_group = owner_group(parsed_list(['Mine 1', 'Mine 2']), $group_attrs, $heading, $group_open);
$out = place([ws("\n"), $hand_group, ws(), $spacer, ws(), $outro], $three, $pattern, 'takeaways');
check('unmarked Group "Key Takeaways" + Spacer at the top → that run replaced by the new copy',
    $out === "\n" . owner_html($three) . serialize_blocks([ws(), $outro]));
check('no second copy', substr_count($out, 'Key Takeaways</h2>') === 1 && !str_contains($out, 'Mine 1'));
$other = owner_group(parsed_list(['Mine']), ['metadata' => ['name' => 'Summary']], $heading, $group_open);
check('first block named differently → not matched, copy prepended',
    place([$other, ws(), $spacer], $three, $pattern, 'takeaways') === owner_html($three) . "\n\n" . serialize_blocks([$other, ws(), $spacer]));
check('names out of order (Group then Paragraph) → not matched, copy prepended',
    place([$hand_group, ws(), $outro], $three, $pattern, 'takeaways') === owner_html($three) . "\n\n" . serialize_blocks([$hand_group, ws(), $outro]));
$nameless = [owner_group(parsed_list($sample), ['layout' => ['type' => 'constrained']], $heading, $group_open), $spacer];
check('pattern whose first block has no name → no fallback, copy prepended',
    substr_count(place([$hand_group, ws(), $spacer], $three, $nameless, 'takeaways'), '<!-- wp:spacer') === 2);

/* Row: same names, mid-post */
echo "Same names, mid-post\n";
check('unmarked "Key Takeaways" Group not at the edge is left alone; the copy is prepended',
    place([$intro, ws(), $hand_group, ws(), $spacer], $three, $pattern, 'takeaways')
    === owner_html($three) . "\n\n" . serialize_blocks([$intro, ws(), $hand_group, ws(), $spacer]));

/* Row: sources */
echo "Sources\n";
check('plain list: 3 sources appended last after block whitespace',
    place([$intro, ws(), $outro], $three, [], 'sources') === serialize_blocks([$intro, ws(), $outro]) . "\n\n" . list_html($three, 'sources'));
$tk_only = PQM_Blocks::place([], $four, $pattern, 'takeaways');
$out = place($tk_only, $three, $pattern, 'sources');
check('same pattern for Sources, post holds only the Takeaways copy → Sources copy appended last, Takeaways copy untouched',
    $out === owner_html($four) . "\n\n" . owner_html($three, 'sources'));
$both = PQM_Blocks::place($tk_only, $three, $pattern, 'sources');
check('a second Sources insert replaces its own copy and leaves Takeaways alone',
    place($both, ['Only one.'], $pattern, 'sources') === owner_html($four) . "\n\n" . owner_html(['Only one.'], 'sources'));
check('a second Takeaways insert replaces its own copy and leaves Sources alone',
    place($both, ['Only one.'], $pattern, 'takeaways') === owner_html(['Only one.']) . "\n\n" . owner_html($three, 'sources'));
check('Sources edge = last blocks: a hand-made copy at the end is replaced',
    place([$intro, ws(), $hand_group, ws(), $spacer, ws("\n")], $three, $pattern, 'sources')
    === serialize_blocks([$intro, ws()]) . owner_html($three, 'sources') . "\n");

/* Row: HTML in item */
echo "HTML in an item\n";
check('escaped text in the <li>', str_contains(place([], ['<b>x</b> & y'], [], 'takeaways'), '<li>&lt;b&gt;x&lt;/b&gt; &amp; y</li>'));
check('…in a pattern copy too', str_contains(place([], ['<b>x</b> & y'], $pattern, 'takeaways'), '<li>&lt;b&gt;x&lt;/b&gt; &amp; y</li>'));

/* Row: settings list — PQM_Blocks::patterns() */
echo "Pattern list\n";
$pattern_html = serialize_blocks($pattern);
$GLOBALS['tw']['parsed'] = [
    $pattern_html => $pattern,
    'no-list'     => [para('Just text')],
    'draft-list'  => [parsed_list(['x'])],
    'top-list'    => [parsed_list(['y'])],
];
$GLOBALS['tw']['patterns'] = [
    31 => ['Key Takeaways', 'publish', $pattern_html],
    32 => ['Banner', 'publish', 'no-list'],
    33 => ['Draft list', 'draft', 'draft-list'],
];
check('3 user patterns, 1 without a List, 1 draft → only the published one with a List (nested at any depth)',
    PQM_Blocks::patterns() === [31 => 'Key Takeaways']);
$q = $GLOBALS['tw']['query'];
check('query: all published wp_block posts by title (no cap: the list gates insert() and the sanitizer)',
    $q['post_type'] === 'wp_block' && $q['post_status'] === 'publish' && $q['orderby'] === 'title' && $q['numberposts'] === -1);
$GLOBALS['tw']['patterns'][34] = ['A list', 'publish', 'top-list'];
check('ordered by title', array_values(PQM_Blocks::patterns()) === ['A list', 'Key Takeaways']);
unset($GLOBALS['tw']['patterns'][34]);

/* ─── Route ──────────────────────────────────────────────────────────── */
echo "Route registration\n";
PQM_REST_API::register_routes();
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/insert-block'] ?? null;
check('POST /insert-block registered', $route !== null && $route['methods'] === WP_REST_Server::CREATABLE);
check('permission_callback = check_can_insert', ($route['permission_callback'] ?? null) === ['PQM_REST_API', 'check_can_insert']);
check('args: post_id integer, command enum takeaways|sources',
    $route['args']['post_id']['type'] === 'integer' && $route['args']['command']['enum'] === ['takeaways', 'sources']);
check('no sanitize_callback on the args (PHP internals fatal on WP\'s 3 args)',
    !isset($route['args']['post_id']['sanitize_callback']) && !isset($route['args']['command']['sanitize_callback']));

$author = ['pqm_view_reviews', 'pqm_run_free_agents'];
$editor = ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents'];
function as_user(array $caps, array $can_edit, bool $pro): void {
    $GLOBALS['tw']['caps'] = $caps;
    $GLOBALS['tw']['can_edit'] = $can_edit;
    $GLOBALS['tw']['pro'] = $pro;
}
function req(int $post_id, string $command): WP_REST_Request { return new WP_REST_Request(['post_id' => $post_id, 'command' => $command]); }

/* Row: no edit rights */
echo "No edit rights\n";
as_user($editor, [], true);
check('cannot edit_post → false (WP answers 403)', PQM_REST_API::check_can_insert(req(7, 'takeaways')) === false);
as_user($editor, [8], true);
check('edit rights are per post', PQM_REST_API::check_can_insert(req(7, 'takeaways')) === false
    && PQM_REST_API::check_can_insert(req(8, 'takeaways')) === true);

/* Row: sources on a free site */
echo "Sources on a free site\n";
as_user($editor, [7], false);
$gate = PQM_REST_API::check_can_insert(req(7, 'sources'));
check('403 with the upgrade copy, as for trigger', $gate instanceof WP_Error && $gate->error_data['status'] === 403
    && $gate->message === 'Sources is a Pro agent. Upgrade to unlock it — Review and Takeaways stay free.');
check('Takeaways still allowed on a free site', PQM_REST_API::check_can_insert(req(7, 'takeaways')) === true);
as_user($author, [7], true);
$gate = PQM_REST_API::check_can_insert(req(7, 'sources'));
check('role without paid agents → 403 role copy', $gate instanceof WP_Error && $gate->error_data['status'] === 403
    && str_contains($gate->message, 'not available for your role'));
as_user($editor, [7], false);
$GLOBALS['tw']['settings'] = ['n8n_webhook_url' => 'https://n8n.example/webhook'];
$trigger = PQM_REST_API::trigger_review_callback(req(7, 'sources'));
$insert  = PQM_REST_API::check_can_insert(req(7, 'sources'));
check('trigger-review refuses with the identical gate error (shared helper)',
    $trigger instanceof WP_Error && $insert instanceof WP_Error && $trigger == $insert);
$GLOBALS['tw']['settings'] = [];

/* ─── Callback: insert() end to end ──────────────────────────────────── */
function stored(string $command, string $status, array $result): array {
    return ['id' => '1', 'post_id' => '7', 'command' => $command, 'overall_score' => '80', 'status' => $status,
        'result_data' => json_encode($result), 'reviewed_at' => '2026-10-05 09:00:00', 'created_at' => '2026-10-05 09:00:00'];
}
function run_insert(string $command, array $rows, string $content, array $parsed): WP_REST_Response|WP_Error {
    global $wpdb;
    $wpdb->rows = $rows;
    $GLOBALS['tw']['posts'] = [7 => $content];
    $GLOBALS['tw']['parsed'][$content] = $parsed; // pattern fixtures stay parseable
    $GLOBALS['tw']['updates'] = [];
    return PQM_REST_API::insert_block_callback(req(7, $command));
}
function written(): string { return stripslashes($GLOBALS['tw']['updates'][0]['post_content'] ?? ''); }
function with_pattern(string $command, $id): void { $GLOBALS['tw']['settings'] = ['agent_prompts' => [$command => ['pattern' => $id]]]; }

echo "Insert end to end\n";
as_user($editor, [7], true);
$GLOBALS['tw']['settings'] = [];
$content = '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->';
$res = run_insert('takeaways', [stored('review', 'ship', []), stored('takeaways', 'ship', ['rewritten_items' => $four])], $content, [$intro]);
check('200 { success, message }', $res instanceof WP_REST_Response && $res->get_status() === 200 && $res->data['success'] === true
    && $res->data['message'] === 'Takeaways inserted into the post. The previous version is in Revisions.');
$up = $GLOBALS['tw']['updates'][0] ?? [];
check('one wp_update_post with ID + post_content only (status kept)', count($GLOBALS['tw']['updates']) === 1 && array_keys($up) === ['ID', 'post_content'] && $up['ID'] === 7);
check('default (pattern 0): content is slashed for wp_update_post and serializes a plain list',
    $up['post_content'] === addslashes(list_html($four) . "\n\n" . serialize_blocks([$intro])));
check('no History row, no quota spent', $wpdb->inserts === 0 && $GLOBALS['tw']['meta'] === 0);

with_pattern('takeaways', 31);
run_insert('takeaways', [stored('takeaways', 'ship', ['rewritten_items' => $three])], $content, [$intro]);
check('pro site, chosen pattern: an inline copy with the items', written() === owner_html($three) . "\n\n" . serialize_blocks([$intro]));
check('never a synced wp:block reference', !str_contains(written(), 'wp:block '));
foreach (['deleted' => 99, 'unpublished' => 33, 'without a List' => 32, 'a non-number' => 'abc'] as $label => $id) {
    with_pattern('takeaways', $id);
    run_insert('takeaways', [stored('takeaways', 'ship', ['rewritten_items' => $four])], $content, [$intro]);
    check("pattern $label → plain list, insert still succeeds", written() === list_html($four) . "\n\n" . serialize_blocks([$intro]));
}
with_pattern('takeaways', 31);
as_user($editor, [7], false);
run_insert('takeaways', [stored('takeaways', 'ship', ['rewritten_items' => $four])], $content, [$intro]);
check('free site: Takeaways inserts as a plain list, whatever pattern is stored',
    written() === list_html($four) . "\n\n" . serialize_blocks([$intro]));

as_user($editor, [7], true);
with_pattern('sources', 31);
$tk_content = 'post-with-takeaways';
run_insert('sources', [stored('sources', 'needs_revision', ['rewritten_sources' => $three, 'rewritten_items' => ['nope']])], $tk_content, $tk_only);
check('sources read rewritten_sources and append the pattern copy last, Takeaways copy untouched',
    written() === owner_html($four) . "\n\n" . owner_html($three, 'sources'));
$GLOBALS['tw']['settings'] = [];
run_insert('sources', [stored('sources', 'ship', ['rewritten_sources' => $three])], $content, [$intro]);
check('sources default: plain list appended last', written() === serialize_blocks([$intro]) . "\n\n" . list_html($three, 'sources'));
run_insert('takeaways', [stored('takeaways', 'ship', ['rewritten_items' => ['  A  ', '', '   ', 'B', ['nested']]])], $content, [$intro]);
check('items trimmed, blanks and non-scalars dropped', str_starts_with(written(), list_html(['A', 'B'])));

/* Row: no items */
echo "No items → 409\n";
foreach ([
    'no row at all'                         => [],
    'only other commands'                   => [stored('review', 'ship', ['rewritten_items' => $four])],
    'empty items'                           => [stored('takeaways', 'ship', ['rewritten_items' => []])],
    'latest row still processing'           => [stored('takeaways', 'processing', ['summary' => 'Running…']), stored('takeaways', 'ship', ['rewritten_items' => $four])],
    'latest row failed'                     => [stored('takeaways', 'error', ['error' => 'Invalid JSON.'])],
    'items not a list'                      => [stored('takeaways', 'ship', ['rewritten_items' => 'A, B'])],
] as $label => $rows) {
    $res = run_insert('takeaways', $rows, $content, [$intro]);
    check("$label: 409 \"Run Takeaways first, then insert.\", nothing written",
        $res instanceof WP_Error && $res->error_data['status'] === 409 && $res->message === 'Run Takeaways first, then insert.'
        && $GLOBALS['tw']['updates'] === []);
}
$res = run_insert('sources', [], $content, [$intro]);
check('Sources copy names Sources', $res instanceof WP_Error && $res->message === 'Run Sources first, then insert.');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
