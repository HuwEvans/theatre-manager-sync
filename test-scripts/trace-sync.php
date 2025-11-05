<?php
/**
 * Test to trace exactly what happens during sync
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

echo "=== Tracing Sync Behavior for dave.jpg ===\n\n";

// Find the Executive Producer post
$exec_producer = get_posts([
    'post_type' => 'board_member',
    's' => 'Executive Producer',
    'posts_per_page' => 1
])[0] ?? null;

if (!$exec_producer) {
    echo "ERROR: Could not find Executive Producer post\n";
    exit(1);
}

echo "TARGET POST:\n";
echo "- Post ID: " . $exec_producer->ID . "\n";
echo "- Post Title: " . $exec_producer->post_title . "\n";
echo "- SP ID: " . get_post_meta($exec_producer->ID, '_tm_sp_id', true) . "\n";
echo "- Current Photo ID: " . get_post_meta($exec_producer->ID, '_tm_photo', true) . "\n\n";

// Get current attachment
$current_photo_id = get_post_meta($exec_producer->ID, '_tm_photo', true);
$current_attachment = get_post($current_photo_id);
if ($current_attachment) {
    echo "CURRENT PHOTO:\n";
    echo "- Attachment ID: " . $current_attachment->ID . "\n";
    echo "- Post Title: " . $current_attachment->post_title . "\n";
    echo "- File: " . get_attached_file($current_attachment->ID) . "\n\n";
}

// Load the sync functions
require_once('C:/xampp/htdocs/wordpress/wp-content/plugins/theatre-manager-sync/includes/sync/folder-discovery.php');
require_once('C:/xampp/htdocs/wordpress/wp-content/plugins/theatre-manager-sync/includes/sync/generic-image-sync.php');

// Simulate what tm_sync_attachment_exists does
$filename = 'dave.jpg';
$meta_key = '_tm_photo';
$image_type = 'photo';

echo "SIMULATING tm_sync_attachment_exists():\n";
echo "- Searching for: $filename\n";
echo "- Meta key: $meta_key\n\n";

// Check 1: Post meta
echo "STEP 1: Check post meta\n";
$meta_result = get_post_meta($exec_producer->ID, $meta_key, true);
if ($meta_result) {
    echo "- ✓ Found in post meta: $meta_result\n\n";
} else {
    echo "- ✗ Not found in post meta\n\n";
}

// Check 2: Media library search
echo "STEP 2: Search media library for orphaned file\n";
$orphaned = tm_sync_find_attachment_by_filename($filename, $image_type);
echo "- Result: " . ($orphaned ? "ID $orphaned" : "null") . "\n";

if ($orphaned) {
    $orphan_post = get_post($orphaned);
    echo "- File: " . get_attached_file($orphaned) . "\n";
    echo "- This would be reattached to post " . $exec_producer->ID . "\n\n";
}

// Now check all dave.jpg files
echo "ALL dave.jpg FILES IN MEDIA LIBRARY:\n";
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT p.ID, p.post_title, pm.meta_value
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
    echo "- ID " . $row->ID . ": " . basename($row->meta_value) . " [$status]\n";
}

?>
