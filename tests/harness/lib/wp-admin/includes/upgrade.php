<?php
/**
 * Stand-in for wp-admin/includes/upgrade.php, which
 * PostQuality_Activator::create_table() requires by its ABSPATH path. A
 * harness that calls activate() defines ABSPATH as tests/harness/lib/.
 * dbDelta() records the table a CREATE TABLE names on the $wpdb stub.
 */

if ( ! function_exists( 'dbDelta' ) ) {
    function dbDelta( $queries = '', $execute = true ) {
        preg_match( '/CREATE TABLE (\S+)/', is_array( $queries ) ? implode( "\n", $queries ) : (string) $queries, $m );
        $GLOBALS['wpdb']->ddl[] = $m[1] ?? '';
        $GLOBALS['wpdb']->tables[ $m[1] ?? '' ] ??= [];
        return [];
    }
}
