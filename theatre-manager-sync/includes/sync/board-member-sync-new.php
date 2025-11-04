<?php

tm_sync_log('INFO','Board-Member-Sync File loading');

function tm_sync_fetch_board_members_data() {
    tm_sync_log('debug', 'Fetching SharePoint board members data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    $client = new TM_Graph_Client();
    $items = $client->get_list_items('Board Members');

    tm_sync_log('debug', 'API Response received', [
        'items_type' => gettype($items),
        'items_count' => is_array($items) ? count($items) : 0
    ]);

    if ($items === null) {
        tm_sync_log('error', 'API client returned null - credentials or list may be invalid');
        return null;
    }

    if (!is_array($items)) {
        tm_sync_log('error', 'Failed to fetch items from SharePoint list - not an array', [
            'items_type' => gettype($items)
        ]);
        return null;
    }

    if (empty($items)) {
        tm_sync_log('warning', 'SharePoint Board Members list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint board members data successfully', [
        'item_count' => count($items)
    ]);
    return $items;
}

function tm_sync_process_board_member($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing board member item', [
            'item_id' => $item['id'] ?? 'unknown'
        ]);

        $fields = $item['fields'] ?? $item;
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? '');
        $position = trim($fields['Position'] ?? '');

        // Handle photo URL (may be string or object)
        $photo_url = '';
        if (isset($fields['Photo'])) {
            if (is_array($fields['Photo']) || is_object($fields['Photo'])) {
                $photo_url = $fields['Photo']['Url'] ?? $fields['Photo']->Url ?? '';
            } else {
                $photo_url = $fields['Photo'];
            }
        }

        tm_sync_log('debug', 'Extracted fields', [
            'sp_id' => $sp_id,
            'name' => $name,
            'position' => $position
        ]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID', [
                'sp_id' => $sp_id,
                'name' => $name
            ]);
            return false;
        }

        tm_sync_log('debug', 'Processing board member', [
            'name' => $name,
            'position' => $position,
            'sp_id' => $sp_id
        ]);

        $existing = get_posts([
            'post_type' => 'board_member',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', [
            'sp_id' => $sp_id,
            'existing_count' => count($existing)
        ]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process board member', [
                'name' => $name,
                'sp_id' => $sp_id
            ]);
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
            tm_sync_log('info', 'Updated board member', [
                'name' => $name,
                'sp_id' => $sp_id,
                'post_id' => $post_id
            ]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert board member post', [
                    'name' => $name,
                    'error' => $post_id->get_error_message()
                ]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created board member', [
                'name' => $name,
                'sp_id' => $sp_id,
                'post_id' => $post_id
            ]);
        }

        // Update metadata
        update_post_meta($post_id, '_tm_name', $name);
        update_post_meta($post_id, '_tm_position', $position);

        // Sync photo if present
        if ($photo_url) {
            $filename = basename($photo_url);
            
            // Get SharePoint site and library IDs
            $site_id = 'miltonplayers.sharepoint.com,9122b47c-2748-446f-820e-ab3bc46b80d0,5d9211a6-6d28-4644-ad40-82fe3972fbf1';
            $image_media_list_id = '36cd8ce2-6611-401a-ae0c-20dd4abcf36b';
            
            // Get access token
            if (class_exists('TM_Graph_Client')) {
                $client = new TM_Graph_Client();
                $token = $client->get_access_token_public();

                if ($token) {
                    tm_sync_image_for_post(
                        $post_id,
                        $filename,
                        $photo_url,
                        '_tm_photo',
                        $sp_id,
                        'photo',
                        $site_id,
                        $image_media_list_id,
                        $token
                    );
                }
            }
        }

        tm_sync_log('debug', 'Finished processing board member', [
            'sp_id' => $sp_id,
            'post_id' => $post_id
        ]);
        return true;

    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing board member: ' . $e->getMessage(), [
            'item_id' => $item['id'] ?? 'unknown',
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing board member: ' . $t->getMessage(), [
            'item_id' => $item['id'] ?? 'unknown',
            'trace' => $t->getTraceAsString()
        ]);
        return false;
    }
}

function tm_sync_board_members($dry_run = false) {
    tm_sync_log('info', 'Starting board members sync', [
        'dry_run' => $dry_run
    ]);

    $items = tm_sync_fetch_board_members_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_board_member($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process board member item', [
                'item' => $item
            ]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} board member(s) would be synced."
        : "Sync complete: {$count} board member(s) synced.";

    tm_sync_log('info', 'Board members sync completed', [
        'dry_run' => $dry_run,
        'synced_count' => $count
    ]);
    return $summary;
}
