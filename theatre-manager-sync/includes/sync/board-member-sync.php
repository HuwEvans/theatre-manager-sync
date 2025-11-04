<?php

tm_sync_log('INFO','Board-Member-Sync File loading');

/**
 * Check if we already downloaded this SharePoint file for this board member
 * Returns attachment ID if found, null otherwise
 */
function tm_sync_attachment_exists_for_board_member($post_id, $filename, $type = 'photo') {
    // Check if we have metadata about already downloaded images
    $existing_attachment_id = get_post_meta($post_id, '_tm_' . $type, true);
    
    if (!empty($existing_attachment_id) && is_numeric($existing_attachment_id)) {
        // Verify the attachment still exists and has the right file
        $attachment = get_post($existing_attachment_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            $attached_file = get_attached_file($attachment->ID);
            if ($attached_file) {
                tm_sync_log('debug', 'Found existing attachment for board member.', array(
                    'filename' => $filename,
                    'type' => $type,
                    'attachment_id' => $existing_attachment_id,
                    'attached_file' => $attached_file
                ));
                return $existing_attachment_id;
            }
        }
    }
    
    return null;
}

function tm_sync_download_media_for_board_member($filename, $filename_prefix) {
    if (empty($filename)) {
        return null;
    }

    tm_sync_log('debug', 'Downloading image from Image Media library.', array('filename' => $filename));
    
    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not available');
        return null;
    }

    $client = new TM_Graph_Client();
    $token = $client->get_access_token_public();
    
    if (!$token) {
        tm_sync_log('error', 'Failed to get access token');
        return null;
    }

    // Get Site and List IDs from options
    $site_id = 'miltonplayers.sharepoint.com,9122b47c-2748-446f-820e-ab3bc46b80d0,5d9211a6-6d28-4644-ad40-82fe3972fbf1';
    $image_media_list_id = '36cd8ce2-6611-401a-ae0c-20dd4abcf36b';
    $board_members_folder_id = '01JHLCF5AMCWQY2NPGBFJ7BWVJ32DL3L5Z';  // Board Members folder ID
    
    // Get the file ID from Board Members folder by searching for the filename
    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$board_members_folder_id/children";
    
    $response = wp_remote_get($search_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to list Board Members folder in Image Media.', [
            'error' => $response->get_error_message()
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
        tm_sync_log('warning', 'File not found in Image Media Board Members folder.', ['filename' => $filename]);
        return null;
    }

    tm_sync_log('debug', 'Found file in Image Media library.', ['filename' => $filename, 'file_id' => $file_id]);

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
        tm_sync_log('error', 'Failed to download file from Image Media library.', [
            'filename' => $filename,
            'error' => $response->get_error_message()
        ]);
        return null;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        tm_sync_log('error', 'Failed to download file - HTTP error.', [
            'filename' => $filename,
            'http_code' => $http_code
        ]);
        return null;
    }

    $file_content = wp_remote_retrieve_body($response);
    if (empty($file_content)) {
        tm_sync_log('error', 'Downloaded file is empty.', ['filename' => $filename]);
        return null;
    }

    tm_sync_log('debug', 'File downloaded from Image Media library.', [
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
        tm_sync_log('error', 'Failed to write file to uploads folder.', ['filename' => $filename, 'dest' => $dest_file]);
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
        tm_sync_log('error', 'Failed to insert attachment.', ['filename' => $filename, 'error' => $attachment_id->get_error_message()]);
        @unlink($dest_file);
        return null;
    }

    // Generate attachment metadata (thumbnails, etc.)
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $dest_file);
    wp_update_attachment_metadata($attachment_id, $attach_data);
    
    tm_sync_log('info', 'Image successfully downloaded from Image Media library.', array(
        'filename' => $filename,
        'attachment_id' => $attachment_id,
        'dest_file' => $dest_file
    ));
    
    return $attachment_id;
}

function tm_sync_fetch_board_members_data() {
    tm_sync_log('debug', 'Fetching SharePoint board members data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Board Members');
    $items = $client->get_list_items('Board Members');

    tm_sync_log('debug', 'API Response received', ['items_type' => gettype($items), 'items_count' => is_array($items) ? count($items) : 0]);

    if ($items === null) {
        tm_sync_log('error', 'API client returned null - credentials or list may be invalid');
        return null;
    }

    if (!is_array($items)) {
        tm_sync_log('error', 'Failed to fetch items from SharePoint list - not an array.', ['items_type' => gettype($items)]);
        return null;
    }

    if (empty($items)) {
        tm_sync_log('warning', 'SharePoint Board Members list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint board members data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_board_member($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing board member item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? '');
        $position = trim($fields['Position'] ?? '');
        
        // Photo can be a string or an object with Url property
        $photo_url = '';
        if (isset($fields['Photo'])) {
            if (is_array($fields['Photo']) || is_object($fields['Photo'])) {
                $photo_url = $fields['Photo']['Url'] ?? $fields['Photo']->Url ?? '';
            } else {
                $photo_url = $fields['Photo'];
            }
        }

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'position' => $position]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing board member', ['name' => $name, 'position' => $position, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'board_member',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process board member.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'board_member',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated board member.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert board member post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created board member.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update board member metadata
        update_post_meta($post_id, '_tm_name', $name);
        update_post_meta($post_id, '_tm_position', $position);

        if ($photo_url) {
            // Extract filename from URL (e.g., "john-doe.jpg")
            $filename = basename($photo_url);
            
            // Check if we already have this attachment for this board member
            $existing_photo_id = tm_sync_attachment_exists_for_board_member($post_id, $filename, 'photo');
            if ($existing_photo_id) {
                tm_sync_log('info', 'Reusing existing photo attachment (not re-downloading).', array(
                    'filename' => $filename,
                    'attachment_id' => $existing_photo_id,
                    'post_id' => $post_id
                ));
                update_post_meta($post_id, '_tm_photo', $existing_photo_id);
            } else {
                tm_sync_log('debug', 'Attempting to download photo from Image Media library.', array('filename' => $filename));
                $photo_id = tm_sync_download_media_for_board_member($filename, "photo-{$sp_id}");
                if ($photo_id) {
                    update_post_meta($post_id, '_tm_photo', $photo_id);
                    tm_sync_log('info', 'Photo successfully attached.', array('filename' => $filename, 'attachment_id' => $photo_id));
                } else {
                    tm_sync_log('warning', 'Photo could not be synced.', array(
                        'filename' => $filename,
                        'note' => 'File may not exist in SharePoint Image Media Board Members folder'
                    ));
                }
            }
        }

        tm_sync_log('debug', 'Finished processing board member.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing board member item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing board member item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_board_members($dry_run = false) {
    tm_sync_log('info', 'Starting board members sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_board_members_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_board_member($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process board member item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} board member(s) would be synced."
        : "Sync complete: {$count} board member(s) synced.";

    tm_sync_log('info', 'Board members sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
