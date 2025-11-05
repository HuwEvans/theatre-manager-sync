<?php
/**
 * Image Management for Theatre Manager Sync
 * Handles creation and cleanup of synced images folder
 */

// Include folder discovery functions for PDF downloads
if (!function_exists('tm_sync_get_folder_id')) {
    require_once dirname(__FILE__) . '/sync/folder-discovery.php';
}

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

/**
 * Download an image from a URL and attach it to a post
 * This is used for Season images where SharePoint returns full redirect URLs
 * 
 * @param int $post_id The post ID to attach the image to
 * @param string $image_url The full SharePoint URL to download from
 * @param string $meta_key The meta key to store the attachment ID in
 * @param string $prefix Filename prefix for the downloaded image
 * @param string $image_type Human-readable image type for logging
 * 
 * @return int|null Attachment ID if successful, null otherwise
 */
function tm_sync_download_and_attach_image($post_id, $image_url, $meta_key, $prefix, $image_type = 'image') {
    if (empty($image_url) || empty($post_id)) {
        return null;
    }
    
    tm_sync_log('debug', "Downloading season image: $image_type", [
        'post_id' => $post_id,
        'url' => substr($image_url, 0, 100) . '...'
    ]);
    
    // Check if we already have this attachment
    // Only reuse if it's a valid attachment ID (numeric), not a URL
    $existing_id = get_post_meta($post_id, $meta_key, true);
    if ($existing_id && !empty($existing_id) && is_numeric($existing_id)) {
        $attachment_id = intval($existing_id);
        // Verify the attachment actually exists
        if (get_post_type($attachment_id) === 'attachment') {
            tm_sync_log('debug', "Reusing existing $image_type attachment", [
                'post_id' => $post_id,
                'attachment_id' => $attachment_id
            ]);
            return $attachment_id;
        }
    }
    
    // Download the image from SharePoint
    // SharePoint redirect URLs (:i:/g/ID format) need ?download=1 to get actual file, not HTML
    $download_url = $image_url;
    if (strpos($download_url, '?') === false) {
        $download_url .= '?download=1';
    } else {
        $download_url .= '&download=1';
    }
    
    tm_sync_log('debug', "Using download URL for season $image_type", [
        'post_id' => $post_id,
        'url' => substr($download_url, 0, 150) . '...'
    ]);
    
    $response = wp_remote_get($download_url, [
        'timeout' => 60,
        'sslverify' => true,
        'redirection' => 10,
    ]);
    
    if (is_wp_error($response)) {
        tm_sync_log('error', "Failed to download season $image_type", [
            'post_id' => $post_id,
            'error' => $response->get_error_message()
        ]);
        return null;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        tm_sync_log('error', "Failed to download season $image_type - HTTP error", [
            'post_id' => $post_id,
            'http_code' => $http_code
        ]);
        return null;
    }
    
    $file_content = wp_remote_retrieve_body($response);
    if (empty($file_content)) {
        tm_sync_log('error', "Downloaded season $image_type is empty", [
            'post_id' => $post_id
        ]);
        return null;
    }
    
    // Check if we got HTML instead of an image (error indicator)
    if (strpos($file_content, '<!DOCTYPE') === 0 || strpos($file_content, '<html') === 0) {
        tm_sync_log('error', "Downloaded season $image_type is HTML, not an image - possible permission issue", [
            'post_id' => $post_id,
            'first_chars' => substr($file_content, 0, 100)
        ]);
        return null;
    }
    
    // Get the synced images folder
    $images_dir = tm_sync_get_images_dir();
    if (!file_exists($images_dir)) {
        tm_sync_create_images_folder();
    }
    
    // Create filename - extract extension from URL or use default jpg
    $filename = $prefix . '.jpg';
    if (preg_match('/\.(\w+)(?:\?|$)/', $image_url, $matches)) {
        $ext = strtolower($matches[1]);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = $prefix . '.' . $ext;
        }
    }
    
    $dest_file = $images_dir . '/' . sanitize_file_name($filename);
    
    // Write the file
    if (!file_put_contents($dest_file, $file_content)) {
        tm_sync_log('error', "Failed to write season $image_type to disk", [
            'post_id' => $post_id,
            'path' => $dest_file
        ]);
        return null;
    }
    
    @chmod($dest_file, 0644);
    
    // Determine MIME type
    $ext = strtolower(pathinfo($dest_file, PATHINFO_EXTENSION));
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mime_type = $mime_types[$ext] ?? 'image/jpeg';
    
    // Create WordPress attachment
    $attachment_data = [
        'post_mime_type' => $mime_type,
        'post_title' => $prefix,
        'post_content' => '',
        'post_status' => 'inherit',
    ];
    
    $attachment_id = wp_insert_attachment($attachment_data, $dest_file, $post_id);
    
    if (is_wp_error($attachment_id)) {
        tm_sync_log('error', "Failed to create season $image_type attachment", [
            'post_id' => $post_id,
            'error' => $attachment_id->get_error_message()
        ]);
        @unlink($dest_file);
        return null;
    }
    
    // Generate attachment metadata
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attachment_id, $dest_file);
    wp_update_attachment_metadata($attachment_id, $attach_data);
    
    // Store the attachment ID in post meta
    update_post_meta($post_id, $meta_key, $attachment_id);
    
    tm_sync_log('info', "Successfully attached season $image_type", [
        'post_id' => $post_id,
        'attachment_id' => $attachment_id,
        'filename' => $filename
    ]);
    
    return $attachment_id;
}

