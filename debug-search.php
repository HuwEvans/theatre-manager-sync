<?php
/**
 * Deep debug of the search algorithm
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

echo "=== Deep Debug: Filename Search Logic ===\n\n";

$filename = 'dave.jpg';
$clean_filename = basename($filename);
$filename_no_ext = pathinfo($clean_filename, PATHINFO_FILENAME);
$extension = strtolower(pathinfo($clean_filename, PATHINFO_EXTENSION));

echo "Search parameters:\n";
echo "- Search filename: $filename\n";
echo "- Clean filename: $clean_filename\n";
echo "- Base name: $filename_no_ext\n";
echo "- Extension: $extension\n\n";

global $wpdb;

// Run the exact query
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT p.ID, pm.meta_value
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type = 'attachment'
     AND pm.meta_key = '_wp_attached_file'
     AND (
        pm.meta_value LIKE %s
        OR pm.meta_value LIKE %s
        OR pm.meta_value LIKE %s
     )
     LIMIT 50",
    '%' . $clean_filename,
    '%' . strtoupper($clean_filename),
    '%' . strtolower($clean_filename)
));

echo "Query results: " . count($results) . " rows\n\n";

foreach ($results as $row) {
    $attached_filename = basename($row->meta_value);
    $attached_base = pathinfo($attached_filename, PATHINFO_FILENAME);
    $attached_ext = strtolower(pathinfo($attached_filename, PATHINFO_EXTENSION));
    
    $exact_match = (strtolower($attached_filename) === strtolower($clean_filename));
    $base_match = (strtolower($attached_base) === strtolower($filename_no_ext) && 
                   strtolower($attached_ext) === $extension);
    
    echo "ID " . $row->ID . ":\n";
    echo "  - File: " . basename($row->meta_value) . "\n";
    echo "  - Base: $attached_base\n";
    echo "  - Ext: $attached_ext\n";
    echo "  - Exact match: " . ($exact_match ? "YES" : "NO") . "\n";
    echo "  - Base match: " . ($base_match ? "YES" : "NO") . "\n";
    echo "  - INCLUDE: " . ($exact_match || $base_match ? "YES ✓" : "NO ✗") . "\n";
    echo "\n";
}

?>
