<?php

tm_sync_log('INFO','Generic-Image-Sync File loading');

/**
 * Find attachment by filename in WordPress media library
 * Searches global media library to detect orphaned attachments from deleted posts
 * Performs case-insensitive search to handle old uploads and file name variations
 * 
 * @param string $filename File name to search for (original SharePoint filename)
 * @param string $image_type Type of image for logging (e.g., "photo", "logo")
 * @return int|null Attachment ID if found, null otherwise
 */
function tm_sync_find_attachment_by_filename($filename, $image_type = 'image') {
    global $wpdb;
    
    if (empty($filename)) {
        return null;
    }
    
    $clean_filename = basename($filename);
    $filename_no_ext = pathinfo($clean_filename, PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($clean_filename, PATHINFO_EXTENSION));
    
    tm_sync_log('debug', "Searching for orphaned $image_type attachment", [
        'search_filename' => $clean_filename,
        'base_name' => $filename_no_ext,
        'extension' => $extension
    ]);
    
    // Search attachments by checking their file paths
    // Case-insensitive search for: filename.ext, FILENAME.EXT, FileName.ext, etc.
    // Also searches for files with prefixes like "photo-9-filename.ext"
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, pm.meta_value
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'attachment'
         AND pm.meta_key = '_wp_attached_file'
         AND (
            pm.meta_value LIKE %s
            OR pm.meta_value LIKE %s
            OR pm.meta_value LIKE %s
         )
         LIMIT 50",
        '%' . $clean_filename,
        '%' . strtoupper($clean_filename),
        '%' . strtolower($clean_filename)
    ));
    
    if (empty($results)) {
        tm_sync_log('debug', "No attachments found with filename", [
            'search_filename' => $clean_filename
        ]);
        return null;
    }
    
    tm_sync_log('debug', "Found " . count($results) . " potential matches for $image_type", [
        'search_filename' => $clean_filename
    ]);
    
    // Check each result for filename match (prefer higher ID = more recent)
    $candidates = [];
    foreach ($results as $row) {
        $attached_file = $row->meta_value;
        $attached_filename = basename($attached_file);
        $attached_base = pathinfo($attached_filename, PATHINFO_FILENAME);
        $attached_ext = strtolower(pathinfo($attached_filename, PATHINFO_EXTENSION));
        
        // Match if:
        // 1. Exact filename match (dave.jpg = dave.jpg)
        // 2. Case-insensitive match (dave.jpg = Dave.jpg = DAVE.JPG)
        // 3. Filename with prefix (photo-9-dave.jpg where "dave.jpg" is after prefix)
        $exact_match = (strtolower($attached_filename) === strtolower($clean_filename));
        $base_match = (strtolower($attached_base) === strtolower($filename_no_ext) && 
                       strtolower($attached_ext) === $extension);
        
        // Check if the filename appears after a prefix pattern (e.g., "photo-9-")
        // Pattern: word-digits-filename.ext
        $prefix_match = false;
        if (preg_match('/^[a-z]+-\d+-(.+)$/i', $attached_filename, $matches)) {
            $after_prefix = $matches[1];
            if (strtolower($after_prefix) === strtolower($clean_filename)) {
                $prefix_match = true;
            }
        }
        
        if ($exact_match || $base_match || $prefix_match) {
            $candidates[] = [
                'id' => intval($row->ID),
                'filename' => $attached_filename,
                'file_path' => $attached_file
            ];
        }
    }
    
    if (empty($candidates)) {
        tm_sync_log('debug', "No matching attachments after filename comparison", [
            'search_filename' => $clean_filename,
            'checked' => count($results)
        ]);
        return null;
    }
    
    // Return the newest (highest ID) attachment for this filename
    // This handles the case where multiple copies exist (prefer the most recent)
    usort($candidates, function($a, $b) {
        return $b['id'] - $a['id'];  // Sort descending (newest first)
    });
    
    $selected = $candidates[0];
    tm_sync_log('info', "Found existing $image_type attachment in media library", [
        'search_filename' => $clean_filename,
        'found_filename' => $selected['filename'],
        'attachment_id' => $selected['id'],
        'candidates_found' => count($candidates),
        'action' => count($candidates) > 1 ? 'selected_most_recent' : 'found_single_match'
    ]);
    
    return $selected['id'];
}

