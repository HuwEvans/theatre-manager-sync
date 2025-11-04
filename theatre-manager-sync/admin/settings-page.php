<?php

tm_sync_log('INFO', 'Admin Settings File loading');

/**
 * Add Settings submenu to TM Sync admin menu
 */
function tm_sync_register_settings_submenu() {
    if (!is_admin()) return;
    
    add_submenu_page(
        'theatre-manager-sync',           // Parent menu slug
        'TM Sync Settings',               // Page title
        'Settings',                       // Menu title
        'manage_options',                 // Capability
        'tm-sync-settings',               // Menu slug
        'tm_sync_render_settings_page'    // Callback
    );
    
    tm_sync_log('debug', 'Registered TM Sync Settings submenu');
}
add_action('admin_menu', 'tm_sync_register_settings_submenu');

/**
 * Register settings with WordPress Settings API
 */
function tm_sync_register_settings() {
    register_setting(
        'tm_sync_settings_group',
        'tm_sync_folder_overrides',
        array(
            'type' => 'array',
            'sanitize_callback' => 'tm_sync_sanitize_folder_overrides',
            'show_in_rest' => false
        )
    );
    
    tm_sync_log('debug', 'Registered TM Sync settings');
}
add_action('admin_init', 'tm_sync_register_settings');

/**
 * Sanitize folder override settings
 */
function tm_sync_sanitize_folder_overrides($value) {
    if (!is_array($value)) {
        return array();
    }
    
    $sanitized = array();
    foreach ($value as $folder_name => $folder_id) {
        $folder_name = sanitize_text_field($folder_name);
        $folder_id = sanitize_text_field($folder_id);
        
        if (!empty($folder_name) && !empty($folder_id)) {
            $sanitized[$folder_name] = $folder_id;
        }
    }
    
    return $sanitized;
}

/**
 * Handle AJAX actions for cache management
 */
