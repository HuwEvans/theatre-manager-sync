<?php
/**
 * Verify SharePoint Shows List Field Configuration
 * 
 * This script validates that all required fields exist in the SharePoint Shows list
 * and provides guidance on populating them with data.
 * 
 * Usage: php verify-show-fields.php
 */

// Load WordPress
if (file_exists('wp-load.php')) {
    require 'wp-load.php';
} else {
    echo "ERROR: wp-load.php not found. Run this script from WordPress root directory.\n";
    exit(1);
}

// Load Graph Client
if (!class_exists('TM_Graph_Client')) {
    require_once 'wp-content/plugins/theatre-manager-sync/includes/api/class-tm-graph-client.php';
}

$client = new TM_Graph_Client();

echo "=== Shows List Field Validation ===\n\n";

// Get all show items to analyze field coverage
$items = $client->get_list_items('Shows');

if (!$items) {
    echo "ERROR: Could not retrieve Shows list items\n";
    exit(1);
}

// Track which fields are present and populated
$field_stats = [];
$shows_analysis = [];

foreach ($items as $item) {
    $fields = $item['fields'] ?? $item;
    $title = $fields['Title'] ?? 'Unknown';
    
    $shows_analysis[$title] = [
        'all_fields' => array_keys((array)$fields),
        'populated_fields' => []
    ];
    
    // Check which fields have data
    foreach ($fields as $field_name => $value) {
        if (empty($value) || $value === '{}') {
            continue; // Skip empty fields
        }
        
        $shows_analysis[$title]['populated_fields'][] = $field_name;
        
        if (!isset($field_stats[$field_name])) {
            $field_stats[$field_name] = 0;
        }
        $field_stats[$field_name]++;
    }
}

// Expected fields for Shows
$expected_fields = [
    'Title',
    'ShowName',
    'TimeSlot',
    'Author',
    'SubAuthors',
    'Director',
    'AssociateDirector',
    'StartDate',
    'EndDate',
    'ShowDatesText',
    'Description',
    'ProgramFileURL',
    'SMImage',
    'SeasonIDLookup',
    'SeasonIDLookupSeasonName'
];

echo "FIELD COVERAGE ANALYSIS\n";
echo str_repeat("-", 60) . "\n";
echo sprintf("%-30s | %10s | %10s\n", "Field Name", "Shows", "Status");
echo str_repeat("-", 60) . "\n";

foreach ($expected_fields as $field) {
    $count = $field_stats[$field] ?? 0;
    $total = count($items);
    $coverage = $count > 0 ? round(100 * $count / $total) : 0;
    
    $status = match(true) {
        $count === 0 => "⚠️  EMPTY",
        $count === $total => "✅ FULL",
        default => "⚠️  PARTIAL"
    };
    
    printf("%-30s | %7d/%d | %s\n", $field, $count, $total, $status);
}

echo "\nDETAILED SHOW ANALYSIS\n";
echo str_repeat("-", 60) . "\n";

foreach ($shows_analysis as $show => $data) {
    $populated = count($data['populated_fields']);
    $total = count($expected_fields);
    
    echo "\n$show: $populated/$total fields populated\n";
    
    $missing = array_diff($expected_fields, $data['populated_fields']);
    if (!empty($missing)) {
        echo "  Missing fields:\n";
        foreach ($missing as $field) {
            echo "    - $field\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "RECOMMENDATIONS\n";
echo str_repeat("=", 60) . "\n";

$empty_fields = array_filter($expected_fields, function($f) use ($field_stats, $items) {
    return ($field_stats[$f] ?? 0) === 0;
});
if (!empty($empty_fields)) {
    echo "\n1. Add missing data to SharePoint:\n";
    echo "   The following fields have no data in any shows:\n";
    foreach ($empty_fields as $field) {
        echo "   - $field\n";
    }
}

$partial_fields = array_filter($expected_fields, function($f) use ($field_stats, $items) {
    $c = $field_stats[$f] ?? 0;
    return $c > 0 && $c < count($items);
});

if (!empty($partial_fields)) {
    echo "\n2. Complete partial data:\n";
    echo "   The following fields exist but not all shows have data:\n";
    foreach ($partial_fields as $field) {
        $count = $field_stats[$field];
        $total = count($items);
        echo "   - $field (" . ($total - $count) . " shows missing)\n";
    }
}

echo "\n3. After adding SharePoint data:\n";
echo "   Run the sync from WordPress admin:\n";
echo "   Shows → Sync Now\n";

?>
