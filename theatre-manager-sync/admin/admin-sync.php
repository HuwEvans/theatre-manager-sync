<?php
function tm_sync_admin_page() {
    if (!current_user_can('manage_options')) return;

    $cpts = ['contributor', 'advertiser', 'board_member', 'sponsor', 'testimonial', 'season', 'show', 'cast'];
    tm_sync_log('INFO', 'Manual sync handler started');
    ?>
    <div class="wrap">
        <h1>Theatre Manager Sync</h1>

        <p>
            <label>
                <input type="checkbox" id="tm-sync-dry-run" />
                Dry Run (Preview only)
            </label>
        </p>

        <button id="tm-sync-all" class="button button-primary">Run All Syncs</button>
        <div id="tm-sync-global-status" style="margin-top:10px;"></div>

        <?php foreach ($cpts as $cpt): ?>
            <hr>
            <h2><?php echo ucfirst(str_replace('_', ' ', $cpt)); ?> Sync</h2>
            <button class="tm-sync-button button" data-cpt="<?php echo esc_attr($cpt); ?>">Run Sync Now</button>
            <div id="tm-sync-status-<?php echo esc_attr($cpt); ?>" style="margin-top:5px;"></div>
        <?php endforeach; ?>
    </div>
    <?php
}

function tm_sync_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_theatre-manager-sync') return;

    wp_enqueue_script('tm-sync-admin', plugin_dir_url(__FILE__) . '../assets/js/admin-sync.js', ['jquery'], null, true);

    wp_localize_script('tm-sync-admin', 'tm_sync_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('tm_sync_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'tm_sync_enqueue_admin_scripts');
