<?php
require_once __DIR__ . '/../../../wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'pqm_reviews';
$rows = $wpdb->get_results("SELECT * FROM $table WHERE command = 'sources' ORDER BY id DESC LIMIT 2", ARRAY_A);
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . "\n";
    echo "Result Data (Raw): " . var_export($row['result_data'], true) . "\n";
    echo "Result Data (Decoded): " . var_export(json_decode($row['result_data'], true), true) . "\n\n";
}
