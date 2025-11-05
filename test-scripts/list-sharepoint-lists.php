<?php
/**
 * List All SharePoint Lists
 * 
 * Run from WordPress root directory:
 * php list-sharepoint-lists.php
 * 
 * This will show all available SharePoint lists and their exact names
 */

// Load WordPress
require_once('wp-load.php');

echo "\n=== SHAREPOINT LISTS DIAGNOSTIC ===\n\n";

// Check if plugin is active
if (!is_plugin_active('theatre-manager-sync/theatre-manager-sync.php')) {
    echo "✗ Theatre Manager Sync plugin is NOT active\n";
    exit(1);
}

// Check Graph Client
if (!class_exists('TM_Graph_Client')) {
    echo "✗ TM_Graph_Client class not found\n";
    exit(1);
}

try {
    $client = new TM_Graph_Client();
    echo "Creating TM_Graph_Client instance...\n";
    
    // Get access token
    $token = $client->get_access_token_public();
    if (!$token) {
        echo "✗ Failed to obtain access token\n";
        exit(1);
    }
    
    echo "✓ Access token obtained\n\n";
    
    // Now attempt to fetch all lists
    echo "Fetching SharePoint lists via Graph API...\n";
    
    // Use reflection to call private resolve_list_id to get site lists
    $reflection = new ReflectionClass('TM_Graph_Client');
    
    // Try to get site_id first by checking get_access_token_public method
    echo "\nAttempting to retrieve all lists from SharePoint...\n";
    
    // The Graph Client should have methods we can use
    // Let's try to trigger the API call directly
    
    // Instead, let's create a test by calling get_list_items with a known list name
    $test_lists = ['Seasons', 'Season', 'seasons', 'Shows', 'Cast', 'Advertisers'];
    
    echo "\nTesting different list name variations:\n";
    foreach ($test_lists as $list_name) {
        echo "\n  Testing list name: '$list_name'\n";
        try {
            $items = $client->get_list_items($list_name);
            if ($items === null) {
                echo "    → Returns NULL (list not found or API error)\n";
            } elseif (is_array($items)) {
                echo "    → SUCCESS! Returns array with " . count($items) . " items\n";
                if (!empty($items)) {
                    echo "    → First item ID: " . ($items[0]['id'] ?? 'no id') . "\n";
                    if (isset($items[0]['fields'])) {
                        echo "    → Fields available: " . implode(', ', array_slice(array_keys($items[0]['fields']), 0, 5)) . "...\n";
                    }
                }
            } else {
                echo "    → Returns: " . gettype($items) . "\n";
            }
        } catch (Exception $e) {
            echo "    → Exception: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== END DIAGNOSTIC ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