function tm_sync_handle_cache_ajax() {
    check_ajax_referer('tm_sync_settings_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $action = sanitize_text_field($_POST['action_type'] ?? '');
    
    switch ($action) {
        case 'refresh_cache':
            tm_sync_refresh_folder_cache();
            wp_send_json_success(array('message' => 'Cache refreshed successfully'));
            break;
            
        case 'clear_cache':
            tm_sync_clear_folder_cache();
            wp_send_json_success(array('message' => 'Cache cleared successfully'));
            break;
            
        case 'get_cache_status':
            $cached = tm_sync_get_cached_folder_ids();
            wp_send_json_success(array('folders' => $cached));
            break;
            
        default:
            wp_send_json_error('Unknown action');
    }
}
add_action('wp_ajax_tm_sync_cache_action', 'tm_sync_handle_cache_ajax');

/**
 * Handle AJAX actions for data cleaning
 */
function tm_sync_handle_clean_ajax() {
    check_ajax_referer('tm_sync_settings_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $cpts = isset($_POST['cpts']) ? (array) $_POST['cpts'] : [];
    
    if (empty($cpts)) {
        wp_send_json_error('No CPTs selected');
    }
    
    $total_deleted = 0;
    $results = [];
    
    foreach ($cpts as $cpt) {
        $cpt = sanitize_text_field($cpt);
        
        $posts = get_posts([
            'post_type' => $cpt,
            'numberposts' => -1,
            'post_status' => 'any'
        ]);
        
        $deleted = 0;
        foreach ($posts as $post) {
            if (wp_delete_post($post->ID, true)) {
                $deleted++;
            }
        }
        
        $results[$cpt] = $deleted;
        $total_deleted += $deleted;
        
        tm_sync_log('info', 'User deleted CPT posts via settings', ['cpt' => $cpt, 'deleted_count' => $deleted]);
    }
    
    $message = sprintf('Successfully deleted %d post(s) from %d CPT(s).', $total_deleted, count($cpts));
    wp_send_json_success([
        'message' => $message,
        'results' => $results,
        'total_deleted' => $total_deleted
    ]);
}
add_action('wp_ajax_tm_sync_clean_action', 'tm_sync_handle_clean_ajax');

/**
 * Refresh folder cache by discovering all folders
 */
function tm_sync_refresh_folder_cache() {
    if (!function_exists('tm_sync_discover_all_folders')) {
        tm_sync_log('error', 'Folder discovery functions not available');
        return false;
    }
    
    tm_sync_log('info', 'User initiated folder cache refresh');
    $result = tm_sync_discover_all_folders();
    
    if ($result) {
        tm_sync_log('info', 'Folder cache refresh completed successfully');
    } else {
        tm_sync_log('warning', 'Folder cache refresh encountered errors');
    }
    
    return $result;
}

/**
 * Render the admin settings page
 */
function tm_sync_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $cached_folders = tm_sync_get_cached_folder_ids();
    $overrides = get_option('tm_sync_folder_overrides', array());
    ?>
    
    <div class="wrap tm-sync-settings">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Tabs -->
        <nav class="nav-tab-wrapper">
            <a href="#" class="nav-tab nav-tab-active" data-tab="cached-folders">
                <span class="dashicons dashicons-list-view"></span> Cached Folders
            </a>
            <a href="#" class="nav-tab" data-tab="manual-overrides">
                <span class="dashicons dashicons-edit"></span> Manual Overrides
            </a>
            <a href="#" class="nav-tab" data-tab="clean-data">
                <span class="dashicons dashicons-trash"></span> Clean Data
            </a>
            <a href="#" class="nav-tab" data-tab="help">
                <span class="dashicons dashicons-editor-help"></span> Help
            </a>
        </nav>
        
        <!-- Tab: Cached Folders -->
        <div class="nav-content" id="cached-folders" style="display: block;">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-folder"></span> Folder Discovery Cache</h2>
                <div class="inside">
                    <p>These folders have been automatically discovered from SharePoint and cached for faster syncing.</p>
                    
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Folder Name</th>
                                <th>Folder ID</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cached_folders)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 20px;">
                                        <em>No folders cached yet. Run a sync to discover folders.</em>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cached_folders as $folder_name => $folder_id): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($folder_name); ?></strong></td>
                                        <td><code><?php echo esc_html($folder_id); ?></code></td>
                                        <td>
                                            <button class="button button-small copy-id" data-id="<?php echo esc_attr($folder_id); ?>">
                                                Copy ID
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" class="button button-primary tm-sync-action" data-action="refresh_cache">
                            <span class="dashicons dashicons-update"></span> Refresh Cache
                        </button>
                        <button type="button" class="button tm-sync-action" data-action="clear_cache" style="background-color: #dc3545; color: white; border-color: #dc3545;">
                            <span class="dashicons dashicons-trash"></span> Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Manual Overrides -->
        <div class="nav-content" id="manual-overrides" style="display: none;">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-edit"></span> Manual Folder ID Overrides</h2>
                <div class="inside">
                    <p>Manually specify folder IDs to override auto-discovery. Useful for non-standard folder structures.</p>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('tm_sync_settings_group'); ?>
                        
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Folder Name</th>
                                    <th>Folder ID (Override)</th>
                                    <th width="50">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="overrides-tbody">
                                <?php if (!empty($overrides)): ?>
                                    <?php foreach ($overrides as $folder_name => $folder_id): ?>
                                        <tr class="override-row">
                                            <td>
                                                <input type="hidden" name="tm_sync_folder_overrides[<?php echo esc_attr($folder_name); ?>]" value="<?php echo esc_attr($folder_name); ?>">
                                                <strong><?php echo esc_html($folder_name); ?></strong>
                                            </td>
                                            <td>
                                                <input type="text" name="tm_sync_folder_overrides[<?php echo esc_attr($folder_name); ?>]" value="<?php echo esc_attr($folder_id); ?>" class="regular-text code" placeholder="Folder ID from SharePoint">
                                            </td>
                                            <td>
                                                <button type="button" class="button button-small remove-override">✕</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <!-- Template for new rows -->
                                <tr class="override-row template" style="display: none;">
                                    <td>
                                        <input type="text" class="regular-text folder-name" placeholder="Folder Name">
                                    </td>
                                    <td>
                                        <input type="text" class="regular-text code folder-id" placeholder="Folder ID">
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small remove-override">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px;">
                            <button type="button" class="button" id="add-override">
                                <span class="dashicons dashicons-plus-alt"></span> Add Override
                            </button>
                            <?php submit_button('Save Overrides', 'primary', 'submit'); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Tab: Clean Data -->
        <div class="nav-content" id="clean-data" style="display: none;">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-trash"></span> Clean Synced Data</h2>
                <div class="inside">
                    <p><strong>WARNING:</strong> Deleting posts is permanent. This will remove all synced posts for the selected CPT(s).</p>
                    
                    <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin-bottom: 15px; border-radius: 3px;">
                        <strong>⚠️ Caution:</strong> Deleted posts cannot be recovered. Please backup your database first.
                    </div>
                    
                    <h3>Delete Posts by CPT</h3>
                    <p>Select which CPT data to delete:</p>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="advertiser" id="cpt-advertiser">
                            <strong>Advertisers</strong> - Delete all advertiser posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="board_member" id="cpt-board_member">
                            <strong>Board Members</strong> - Delete all board member posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="cast" id="cpt-cast">
                            <strong>Cast</strong> - Delete all cast posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="sponsor" id="cpt-sponsor">
                            <strong>Sponsors</strong> - Delete all sponsor posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="season" id="cpt-season">
                            <strong>Seasons</strong> - Delete all season posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="show" id="cpt-show">
                            <strong>Shows</strong> - Delete all show posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="contributor" id="cpt-contributor">
                            <strong>Contributors</strong> - Delete all contributor posts
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" class="cpt-checkbox" value="testimonial" id="cpt-testimonial">
                            <strong>Testimonials</strong> - Delete all testimonial posts
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <button type="button" class="button" id="select-all-cpts">
                            Select All
                        </button>
                        <button type="button" class="button" id="clear-all-cpts">
                            Clear All
                        </button>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Posts to delete:</strong> <span id="cpt-count">0</span>
                    </div>
                    
                    <button type="button" class="button button-danger tm-clean-action" data-action="delete_cpts" style="background-color: #dc3545; color: white; border-color: #dc3545; margin-right: 10px;">
                        <span class="dashicons dashicons-trash"></span> Delete Selected CPT Data
                    </button>
                    
                    <div id="clean-status" style="margin-top: 15px; display: none; padding: 10px; border-radius: 3px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Help -->
        <div class="nav-content" id="help" style="display: none;">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-editor-help"></span> Help & Documentation</h2>
                <div class="inside">
                    <h3>How Folder Discovery Works</h3>
                    <p>Theatre Manager Sync automatically discovers SharePoint folders on first sync:</p>
                    <ol>
                        <li>Extract folder name from SharePoint image URL (e.g., "People", "Sponsors")</li>
                        <li>Search SharePoint for matching folder</li>
                        <li>Cache the folder ID for future syncs (80% faster)</li>
                    </ol>
                    
                    <h3>Manual Overrides</h3>
                    <p>If auto-discovery doesn't work for your setup:</p>
                    <ul>
                        <li>Go to the "Manual Overrides" tab</li>
                        <li>Add folder name and manually specify its ID</li>
                        <li>These overrides take precedence over auto-discovery</li>
                    </ul>
                    
                    <h3>Cache Management</h3>
                    <ul>
                        <li><strong>Refresh Cache:</strong> Re-discover all folders from SharePoint</li>
                        <li><strong>Clear Cache:</strong> Remove all cached folders (auto-discover on next sync)</li>
                    </ul>
                    
                    <h3>Finding Folder IDs</h3>
                    <p>To get a folder ID from SharePoint:</p>
                    <ol>
                        <li>Open SharePoint in your browser</li>
                        <li>Navigate to the folder</li>
                        <li>Check the URL for the folder ID (usually in the path)</li>
                        <li>Or use the copy button next to cached folders</li>
                    </ol>
                    
                    <h3>Performance Tips</h3>
                    <ul>
                        <li>First sync: Auto-discovers folders (normal speed)</li>
                        <li>Subsequent syncs: Use cached IDs (~80% faster)</li>
                        <li>Multiple syncs of same data: Reuse existing attachments (no re-downloads)</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Status box -->
        <div class="postbox" style="margin-top: 20px;">
            <h2 class="hndle">System Status</h2>
            <div class="inside">
                <table>
                    <tr>
                        <td><strong>Generic Image Sync:</strong></td>
                        <td><?php echo function_exists('tm_sync_image_for_post') ? '<span style="color: green;">✓ Loaded</span>' : '<span style="color: red;">✗ Not Found</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Folder Discovery:</strong></td>
                        <td><?php echo function_exists('tm_sync_discover_all_folders') ? '<span style="color: green;">✓ Loaded</span>' : '<span style="color: red;">✗ Not Found</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Graph API Client:</strong></td>
                        <td><?php echo class_exists('TM_Graph_Client') ? '<span style="color: green;">✓ Available</span>' : '<span style="color: red;">✗ Not Found</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cached Folders:</strong></td>
                        <td><?php echo count($cached_folders); ?> folder(s)</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <style>
        .tm-sync-settings .nav-tab-wrapper {
            margin-bottom: 0;
            border-bottom: 1px solid #ccc;
        }
        
        .tm-sync-settings .nav-tab {
            color: #0073aa;
            border: 1px solid transparent;
            padding: 8px 12px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .tm-sync-settings .nav-tab:hover {
            color: #005a87;
            border-bottom-color: #005a87;
        }
        
        .tm-sync-settings .nav-tab.nav-tab-active {
            color: #000;
            border-bottom: 4px solid #0073aa;
        }
        
        .tm-sync-settings .nav-content {
            padding: 15px 0;
        }
        
        .tm-sync-settings code {
            background-color: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .tm-sync-settings .postbox {
            margin-top: 15px;
        }
        
        .tm-sync-settings .dashicons {
            margin-right: 5px;
        }
        
        .copy-id {
            cursor: pointer;
        }
        
        .tm-sync-action {
            margin-top: 10px;
        }
        
        .override-row.template {
            opacity: 0.5;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.nav-content').hide();
            $('#' + tab).show();
        });
        
        // Copy folder ID to clipboard
        $('.copy-id').on('click', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);
            
            // Copy to clipboard
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(id).select();
            document.execCommand('copy');
            $temp.remove();
            
            // Show feedback
            var originalText = $btn.text();
            $btn.text('✓ Copied!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        });
        
        // Cache management actions
        $('.tm-sync-action').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var action = $btn.data('action');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tm_sync_cache_action',
                    action_type: action,
                    nonce: '<?php echo wp_create_nonce('tm_sync_settings_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (action === 'clear_cache' || action === 'refresh_cache') {
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('AJAX error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Add override row
        $('#add-override').on('click', function(e) {
            e.preventDefault();
            var template = $('.override-row.template').clone();
            template.removeClass('template');
            template.show();
            $('#overrides-tbody').append(template);
        });
        
        // Remove override row
        $(document).on('click', '.remove-override', function(e) {
            e.preventDefault();
            $(this).closest('.override-row').remove();
        });
        
        // Clean data functionality
        function updateCptCount() {
            var count = $('.cpt-checkbox:checked').length;
            $('#cpt-count').text(count);
        }
        
        $('.cpt-checkbox').on('change', updateCptCount);
        
        $('#select-all-cpts').on('click', function(e) {
            e.preventDefault();
            $('.cpt-checkbox').prop('checked', true);
            updateCptCount();
        });
        
        $('#clear-all-cpts').on('click', function(e) {
            e.preventDefault();
            $('.cpt-checkbox').prop('checked', false);
            updateCptCount();
        });
        
        $('.tm-clean-action').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var selectedCpts = [];
            
            $('.cpt-checkbox:checked').each(function() {
                selectedCpts.push($(this).val());
            });
            
            if (selectedCpts.length === 0) {
                alert('Please select at least one CPT to delete.');
                return;
            }
            
            var message = 'Are you sure you want to delete all posts for: ' + selectedCpts.join(', ') + '?\n\nThis action cannot be undone!';
            if (!confirm(message)) {
                return;
            }
            
            $btn.prop('disabled', true);
            var $status = $('#clean-status');
            $status.show().html('<p style="color: blue;">Deleting posts...</p>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tm_sync_clean_action',
                    cpts: selectedCpts,
                    nonce: '<?php echo wp_create_nonce('tm_sync_settings_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<p style="color: green;">✓ ' + response.data.message + '</p>');
                        $('.cpt-checkbox:checked').prop('checked', false);
                        updateCptCount();
                    } else {
                        $status.html('<p style="color: red;">✗ Error: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $status.html('<p style="color: red;">✗ AJAX error occurred</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
    <?php
}

?>
