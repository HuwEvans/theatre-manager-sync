<?php
defined('ABSPATH') || exit;

if ( ! defined('TM_SYNC_CAP') ) {
    // Capability required to access TM Sync admin pages.
    define('TM_SYNC_CAP', 'manage_options');
}

	require_once TMS_PLUGIN_DIR . 'admin/auth-page.php';
	require_once TMS_PLUGIN_DIR . 'admin/details-page.php';
/**
 * Register the TM Sync admin menu and submenus.
 */
add_action('admin_menu', 'tm_sync_register_admin_menu');
function tm_sync_register_admin_menu() {
    // Top-level menu (parent)
    // Page slug: 'tm-sync' — we’ll reuse this slug for the "Auth" submenu to avoid a duplicate item.
    $parent_hook = add_submenu_page(
        'theatre-manager',
        __('Theatre Manager Sync', 'theatre-manager-sync'),
        __('Sync', 'theatre-manager-sync'),
        TM_SYNC_CAP,
        'theatre-manager-sync',
        'tm_sync_admin_page'
    );

    // Submenu: Auth
    $auth_hook = add_submenu_page(
        'theatre-manager-sync',
        __('TM Sync – Authentication', 'theatre-manager-sync'),
        __('Authentication', 'theatre-manager-sync'),
        TM_SYNC_CAP,
        'tm-sync-auth',
        'tm_sync_page_auth'
    );

    // Submenu: Logs
    $logs_hook = add_submenu_page(
        'theatre-manager-sync',
        __('TM Sync – Logs', 'theatre-manager-sync'),
        __('Logs', 'theatre-manager-sync'),
        TM_SYNC_CAP,
        'tm-sync-logs',
        'tm_sync_page_logs'
    );

    // Submenu: Details
    $details_hook = add_submenu_page(
        'theatre-manager-sync',
        __('TM Sync – Details', 'theatre-manager-sync'),
        __('Details', 'theatre-manager-sync'),
        TM_SYNC_CAP,
        'tm-sync-details',
        'tm_sync_page_details'
    );

    // Per-screen hooks for assets and help tabs
    add_action("load-$parent_hook", 'tm_sync_load_admin_screen');
    add_action("load-$auth_hook", 'tm_sync_load_auth_screen');
    add_action("load-$logs_hook", 'tm_sync_load_logs_screen');
    add_action("load-$details_hook", 'tm_sync_load_details_screen');
}

/** =========================
 *  Page callbacks (renderers)
 *  ========================= */

function tm_sync_cap_check() {
    if ( ! current_user_can(TM_SYNC_CAP) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'theatre-manager-sync' ) );
    }
}


function tm_sync_page_sync() {
    tm_sync_cap_check();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'TM Sync · Sync', 'theatre-manager-sync' ); ?></h1>
        <p><?php echo esc_html__( 'Run a manual sync, configure schedules, or do a dry run.', 'theatre-manager-sync' ); ?></p>
        <!-- Controls for "Run Now", scheduler options, dry-run preview, etc. -->
    </div>
    <?php
    tm_sync_cap_check();
    require_once TMS_PLUGIN_DIR . 'admin/admin-sync.php';
    tm_sync_admin_page(); // This renders the full Manual Sync UI

}




/** =========================
 *  load-{$hook_suffix} handlers
 *  (enqueue assets, add help tabs, screen options)
 *  ========================= */

function tm_sync_load_sync_screen() { /* ... */ }
function tm_sync_load_logs_screen() { /* ... */ }
function tm_sync_load_details_screen() { /* ... */ }
