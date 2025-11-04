<?php
/**
 * Test the actual deletion + sync scenario
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

echo "=== Testing Delete + Sync Scenario ===\n\n";

// Get Executive Producer
$exec_producer = get_posts([
    'post_type' => 'board_member',
    's' => 'Executive Producer',
    'posts_per_page' => 1
])[0] ?? null;

if (!$exec_producer) {
    // Fallback: get first board member
    $bms = get_posts(['post_type' => 'board_member', 'posts_per_page' => 1]);
    $exec_producer = $bms[0] ?? null;
}

if (!$exec_producer) {
    echo "ERROR: Could not find any board member\n";
    exit(1);
}

$sp_id = get_post_meta($exec_producer->ID, '_tm_sp_id', true);
$current_photo_id = get_post_meta($exec_producer->ID, '_tm_photo', true);

echo "BEFORE:\n";
echo "- Post ID: " . $exec_producer->ID . "\n";
echo "- SP ID: $sp_id\n";
echo "- Photo ID: $current_photo_id\n";

// Count attachments
$before_count = count(get_posts(['post_type' => 'attachment', 'posts_per_page' => -1]));
echo "- Total attachments: $before_count\n\n";

// Delete the post
echo "DELETING POST...\n";
wp_delete_post($exec_producer->ID, true);
echo "- Post deleted\n\n";

// Create new post with same data
echo "CREATING NEW POST...\n";
$new_post = wp_insert_post([
    'post_type' => 'board_member',
    'post_title' => 'Executive Producer',
    'post_status' => 'publish'
]);
update_post_meta($new_post, '_tm_sp_id', $sp_id);
echo "- New post created ID: $new_post\n";
echo "- SP ID set to: $sp_id\n";
echo "- Note: post meta '_tm_photo' is NOT set yet (will be set by sync)\n\n";

// Now simulate what the sync does
echo "SIMULATING SYNC...\n";
require_once('C:/xampp/htdocs/wordpress/wp-content/plugins/theatre-manager-sync/includes/sync/generic-image-sync.php');

// The sync would call tm_sync_image_for_post with filename 'dave.jpg'
$filename = 'dave.jpg';
$meta_key = '_tm_photo';
$image_type = 'photo';

echo "- Calling tm_sync_attachment_exists() for new post\n";
$found = tm_sync_attachment_exists($new_post, $filename, $meta_key, $image_type);

if ($found) {
    echo "- ✓ Found orphaned/existing attachment: ID $found\n";
    echo "- This attachment WOULD be reused\n\n";
} else {
    echo "- ✗ No orphaned attachment found\n";
    echo "- System WOULD download new copy from SharePoint\n\n";
}

// Check final state
$after_count = count(get_posts(['post_type' => 'attachment', 'posts_per_page' => -1]));
echo "AFTER SIMULATION:\n";
echo "- Total attachments: $after_count\n";
echo "- New attachments created: " . ($after_count - $before_count) . "\n";
echo "- Expected: 0 (should reuse orphaned file)\n\n";

if ($after_count === $before_count) {
    echo "✓ SUCCESS: No duplicate attachments created\n";
} else {
    echo "✗ FAILURE: New attachments were created\n";
}

// List all dave.jpg files now
echo "\nALL dave.jpg FILES NOW:\n";
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

// Clean up
wp_delete_post($new_post, true);

?>
