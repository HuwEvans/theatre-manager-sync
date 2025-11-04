<?php
/**
 * Simple Seasons Sync Test
 * 
 * Run from WordPress root: php test-seasons-only.php
 * 
 * Tests specifically why Seasons sync is failing
 */

require_once('wp-load.php');

echo "\n=== SEASONS SYNC TEST ===\n\n";

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
    
    echo "2. Testing get_list_items('Seasons')...\n";
    $items = $client->get_list_items('Seasons');
    
    if ($items === null) {
        echo "   ✗ Returned NULL\n";
        echo "   Check WordPress debug.log for error messages\n";
        exit(1);
    }
    
    if (!is_array($items)) {
        echo "   ✗ Returned " . gettype($items) . " instead of array\n";
        exit(1);
    }
    
    if (empty($items)) {
        echo "   ⚠ Returned empty array (no items in list)\n";
        exit(0);
    }
    
    echo "   ✓ Got " . count($items) . " items\n\n";
    
    echo "3. First item details:\n";
    $first = $items[0];
    echo "   ID: " . ($first['id'] ?? 'N/A') . "\n";
    
    if (isset($first['fields'])) {
        $fields = $first['fields'];
        echo "   Fields available: " . implode(', ', array_keys($fields)) . "\n";
        echo "   Title: " . ($fields['Title'] ?? 'N/A') . "\n";
        echo "   SeasonName: " . ($fields['SeasonName'] ?? 'N/A') . "\n";
    } else {
        echo "   ✗ No 'fields' key in response\n";
        echo "   Available keys: " . implode(', ', array_keys($first)) . "\n";
    }
    
    echo "\n✓ SUCCESS - Seasons data is being retrieved from SharePoint\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

?>
