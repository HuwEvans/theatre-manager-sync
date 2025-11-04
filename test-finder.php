<?php
/**
 * Test the updated orphaned attachment finder
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');
require_once('C:/xampp/htdocs/wordpress/wp-content/plugins/theatre-manager-sync/includes/sync/generic-image-sync.php');

echo "=== Testing Updated Orphaned Attachment Finder ===\n\n";

echo "Test 1: Find dave.jpg (should find most recent with photo-9- prefix)\n";
$result = tm_sync_find_attachment_by_filename('dave.jpg', 'photo');
echo "Result: " . ($result ? "ID $result" : "null") . "\n\n";

echo "All dave.jpg files:\n";
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT p.ID, pm.meta_value
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type = 'attachment'
     AND pm.meta_key = '_wp_attached_file'
     AND pm.meta_value LIKE %s",
    '%dave.jpg'
));

foreach ($results as $row) {
    $parent = get_posts([
        'post_type' => 'board_member',
        'meta_key' => '_tm_photo',
        'meta_value' => $row->ID,
        'posts_per_page' => 1
    ]);
    
    $status = empty($parent) ? "ORPHANED" : "LINKED to post " . $parent[0]->ID;
    echo "  - ID " . str_pad($row->ID, 3) . ": " . str_pad(basename($row->meta_value), 20) . " [$status]\n";
}

?>
