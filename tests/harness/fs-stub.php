<?php
/**
 * Shared Freemius SDK stand-in for the CLI harnesses (story 44). Required
 * before any plugin file: pqm_fs() is then already defined, so the main
 * plugin file's function_exists guard skips the SDK bootstrap and
 * PQM_License reads this object instead. Every harness runs without
 * WordPress and without the SDK.
 *
 * Switch the plan per row: pqm_fs()->pro = true (a valid license);
 * has_license = true with pro = false is an expired or cancelled license.
 * is__premium_only() is always true: the harnesses test the Pro build
 * (ADR 0014), every `if ( pqm_fs()->is__premium_only() )` block runs, as in
 * the zip Freemius serves to licensed sites. The Free build is checked on
 * the stripped tree (tests/harness/free-build-harness.php).
 */

class PQM_FS_Stub {
    /** can_use_premium_code(): a trial or a features-enabled license. */
    public $pro = false;
    /** _get_license(): this site's license, valid or not. */
    public $has_license = false;
    /** is_registered(): the site opted in or activated a license (false = skipped or never answered). */
    public $registered = false;
    /** is_deactivation_snoozed(): the SDK's "temporary deactivation" period is running (bug 90). */
    public static $snoozed = false;
    /** Hooks added through the SDK: tag => [callback, …]. */
    public $actions = [];
    public $filters = [];

    public function can_use_premium_code() { return $this->pro; }
    public function is__premium_only() { return true; }
    public function _get_license() { return $this->has_license ? (object) ['id' => 1] : null; }
    public function is_registered() { return $this->registered; }
    public static function is_deactivation_snoozed() { return self::$snoozed; }
    public function get_account_url() { return 'https://example.test/wp-admin/admin.php?page=pqm-dashboard-account'; }
    public function get_upgrade_url() { return 'https://example.test/wp-admin/admin.php?page=pqm-dashboard-pricing'; }
    public function add_action($tag, $callback) { $this->actions[$tag][] = $callback; }
    public function add_filter($tag, $callback) { $this->filters[$tag][] = $callback; }
}

function pqm_fs() {
    static $fs = null;
    return $fs ??= new PQM_FS_Stub();
}
