<?php

tm_sync_log('INFO','Advertiser-Sync File loading');

/**
 * Convert SharePoint hyperlink URL to Graph API file reference
 * URLs like "https://miltonplayers.sharepoint.com/Image%20Media/Advertisers/filename.jpg"
 * Get converted to API path for download
 */
function tm_sync_convert_sharepoint_url_to_file_path($url) {
    // Parse URL to extract path components
    // Example: /Image%20Media/Advertisers/sandytoes.jpg
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    
    // Decode and normalize path
    $path = urldecode($path);
    
    // Remove leading slash and extract folder structure
    $path = ltrim($path, '/');
    
    // Capitalize for proper folder names
    // /Image Media/Advertisers/filename.jpg -> Image Media/Advertisers/filename.jpg
    
    return $path;
}

/**
 * Check if we already downloaded this SharePoint file for this advertiser
 * Returns attachment ID if found, null otherwise
 */
function tm_sync_attachment_exists_for_advertiser($post_id, $filename, $type = 'logo') {
    // Check if we have metadata about already downloaded images
    $existing_attachment_id = get_post_meta($post_id, '_tm_' . $type, true);
    
    if (!empty($existing_attachment_id) && is_numeric($existing_attachment_id)) {
        // Verify the attachment still exists and has the right file
        $attachment = get_post($existing_attachment_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            $attached_file = get_attached_file($attachment->ID);
            if ($attached_file) {
                tm_sync_log('debug', 'Found existing attachment for advertiser.', array(
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

function tm_sync_download_media_from_image_library($filename, $filename_prefix) {
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
    $advertisers_folder_id = '01JHLCF5HIHU4LFHIXHNBISNH3NBF2BDOM';
    
    // Get the file ID from Advertisers folder by searching for the filename
    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/items/$advertisers_folder_id/children";
    
    $response = wp_remote_get($search_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to list Image Media folder.', [
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
        tm_sync_log('warning', 'File not found in Image Media library.', ['filename' => $filename]);
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

function tm_sync_fetch_sharepoint_data($list_name) {
    tm_sync_log('debug', 'Fetching SharePoint data.', ['list_name' => $list_name]);

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items', ['list_name' => $list_name]);
    $items = $client->get_list_items($list_name);

    tm_sync_log('debug', 'API Response received', ['items_type' => gettype($items), 'items_count' => is_array($items) ? count($items) : 0]);

    if ($items === null) {
        tm_sync_log('error', 'API client returned null - credentials or list may be invalid', ['list' => $list_name]);
        return null;
    }

    if (!is_array($items)) {
        tm_sync_log('error', 'Failed to fetch items from SharePoint list - not an array.', ['list' => $list_name, 'items_type' => gettype($items)]);
        return null;
    }

    if (empty($items)) {
        tm_sync_log('warning', 'SharePoint list is empty', ['list' => $list_name]);
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint data successfully.', ['list' => $list_name, 'item_count' => count($items)]);
    return $items;
}

function tm_sync_process_item($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $title = trim($fields['Title'] ?? '');
        
        // Website can be a string or an object with Url property
        $website = '';
        if (isset($fields['Website'])) {
            if (is_array($fields['Website']) || is_object($fields['Website'])) {
                $website = $fields['Website']['Url'] ?? $fields['Website']->Url ?? '';
            } else {
                $website = $fields['Website'];
            }
        }
        $website = isset($website) ? tm_sync_normalize_url($website) : '';
        
        // Logo can be a string or an object with Url property
        $logo_url = '';
        if (isset($fields['Logo'])) {
            if (is_array($fields['Logo']) || is_object($fields['Logo'])) {
                $logo_url = $fields['Logo']['Url'] ?? $fields['Logo']->Url ?? '';
            } else {
                $logo_url = $fields['Logo'];
            }
        }
        
        // Banner can be a string or an object with Url property
        $banner_url = '';
        if (isset($fields['Banner'])) {
            if (is_array($fields['Banner']) || is_object($fields['Banner'])) {
                $banner_url = $fields['Banner']['Url'] ?? $fields['Banner']->Url ?? '';
            } else {
                $banner_url = $fields['Banner'];
            }
        }
        
        // PDF can be a string or an object with Url property
        $pdf_url = '';
        if (isset($fields['PDF'])) {
            if (is_array($fields['PDF']) || is_object($fields['PDF'])) {
                $pdf_url = $fields['PDF']['Url'] ?? $fields['PDF']->Url ?? '';
            } else {
                $pdf_url = $fields['PDF'];
            }
        }
        
        $is_restaurant = $fields['IsRestaurant'] ?? false;
        $description = $fields['Description'] ?? '';

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'title' => $title, 'title_type' => gettype($title), 'title_empty' => empty($title)]);

        if (!$title || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing title or ID.', ['sp_id' => $sp_id, 'title' => $title, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing advertiser', ['title' => $title, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'advertiser',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

    if ($dry_run) {
        tm_sync_log('debug', 'Dry-run: Would process item.', ['title' => $title, 'sp_id' => $sp_id]);
        return true;
    }

    $post_data = [
        'post_title' => $title,
        'post_content' => $description,
        'post_type' => 'advertiser',
        'post_status' => 'publish'
    ];

    if ($existing) {
        $post_id = $existing[0]->ID;
        wp_update_post(array_merge($post_data, ['ID' => $post_id]));
        tm_sync_log('info', 'Updated advertiser.', ['title' => $title, 'sp_id' => $sp_id, 'post_id' => $post_id]);
    } else {
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            tm_sync_log('error', 'Failed to insert post.', ['title' => $title, 'error' => $post_id->get_error_message()]);
            return false;
        }
        update_post_meta($post_id, '_tm_sp_id', $sp_id);
        tm_sync_log('info', 'Created advertiser.', ['title' => $title, 'sp_id' => $sp_id, 'post_id' => $post_id]);
    }

    update_post_meta($post_id, '_tm_name', $title);
    update_post_meta($post_id, '_tm_website', $website);
    update_post_meta($post_id, '_tm_restaurant', $is_restaurant);
    update_post_meta($post_id, '_tm_description', $description);

    if ($logo_url) {
        // Extract filename from URL (e.g., "sandytoes.jpg" from the URL)
        $filename = basename($logo_url);
        
        // Check if we already have this attachment for this advertiser
        $existing_logo_id = tm_sync_attachment_exists_for_advertiser($post_id, $filename, 'logo');
        if ($existing_logo_id) {
            tm_sync_log('info', 'Reusing existing logo attachment (not re-downloading).', array(
                'filename' => $filename,
                'attachment_id' => $existing_logo_id,
                'post_id' => $post_id
            ));
            update_post_meta($post_id, '_tm_logo', $existing_logo_id);
        } else {
            tm_sync_log('debug', 'Attempting to download logo from Image Media library.', array('filename' => $filename));
            $logo_id = tm_sync_download_media_from_image_library($filename, "logo-{$sp_id}");
            if ($logo_id) {
                update_post_meta($post_id, '_tm_logo', $logo_id);
                tm_sync_log('info', 'Logo successfully attached.', array('filename' => $filename, 'attachment_id' => $logo_id));
            } else {
                tm_sync_log('warning', 'Logo could not be synced.', array(
                    'filename' => $filename,
                    'note' => 'File may not exist in SharePoint Image Media library'
                ));
            }
        }
    }

    if ($banner_url) {
        $filename = basename($banner_url);
        
        // Check if we already have this attachment for this advertiser
        $existing_banner_id = tm_sync_attachment_exists_for_advertiser($post_id, $filename, 'banner');
        if ($existing_banner_id) {
            tm_sync_log('info', 'Reusing existing banner attachment (not re-downloading).', array(
                'filename' => $filename,
                'attachment_id' => $existing_banner_id,
                'post_id' => $post_id
            ));
            update_post_meta($post_id, '_tm_banner', $existing_banner_id);
        } else {
            tm_sync_log('debug', 'Attempting to download banner from Image Media library.', array('filename' => $filename));
            $banner_id = tm_sync_download_media_from_image_library($filename, "banner-{$sp_id}");
            if ($banner_id) {
                update_post_meta($post_id, '_tm_banner', $banner_id);
                tm_sync_log('info', 'Banner successfully attached.', array('filename' => $filename, 'attachment_id' => $banner_id));
            } else {
                tm_sync_log('warning', 'Banner could not be synced.', array('filename' => $filename));
            }
        }
    }

    if ($pdf_url) {
        $filename = basename($pdf_url);
        
        // Check if we already have this attachment for this advertiser
        $existing_pdf_id = tm_sync_attachment_exists_for_advertiser($post_id, $filename, 'pdf');
        if ($existing_pdf_id) {
            tm_sync_log('info', 'Reusing existing PDF attachment (not re-downloading).', array(
                'filename' => $filename,
                'attachment_id' => $existing_pdf_id,
                'post_id' => $post_id
            ));
            update_post_meta($post_id, '_tm_pdf', $existing_pdf_id);
        } else {
            tm_sync_log('debug', 'Attempting to download PDF from Image Media library.', array('filename' => $filename));
            $pdf_id = tm_sync_download_media_from_image_library($filename, "pdf-{$sp_id}");
            if ($pdf_id) {
                update_post_meta($post_id, '_tm_pdf', $pdf_id);
                tm_sync_log('info', 'PDF successfully attached.', array('filename' => $filename, 'attachment_id' => $pdf_id));
            } else {
                tm_sync_log('warning', 'PDF could not be synced.', array('filename' => $filename));
            }
        }
    }

    tm_sync_log('debug', 'Finished processing advertiser.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
    return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_advertisers($dry_run = false) {
    tm_sync_log('info', 'Starting advertiser sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_sharepoint_data('Advertisers');
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_item($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} item(s) would be synced."
        : "Sync complete: {$count} item(s) synced.";

    tm_sync_log('info', 'Advertiser sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
