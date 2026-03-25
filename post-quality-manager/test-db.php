<?php
require_once dirname(__FILE__, 4) . '/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'pqm_reviews';
$rows = $wpdb->get_results("SELECT id, post_id, command, result_data FROM $table WHERE command = 'sources' ORDER BY id DESC LIMIT 5", ARRAY_A);
print_r($rows);