/**
 * Check if we already downloaded this SharePoint file for a post
 * Checks both post meta (for already-attached files) and global media library (for orphaned files from deleted posts)
 * 
 * @param int $post_id WordPress post ID
 * @param string $filename File name
 * @param string $meta_key Meta key to store attachment ID (e.g., '_tm_logo', '_tm_photo')
 * @param string $image_type Type of image for logging (e.g., "photo", "logo")
 * @return int|null Attachment ID if found and exists, null otherwise
 */
function tm_sync_attachment_exists($post_id, $filename, $meta_key = '_tm_logo', $image_type = 'image') {
    // First check: Is this attachment linked to this post in post meta?
    $existing_attachment_id = get_post_meta($post_id, $meta_key, true);
    
    if (!empty($existing_attachment_id) && is_numeric($existing_attachment_id)) {
        $attachment = get_post($existing_attachment_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            $attached_file = get_attached_file($attachment->ID);
            if ($attached_file) {
                tm_sync_log('debug', "Found existing $image_type linked to post", array(
                    'filename' => $filename,
                    'meta_key' => $meta_key,
                    'attachment_id' => $existing_attachment_id,
                    'post_id' => $post_id
                ));
                return $existing_attachment_id;
            }
        }
    }
    
    // Second check: Search media library for orphaned file (from deleted posts)
    // This prevents duplicate uploads when a post is deleted and sync runs again
    $orphaned_attachment_id = tm_sync_find_attachment_by_filename($filename, $image_type);
    if ($orphaned_attachment_id) {
        tm_sync_log('info', "Found orphaned $image_type in media library (post may have been deleted)", array(
            'filename' => $filename,
            'attachment_id' => $orphaned_attachment_id,
            'post_id' => $post_id,
            'action' => 'reattaching orphaned file'
        ));
        // Reattach the orphaned file to this post
        update_post_meta($post_id, $meta_key, $orphaned_attachment_id);
        return $orphaned_attachment_id;
    }
    
    return null;
}

/**
 * Generic image download from SharePoint Image Media
 * 
 * Handles discovery of the correct folder and downloads the image file,
 * then creates a WordPress attachment for it.
 * 
 * @param string $filename File name to download (e.g., "kim-headshot.avif")
 * @param string $image_url SharePoint image URL (for folder detection)
 * @param string $filename_prefix Prefix for uploaded file (e.g., "photo-5" for ID-based prefix)
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return int|null Attachment ID if successful, null otherwise
 */
