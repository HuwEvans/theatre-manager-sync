<?php

tm_sync_log('INFO','Shows-Sync File loading');

function tm_sync_fetch_shows_data() {
    tm_sync_log('debug', 'Fetching SharePoint shows data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Shows');
    $items = $client->get_list_items('Shows');

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
        tm_sync_log('warning', 'SharePoint Shows list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint shows data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_show($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing show item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? $fields['ShowName'] ?? '');
        $author = trim($fields['Author'] ?? '');
        $synopsis = trim($fields['Synopsis'] ?? '');
        $director = trim($fields['Director'] ?? '');
        $genre = trim($fields['Genre'] ?? '');
        
        // Helper function to extract URL from hyperlink/image field
        $extract_url = function($field) {
            if (empty($field)) return '';
            if (is_string($field)) return $field;
            if (is_array($field)) return $field['Url'] ?? '';
            if (is_object($field)) return $field->Url ?? '';
            return '';
        };
        
        $sm_image_url = $extract_url($fields['SMImage'] ?? $fields['Image'] ?? $fields['SmallMediumImage'] ?? '');

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'author' => $author]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing show', ['name' => $name, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'show',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process show.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'show',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated show.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert show post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created show.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update show metadata
        update_post_meta($post_id, '_tm_show_name', $name);
        update_post_meta($post_id, '_tm_show_author', $author);
        update_post_meta($post_id, '_tm_show_synopsis', $synopsis);
        update_post_meta($post_id, '_tm_show_director', $director);
        update_post_meta($post_id, '_tm_show_genre', $genre);

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
                
                // Sync small/medium image
                if ($sm_image_url) {
                    $sm_filename = basename($sm_image_url);
                    tm_sync_image_for_post(
                        $post_id,
                        $sm_filename,
                        $sm_image_url,
                        '_tm_show_sm_image',
                        $sp_id,
                        'sm_image',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
            }
        }

        tm_sync_log('debug', 'Finished processing show.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing show item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing show item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_shows($dry_run = false) {
    tm_sync_log('info', 'Starting shows sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_shows_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_show($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process show item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} show(s) would be synced."
        : "Sync complete: {$count} show(s) synced.";

    tm_sync_log('info', 'Shows sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
