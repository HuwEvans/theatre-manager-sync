<?php
/**
 * Seasons Sync Diagnostic Tool
 * 
 * Run from WordPress root directory:
 * php debug-seasons-sync.php
 * 
 * This tool helps diagnose why Seasons are not syncing properly
 */

// Load WordPress
require_once('wp-load.php');

echo "\n=== SEASONS SYNC DIAGNOSTIC REPORT ===\n\n";

// 1. Check if plugin is active
echo "1. Checking if Theatre Manager Sync is active...\n";
if (is_plugin_active('theatre-manager-sync/theatre-manager-sync.php')) {
    echo "   ✓ Theatre Manager Sync plugin is active\n";
} else {
    echo "   ✗ Theatre Manager Sync plugin is NOT active\n";
    exit(1);
}

// 2. Check if Seasons CPT exists
echo "\n2. Checking if Season CPT exists...\n";
if (post_type_exists('season')) {
    echo "   ✓ Season CPT is registered\n";
    
    // Count seasons
    $season_count = wp_count_posts('season');
    echo "   • Seasons in database: " . $season_count->publish . " published, " . ($season_count->total - $season_count->publish) . " other\n";
} else {
    echo "   ✗ Season CPT does not exist\n";
}

// 3. Check Graph Client
echo "\n3. Checking TM_Graph_Client...\n";
if (class_exists('TM_Graph_Client')) {
    echo "   ✓ TM_Graph_Client class exists\n";
    
    try {
        $client = new TM_Graph_Client();
        echo "   ✓ TM_Graph_Client can be instantiated\n";
        
        // Try to get token
        $token = $client->get_access_token_public();
        if ($token) {
            echo "   ✓ Access token obtained (expires in 3600 seconds)\n";
        } else {
            echo "   ✗ Could not obtain access token\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error instantiating TM_Graph_Client: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ TM_Graph_Client class not found\n";
}

// 4. Check Seasons list in SharePoint
echo "\n4. Attempting to fetch Seasons data from SharePoint...\n";
try {
    if (class_exists('TM_Graph_Client')) {
        $client = new TM_Graph_Client();
        $items = $client->get_list_items('Seasons');
        
        if ($items === null) {
            echo "   ✗ API returned null - check credentials\n";
        } elseif (!is_array($items)) {
            echo "   ✗ API returned non-array: " . gettype($items) . "\n";
        } elseif (empty($items)) {
            echo "   ⚠ SharePoint Seasons list is empty\n";
        } else {
            echo "   ✓ Successfully fetched " . count($items) . " items from SharePoint\n";
            
            // Show first item structure
            $first_item = reset($items);
            echo "\n   First item structure:\n";
            echo "   ID: " . ($first_item['id'] ?? 'MISSING') . "\n";
            echo "   Fields available:\n";
            
            if (isset($first_item['fields'])) {
                $fields = $first_item['fields'];
                $field_keys = array_keys((array)$fields);
                foreach ($field_keys as $key) {
                    echo "     - $key\n";
                }
                
                // Check required fields
                echo "\n   Required fields check:\n";
                $required_fields = ['SeasonName', 'Title', 'StartDate', 'EndDate'];
                foreach ($required_fields as $field) {
                    if (isset($fields[$field])) {
                        $value = $fields[$field];
                        if (is_array($value) || is_object($value)) {
                            echo "     ✓ $field: [object/array]\n";
                        } else {
                            echo "     ✓ $field: " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "\n";
                        }
                    } else {
                        echo "     ✗ $field: NOT FOUND\n";
                    }
                }
            } else {
                echo "   ✗ First item has no 'fields' property!\n";
                echo "   Available properties: " . implode(', ', array_keys((array)$first_item)) . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error fetching data: " . $e->getMessage() . "\n";
}

// 5. Check recent sync logs
echo "\n5. Recent Sync Logs:\n";
$log_dir = wp_upload_dir()['basedir'] . '/tm-sync/logs';
if (is_dir($log_dir)) {
    $today = date('Y-m-d');
    $log_file = $log_dir . '/tm-sync-' . $today . '.log';
    
    if (file_exists($log_file)) {
        echo "   ✓ Log file found: $log_file\n\n";
        
        // Read last 50 lines
        $fp = fopen($log_file, 'r');
        $lines = array();
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                $lines[] = $line;
            }
        }
        fclose($fp);
        
        $last_lines = array_slice($lines, -50);
        foreach ($last_lines as $line) {
            if (strpos($line, 'SEASON') !== false || strpos($line, 'season') !== false) {
                echo "   " . trim($line) . "\n";
            }
        }
    } else {
        echo "   ⚠ No log file found for today\n";
    }
} else {
    echo "   ✗ Log directory not found: $log_dir\n";
}

// 6. Check Season metadata for a sample season
echo "\n6. Checking Season metadata in database:\n";
$seasons = get_posts([
    'post_type' => 'season',
    'numberposts' => 3
]);

if (empty($seasons)) {
    echo "   ⚠ No seasons found in database\n";
} else {
    echo "   Found " . count($seasons) . " seasons:\n\n";
    
    foreach ($seasons as $season) {
        echo "   Season: " . $season->post_title . " (ID: " . $season->ID . ")\n";
        
        $meta_keys = ['_tm_sp_id', '_tm_season_name', '_tm_season_start_date', '_tm_season_end_date', '_tm_season_image_front', '_tm_season_image_back'];
        
        foreach ($meta_keys as $key) {
            $value = get_post_meta($season->ID, $key, true);
            if ($value) {
                echo "     ✓ $key: " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "\n";
            } else {
                echo "     ✗ $key: NOT SET\n";
            }
        }
        echo "\n";
    }
}

// 7. Check WordPress error log
echo "7. WordPress Debug Log Issues:\n";
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    $fp = fopen($debug_log, 'r');
    $lines = array();
    while (!feof($fp)) {
        $line = fgets($fp);
        if ($line !== false) {
            $lines[] = $line;
        }
    }
    fclose($fp);
    
    $last_lines = array_slice($lines, -30);
    $season_errors = array_filter($last_lines, function($line) {
        return (strpos(strtolower($line), 'season') !== false || strpos(strtolower($line), 'field') !== false) && strpos($line, 'error') !== false;
    });
    
    if (empty($season_errors)) {
        echo "   ✓ No season-related errors in debug log\n";
    } else {
        echo "   ✗ Found " . count($season_errors) . " season-related errors:\n";
        foreach ($season_errors as $error) {
            echo "   " . trim($error) . "\n";
        }
    }
} else {
    echo "   ⚠ Debug log not found at: $debug_log\n";
}

echo "\n=== END DIAGNOSTIC REPORT ===\n\n";
