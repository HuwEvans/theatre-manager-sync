<?php

tm_sync_log('INFO','Contributors-Sync File loading');

function tm_sync_fetch_contributors_data() {
    tm_sync_log('debug', 'Fetching SharePoint contributors data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Contributors');
    $items = $client->get_list_items('Contributors');

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
        tm_sync_log('warning', 'SharePoint Contributors list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint contributors data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_contributor($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing contributor item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? $fields['Name'] ?? '');
        $company = trim($fields['Company'] ?? '');
        $level = trim($fields['Level'] ?? $fields['ContributionLevel'] ?? '');

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'company' => $company, 'level' => $level]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing contributor', ['name' => $name, 'company' => $company, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'contributor',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process contributor.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'contributor',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated contributor.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert contributor post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created contributor.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update contributor metadata
        update_post_meta($post_id, '_tm_name', $name);
        update_post_meta($post_id, '_tm_company', $company);
        update_post_meta($post_id, '_tm_level', $level);

        tm_sync_log('debug', 'Finished processing contributor.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing contributor item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing contributor item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_contributors($dry_run = false) {
    tm_sync_log('info', 'Starting contributors sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_contributors_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_contributor($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process contributor item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} contributor(s) would be synced."
        : "Sync complete: {$count} contributor(s) synced.";

    tm_sync_log('info', 'Contributors sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
