<?php

tm_sync_log('INFO','Seasons-Sync File loading');

function tm_sync_fetch_seasons_data() {
    tm_sync_log('debug', 'Fetching SharePoint seasons data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Seasons');
    $items = $client->get_list_items('Seasons');

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
        tm_sync_log('warning', 'SharePoint Seasons list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint seasons data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_season($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing season item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        // Log all available fields for debugging
        error_log('[SEASONS_DEBUG] Complete SharePoint item: ' . json_encode($item, JSON_PRETTY_PRINT));
        error_log('[SEASONS_DEBUG] Fields array: ' . json_encode($fields, JSON_PRETTY_PRINT));
        error_log('[SEASONS_DEBUG] Field keys: ' . implode(', ', array_keys((array)$fields)));
        
        $sp_id = $item['id'] ?? null;
        // SharePoint field mapping for Seasons:
        // - SeasonName contains season name (fallback to Title)
        // - StartDate, EndDate for dates
        // - WebsireBanner, 3-upFront, 3-upBack, SMSquare, SMPortrait for images
        // - IsCurrentSeason, IsUpcomingSeason for flags
        $name = trim($fields['SeasonName'] ?? $fields['Title'] ?? '');
        $start_date = trim($fields['StartDate'] ?? '');
        $end_date = trim($fields['EndDate'] ?? '');
        $is_current_season = trim($fields['IsCurrentSeason'] ?? '');
        $is_upcoming_season = trim($fields['IsUpcomingSeason'] ?? '');
        
        // DETAILED DEBUG LOGGING
        error_log('[SEASONS_DEBUG] SharePoint field mapping:');
        error_log('[SEASONS_DEBUG] - SeasonName field: ' . (isset($fields['SeasonName']) ? 'EXISTS' : 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - Title field: ' . (isset($fields['Title']) ? 'EXISTS' : 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - Final name value: "' . $name . '"');
        error_log('[SEASONS_DEBUG] - Start date: "' . $start_date . '"');
        error_log('[SEASONS_DEBUG] - End date: "' . $end_date . '"');
        error_log('[SEASONS_DEBUG] - Is current: "' . $is_current_season . '"');
        error_log('[SEASONS_DEBUG] - Is upcoming: "' . $is_upcoming_season . '"');
        
        // Helper function to extract URL from hyperlink/image field
        $extract_url = function($field) {
            if (empty($field)) return '';
            if (is_string($field)) return $field;
            if (is_array($field)) return $field['Url'] ?? '';
            if (is_object($field)) return $field->Url ?? '';
            return '';
        };
        
        $website_banner_url = $extract_url($fields['WebsireBanner'] ?? '');
        $image_front_url = $extract_url($fields['3-upFront'] ?? '');
        $image_back_url = $extract_url($fields['3-upBack'] ?? '');
        $sm_square_url = $extract_url($fields['SMSquare'] ?? '');
        $sm_portrait_url = $extract_url($fields['SMPortrait'] ?? '');

        error_log('[SEASONS_DEBUG] Extracted images:');
        error_log('[SEASONS_DEBUG] - Website banner: ' . ($website_banner_url ? 'YES' : 'NO'));
        error_log('[SEASONS_DEBUG] - Front image: ' . ($image_front_url ? 'YES' : 'NO'));
        error_log('[SEASONS_DEBUG] - Back image: ' . ($image_back_url ? 'YES' : 'NO'));
        error_log('[SEASONS_DEBUG] - SM square: ' . ($sm_square_url ? 'YES' : 'NO'));
        error_log('[SEASONS_DEBUG] - SM portrait: ' . ($sm_portrait_url ? 'YES' : 'NO'));

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'start_date' => $start_date, 'is_current' => $is_current_season, 'is_upcoming' => $is_upcoming_season]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing season', ['name' => $name, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'season',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process season.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'season',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated season.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert season post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created season.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update season metadata
        update_post_meta($post_id, '_tm_season_name', $name);
        update_post_meta($post_id, '_tm_season_start_date', $start_date);
        update_post_meta($post_id, '_tm_season_end_date', $end_date);
        update_post_meta($post_id, '_tm_season_is_current', $is_current_season);
        update_post_meta($post_id, '_tm_season_is_upcoming', $is_upcoming_season);
        
        // Store all image URLs - images will be synced below
        // _tm_season_image_front and _tm_season_image_back are synced via tm_sync_image_for_post
        // Store additional images
        update_post_meta($post_id, '_tm_season_social_banner', $website_banner_url);
        update_post_meta($post_id, '_tm_season_sm_square', $sm_square_url);
        update_post_meta($post_id, '_tm_season_sm_portrait', $sm_portrait_url);
        
        // VERIFY METADATA WAS SAVED
        $verify_name = get_post_meta($post_id, '_tm_season_name', true);
        $verify_start = get_post_meta($post_id, '_tm_season_start_date', true);
        $verify_end = get_post_meta($post_id, '_tm_season_end_date', true);
        
        error_log('[SEASONS_DEBUG] Saved metadata for season: name=' . $name . ', post_id=' . $post_id);
        error_log('[SEASONS_DEBUG] VERIFICATION - Retrieved from DB:');
        error_log('[SEASONS_DEBUG] - Name: "' . $verify_name . '" (matches: ' . ($verify_name === $name ? 'YES' : 'NO') . ')');
        error_log('[SEASONS_DEBUG] - Start date: "' . $verify_start . '" (matches: ' . ($verify_start === $start_date ? 'YES' : 'NO') . ')');
        error_log('[SEASONS_DEBUG] - End date: "' . $verify_end . '" (matches: ' . ($verify_end === $end_date ? 'YES' : 'NO') . ')');

        // Get access token for image syncing
        if (!class_exists('TM_Graph_Client')) {
            tm_sync_log('warning', 'TM_Graph_Client not available for image sync');
        } else {
            $client = new TM_Graph_Client();
            $token = $client->get_access_token_public();
            
            if ($token) {
                // Get Site and List IDs
                $site_id = 'miltonplayers.sharepoint.com,9122b47c-2748-446f-820e-ab3bc46b80d0,5d9211a6-6d28-4644-ad40-82fe3972fbf1';
                $image_media_list_id = '36cd8ce2-6611-401a-ae0c-20dd4abcf36b';
                
                // Sync front image
                if ($image_front_url) {
                    $front_filename = basename($image_front_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $front_filename,
                        $image_front_url,
                        '_tm_season_image_front',
                        $sp_id,
                        'image_front',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
                
                // Sync back image
                if ($image_back_url) {
                    $back_filename = basename($image_back_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $back_filename,
                        $image_back_url,
                        '_tm_season_image_back',
                        $sp_id,
                        'image_back',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
                
                // Sync social banner / website banner
                if ($website_banner_url) {
                    $banner_filename = basename($website_banner_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $banner_filename,
                        $website_banner_url,
                        '_tm_season_social_banner',
                        $sp_id,
                        'social_banner',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
                
                // Sync SM Square image
                if ($sm_square_url) {
                    $square_filename = basename($sm_square_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $square_filename,
                        $sm_square_url,
                        '_tm_season_sm_square',
                        $sp_id,
                        'sm_square',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
                
                // Sync SM Portrait image
                if ($sm_portrait_url) {
                    $portrait_filename = basename($sm_portrait_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $portrait_filename,
                        $sm_portrait_url,
                        '_tm_season_sm_portrait',
                        $sp_id,
                        'sm_portrait',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
            }
        }

        tm_sync_log('debug', 'Finished processing season.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing season item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing season item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_seasons($dry_run = false) {
    tm_sync_log('info', 'Starting seasons sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_seasons_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_season($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process season item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} season(s) would be synced."
        : "Sync complete: {$count} season(s) synced.";

    tm_sync_log('info', 'Seasons sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