// Create images folder on WordPress init
add_action('init', function() {
    // Only on admin pages to avoid repeated checks on frontend
    if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
        tm_sync_create_images_folder();
    }
}, 5); // Early priority

/**
 * Download PDF from SharePoint and save to sync folder
 * SharePoint direct links may require authentication for non-public files
 * Falls back gracefully by storing the SharePoint URL if download fails
 * 
 * @param string $pdf_url URL or object containing PDF file from SharePoint
 * @param string $filename Filename to save as (should include .pdf extension)
 * @return string|false Full path to downloaded PDF file, or false on failure
 */
function tm_sync_download_pdf($pdf_url, $filename) {
    if (empty($pdf_url) || empty($filename)) {
        tm_sync_log('warning', 'PDF download called with empty URL or filename');
        return false;
    }

    // Extract URL if SharePoint returned an object
    if (is_array($pdf_url)) {
        $pdf_url = $pdf_url['Url'] ?? '';
    } elseif (is_object($pdf_url)) {
        $pdf_url = $pdf_url->Url ?? '';
    }

    if (empty($pdf_url)) {
        tm_sync_log('warning', 'Could not extract URL from PDF field');
        return false;
    }

    tm_sync_log('debug', 'Downloading PDF from SharePoint using Graph API', [
        'url' => substr($pdf_url, 0, 100) . '...',
        'filename' => $filename
    ]);

    // Get access token for Graph API authentication
    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not available for PDF download');
        return false;
    }

    $client = new TM_Graph_Client();
    $token = $client->get_access_token_public();
    
    if (!$token) {
        tm_sync_log('error', 'Failed to get access token for PDF download');
        return false;
    }

    // Extract folder name from URL (e.g., "PDFs" from /Image Media/PDFs/filename.pdf)
    $folder_name = tm_sync_extract_folder_from_url($pdf_url);
    if (!$folder_name) {
        tm_sync_log('warning', 'Could not extract folder name from PDF URL', ['url' => $pdf_url]);
        return false;
    }

    // Get Site and List IDs
    $site_id = 'miltonplayers.sharepoint.com,9122b47c-2748-446f-820e-ab3bc46b80d0,5d9211a6-6d28-4644-ad40-82fe3972fbf1';
    $image_media_list_id = '36cd8ce2-6611-401a-ae0c-20dd4abcf36b';
    
    // Get folder ID for the PDF folder using generic folder discovery
    $folder_id = tm_sync_get_folder_id($folder_name, $site_id, $image_media_list_id, $token);
    
    if (!$folder_id) {
        tm_sync_log('error', 'Could not find PDF folder in Image Media', ['folder' => $folder_name]);
        return false;
    }

    tm_sync_log('debug', 'Found PDF folder in Image Media', [
        'folder' => $folder_name,
        'folder_id' => $folder_id
    ]);

    // List files in folder to find the matching PDF
    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$folder_id/children";
    
    $response = wp_remote_get($search_url, [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to list PDF folder in Image Media', [
            'error' => $response->get_error_message(),
            'folder' => $folder_name
        ]);
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    
    if (!isset($json['value'])) {
        tm_sync_log('error', 'Unexpected response from Graph API listing PDF folder', [
            'folder' => $folder_name
        ]);
        return false;
    }

    // Find the file by matching filename
    $file_id = null;
    $pdf_filename = basename($pdf_url);
    foreach ($json['value'] as $item) {
        if (strtolower($item['name']) === strtolower($pdf_filename)) {
            $file_id = $item['id'];
            break;
        }
    }
    
    if (!$file_id) {
        tm_sync_log('warning', 'PDF file not found in SharePoint folder', [
            'filename' => $pdf_filename,
            'folder' => $folder_name
        ]);
        return false;
    }

    tm_sync_log('debug', 'Found PDF in Image Media library', [
        'filename' => $pdf_filename,
        'file_id' => $file_id
    ]);

    // Download the file content using Graph API
    $download_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$file_id/content";
    
    $response = wp_remote_get($download_url, [
        'timeout' => 120,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
        'sslverify' => true,
        'redirection' => 10,
    ]);

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to download PDF from Image Media library', [
            'filename' => $filename,
            'error' => $response->get_error_message()
        ]);
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        tm_sync_log('error', 'PDF download returned HTTP error', [
            'filename' => $filename,
            'http_code' => $http_code
        ]);
        return false;
    }

    $file_content = wp_remote_retrieve_body($response);
    if (empty($file_content)) {
        tm_sync_log('error', 'Downloaded PDF is empty', ['filename' => $filename]);
        return false;
    }

    // Check if we got HTML instead of PDF (error indicator)
    if (strpos($file_content, '<!DOCTYPE') === 0 || strpos($file_content, '<html') === 0) {
        tm_sync_log('error', 'Downloaded content is HTML, not PDF', ['filename' => $filename]);
        return false;
    }

    // Verify it's a PDF
    if (strpos($file_content, '%PDF') !== 0) {
        tm_sync_log('warning', 'Downloaded file does not start with PDF marker', ['filename' => $filename]);
        // Still save it anyway, might be valid
    }

    // Ensure folder exists
    $pdf_dir = tm_sync_get_images_dir();
    if (!file_exists($pdf_dir)) {
        if (!wp_mkdir_p($pdf_dir)) {
            tm_sync_log('error', 'Failed to create sync folder for PDF', ['path' => $pdf_dir]);
            return false;
        }
    }

    // Construct full file path
    $file_path = trailingslashit($pdf_dir) . $filename;

    // Write file to disk
    $written = file_put_contents($file_path, $file_content);
    if ($written === false) {
        tm_sync_log('error', 'Failed to write PDF file to disk', ['path' => $file_path]);
        return false;
    }

    @chmod($file_path, 0644);

    tm_sync_log('info', 'Successfully downloaded and saved PDF', [
        'filename' => $filename,
        'size' => strlen($file_content),
        'path' => $file_path
    ]);

    return $file_path;
}

/**
 * Get URL for a synced PDF file
 * 
 * @param string $filename Filename (relative to sync folder)
 * @return string Full URL to the PDF file
 */
function tm_sync_get_pdf_url($filename) {
    if (empty($filename)) {
        return '';
    }

    // If already a full URL, return it
    if (strpos($filename, 'http') === 0 || strpos($filename, '//') === 0) {
        return $filename;
    }

    // If it's a relative path, convert to URL
    $base_url = tm_sync_get_images_url();
    return trailingslashit($base_url) . basename($filename);
}

?>
