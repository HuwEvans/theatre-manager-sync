<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

// Run the sync
if (function_exists('tm_sync_board_members')) {
    $result = tm_sync_board_members();
    echo $result . "\n";
} else {
    echo "Function not found\n";
}

// Check for duplicates
global $wpdb;
$dupes = $wpdb->get_results("
    SELECT meta_value, COUNT(*) as cnt
    FROM wp_postmeta
    WHERE meta_key = '_wp_attached_file'
    AND meta_value LIKE '%photo-%'
    GROUP BY meta_value
    HAVING cnt > 1
");

echo "\nFiles with duplicates:\n";
if (empty($dupes)) {
    echo "✓ No duplicate files found!\n";
} else {
    echo "✗ Found " . count($dupes) . " files with duplicates:\n";
    foreach ($dupes as $d) {
        echo "  - " . basename($d->meta_value) . " (" . $d->cnt . " copies)\n";
    }
}

?>
