<?php
/**
 * Display recent TM-Sync logs for debugging
 * Add this to a test page or WordPress admin page
 */

if (!defined('ABSPATH')) {
    echo "This script must be run within WordPress context\n";
    exit;
}

// Get the most recent log file
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/tm-sync/logs';

if (!is_dir($log_dir)) {
    echo "Log directory not found: " . $log_dir . "\n";
    exit;
}

$log_files = glob($log_dir . '/*.log');
if (empty($log_files)) {
    echo "No log files found in: " . $log_dir . "\n";
    exit;
}

// Sort by modification time, newest first
usort($log_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latest_log = $log_files[0];

echo "=== Latest TM-Sync Log ===\n";
echo "File: " . basename($latest_log) . "\n";
echo "Modified: " . date('Y-m-d H:i:s', filemtime($latest_log)) . "\n";
echo "Size: " . filesize($latest_log) . " bytes\n\n";

// Read and parse the log file
$lines = file($latest_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

echo "=== Recent Log Entries (Last 50) ===\n\n";

$recent_lines = array_slice($lines, max(0, count($lines) - 50));

foreach ($recent_lines as $line) {
    $entry = json_decode($line, true);
    if ($entry) {
        $timestamp = $entry['timestamp'] ?? 'N/A';
        $level = $entry['level'] ?? 'N/A';
        $channel = $entry['channel'] ?? 'N/A';
        $message = $entry['message'] ?? 'N/A';
        $context = $entry['context'] ?? [];
        
        echo "[{$timestamp}] [{$level}] [{$channel}] {$message}\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}

echo "\n=== Testimonials-Specific Log Entries ===\n\n";

foreach ($lines as $line) {
    $entry = json_decode($line, true);
    if ($entry && (stripos($entry['message'] ?? '', 'testimonial') !== false || 
                   stripos($entry['channel'] ?? '', 'testimonial') !== false ||
                   (isset($entry['context']) && json_encode($entry['context']) && stripos(json_encode($entry['context']), 'testimonial') !== false))) {
        $timestamp = $entry['timestamp'] ?? 'N/A';
        $level = $entry['level'] ?? 'N/A';
        $message = $entry['message'] ?? 'N/A';
        $context = $entry['context'] ?? [];
        
        echo "[{$timestamp}] [{$level}] {$message}\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}

echo "\n=== Rating Extraction Debug ===\n\n";

foreach ($lines as $line) {
    $entry = json_decode($line, true);
    if ($entry && (stripos($entry['message'] ?? '', 'rating') !== false)) {
        $timestamp = $entry['timestamp'] ?? 'N/A';
        $level = $entry['level'] ?? 'N/A';
        $message = $entry['message'] ?? 'N/A';
        $context = $entry['context'] ?? [];
        
        echo "[{$timestamp}] [{$level}] {$message}\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}
?>
