<?php
/**
 * Seasons Field Extraction Diagnostic
 * 
 * Run from WordPress root: php test-seasons-fields.php
 * 
 * Shows exactly what fields are being extracted from SharePoint
 */

require_once('wp-load.php');

echo "\n=== SEASONS FIELD EXTRACTION TEST ===\n\n";

if (!is_plugin_active('theatre-manager-sync/theatre-manager-sync.php')) {
    echo "✗ Plugin not active\n";
    exit(1);
}

if (!class_exists('TM_Graph_Client')) {
    echo "✗ TM_Graph_Client not found\n";
    exit(1);
}

try {
    echo "1. Creating Graph Client...\n";
    $client = new TM_Graph_Client();
    
    echo "2. Fetching Seasons items...\n";
    $items = $client->get_list_items('Seasons');
    
    if ($items === null || empty($items)) {
        echo "   ✗ No items returned\n";
        exit(1);
    }
    
    echo "   ✓ Got " . count($items) . " items\n\n";
    
    echo "3. First Season Item Analysis:\n";
    echo "   ============================================\n";
    $first = $items[0];
    
    if (!isset($first['fields'])) {
        echo "   ✗ ERROR: No 'fields' key in response!\n";
        echo "   Available keys: " . implode(', ', array_keys($first)) . "\n";
        echo "\n   This means the fallback is being used or the API returned data in an unexpected format\n";
        exit(1);
    }
    
    $fields = $first['fields'];
    
    echo "   ID: " . ($first['id'] ?? 'N/A') . "\n";
    echo "   Total fields: " . count($fields) . "\n\n";
    
    // Expected fields for Seasons
    $expected_fields = [
        'Title' => 'Title',
        'SeasonName' => 'Season Name',
        'StartDate' => 'Start Date',
        'EndDate' => 'End Date',
        'IsCurrentSeason' => 'Is Current Season',
        'IsUpcomingSeason' => 'Is Upcoming Season',
        'WebsireBanner' => 'Website Banner',
        '3-upFront' => '3-up Front',
        '3-upBack' => '3-up Back',
        'SMSquare' => 'Social Media Square',
        'SMPortrait' => 'Social Media Portrait',
        'Description' => 'Description'
    ];
    
    echo "   EXPECTED FIELDS:\n";
    echo "   ============================================\n";
    
    $found_count = 0;
    $missing_count = 0;
    
    foreach ($expected_fields as $field_key => $field_label) {
        $exists = isset($fields[$field_key]);
        $status = $exists ? '✓' : '✗';
        $value = $exists ? substr((string)$fields[$field_key], 0, 50) : 'N/A';
        
        printf("   %s %-20s %s\n", $status, $field_key . ':', $value);
        
        if ($exists) {
            $found_count++;
        } else {
            $missing_count++;
        }
    }
    
    echo "\n   Summary: $found_count found, $missing_count missing\n\n";
    
    echo "   ALL AVAILABLE FIELDS:\n";
    echo "   ============================================\n";
    
    foreach ($fields as $key => $value) {
        $value_str = is_string($value) ? substr($value, 0, 40) : gettype($value);
        if (is_array($value)) {
            $value_str = 'array(' . count($value) . ')';
        } elseif (is_object($value)) {
            $value_str = 'object: ' . get_class($value);
        }
        printf("   %-25s => %s\n", $key, $value_str);
    }
    
    echo "\n";
    
    if ($missing_count > 0) {
        echo "⚠ WARNING: " . $missing_count . " expected fields are missing!\n";
        echo "This could mean:\n";
        echo "  1. The field names in SharePoint are different\n";
        echo "  2. The fallback query was used (without explicit $select)\n";
        echo "  3. SharePoint fields were not created properly\n\n";
        echo "Check /wp-content/debug.log for [TM_Graph_Client] messages\n";
    } else {
        echo "✓ SUCCESS - All expected fields are present!\n";
    }
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

?>