function tm_sync_download_image_from_media_library(
    $filename,
    $image_url,
    $filename_prefix,
    $site_id,
    $image_media_list_id,
    $token
) {
    if (empty($filename)) {
        return null;
    }

    tm_sync_log('debug', 'Downloading image from Image Media library', array(
        'filename' => $filename,
        'image_url' => $image_url
    ));

    // Discover folder ID from URL (with auto-caching)
    $folder_id = tm_sync_get_folder_id_from_image_url(
        $image_url,
        $site_id,
        $image_media_list_id,
        $token
    );

    if (!$folder_id) {
        tm_sync_log('warning', 'Could not determine folder for image', [
            'filename' => $filename,
            'url' => $image_url
        ]);
        return null;
    }

    tm_sync_log('debug', 'Using folder for image download', [
        'filename' => $filename,
        'folder_id' => $folder_id
    ]);

    // Search for the file in the discovered folder
    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$folder_id/children";

    $response = wp_remote_get($search_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to list folder in Image Media', [
            'error' => $response->get_error_message(),
            'filename' => $filename
        ]);
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    $file_id = null;
    if (isset($json['value'])) {
        foreach ($json['value'] as $item) {
            if (strtolower($item['name']) === strtolower($filename)) {
                $file_id = $item['id'];
                break;
            }
        }
    }

    if (!$file_id) {
        tm_sync_log('warning', 'File not found in Image Media folder', [
            'filename' => $filename,
            'folder_id' => $folder_id
        ]);
        return null;
    }

    tm_sync_log('debug', 'Found file in Image Media', [
        'filename' => $filename,
        'file_id' => $file_id
    ]);

    // Download the file content
    $download_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$file_id/content";

    $response = wp_remote_get($download_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
        'redirection' => 10,  // Follow redirects
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to download file from Image Media', [
            'filename' => $filename,
            'error' => $response->get_error_message()
        ]);
        return null;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        tm_sync_log('error', 'Failed to download file - HTTP error', [
            'filename' => $filename,
            'http_code' => $http_code
        ]);
        return null;
    }

    $file_content = wp_remote_retrieve_body($response);
    if (empty($file_content)) {
        tm_sync_log('error', 'Downloaded file is empty', [
            'filename' => $filename
        ]);
        return null;
    }

    tm_sync_log('debug', 'File downloaded from Image Media', [
        'filename' => $filename,
        'size' => strlen($file_content)
    ]);

    // Get upload directory
    $wp_upload_dir = wp_upload_dir();
    $upload_path = $wp_upload_dir['path'];

    // Create destination filename
    $safe_filename = sanitize_file_name($filename);
    $dest_file = $upload_path . '/' . $filename_prefix . '-' . $safe_filename;

    // Copy file directly to WordPress uploads folder
    if (!file_put_contents($dest_file, $file_content)) {
        tm_sync_log('error', 'Failed to write file to uploads folder', [
            'filename' => $filename,
            'dest' => $dest_file
        ]);
        return null;
    }

    @chmod($dest_file, 0644);

    // Determine MIME type based on file extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    );
    $mime_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';

    // Create attachment record in WordPress
    $attachment = array(
        'post_mime_type' => $mime_type,
        'post_title'     => $filename_prefix,
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attachment_id = wp_insert_attachment($attachment, $dest_file);

    if (is_wp_error($attachment_id)) {
        tm_sync_log('error', 'Failed to insert attachment', [
            'filename' => $filename,
            'error' => $attachment_id->get_error_message()
        ]);
        @unlink($dest_file);
        return null;
    }

    // Generate attachment metadata (thumbnails, etc.)
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $dest_file);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    tm_sync_log('info', 'Image successfully downloaded from Image Media library', array(
        'filename' => $filename,
        'attachment_id' => $attachment_id,
        'dest_file' => $dest_file
    ));

    return $attachment_id;
}

/**
 * Sync image for a post
 * 
 * High-level function that handles:
 * - Checking for existing attachment
 * - Downloading if needed
 * - Storing attachment ID in post meta
 * 
 * @param int $post_id WordPress post ID
 * @param string $filename File name to download
 * @param string $image_url SharePoint image URL (for folder detection)
 * @param string $meta_key Post meta key for storing attachment ID
 * @param string $sp_id SharePoint item ID (for filename prefix)
 * @param string $image_type Type of image for logging (e.g., "logo", "photo", "banner")
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return int|null Attachment ID if successful, null otherwise
 */
function tm_sync_image_for_post(
    $post_id,
    $filename,
    $image_url,
    $meta_key,
    $sp_id,
    $image_type,
    $site_id,
    $image_media_list_id,
    $token
) {
    if (empty($filename) || empty($image_url)) {
        return null;
    }

    tm_sync_log('debug', "Attempting to download $image_type from Image Media library", [
        'filename' => $filename,
        'post_id' => $post_id
    ]);

    // Check if we already have this attachment (checks post meta and global media library)
    $existing_attachment_id = tm_sync_attachment_exists($post_id, $filename, $meta_key, $image_type);
    if ($existing_attachment_id) {
        tm_sync_log('info', "Reusing existing $image_type attachment (not re-downloading)", array(
            'filename' => $filename,
            'attachment_id' => $existing_attachment_id,
            'post_id' => $post_id
        ));
        update_post_meta($post_id, $meta_key, $existing_attachment_id);
        return $existing_attachment_id;
    }

    // Download the image
    $attachment_id = tm_sync_download_image_from_media_library(
        $filename,
        $image_url,
        "$image_type-$sp_id",
        $site_id,
        $image_media_list_id,
        $token
    );

    if ($attachment_id) {
        update_post_meta($post_id, $meta_key, $attachment_id);
        tm_sync_log('info', "Successfully attached $image_type", array(
            'filename' => $filename,
            'attachment_id' => $attachment_id,
            'post_id' => $post_id
        ));
        return $attachment_id;
    } else {
        tm_sync_log('warning', "Could not sync $image_type", array(
            'filename' => $filename,
            'post_id' => $post_id,
            'note' => 'File may not exist in SharePoint Image Media'
        ));
        return null;
    }
}
