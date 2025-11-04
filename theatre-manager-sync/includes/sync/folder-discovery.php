<?php

tm_sync_log('INFO','Folder-Discovery File loading');

/**
 * Extract folder name from SharePoint image URL
 * 
 * Examples:
 * - https://miltonplayers.sharepoint.com/Image%20Media/People/kim-headshot.avif -> "People"
 * - https://miltonplayers.sharepoint.com/Image%20Media/Sponsors/logo.png -> "Sponsors"
 * - https://miltonplayers.sharepoint.com/Image%20Media/Advertisers/sandytoes.jpg -> "Advertisers"
 * 
 * @param string $image_url SharePoint image URL
 * @return string|null Folder name or null if not found
 */
function tm_sync_extract_folder_from_url($image_url) {
    if (empty($image_url)) {
        return null;
    }

    // Parse the URL path
    $parsed = parse_url($image_url);
    if (!isset($parsed['path'])) {
        return null;
    }

    $path = $parsed['path'];
    
    // Decode URL-encoded characters
    $path = urldecode($path);
    
    // Extract path components
    // Expected format: /Image Media/[FolderName]/filename.ext
    // or: /sites/sitename/Image%20Media/[FolderName]/filename.ext
    
    preg_match('/Image\s+Media\/([^\/]+)\/[^\/]+$/', $path, $matches);
    
    if (isset($matches[1])) {
        $folder_name = trim($matches[1]);
        tm_sync_log('debug', 'Extracted folder from URL', [
            'url' => $image_url,
            'folder' => $folder_name
        ]);
        return $folder_name;
    }
    
    tm_sync_log('warning', 'Could not extract folder from URL', [
        'url' => $image_url,
        'path' => $path
    ]);
    return null;
}

/**
 * Get cached folder IDs for Image Media library
 * Returns all discovered/cached folder IDs
 * 
 * @return array Folder names -> IDs mapping
 */
function tm_sync_get_cached_folder_ids() {
    $cached = get_option('tm_sync_image_media_folder_ids', []);
    return is_array($cached) ? $cached : [];
}

/**
 * Store folder ID in cache
 * 
 * @param string $folder_name Folder name (e.g., "People", "Advertisers")
 * @param string $folder_id SharePoint folder ID
 */
function tm_sync_cache_folder_id($folder_name, $folder_id) {
    $cached = tm_sync_get_cached_folder_ids();
    $cached[$folder_name] = $folder_id;
    update_option('tm_sync_image_media_folder_ids', $cached);
    
    tm_sync_log('debug', 'Cached folder ID', [
        'folder_name' => $folder_name,
        'folder_id' => $folder_id
    ]);
}

/**
 * Clear all cached folder IDs
 */
function tm_sync_clear_folder_cache() {
    delete_option('tm_sync_image_media_folder_ids');
    tm_sync_log('info', 'Cleared folder ID cache');
}

/**
 * Get folder ID from cache or discover it
 * 
 * First checks if folder ID is cached. If not, discovers it from SharePoint
 * and caches the result for future use.
 * 
 * @param string $folder_name Folder name to find (e.g., "People")
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return string|null Folder ID if found, null otherwise
 */
function tm_sync_get_folder_id($folder_name, $site_id, $image_media_list_id, $token) {
    if (empty($folder_name)) {
        tm_sync_log('warning', 'Folder name is empty');
        return null;
    }

    // Check cache first
    $cached_ids = tm_sync_get_cached_folder_ids();
    if (isset($cached_ids[$folder_name])) {
        tm_sync_log('debug', 'Using cached folder ID', [
            'folder_name' => $folder_name,
            'folder_id' => $cached_ids[$folder_name]
        ]);
        return $cached_ids[$folder_name];
    }

    // Not in cache, discover from SharePoint
    tm_sync_log('debug', 'Folder not in cache, discovering from SharePoint', [
        'folder_name' => $folder_name
    ]);

    $folder_id = tm_sync_discover_folder_id($folder_name, $site_id, $image_media_list_id, $token);
    
    if ($folder_id) {
        // Cache for future use
        tm_sync_cache_folder_id($folder_name, $folder_id);
        return $folder_id;
    }

    tm_sync_log('warning', 'Folder not found in SharePoint', [
        'folder_name' => $folder_name
    ]);
    return null;
}

/**
 * Discover folder ID from SharePoint by searching root of Image Media
 * 
 * @param string $folder_name Folder name to find
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return string|null Folder ID if found, null otherwise
 */
function tm_sync_discover_folder_id($folder_name, $site_id, $image_media_list_id, $token) {
    if (empty($folder_name)) {
        return null;
    }

    tm_sync_log('debug', 'Searching for folder in Image Media', [
        'folder_name' => $folder_name
    ]);

    // Get root contents of Image Media drive
    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/root/children";

    $response = wp_remote_get($search_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to list Image Media root folder', [
            'error' => $response->get_error_message(),
            'folder_name' => $folder_name
        ]);
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (!isset($json['value'])) {
        tm_sync_log('warning', 'No items in Image Media root', [
            'folder_name' => $folder_name
        ]);
        return null;
    }

    // Search for matching folder (case-insensitive)
    foreach ($json['value'] as $item) {
        if (strtolower($item['name']) === strtolower($folder_name)) {
            tm_sync_log('info', 'Found folder in Image Media', [
                'folder_name' => $folder_name,
                'folder_id' => $item['id']
            ]);
            return $item['id'];
        }
    }

    tm_sync_log('warning', 'Folder not found in Image Media root', [
        'folder_name' => $folder_name,
        'available_folders' => array_column($json['value'], 'name')
    ]);
    
    return null;
}

/**
 * Discover all folders in Image Media and cache them
 * Useful for admin interface or troubleshooting
 * 
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return array Discovered folders (name => id)
 */
function tm_sync_discover_all_folders($site_id, $image_media_list_id, $token) {
    tm_sync_log('info', 'Discovering all folders in Image Media');

    $search_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$image_media_list_id/drive/root/children";

    $response = wp_remote_get($search_url, array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        tm_sync_log('error', 'Failed to discover folders', [
            'error' => $response->get_error_message()
        ]);
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    $discovered = [];
    if (isset($json['value'])) {
        foreach ($json['value'] as $item) {
            // Only include folders (items with 'folder' property)
            if (isset($item['folder'])) {
                $discovered[$item['name']] = $item['id'];
            }
        }
    }

    // Cache all discovered folders
    if (!empty($discovered)) {
        update_option('tm_sync_image_media_folder_ids', array_merge(
            tm_sync_get_cached_folder_ids(),
            $discovered
        ));
        
        tm_sync_log('info', 'Discovered and cached folders', [
            'count' => count($discovered),
            'folders' => array_keys($discovered)
        ]);
    }

    return $discovered;
}

/**
 * Get folder ID for a file based on its SharePoint URL
 * Combines URL parsing and folder discovery
 * 
 * @param string $image_url SharePoint image URL
 * @param string $site_id SharePoint site ID
 * @param string $image_media_list_id Image Media library ID
 * @param string $token Microsoft Graph API access token
 * @return string|null Folder ID if found, null otherwise
 */
function tm_sync_get_folder_id_from_image_url($image_url, $site_id, $image_media_list_id, $token) {
    // Extract folder name from URL
    $folder_name = tm_sync_extract_folder_from_url($image_url);
    
    if (!$folder_name) {
        tm_sync_log('warning', 'Could not extract folder name from image URL', [
            'url' => $image_url
        ]);
        return null;
    }

    // Get folder ID (from cache or discover)
    return tm_sync_get_folder_id($folder_name, $site_id, $image_media_list_id, $token);
}
