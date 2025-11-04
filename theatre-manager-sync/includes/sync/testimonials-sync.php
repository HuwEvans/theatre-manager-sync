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
        
        // Log the complete fields structure for debugging
        tm_sync_log('debug', 'Complete fields object', ['fields' => json_encode($fields, JSON_PRETTY_PRINT), 'field_keys' => array_keys((array)$fields)]);
        
        // IMPORTANT: Log this to stdout/error_log so it appears in WordPress debug logs
        error_log('[TESTIMONIALS_DEBUG] Complete SharePoint item: ' . json_encode($item, JSON_PRETTY_PRINT));
        error_log('[TESTIMONIALS_DEBUG] Fields array: ' . json_encode($fields, JSON_PRETTY_PRINT));
        error_log('[TESTIMONIALS_DEBUG] Field keys: ' . implode(', ', array_keys((array)$fields)));
        
        $sp_id = $item['id'] ?? null;
        $name = trim($fields['Title'] ?? $fields['Name'] ?? '');
        $comment = trim($fields['Comment'] ?? $fields['Testimonial'] ?? '');
        
        // Log all available field names to help debugging
        $available_field_names = array_keys((array)$fields);
        tm_sync_log('info', 'Available SharePoint field names', ['fields' => $available_field_names]);
        
        // Extract rating from RaitingNumber field (type: number) - note correct SharePoint casing
        // SharePoint can return numbers in various formats, try various field name combinations
        $rating_value = $fields['RaitingNumber'] ?? 
                       $fields['Ratingnumber'] ?? 
                       $fields['Rating Number'] ?? 
                       $fields['Rating'] ?? 
                       $fields['Rate'] ?? 
                       $fields['Stars'] ?? 
                       null;
        
        // Check if RaitingNumber might be present but null
        $ratingnumber_exists = array_key_exists('RaitingNumber', (array)$fields);
        $ratingnumber_value = $ratingnumber_exists ? $fields['RaitingNumber'] : 'KEY_NOT_FOUND';
        
        tm_sync_log('debug', 'Rating extraction details', [
            'RaitingNumber_key_exists' => $ratingnumber_exists ? 'YES' : 'NO',
            'RaitingNumber_value' => json_encode($ratingnumber_value),
            'RaitingNumber_is_null' => isset($fields['RaitingNumber']) && $fields['RaitingNumber'] === null ? 'YES' : 'NO',
            'raw_rating_value' => json_encode($rating_value),
            'raw_rating_type' => gettype($rating_value),
            'raw_rating_is_null' => $rating_value === null ? 'YES' : 'NO'
        ]);
        
        $rating = 0;
        
        // Handle different types of rating values from SharePoint
        if ($rating_value === null || $rating_value === '') {
            $rating = 0;
            tm_sync_log('debug', 'Rating value is null or empty', ['rating' => $rating]);
        } elseif (is_array($rating_value) || is_object($rating_value)) {
            // If it's an object/array, try to get the numeric value property
            $rating = intval($rating_value['value'] ?? $rating_value->value ?? 0);
            tm_sync_log('debug', 'Extracted rating from object/array', ['rating' => $rating, 'value_property' => json_encode($rating_value['value'] ?? $rating_value->value ?? null)]);
        } elseif (is_numeric($rating_value)) {
            // Direct numeric value (string or int)
            $rating = intval($rating_value);
            tm_sync_log('debug', 'Extracted rating as numeric value', ['original' => $rating_value, 'original_type' => gettype($rating_value), 'converted' => $rating]);
        } else {
            // Try to extract number from string
            if (preg_match('/\d+/', $rating_value, $matches)) {
                $rating = intval($matches[0]);
                tm_sync_log('debug', 'Extracted rating from string using regex', ['original' => $rating_value, 'extracted' => $rating]);
            } else {
                tm_sync_log('warning', 'Could not extract numeric rating from value', ['value' => $rating_value, 'type' => gettype($rating_value)]);
                $rating = 0;
            }
        }
        
        // Ensure rating is within 1-5 range for star display
        // If rating is 0, log it as a problem but don't clamp it away
        $original_rating = $rating;
        if ($rating > 0) {
            $rating = max(1, min(5, $rating));
        }
        if ($original_rating !== $rating) {
            tm_sync_log('warning', 'Rating was adjusted', ['original' => $original_rating, 'adjusted' => $rating]);
        }
        tm_sync_log('debug', 'Final rating value', ['rating' => $rating]);

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'rating' => $rating, 'raw_rating' => json_encode($rating_value), 'available_fields' => array_keys($fields)]);

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
        
        // Verify the rating was saved
        $saved_rating = get_post_meta($post_id, '_tm_rating', true);
        tm_sync_log('debug', 'Saved testimonial metadata', ['post_id' => $post_id, 'name' => $name, 'rating' => $rating, 'saved_rating' => $saved_rating]);

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
