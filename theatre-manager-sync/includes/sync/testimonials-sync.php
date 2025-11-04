<?php

tm_sync_log('INFO','Testimonials-Sync File loading');

function tm_sync_fetch_testimonials_data() {
    tm_sync_log('debug', 'Fetching SharePoint testimonials data.');

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('error', 'TM_Graph_Client not found. Cannot fetch SharePoint data.');
        return null;
    }

    tm_sync_log('debug', 'Creating TM_Graph_Client instance');
    $client = new TM_Graph_Client();
    
    tm_sync_log('debug', 'Calling get_list_items for Testimonials');
    $items = $client->get_list_items('Testimonials');

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
        tm_sync_log('warning', 'SharePoint Testimonials list is empty');
        return [];
    }

    tm_sync_log('info', 'Fetched SharePoint testimonials data successfully.', ['item_count' => count($items)]);
    return $items;
}

function tm_sync_process_testimonial($item, $dry_run = false) {
    try {
        tm_sync_log('debug', 'Processing testimonial item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? $fields['Name'] ?? '');
        $comment = trim($fields['Comment'] ?? $fields['Testimonial'] ?? '');
        $rating = intval($fields['Rating'] ?? 0);

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'rating' => $rating]);

        if (!$name || !$sp_id) {
            tm_sync_log('warning', 'Skipped item with missing name or ID.', ['sp_id' => $sp_id, 'name' => $name, 'fields_keys' => array_keys($fields)]);
            return false;
        }

        tm_sync_log('debug', 'Processing testimonial', ['name' => $name, 'rating' => $rating, 'sp_id' => $sp_id]);

        $existing = get_posts([
            'post_type' => 'testimonial',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        tm_sync_log('debug', 'Checked for existing posts', ['sp_id' => $sp_id, 'existing_count' => count($existing)]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would process testimonial.', ['name' => $name, 'sp_id' => $sp_id]);
            return true;
        }

        $post_data = [
            'post_title' => $name,
            'post_type' => 'testimonial',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated testimonial.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                tm_sync_log('error', 'Failed to insert testimonial post.', ['name' => $name, 'error' => $post_id->get_error_message()]);
                return false;
            }
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created testimonial.', ['name' => $name, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        // Update testimonial metadata
        update_post_meta($post_id, '_tm_name', $name);
        update_post_meta($post_id, '_tm_comment', $comment);
        update_post_meta($post_id, '_tm_rating', $rating);

        tm_sync_log('debug', 'Finished processing testimonial.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        return true;
    } catch (Exception $e) {
        tm_sync_log('error', 'Exception processing testimonial item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        tm_sync_log('error', 'Error processing testimonial item: ' . $t->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $t->getTraceAsString()]);
        return false;
    }
}

function tm_sync_testimonials($dry_run = false) {
    tm_sync_log('info', 'Starting testimonials sync.', ['dry_run' => $dry_run]);

    $items = tm_sync_fetch_testimonials_data();
    if (!$items) {
        tm_sync_log('error', 'No data fetched from SharePoint.');
        return 'Sync failed: No data fetched.';
    }

    $count = 0;
    foreach ($items as $item) {
        if (tm_sync_process_testimonial($item, $dry_run)) {
            $count++;
        } else {
            tm_sync_log('warning', 'Failed to process testimonial item.', ['item' => $item]);
        }
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} testimonial(s) would be synced."
        : "Sync complete: {$count} testimonial(s) synced.";

    tm_sync_log('info', 'Testimonials sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);
    return $summary;
}
