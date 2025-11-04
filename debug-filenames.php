<?php
/**
 * Debug script to investigate filename matching issue
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

echo "=== Filename Matching Debug ===\n\n";

// Get all photo attachments
$attachments = get_posts([
    'post_type' => 'attachment',
    'posts_per_page' => -1,
    's' => 'photo-'
]);

echo "Found " . count($attachments) . " attachments with 'photo-' prefix\n\n";

// Group by base filename
$by_base = [];
foreach ($attachments as $att) {
    $file = get_attached_file($att->ID);
    if ($file) {
        $basename = basename($file);
        // Extract base filename (remove "photo-N-" prefix)
        if (preg_match('/^photo-\d+-(.+)$/', $basename, $matches)) {
            $base = $matches[1];
            if (!isset($by_base[$base])) {
                $by_base[$base] = [];
            }
            $by_base[$base][] = [
                'id' => $att->ID,
                'title' => $att->post_title,
                'filename' => $basename,
                'full_path' => $file
            ];
        }
    }
}

echo "Grouped by base filename:\n";
foreach ($by_base as $base => $files) {
    echo "\n  Base: $base\n";
    echo "  Duplicates: " . count($files) . "\n";
    foreach ($files as $f) {
        echo "    - ID " . $f['id'] . ": " . $f['filename'] . " (post_title: " . $f['title'] . ")\n";
    }
}

echo "\n\nAnalysis:\n";
foreach ($by_base as $base => $files) {
    if (count($files) > 1) {
        echo "⚠ DUPLICATE: $base has " . count($files) . " copies\n";
        foreach ($files as $f) {
            $parent_posts = get_posts([
                'post_type' => 'board_member',
                'meta_key' => '_tm_photo',
                'meta_value' => $f['id'],
                'posts_per_page' => 1
            ]);
            if (empty($parent_posts)) {
                echo "  - Orphaned: ID " . $f['id'] . " (" . $f['filename'] . ")\n";
            } else {
                echo "  - Linked to post " . $parent_posts[0]->ID . ": " . $parent_posts[0]->post_title . "\n";
            }
        }
    }
}

?>
