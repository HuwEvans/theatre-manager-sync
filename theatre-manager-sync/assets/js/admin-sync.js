jQuery(document).ready(function($) {
    $('.tm-sync-button').click(function() {
        const cpt = $(this).data('cpt');
        const dryRun = $('#tm-sync-dry-run').is(':checked') ? '1' : '0';
        const statusDiv = $('#tm-sync-status-' + cpt);
        statusDiv.text('Running sync...');

        $.post(tm_sync_vars.ajax_url, {
            action: 'tm_sync_run_' + cpt,
            _ajax_nonce: tm_sync_vars.nonce,
            dry_run: dryRun
        }, function(response) {
            statusDiv.text(response.data.message);
        }).fail(function() {
            statusDiv.text('Sync failed.');
        });
    });

    $('#tm-sync-all').click(function() {
        const dryRun = $('#tm-sync-dry-run').is(':checked') ? '1' : '0';
        const statusDiv = $('#tm-sync-global-status');
        statusDiv.text('Running all syncs...');

        $.post(tm_sync_vars.ajax_url, {
            action: 'tm_sync_run_all',
            _ajax_nonce: tm_sync_vars.nonce,
            dry_run: dryRun
        }, function(response) {
            statusDiv.text(response.data.message);
        }).fail(function() {
            statusDiv.text('Global sync failed.');
        });
    });
});
