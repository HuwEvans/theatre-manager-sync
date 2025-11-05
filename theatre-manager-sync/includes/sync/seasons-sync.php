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
        // When the expand=fields($select=...) fails, SharePoint returns generic field_1, field_2, etc.
        // Map them to actual field meanings based on the field order in the Seasons list
        // If named fields exist, use those. Otherwise, use field_X mapping.
        $name = trim($fields['SeasonName'] ?? $fields['field_1'] ?? $fields['Title'] ?? '');
        
        // Extract and format dates - SharePoint returns ISO 8601 format (2025-01-15T00:00:00Z)
        // We need to convert to simple date format (2025-01-15) for WordPress date fields
        $start_date_raw = $fields['StartDate'] ?? $fields['field_2'] ?? '';
        $end_date_raw = $fields['EndDate'] ?? $fields['field_3'] ?? '';
        
        // For boolean fields that come as generic field_4 and field_5
        $is_current_season = $fields['IsCurrentSeason'] ?? $fields['field_4'] ?? '';
        $is_upcoming_season = $fields['IsUpcomingSeason'] ?? $fields['field_5'] ?? '';
        
        // Helper function to extract date in YYYY-MM-DD format from SharePoint date
        $extract_date = function($date_field) {
            if (empty($date_field)) return '';
            // If it's an object or array, try to get the date value
            if (is_object($date_field)) {
                $date_field = isset($date_field->dateTime) ? $date_field->dateTime : (string)$date_field;
            } elseif (is_array($date_field)) {
                $date_field = $date_field['dateTime'] ?? $date_field[0] ?? '';
            }
            // Convert ISO 8601 (2025-01-15T00:00:00Z) to date only (2025-01-15)
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string)$date_field, $matches)) {
                return $matches[1];
            }
            return '';
        };
        
        $start_date = $extract_date($start_date_raw);
        $end_date = $extract_date($end_date_raw);
        
        // DETAILED DEBUG LOGGING
        error_log('[SEASONS_DEBUG] SharePoint field mapping:');
        error_log('[SEASONS_DEBUG] - SeasonName field: ' . (isset($fields['SeasonName']) ? 'EXISTS' : 'NOT FOUND (using field_1)'));
        error_log('[SEASONS_DEBUG] - Title field: ' . (isset($fields['Title']) ? 'EXISTS' : 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - Final name value: "' . $name . '"');
        error_log('[SEASONS_DEBUG] - Start date (raw): "' . (is_string($start_date_raw) ? $start_date_raw : json_encode($start_date_raw)) . '"');
        error_log('[SEASONS_DEBUG] - Start date (formatted): "' . $start_date . '"');
        error_log('[SEASONS_DEBUG] - End date (raw): "' . (is_string($end_date_raw) ? $end_date_raw : json_encode($end_date_raw)) . '"');
        error_log('[SEASONS_DEBUG] - End date (formatted): "' . $end_date . '"');
        error_log('[SEASONS_DEBUG] - Is current: "' . $is_current_season . '" (from ' . (isset($fields['IsCurrentSeason']) ? 'IsCurrentSeason' : 'field_4') . ')');
        error_log('[SEASONS_DEBUG] - Is upcoming: "' . $is_upcoming_season . '" (from ' . (isset($fields['IsUpcomingSeason']) ? 'IsUpcomingSeason' : 'field_5') . ')');
        
        // Helper function to extract URL from hyperlink/image field
        $extract_url = function($field) {
            if (empty($field)) return '';
            if (is_string($field)) return $field;
            if (is_array($field)) return $field['Url'] ?? '';
            if (is_object($field)) return $field->Url ?? '';
            return '';
        };
        
        // Try both named fields and generic field_X names
        // NOTE: SharePoint sometimes returns field names URL-encoded
        // _x0033__x002d_upFront is "3-upFront", _x0033__x002d_upBack is "3-upBack"
        $website_banner_url = $extract_url($fields['WebsiteBanner'] ?? $fields['field_6'] ?? '');
        $image_front_url = $extract_url($fields['3-upFront'] ?? $fields['_x0033__x002d_upFront'] ?? $fields['field_7'] ?? '');
        $image_back_url = $extract_url($fields['3-upBack'] ?? $fields['_x0033__x002d_upBack'] ?? $fields['field_8'] ?? '');
        $sm_square_url = $extract_url($fields['SMSquare'] ?? $fields['field_9'] ?? '');
        $sm_portrait_url = $extract_url($fields['SMPortrait'] ?? $fields['field_10'] ?? '');

        error_log('[SEASONS_DEBUG] Extracted images:');
        error_log('[SEASONS_DEBUG] - Website banner: ' . ($website_banner_url ? 'YES (' . substr($website_banner_url, 0, 60) . ')' : 'NO'));
        error_log('[SEASONS_DEBUG] - Front image: ' . ($image_front_url ? 'YES (' . substr($image_front_url, 0, 60) . ')' : 'NO'));
        error_log('[SEASONS_DEBUG] - Back image: ' . ($image_back_url ? 'YES (' . substr($image_back_url, 0, 60) . ')' : 'NO'));
        error_log('[SEASONS_DEBUG] - SM square: ' . ($sm_square_url ? 'YES (' . substr($sm_square_url, 0, 60) . ')' : 'NO'));
        error_log('[SEASONS_DEBUG] - SM portrait: ' . ($sm_portrait_url ? 'YES (' . substr($sm_portrait_url, 0, 60) . ')' : 'NO'));
        
        // Log the actual SharePoint field values for debugging
        error_log('[SEASONS_DEBUG] Raw SharePoint field values:');
        error_log('[SEASONS_DEBUG] - WebsiteBanner: ' . json_encode($fields['WebsiteBanner'] ?? 'NOT FOUND') . ' / field_6: ' . json_encode($fields['field_6'] ?? 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - 3-upFront (alt: _x0033__x002d_upFront): ' . json_encode($fields['3-upFront'] ?? $fields['_x0033__x002d_upFront'] ?? 'NOT FOUND') . ' / field_7: ' . json_encode($fields['field_7'] ?? 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - 3-upBack (alt: _x0033__x002d_upBack): ' . json_encode($fields['3-upBack'] ?? $fields['_x0033__x002d_upBack'] ?? 'NOT FOUND') . ' / field_8: ' . json_encode($fields['field_8'] ?? 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - SMSquare: ' . json_encode($fields['SMSquare'] ?? 'NOT FOUND') . ' / field_9: ' . json_encode($fields['field_9'] ?? 'NOT FOUND'));
        error_log('[SEASONS_DEBUG] - SMPortrait: ' . json_encode($fields['SMPortrait'] ?? 'NOT FOUND') . ' / field_10: ' . json_encode($fields['field_10'] ?? 'NOT FOUND'));

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
        
        // For images: Since SharePoint returns URLs not filenames, we need a different approach
        // We'll directly download and attach the images using the URL
        // The images are already coming as full URLs to downloadable content
        
        tm_sync_log('debug', 'Processing season images', [
            'post_id' => $post_id,
            'website_banner_url_set' => !empty($website_banner_url),
            'front_url_set' => !empty($image_front_url),
            'back_url_set' => !empty($image_back_url),
            'square_url_set' => !empty($sm_square_url),
            'portrait_url_set' => !empty($sm_portrait_url),
        ]);
        
        // Get access token for image syncing
        if (!class_exists('TM_Graph_Client')) {
            tm_sync_log('warning', 'TM_Graph_Client not available for image sync');
        } else {
            $client = new TM_Graph_Client();
            $token = $client->get_access_token_public();
            
            if ($token) {
                // For Season images, we're getting full URLs from SharePoint fields
                // These are redirect URLs that we need to download directly
                
                // Sync front image (3-upFront)
                if ($image_front_url) {
                    $attachment_id = tm_sync_download_and_attach_image(
                        $post_id,
                        $image_front_url,
                        '_tm_season_image_front',
                        'season-3up-front-' . $sp_id,
                        'image_front'
                    );
                    if ($attachment_id) {
                        error_log('[SEASONS_DEBUG] Successfully attached 3-up front image: attachment_id=' . $attachment_id);
                    } else {
                        // Only store URL as fallback if download failed
                        update_post_meta($post_id, '_tm_season_image_front_url', $image_front_url);
                        error_log('[SEASONS_DEBUG] Stored fallback URL for front image');
                    }
                }
                
                // Sync back image (3-upBack)
                if ($image_back_url) {
                    $attachment_id = tm_sync_download_and_attach_image(
                        $post_id,
                        $image_back_url,
                        '_tm_season_image_back',
                        'season-3up-back-' . $sp_id,
                        'image_back'
                    );
                    if ($attachment_id) {
                        error_log('[SEASONS_DEBUG] Successfully attached 3-up back image: attachment_id=' . $attachment_id);
                    } else {
                        // Only store URL as fallback if download failed
                        update_post_meta($post_id, '_tm_season_image_back_url', $image_back_url);
                        error_log('[SEASONS_DEBUG] Stored fallback URL for back image');
                    }
                }
                
                // Sync social banner / website banner
                if ($website_banner_url) {
                    $attachment_id = tm_sync_download_and_attach_image(
                        $post_id,
                        $website_banner_url,
                        '_tm_season_social_banner',
                        'season-website-banner-' . $sp_id,
                        'social_banner'
                    );
                    if ($attachment_id) {
                        error_log('[SEASONS_DEBUG] Successfully attached website banner image: attachment_id=' . $attachment_id);
                    } else {
                        // Only store URL as fallback if download failed
                        update_post_meta($post_id, '_tm_season_social_banner_url', $website_banner_url);
                        error_log('[SEASONS_DEBUG] Stored fallback URL for banner image');
                    }
                }
                
                // Sync SM Square image
                if ($sm_square_url) {
                    $attachment_id = tm_sync_download_and_attach_image(
                        $post_id,
                        $sm_square_url,
                        '_tm_season_sm_square',
                        'season-sm-square-' . $sp_id,
                        'sm_square'
                    );
                    if ($attachment_id) {
                        error_log('[SEASONS_DEBUG] Successfully attached SM square image: attachment_id=' . $attachment_id);
                    } else {
                        // Only store URL as fallback if download failed
                        update_post_meta($post_id, '_tm_season_sm_square_url', $sm_square_url);
                        error_log('[SEASONS_DEBUG] Stored fallback URL for SM square image');
                    }
                }
                
                // Sync SM Portrait image
                if ($sm_portrait_url) {
                    $attachment_id = tm_sync_download_and_attach_image(
                        $post_id,
                        $sm_portrait_url,
                        '_tm_season_sm_portrait',
                        'season-sm-portrait-' . $sp_id,
                        'sm_portrait'
                    );
                    if ($attachment_id) {
                        error_log('[SEASONS_DEBUG] Successfully attached SM portrait image: attachment_id=' . $attachment_id);
                    } else {
                        // Only store URL as fallback if download failed
                        update_post_meta($post_id, '_tm_season_sm_portrait_url', $sm_portrait_url);
                        error_log('[SEASONS_DEBUG] Stored fallback URL for SM portrait image');
                    }
                }
            }
        }
        
        // No longer store unconditional fallback URLs - they're only stored on failure above

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
