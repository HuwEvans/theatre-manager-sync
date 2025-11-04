jQuery(document).ready(function($) {
    console.log('TM Sync admin script loaded');
    
    function updateStatus($container, message, type) {
        console.log('Updating status:', { container: $container.attr('id'), message: message, type: type });
        if ($container.length === 0) {
            console.error('Status container not found');
            return;
        }
        $container
            .html(message)
            .removeClass('notice-error notice-success notice-info')
            .addClass('notice-' + type)
            .show();
    }

    $('.tm-sync-button').on('click', function() {
        var $button = $(this);
        var cpt = $button.data('cpt');
        var $status = $('#tm-sync-status-' + cpt);
        var dryRun = $('#tm-sync-dry-run').is(':checked');

        $button.prop('disabled', true);
        updateStatus($status, 'Syncing...', 'info');

        $.ajax({
            url: tm_sync_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'tm_sync_run',
                nonce: tm_sync_vars.nonce,
                cpt: cpt,
                dry_run: dryRun
            },
            success: function(response) {
                if (response.success) {
                    updateStatus($status, 'Success: ' + response.data, 'success');
                } else {
                    updateStatus($status, 'Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                updateStatus($status, 'Ajax error: ' + error, 'error');
                console.error('Sync error:', {xhr: xhr, status: status, error: error});
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    $('#tm-sync-all').on('click', function() {
        var $button = $(this);
        var $globalStatus = $('#tm-sync-global-status');
        var dryRun = $('#tm-sync-dry-run').is(':checked');

        // Disable all buttons during sync
        $button.prop('disabled', true);
        $('.tm-sync-button').prop('disabled', true);
        
        updateStatus($globalStatus, 'Starting global sync...', 'info');

        // Get all CPTs from the existing buttons
        var cpts = $('.tm-sync-button').map(function() {
            return $(this).data('cpt');
        }).get();

        var completedCpts = 0;
        var failedCpts = [];

        function syncNext() {
            if (completedCpts >= cpts.length) {
                // All done
                var message = 'All syncs completed.';
                if (failedCpts.length > 0) {
                    message += ' Failed CPTs: ' + failedCpts.join(', ');
                    updateStatus($globalStatus, message, 'error');
                } else {
                    updateStatus($globalStatus, message, 'success');
                }
                $button.prop('disabled', false);
                $('.tm-sync-button').prop('disabled', false);
                return;
            }

            var currentCpt = cpts[completedCpts];
            var $status = $('#tm-sync-status-' + currentCpt);
            updateStatus($status, 'Syncing...', 'info');

            $.ajax({
                url: tm_sync_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'tm_sync_run',
                    nonce: tm_sync_vars.nonce,
                    cpt: currentCpt,
                    dry_run: dryRun
                },
                success: function(response) {
                    if (response.success) {
                        updateStatus($status, 'Success: ' + response.data, 'success');
                    } else {
                        updateStatus($status, 'Error: ' + response.data, 'error');
                        failedCpts.push(currentCpt);
                    }
                },
                error: function(xhr, status, error) {
                    updateStatus($status, 'Ajax error: ' + error, 'error');
                    failedCpts.push(currentCpt);
                    console.error('Sync error:', {xhr: xhr, status: status, error: error});
                },
                complete: function() {
                    completedCpts++;
                    updateStatus($globalStatus, 'Progress: ' + completedCpts + '/' + cpts.length + ' completed', 'info');
                    syncNext(); // Process next CPT
                }
            });
        }

        // Start the sync process
        syncNext();
    });
});
