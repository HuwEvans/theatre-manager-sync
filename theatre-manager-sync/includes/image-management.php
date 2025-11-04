<?php
/**
 * Image Management for Theatre Manager Sync
 * Handles creation and cleanup of synced images folder
 */

/**
 * Get the synced images directory path
 * 
 * @return string Path to synced images folder
 */
function tm_sync_get_images_dir() {
    $upload_dir = wp_upload_dir();
    return trailingslashit($upload_dir['basedir']) . 'theatre-manager-sync-images';
}

/**
 * Get the synced images directory URL
 * 
 * @return string URL to synced images folder
 */
function tm_sync_get_images_url() {
    $upload_dir = wp_upload_dir();
    return trailingslashit($upload_dir['baseurl']) . 'theatre-manager-sync-images';
}

/**
 * Create the synced images folder if it doesn't exist
 * 
 * @return bool True if folder exists or was created, false on error
 */
function tm_sync_create_images_folder() {
    $images_dir = tm_sync_get_images_dir();
    
    if (file_exists($images_dir)) {
        if (is_dir($images_dir) && is_writable($images_dir)) {
            tm_sync_log('debug', 'Synced images folder already exists and is writable', ['path' => $images_dir]);
            return true;
        }
    }
    
    // Create folder
    if (!is_dir($images_dir)) {
        if (!wp_mkdir_p($images_dir)) {
            tm_sync_log('error', 'Failed to create synced images folder', ['path' => $images_dir]);
            return false;
        }
    }
    
    // Create .htaccess to prevent script execution
    $htaccess_file = trailingslashit($images_dir) . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# Deny execution of scripts in this directory\n";
        $htaccess_content .= "<FilesMatch \"\\.php$\">\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        
        if (@file_put_contents($htaccess_file, $htaccess_content) === false) {
            tm_sync_log('warning', 'Failed to create .htaccess in synced images folder', ['path' => $htaccess_file]);
            // Don't fail - folder still works without it
        }
    }
    
    // Create index.php to prevent directory listing
    $index_file = trailingslashit($images_dir) . 'index.php';
    if (!file_exists($index_file)) {
        if (@file_put_contents($index_file, "<?php // Silence is golden\n") === false) {
            tm_sync_log('warning', 'Failed to create index.php in synced images folder', ['path' => $index_file]);
            // Don't fail - folder still works without it
        }
    }
    
    tm_sync_log('info', 'Successfully created synced images folder', ['path' => $images_dir]);
    return true;
}

/**
 * Delete all synced images and the folder
 * 
 * @return bool True if successful, false on error
 */
function tm_sync_delete_all_images() {
    $images_dir = tm_sync_get_images_dir();
    
    if (!file_exists($images_dir)) {
        tm_sync_log('info', 'Synced images folder does not exist - nothing to delete', ['path' => $images_dir]);
        return true;
    }
    
    // Get list of all files in folder
    $files = glob(trailingslashit($images_dir) . '*');
    $deleted_count = 0;
    $errors = [];
    
    if ($files) {
        foreach ($files as $file) {
            // Skip .htaccess and index.php (we'll delete folder after)
            $basename = basename($file);
            if (in_array($basename, ['.htaccess', 'index.php'])) {
                if (@unlink($file)) {
                    $deleted_count++;
                }
                continue;
            }
            
            if (is_file($file)) {
                if (@unlink($file)) {
                    $deleted_count++;
                } else {
                    $errors[] = $file;
                }
            } elseif (is_dir($file)) {
                // Recursively delete subdirectories if any
                if (!tm_sync_delete_directory_recursive($file)) {
                    $errors[] = $file;
                }
            }
        }
    }
    
    // Try to remove the main folder
    if (empty($errors)) {
        if (@rmdir($images_dir)) {
            tm_sync_log('info', 'Successfully deleted synced images folder and all contents', [
                'path' => $images_dir,
                'files_deleted' => $deleted_count
            ]);
            return true;
        } else {
            tm_sync_log('warning', 'Failed to delete synced images folder (files deleted but folder remains)', [
                'path' => $images_dir,
                'files_deleted' => $deleted_count
            ]);
            return false;
        }
    } else {
        tm_sync_log('error', 'Failed to delete some synced images', [
            'path' => $images_dir,
            'files_deleted' => $deleted_count,
            'failed_files' => $errors
        ]);
        return false;
    }
}

/**
 * Recursively delete a directory and all its contents
 * 
 * @param string $dir Directory path to delete
 * @return bool True if successful
 */
function tm_sync_delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = glob(trailingslashit($dir) . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_dir($file)) {
                tm_sync_delete_directory_recursive($file);
            } else {
                @unlink($file);
            }
        }
    }
    
    return @rmdir($dir);
}

/**
 * Clear all Theatre Manager Sync data when plugin is deactivated
 * Removes: synced images folder, scheduled events, etc.
 * 
 * @return void
 */
function tm_sync_deactivate_cleanup() {
    // Delete synced images
    tm_sync_delete_all_images();
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook('tm_sync_daily_check');
    
    tm_sync_log('info', 'Theatre Manager Sync deactivated - cleanup completed');
}

/**
 * Initialize image folder on plugin activation
 * 
 * @return void
 */
function tm_sync_activate_setup() {
    // Ensure uploads folder exists
    wp_mkdir_p(wp_upload_dir()['basedir']);
    
    // Create synced images folder
    tm_sync_create_images_folder();
    
    tm_sync_log('info', 'Theatre Manager Sync activated - setup completed');
}

// Create images folder on WordPress init
add_action('init', function() {
    // Only on admin pages to avoid repeated checks on frontend
    if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
        tm_sync_create_images_folder();
    }
}, 5); // Early priority

?>
