<?php
/**
 * Quick test to inspect SharePoint Testimonials list structure
 * Run this from WordPress admin bar or test page
 */

if (!defined('ABSPATH')) {
    wp_die('WordPress not loaded');
}

// Check if we're in WordPress admin
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

// Include necessary files
require_once dirname(__FILE__) . '/theatre-manager-sync/includes/api/class-tm-graph-client.php';
require_once dirname(__FILE__) . '/theatre-manager-sync/includes/logger.php';

echo "<pre style='background:#f5f5f5; padding: 20px; font-family: monospace;'>\n";
echo "=== Testimonials SharePoint List Inspector ===\n\n";

try {
    $client = new TM_Graph_Client();
    $items = $client->get_list_items('Testimonials');
    
    if (!$items || !is_array($items)) {
        echo "ERROR: Could not fetch items\n";
        echo "</pre>";
        return;
    }
    
    echo "Found " . count($items) . " testimonials\n\n";
    
    if (count($items) > 0) {
        $item = $items[0];
        echo "=== First Testimonial Structure ===\n\n";
        
        echo "Item ID: " . ($item['id'] ?? 'N/A') . "\n\n";
        
        if (isset($item['fields'])) {
            echo "Fields object found\n\n";
            $fields = $item['fields'];
        } else {
            echo "No fields wrapper, using item directly\n\n";
            $fields = $item;
        }
        
        echo "Available field names:\n";
        $field_names = array_keys((array)$fields);
        foreach ($field_names as $name) {
            echo "  - " . $name . "\n";
        }
        
        echo "\n\n=== Field Values ===\n\n";
        foreach ($field_names as $name) {
            $value = $fields[$name];
            echo "Field: {$name}\n";
            echo "  Type: " . gettype($value) . "\n";
            echo "  Value: " . (is_string($value) || is_numeric($value) ? $value : json_encode($value)) . "\n";
            echo "\n";
        }
        
        echo "\n=== Ratingnumber Field Specifically ===\n\n";
        if (isset($fields['Ratingnumber'])) {
            $rating = $fields['Ratingnumber'];
            echo "Found: YES\n";
            echo "Type: " . gettype($rating) . "\n";
            echo "Value: " . (is_string($rating) || is_numeric($rating) ? $rating : json_encode($rating)) . "\n";
            echo "As int: " . intval($rating) . "\n";
        } else {
            echo "Found: NO\n";
            echo "\nSearching for rating-like fields:\n";
            foreach ($field_names as $name) {
                if (stripos($name, 'rat') !== false || stripos($name, 'star') !== false || stripos($name, 'score') !== false || stripos($name, 'review') !== false) {
                    echo "  - Possible match: {$name} = " . json_encode($fields[$name]) . "\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n</pre>\n";
?>
