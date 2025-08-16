<?php
if (!is_admin()) return;

require_once plugin_dir_path(__FILE__) . '../api/class-tm-graph-client.php';
require_once plugin_dir_path(__FILE__) . 'advertiser-sync.php';


tm_sync_log('INFO','Sync Handlers Loading');

add_action('wp_ajax_tm_sync_run_advertiser', function() {
    tm_sync_log('INFO', 'AJAX handler triggered for advertiser sync');
    check_ajax_referer('tm_sync_nonce');
    $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
    tm_sync_log('INFO', 'Dry run value: ' . ($dry_run ? 'Yes' : 'No'));
    $summary = tm_sync_advertisers($dry_run);
    wp_send_json_success(['message' => $summary]);
});


add_action('wp_ajax_tm_sync_log_event', function() {
    check_ajax_referer('tm_sync_nonce');
    $level = sanitize_text_field($_POST['level'] ?? 'info');
    $message = sanitize_text_field($_POST['message'] ?? '');
    $context = isset($_POST['context']) ? (array) $_POST['context'] : [];

    if (function_exists('tm_sync_log')) {
        tm_sync_log($level, $message, $context);
    }

    wp_send_json_success(['logged' => true]);
});


add_action('init', function() {
    if (function_exists('tm_sync_advertisers')) {
        tm_sync_log('INFO','tm_sync_advertisers is available');
    } else {
        tm_sync_log('INFO','tm_sync_advertisers is NOT available');
    }
});

