<?php
/**
 * Shared WordPress stub world + runner for the CLI harnesses (story 78,
 * build 1). Every harness requires it after its own specific stubs, or at
 * the top when its checks start before its stub block (free-build): PHP
 * hoists the harness's declarations, and every declaration here is
 * guarded, so a stub the harness declared anywhere in its file wins and
 * nothing redeclares.
 * Holds only the runner, the WP classes and the stubs whose meaning is the
 * same in every harness. Lives in lib/ so CI's `tests/harness/*.php` loop
 * never runs it alone; run by hand it defines and exits 0.
 *
 * Options store: $GLOBALS['tw']['options'] (name => value). update_option()
 * records the autoload flag in tw.autoload and the name in tw.writes;
 * delete_option() appends "-name" to tw.writes. A harness seeds defaults
 * into tw.options instead of branching in the stub.
 */

error_reporting(E_ALL);

require_once __DIR__ . '/../fs-stub.php'; // the Freemius SDK stand-in (story 44)

/* ─── Runner ─────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
if (!function_exists('check')) {
    function check($label, $cond) {
        global $pass, $fail;
        if ($cond) { $pass++; echo "  ok  $label\n"; }
        else { $fail++; echo "  FAIL $label\n"; }
    }
}
if (!function_exists('harness_exit')) {
    function harness_exit(): void {
        global $pass, $fail;
        echo "\n$pass passed, $fail failed\n";
        exit($fail === 0 ? 0 : 1);
    }
}

/* ─── WP classes ─────────────────────────────────────────────────────── */
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public $code = '', public $message = '', public $data = null) {}
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
        public function get_error_data() { return $this->data; }
        public function add_data($data) { $this->data = $data; }
    }
}
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE  = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE  = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function __construct(private array $params = [], private $json = null) {}
        public function get_param($k) { return $this->params[$k] ?? null; }
        public function set_param($k, $v) { $this->params[$k] = $v; }
        public function get_json_params() { return $this->json; }
        public function get_header($k) { return null; }
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(public $data = null, public $status = 200) {}
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
        public function header($k, $v) {}
    }
}
if (!class_exists('WP_Role')) {
    class WP_Role {}
}

/* ─── Translation + escaping ─────────────────────────────────────────── */
if (!function_exists('__')) { function __($s, $d = null) { return $s; } }
if (!function_exists('_x')) { function _x($s, $context, $d = null) { return $s; } }
if (!function_exists('esc_html__')) { function esc_html__($s, $d = null) { return $s; } }
if (!function_exists('esc_attr__')) { function esc_attr__($s, $d = null) { return $s; } }
if (!function_exists('_e')) { function _e($s, $d = null) { echo $s; } }
if (!function_exists('esc_html_e')) { function esc_html_e($s, $d = null) { echo $s; } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($s, $d = null) { echo $s; } }
if (!function_exists('esc_html')) { function esc_html($s) { return (string) $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return (string) $s; } }
if (!function_exists('esc_url')) { function esc_url($s) { return (string) $s; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($s) { return trim((string) $s); } }
if (!function_exists('wp_kses')) { function wp_kses($s, $allowed_html = [], $allowed_protocols = []) { return (string) $s; } }

/* ─── Sanitizing + helpers ───────────────────────────────────────────── */
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); } }
if (!function_exists('absint')) { function absint($n) { return abs((int) $n); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($u, $c = -1) { return parse_url($u, $c); } }
if (!function_exists('wp_parse_args')) { function wp_parse_args($args, $defaults = []) { return array_merge($defaults, (array) $args); } }
if (!function_exists('maybe_serialize')) { function maybe_serialize($d) { return is_array($d) || is_object($d) ? serialize($d) : $d; } }
if (!function_exists('maybe_unserialize')) { function maybe_unserialize($d) { $u = is_string($d) ? @unserialize($d) : false; return $u === false ? $d : $u; } }
if (!function_exists('is_wp_error')) { function is_wp_error($t) { return $t instanceof WP_Error; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? ''; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); } }
if (!function_exists('wp_generate_uuid4')) { function wp_generate_uuid4() { return $GLOBALS['tw']['uuid'] ?? 'stub-uuid'; } }
if (!function_exists('untrailingslashit')) { function untrailingslashit($v) { return rtrim((string) $v, '/\\'); } }
if (!function_exists('trailingslashit')) { function trailingslashit($v) { return untrailingslashit($v) . '/'; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return dirname($f) . '/'; } }

/* ─── Hooks (no-ops) ─────────────────────────────────────────────────── */
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('do_action')) { function do_action(...$a) {} }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$a) {} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$a) {} }

/* ─── Options store on $GLOBALS['tw']['options'] ─────────────────────── */
if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        $options = $GLOBALS['tw']['options'] ?? [];
        return array_key_exists($k, $options) ? $options[$k] : $d;
    }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $autoload = null) {
        $GLOBALS['tw']['options'][$k] = $v;
        $GLOBALS['tw']['autoload'][$k] = $autoload;
        $GLOBALS['tw']['writes'][] = $k;
        return true;
    }
}
if (!function_exists('add_option')) {
    function add_option($k, $v, $deprecated = '', $autoload = null) {
        if (array_key_exists($k, $GLOBALS['tw']['options'] ?? [])) { return false; }
        return update_option($k, $v, $autoload);
    }
}
if (!function_exists('delete_option')) {
    function delete_option($k) {
        unset($GLOBALS['tw']['options'][$k]);
        $GLOBALS['tw']['writes'][] = "-$k";
        return true;
    }
}
