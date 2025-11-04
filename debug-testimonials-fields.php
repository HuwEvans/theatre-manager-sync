<?php
/**
 * Debug script to inspect Testimonials SharePoint list fields
 * Run from WordPress CLI or include in a test page
 */

// Load WordPress
require_once dirname(__DIR__, 4) . '/theatre-manager/Theatre-Manager/theatre-manager-plugin.php';

// Include the TM Graph Client
require_once dirname(__FILE__) . '/theatre-manager-sync/includes/api/class-tm-graph-client.php';
require_once dirname(__FILE__) . '/theatre-manager-sync/includes/logger.php';

echo "=== Testimonials SharePoint Field Debug ===\n\n";

// Create client and fetch items
$client = new TM_Graph_Client();
$items = $client->get_list_items('Testimonials');

if (!$items || !is_array($items)) {
    echo "ERROR: Could not fetch items from Testimonials list\n";
    exit;
}

echo "Found " . count($items) . " testimonial items\n\n";

// Inspect first item
if (count($items) > 0) {
    $item = $items[0];
    echo "=== First Item Structure ===\n";
    echo "ID: " . ($item['id'] ?? 'N/A') . "\n\n";
    
    $fields = $item['fields'] ?? $item;
    echo "Available Fields:\n";
    echo json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "=== Field Keys ===\n";
    echo implode(", ", array_keys($fields)) . "\n\n";
    
    // Check for rating-related fields
    echo "=== Rating Field Check ===\n";
    foreach (['Rating', 'Rate', 'Stars', 'Score', 'Rank'] as $field_name) {
        if (isset($fields[$field_name])) {
            echo "✓ Found '$field_name': " . json_encode($fields[$field_name]) . "\n";
        } else {
            echo "✗ Not found: '$field_name'\n";
        }
    }
}
?>
