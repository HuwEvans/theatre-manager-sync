<?php
/**
 * Test script to verify orphaned attachment detection
 * Tests the scenario where:
 * 1. A board member is synced (image attached)
 * 2. The board member post is deleted
 * 3. Sync runs again
 * Expected: Image is reused, no duplicate created
 */

// Setup WordPress
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

echo "=== Orphaned Attachment Detection Test ===\n\n";

// Get a board member with a photo
$board_members = get_posts([
    'post_type' => 'board_member',
    'posts_per_page' => 1,
    'meta_key' => '_tm_photo',
    'meta_compare' => 'EXISTS'
]);

if (empty($board_members)) {
    echo "ERROR: No board members with photos found\n";
    exit(1);
}

$board_member = $board_members[0];
$photo_id = get_post_meta($board_member->ID, '_tm_photo', true);
$attachment = get_post($photo_id);

echo "TEST SETUP:\n";
echo "- Board member: " . $board_member->post_title . " (ID: " . $board_member->ID . ")\n";
echo "- Photo attachment: " . $attachment->post_title . " (ID: " . $photo_id . ")\n";
echo "- Attachment file: " . get_attached_file($photo_id) . "\n\n";

// Get the filename
$attached_file = get_attached_file($photo_id);
$filename = basename($attached_file);

echo "STEP 1: Count attachments in media library before deletion\n";
$before_count = count(get_posts(['post_type' => 'attachment', 'posts_per_page' => -1]));
echo "- Total attachments before: $before_count\n\n";

echo "STEP 2: Delete the board member post\n";
wp_delete_post($board_member->ID, true);
echo "- Board member post deleted\n\n";

echo "STEP 3: Verify attachment is orphaned (still exists in media library)\n";
$orphaned_attachment = get_post($photo_id);
if ($orphaned_attachment) {
    echo "- ✓ Attachment still exists in media library (orphaned)\n";
    echo "- Attachment ID: " . $photo_id . "\n";
    echo "- Attachment file: " . get_attached_file($photo_id) . "\n";
} else {
    echo "- ERROR: Attachment was deleted with post\n";
    exit(1);
}

echo "\nSTEP 4: Create new board member with same data\n";

// Create a new board member with same photo URL
$new_post = wp_insert_post([
    'post_type' => 'board_member',
    'post_title' => 'Test Board Member (New)',
    'post_status' => 'publish'
]);

update_post_meta($new_post, '_tm_sp_id', 'test-sp-id-' . time());
echo "- New board member created (ID: $new_post)\n\n";

echo "STEP 5: Test orphaned attachment detection\n";

// Load the generic-image-sync functions
require_once('C:/xampp/htdocs/wordpress/wp-content/plugins/theatre-manager-sync/includes/sync/generic-image-sync.php');

// Test the orphaned attachment finder
$found_orphaned = tm_sync_find_attachment_by_filename($filename, 'photo');

if ($found_orphaned && intval($found_orphaned) === intval($photo_id)) {
    echo "- ✓ Orphaned attachment detected correctly\n";
    echo "- Found attachment ID: $found_orphaned\n";
    echo "- Original attachment ID: $photo_id\n";
    echo "- Match: YES\n\n";
} else {
    echo "- ERROR: Orphaned attachment not detected\n";
    echo "- Expected: $photo_id\n";
    echo "- Got: " . var_export($found_orphaned, true) . "\n";
    exit(1);
}

echo "STEP 6: Test tm_sync_attachment_exists with new post\n";

$exists_result = tm_sync_attachment_exists($new_post, $filename, '_tm_photo', 'photo');

if ($exists_result && intval($exists_result) === intval($photo_id)) {
    echo "- ✓ Function reattached orphaned file to new post\n";
    echo "- Returned attachment ID: $exists_result\n";
    echo "- New post meta now contains: " . get_post_meta($new_post, '_tm_photo', true) . "\n\n";
} else {
    echo "- ERROR: Failed to reattach orphaned file\n";
    echo "- Expected: $photo_id\n";
    echo "- Got: " . var_export($exists_result, true) . "\n";
    exit(1);
}

echo "STEP 7: Count attachments in media library after\n";
$after_count = count(get_posts(['post_type' => 'attachment', 'posts_per_page' => -1]));
echo "- Total attachments after: $after_count\n";
echo "- Difference: " . ($after_count - $before_count) . " new attachments\n";
echo "- Expected: 0 (no duplicates created)\n\n";

if ($after_count === $before_count) {
    echo "✓ SUCCESS: No duplicate images created!\n";
    echo "- The system correctly detected and reused the orphaned attachment\n";
} else {
    echo "✗ FAILURE: Duplicate images were created\n";
    exit(1);
}

// Cleanup
wp_delete_post($new_post, true);
echo "\n✓ Test cleanup complete\n";
