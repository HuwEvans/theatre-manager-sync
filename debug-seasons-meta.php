<?php
/**
 * Seasons CPT Meta Data Diagnostic
 * 
 * Run from WordPress root: php debug-seasons-meta.php
 * 
 * Shows exactly what metadata is stored in the database for Season posts
 */

require_once('wp-load.php');

echo "\n=== SEASONS CPT META DATA DIAGNOSTIC ===\n\n";

// Get all seasons
$seasons = get_posts([
    'post_type' => 'season',
    'posts_per_page' => 5,
    'orderby' => 'date',
    'order' => 'DESC'
]);

if (empty($seasons)) {
    echo "✗ No season posts found in database\n";
    exit(1);
}

echo "Found " . count($seasons) . " season posts. Checking metadata...\n\n";

foreach ($seasons as $season) {
    echo "===================================================\n";
    echo "Season: " . $season->post_title . " (ID: " . $season->ID . ")\n";
    echo "===================================================\n\n";
    
    // Check all meta fields
    $meta_fields = [
        '_tm_sp_id' => 'SharePoint ID',
        '_tm_season_name' => 'Season Name',
        '_tm_season_start_date' => 'Start Date',
        '_tm_season_end_date' => 'End Date',
        '_tm_season_is_current' => 'Is Current',
        '_tm_season_is_upcoming' => 'Is Upcoming',
        '_tm_season_image_front' => '3-up Front Image',
        '_tm_season_image_back' => '3-up Back Image',
        '_tm_season_social_banner' => 'Website Banner',
        '_tm_season_sm_square' => 'Social Square',
        '_tm_season_sm_portrait' => 'Social Portrait',
    ];
    
    foreach ($meta_fields as $meta_key => $label) {
        $value = get_post_meta($season->ID, $meta_key, true);
        
        if (empty($value)) {
            echo "  ✗ $label ($meta_key): EMPTY\n";
        } else {
            if (strlen($value) > 60) {
                $display = substr($value, 0, 57) . '...';
            } else {
                $display = $value;
            }
            echo "  ✓ $label ($meta_key): $display\n";
        }
    }
    
    echo "\n";
}

echo "===================================================\n";
echo "CHECKING RAW DATABASE META VALUES\n";
echo "===================================================\n\n";

// Get raw meta data directly from database
global $wpdb;

$season_ids = array_map(function($s) { return $s->ID; }, $seasons);
$id_list = implode(',', $season_ids);

$result = $wpdb->get_results("
    SELECT post_id, meta_key, meta_value 
    FROM $wpdb->postmeta 
    WHERE post_id IN ($id_list)
    AND meta_key LIKE '_tm_season%'
    ORDER BY post_id, meta_key
");

foreach ($result as $row) {
    echo "Post ID " . $row->post_id . " | " . $row->meta_key . ": ";
    
    if (strlen($row->meta_value) > 60) {
        echo substr($row->meta_value, 0, 57) . "...\n";
    } else {
        echo $row->meta_value . "\n";
    }
}

echo "\n";
?>
