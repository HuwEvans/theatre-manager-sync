<?php
defined('ABSPATH') || exit;

// Include required files
require_once TMS_PLUGIN_DIR . 'includes/sync/advertiser-sync.php';
require_once TMS_PLUGIN_DIR . 'includes/sync/board-member-sync.php';

/**
 * Display the manual sync admin page.
 */
function tm_sync_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $cpts = ['advertiser', 'board_member', 'cast', 'contributor', 'season', 'show', 'sponsor', 'testimonial'];
    tm_sync_log('info', 'Manual sync page loaded');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="card">
            <h2><?php _e('Sync Options', 'theatre-manager-sync'); ?></h2>
            <p>
                <label>
                    <input type="checkbox" id="tm-sync-dry-run" />
                    <?php _e('Dry Run (Preview changes without making updates)', 'theatre-manager-sync'); ?>
                </label>
            </p>

            <button id="tm-sync-all" class="button button-primary">
                <?php _e('Run All Syncs', 'theatre-manager-sync'); ?>
            </button>
            <div id="tm-sync-global-status" class="notice inline" style="display:none;margin-top:10px;"></div>
        </div>

        <div class="sync-sections">
            <?php foreach ($cpts as $cpt): ?>
                <div class="card">
                    <h3><?php echo esc_html(ucfirst(str_replace('_', ' ', $cpt))); ?></h3>
                    <button class="tm-sync-button button" data-cpt="<?php echo esc_attr($cpt); ?>">
                        <?php _e('Run Sync', 'theatre-manager-sync'); ?>
                    </button>
                    <div id="tm-sync-status-<?php echo esc_attr($cpt); ?>" class="notice inline" style="display:none;margin-top:10px;"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        .sync-sections {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .card {
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-top: 20px;
        }
    </style>
    <?php
}

// Register AJAX handler
add_action('wp_ajax_tm_sync_run', function() {
    check_ajax_referer('tm_sync_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    tm_sync_log('debug', 'AJAX sync handler started');

    $cpt = isset($_POST['cpt']) ? sanitize_text_field($_POST['cpt']) : '';
    $dry_run = isset($_POST['dry_run']) ? filter_var($_POST['dry_run'], FILTER_VALIDATE_BOOLEAN) : false;

    tm_sync_log('info', 'Starting AJAX sync.', [
        'cpt' => $cpt,
        'dry_run' => $dry_run,
        'post_data' => $_POST
    ]);

    $result = '';
    switch ($cpt) {
        case 'advertiser':
            $result = tm_sync_advertisers($dry_run);
            break;
        case 'board_member':
            $result = tm_sync_board_members($dry_run);
            break;
        default:
            $result = 'Unknown CPT type: ' . $cpt;
            tm_sync_log('error', 'Unknown CPT type', ['cpt' => $cpt]);
            break;
    }

    tm_sync_log('info', 'AJAX sync complete.', [
        'cpt' => $cpt,
        'result' => $result
    ]);

    wp_send_json_success($result);
});

function tm_sync_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_theatre-manager-sync') {
        return;
    }

    $version = defined('THEATRE_MANAGER_SYNC_VERSION') ? THEATRE_MANAGER_SYNC_VERSION : '1.0';
    
    wp_enqueue_script(
        'tm-sync-admin',
        plugin_dir_url(__FILE__) . '../assets/js/admin-sync.js',
        ['jquery'],
        $version,
        true
    );

    wp_localize_script('tm-sync-admin', 'tm_sync_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('tm_sync_nonce'),
        'debug'    => WP_DEBUG
    ]);

    tm_sync_log('debug', 'Admin scripts enqueued for sync page');
}
add_action('admin_enqueue_scripts', 'tm_sync_enqueue_admin_scripts');

/**
 * Load screen assets for the admin page.
 */
function tm_sync_load_admin_screen() {
    tm_sync_log('debug', 'Admin page loaded');
}
