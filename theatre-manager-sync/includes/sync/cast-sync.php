<?php

tm_sync_log('INFO','Cast-Sync File loading');

function tm_sync_fetch_cast_data() {
    tm_sync_log('debug', 'Fetching SharePoint cast data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Cast');
    $items = $client->get_list_items('Cast');

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
        tm_sync_log('warning', 'SharePoint Cast list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint cast data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_cast($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing cast item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        // Log all available fields for debugging
        error_log('[CAST_DEBUG] Complete SharePoint item: ' . json_encode($item, JSON_PRETTY_PRINT));
        error_log('[CAST_DEBUG] Fields array: ' . json_encode($fields, JSON_PRETTY_PRINT));
        error_log('[CAST_DEBUG] Field keys: ' . implode(', ', array_keys((array)$fields)));
        
        $sp_id = $item['id'] ?? null;
        // SharePoint field mapping for Cast (using internal field names):
        // - CharacterName (field_2) contains character name (fallback to Title)
        // - ActorName (field_3) contains actor/performer name
        // - Headshot is the actor's picture
        // - ShowIDLookup links to show, ShowIDLookup:ShowName is the show name
        $character_name = trim($fields['field_2'] ?? $fields['Title'] ?? '');
        $actor_name = trim($fields['field_3'] ?? '');
        $show_lookup = trim($fields['ShowIDLookup'] ?? '');
        $show_lookup_name = trim($fields['ShowIDLookup_x003a_ShowName'] ?? '');
        
        // Picture/Headshot can be a string or an object with Url property
        $picture_url = '';
        if (isset($fields['Headshot'])) {
            if (is_array($fields['Headshot']) || is_object($fields['Headshot'])) {
                $picture_url = $fields['Headshot']['Url'] ?? $fields['Headshot']->Url ?? '';
            } else {
                $picture_url = $fields['Headshot'];
            }
        }

        error_log('[CAST_DEBUG] Extracted fields: character=' . $character_name . ', actor=' . $actor_name . ', show=' . $show_lookup_name . ', picture=' . ($picture_url ? 'yes' : 'no'));

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'character' => $character_name, 'actor' => $actor_name, 'show' => $show_lookup_name]);

        if (!$character_name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing character name or ID.', ['sp_id' => $sp_id, 'character' => $character_name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing cast member', ['character' => $character_name, 'actor' => $actor_name, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'cast',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process cast member.', ['character' => $character_name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $character_name,
            'post_type' => 'cast',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated cast member.', ['character' => $character_name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert cast post.', ['character' => $character_name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created cast member.', ['character' => $character_name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update cast metadata
        update_post_meta($post_id, '_tm_cast_character_name', $character_name);
        update_post_meta($post_id, '_tm_cast_actor_name', $actor_name);
        
        // Link to show by name lookup - find the show post with matching name
        if ($show_lookup_name) {
            $show_post = get_posts([
                'post_type' => 'show',
                'title' => $show_lookup_name,
                'numberposts' => 1
            ]);
            if (!empty($show_post)) {
                update_post_meta($post_id, '_tm_cast_show', $show_post[0]->ID);
            }
        }
        
        error_log('[CAST_DEBUG] Saved metadata for cast member: character=' . $character_name . ', post_id=' . $post_id);

        if ($picture_url) {
            // Extract filename from URL
            $filename = basename($picture_url);
            
            // Get access token and SharePoint config
            if (!class_exists('TM_Graph_Client')) {
                tm_sync_log('warning', 'TM_Graph_Client not available for picture sync');
            } else {
                $client = new TM_Graph_Client();
                $token = $client->get_access_token_public();
                
                if ($token) {
                    // Get Site and List IDs
                    $site_id = 'miltonplayers.sharepoint.com,9122b47c-2748-446f-820e-ab3bc46b80d0,5d9211a6-6d28-4644-ad40-82fe3972fbf1';
                    $image_media_list_id = '36cd8ce2-6611-401a-ae0c-20dd4abcf36b';
                    
                    // Use the generic image sync function (handles orphaned attachment detection)
                    tm_sync_image_for_post(
                        $post_id,
                        $filename,
                        $picture_url,
                        '_tm_cast_picture',
                        $sp_id,
                        'picture',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
            }
        }

        tm_sync_log('debug', 'Finished processing cast member.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing cast item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing cast item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_cast($dry_run = false) {
    tm_sync_log('info', 'Starting cast sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_cast_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_cast($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process cast item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} cast member(s) would be synced."
        : "Sync complete: {$count} cast member(s) synced.";

    tm_sync_log('info', 'Cast sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
