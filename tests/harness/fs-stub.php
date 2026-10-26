<?php
/**
 * Shared Freemius SDK stand-in for the CLI harnesses (story 44). Required
 * before any plugin file: postquality_fs() is then already defined, so the main
 * plugin file's function_exists guard skips the SDK bootstrap and
 * PostQuality_License reads this object instead. Every harness runs without
 * WordPress and without the SDK.
 *
 * Switch the plan per row: postquality_fs()->pro = true (a valid license);
 * has_license = true with pro = false is an expired or cancelled license.
 * is__premium_only() is always true: the harnesses test the Pro build
 * (ADR 0014), every `if ( postquality_fs()->is__premium_only() )` block runs, as in
 * the zip Freemius serves to licensed sites. The Free build is checked on
 * the stripped tree (tests/harness/free-build-harness.php).
 */

class PQM_FS_Stub {
    /** can_use_premium_code(): a trial or a features-enabled license. */
    public $pro = false;
    /** _get_license(): this site's license, valid or not. */
    public $has_license = false;
    /** is_registered(): the site opted in or activated a license (false = skipped or never answered); stays true after an opt-out. */
    public $registered = false;
    /** is_tracking_allowed(): the site shares usage data now (false = skipped, or opted out since) (bug 90). */
    public $tracking = false;
    /** is_deactivation_snoozed(): the SDK's "temporary deactivation" period is running (bug 90). */
    public static $snoozed = false;
    /** get_storage(): the SDK's storage; records every key removed through it (bug 90). */
    public $storage;
    /** Hooks added through the SDK: tag => [callback, …]. */
    public $actions = [];
    public $filters = [];

    public function __construct() { $this->storage = new PQM_FS_Storage_Stub(); }
    public function can_use_premium_code() { return $this->pro; }
    public function is__premium_only() { return true; }
    public function _get_license() { return $this->has_license ? (object) ['id' => 1] : null; }
    public function is_registered() { return $this->registered; }
    public function is_tracking_allowed() { return $this->tracking; }
    public static function is_deactivation_snoozed() { return self::$snoozed; }
    public function get_storage() { return $this->storage; }
    public function get_account_url() { return 'https://example.test/wp-admin/admin.php?page=postquality-dashboard-account'; }
    public function get_upgrade_url() { return 'https://example.test/wp-admin/admin.php?page=postquality-dashboard-pricing'; }
    public function add_action($tag, $callback) { $this->actions[$tag][] = $callback; }
    public function add_filter($tag, $callback) { $this->filters[$tag][] = $callback; }
}

/** FS_Storage stand-in: remove() records the key, the one call the plugin makes (bug 90). */
class PQM_FS_Storage_Stub {
    public $removed = [];
    public function remove($key) { $this->removed[] = $key; }
}

function postquality_fs() {
    static $fs = null;
    return $fs ??= new PQM_FS_Stub();
}
