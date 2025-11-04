<?php

tm_sync_log('INFO','Sponsors-Sync File loading');

function tm_sync_fetch_sponsors_data() {
    tm_sync_log('debug', 'Fetching SharePoint sponsors data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Sponsors');
    $items = $client->get_list_items('Sponsors');

    tm_sync_log('debug', 'API Response received', ['items_type' => gettype($items), 'items_count' => is_array($items) ? count($items) : 0]);

    if ($items === null) {
        tm_sync_log('error', 'API client returned null - credentials or list may be invalid. Sponsors list may not exist in SharePoint.');
        return null;
    }

    if (!is_array($items)) {
        tm_sync_log('error', 'Failed to fetch items from SharePoint list - not an array.', ['items_type' => gettype($items)]);
        return null;
    }

    if (empty($items)) {
        tm_sync_log('warning', 'SharePoint Sponsors list is empty or does not exist');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint sponsors data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_sponsor($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing sponsor item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? '');
        $company = trim($fields['Company'] ?? '');
        $sponsor_level = trim($fields['SponsorshipLevel'] ?? $fields['SponsorLevel'] ?? $fields['Level'] ?? '');
        
        // Website can be a string or an object with Url property
        $website = '';
        if (isset($fields['Website'])) {
            if (is_array($fields['Website']) || is_object($fields['Website'])) {
                $website = $fields['Website']['Url'] ?? $fields['Website']->Url ?? '';
            } else {
                $website = $fields['Website'];
            }
        }
        
        // Logo can be a string or an object with Url property
        $logo_url = '';
        if (isset($fields['Logo'])) {
            if (is_array($fields['Logo']) || is_object($fields['Logo'])) {
                $logo_url = $fields['Logo']['Url'] ?? $fields['Logo']->Url ?? '';
            } else {
                $logo_url = $fields['Logo'];
            }
        }
        
        // Banner can be a string or an object with Url property (may not exist in all lists)
        $banner_url = '';
        if (isset($fields['Banner'])) {
            if (is_array($fields['Banner']) || is_object($fields['Banner'])) {
                $banner_url = $fields['Banner']['Url'] ?? $fields['Banner']->Url ?? '';
            } else {
                $banner_url = $fields['Banner'];
            }
        }

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'level' => $sponsor_level, 'all_fields' => $fields]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields), 'full_item' => $item]);
            return false;
        }

        tm_sync_log('debug', 'Processing sponsor', ['name' => $name, 'level' => $sponsor_level, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'sponsor',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process sponsor.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'sponsor',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated sponsor.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert sponsor post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created sponsor.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update sponsor metadata
        update_post_meta($post_id, '_tm_name', $name);
        update_post_meta($post_id, '_tm_sponsor_level', $sponsor_level);
        if (!empty($website)) {
            update_post_meta($post_id, '_tm_website', $website);
        }

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
                
                // Sync logo
                if ($logo_url) {
                    $logo_filename = basename($logo_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $logo_filename,
                        $logo_url,
                        '_tm_logo',
                        $sp_id,
                        'logo',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
                
                // Sync banner
                if ($banner_url) {
                    $banner_filename = basename($banner_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $banner_filename,
                        $banner_url,
                        '_tm_banner',
                        $sp_id,
                        'banner',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
            }
        }

        tm_sync_log('debug', 'Finished processing sponsor.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing sponsor item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing sponsor item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_sponsors($dry_run = false) {
    tm_sync_log('info', 'Starting sponsors sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_sponsors_data();
    if ($items === null) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched from SharePoint (list may not exist).';
    }
    
    if (empty($items)) {
        tm_sync_log('warning', 'No items in Sponsors list.');
        return 'Sync complete: 0 sponsors (list is empty).';
    }

    // Log the first item's complete structure for debugging
    if (!empty($items[0])) {
        tm_sync_log('info', 'First sponsor item structure for debugging:', ['item' => $items[0]]);
    }

    $count = 0;
    $skipped = 0;
    foreach ($items as $item) {
        if (tm_sync_process_sponsor($item, $dry_run)) {
            $count++;
        } else {
            $skipped++;
            tm_sync_log('debug', 'Skipped sponsor item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} sponsor(s) would be synced, {$skipped} skipped."
        : "Sync complete: {$count} sponsor(s) synced, {$skipped} skipped.";

    tm_sync_log('info', 'Sponsors sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count, 'skipped_count' => $skipped]);
    return $summary;
}
