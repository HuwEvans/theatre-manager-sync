<?php
if (!is_admin()) return;

require_once plugin_dir_path(__FILE__) . '../api/class-tm-graph-client.php';
require_once plugin_dir_path(__FILE__) . 'folder-discovery.php';
require_once plugin_dir_path(__FILE__) . 'generic-image-sync.php';
require_once plugin_dir_path(__FILE__) . 'advertiser-sync.php';
require_once plugin_dir_path(__FILE__) . 'board-member-sync.php';


tm_sync_log('INFO','Sync Handlers Loading');

add_action('wp_ajax_tm_sync_run', function() {
    tm_sync_log('info', 'AJAX handler triggered for sync');
    check_ajax_referer('tm_sync_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        tm_sync_log('error', 'Unauthorized sync attempt');
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $cpt = isset($_POST['cpt']) ? sanitize_text_field($_POST['cpt']) : '';
    $dry_run = isset($_POST['dry_run']) ? filter_var($_POST['dry_run'], FILTER_VALIDATE_BOOLEAN) : false;
    
    tm_sync_log('info', 'Processing sync', ['cpt' => $cpt, 'dry_run' => $dry_run]);
    
    switch ($cpt) {
        case 'advertiser':
            $summary = tm_sync_advertisers($dry_run);
            break;
        case 'board_member':
            $summary = tm_sync_board_members($dry_run);
            break;
        default:
            $summary = 'Unknown CPT type: ' . $cpt;
            tm_sync_log('error', 'Unknown CPT type', ['cpt' => $cpt]);
            break;
    }
    
    tm_sync_log('info', 'AJAX sync complete', ['cpt' => $cpt, 'summary' => $summary]);
    wp_send_json_success($summary);
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

