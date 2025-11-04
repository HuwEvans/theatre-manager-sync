<?php

tm_sync_log('INFO','Generic-Image-Sync File loading');

/**
 * Check if we already downloaded this SharePoint file for a post
 * 
 * @param int $post_id WordPress post ID
 * @param string $filename File name
 * @param string $meta_key Meta key to store attachment ID (e.g., '_tm_logo', '_tm_photo')
 * @return int|null Attachment ID if found and exists, null otherwise
 */
function tm_sync_attachment_exists($post_id, $filename, $meta_key = '_tm_logo') {
    $existing_attachment_id = get_post_meta($post_id, $meta_key, true);
    
    if (!empty($existing_attachment_id) && is_numeric($existing_attachment_id)) {
        $attachment = get_post($existing_attachment_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            $attached_file = get_attached_file($attachment->ID);
            if ($attached_file) {
                tm_sync_log('debug', 'Found existing attachment', array(
                    'filename' => $filename,
                    'meta_key' => $meta_key,
                    'attachment_id' => $existing_attachment_id
                ));
                return $existing_attachment_id;
            }
        }
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

    // Check if we already have this attachment
    $existing_attachment_id = tm_sync_attachment_exists($post_id, $filename, $meta_key);
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
