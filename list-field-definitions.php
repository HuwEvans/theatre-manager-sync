<?php
/**
 * Get all field definitions from the Testimonials list
 * This shows what fields SharePoint list actually has
 */

if (!defined('ABSPATH')) {
    wp_die('WordPress not loaded');
}

if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

require_once dirname(__FILE__) . '/theatre-manager-sync/includes/api/class-tm-graph-client.php';

$client = new TM_Graph_Client();

// Get the site and list IDs
$site_id = '9122b47c-2748-446f-820e-ab3bc46b80d0';
$list_id = '7ea94345-e38f-4d62-8030-f1e87955fdf9';

// Create a custom request to get field definitions
$token = $client->get_access_token_public();

if (!$token) {
    echo "ERROR: Could not get access token\n";
    exit;
}

$endpoint = "https://graph.microsoft.com/v1.0/sites/{$site_id}/lists/{$list_id}/columns";

echo "Fetching field definitions from: {$endpoint}\n\n";

$response = wp_remote_get($endpoint, [
    'headers' => [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json'
    ]
]);

if (is_wp_error($response)) {
    echo "ERROR: " . $response->get_error_message() . "\n";
    exit;
}

$code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

echo "HTTP Code: {$code}\n\n";

if ($code !== 200) {
    echo "Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    exit;
}

if (!isset($data['value'])) {
    echo "No columns found in response\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    exit;
}

echo "=== All Fields in Testimonials List ===\n\n";

foreach ($data['value'] as $field) {
    $name = $field['name'] ?? 'N/A';
    $display_name = $field['displayName'] ?? 'N/A';
    $type = $field['text'] ?? ($field['number'] ? 'number' : gettype($field)) ?? 'N/A';
    
    echo "Field Name: {$name}\n";
    echo "Display Name: {$display_name}\n";
    echo "Type Info: " . json_encode($field, JSON_PRETTY_PRINT) . "\n";
    echo "\n---\n\n";
}

echo "\n=== Summary of Number Fields ===\n\n";
foreach ($data['value'] as $field) {
    if (isset($field['number'])) {
        echo "Number field: " . $field['name'] . " (Display: " . $field['displayName'] . ")\n";
    }
}
?>
