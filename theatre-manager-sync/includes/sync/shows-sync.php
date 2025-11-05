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
        error_log('[SHOWS_DEBUG] === START PROCESSING ===');
        tm_sync_log('debug', 'Processing show item.', ['item_id' => $item['id'] ?? 'unknown']);

        // Extract the fields object from SharePoint response
        $fields = $item['fields'] ?? $item;
        
        
        $sp_id = $item['id'] ?? null;
        // SharePoint field mapping for Shows:
        // - ShowName contains show name (fallback to Title)
        // - TimeSlot for show slot (Fall/Winter/Spring)
        // - Author, Sub-Authors for people
        // - Director, AssociateDirector for directors
        // - StartDate, EndDate, ShowDatesText for dates
        // - Description (synopsis), ProgramFileURL
        // - SeasonIDLookup links to season
        // Use INTERNAL field names (e.g., field_2, field_3, etc)
        $name = trim($fields['field_2'] ?? $fields['Title'] ?? '');
        $time_slot = trim($fields['TimeSlot'] ?? '');
        $author = trim($fields['Author'] ?? '');
        $sub_authors = trim($fields['Sub_x002d_Authors'] ?? '');
        $director = trim($fields['field_4'] ?? '');
        $associate_director = trim($fields['field_5'] ?? '');
        $start_date = trim($fields['field_6'] ?? '');
        $end_date = trim($fields['field_7'] ?? '');
        $show_dates_text = trim($fields['field_8'] ?? '');
        $description = trim($fields['field_9'] ?? '');
        $season_lookup = trim($fields['SeasonIDLookup'] ?? '');
        $season_lookup_name = trim($fields['SeasonIDLookup_x003a_SeasonName'] ?? '');
        
        // Helper function to extract URL from hyperlink/image field
        $extract_url = function($field) {
            if (empty($field)) return '';
            if (is_string($field)) return $field;
            if (is_array($field)) return $field['Url'] ?? '';
            if (is_object($field)) return $field->Url ?? '';
            return '';
        };
        
        // Extract SM Image URL
        $sm_image_url = $extract_url($fields['SMImage'] ?? '');
        
        // Extract Program File URL (handle as hyperlink field which is returned as object/array)
        $program_file_url = $extract_url($fields['ProgramFileURL'] ?? '');
        
        error_log('[SHOWS_DEBUG] Extracted fields: name=' . $name . ', timeslot=' . $time_slot . ', author=' . $author . ', sub_authors=' . $sub_authors . ', director=' . $director . ', season=' . $season_lookup_name . ', sm_image=' . substr($sm_image_url, 0, 60) . ', pdf_url=' . substr($program_file_url, 0, 60));
        error_log('[SHOWS_DEBUG] Raw ProgramFileURL field: ' . json_encode($fields['ProgramFileURL'] ?? null));

        tm_sync_log('debug', 'Extracted fields', ['sp_id' => $sp_id, 'name' => $name, 'author' => $author, 'director' => $director, 'season' => $season_lookup_name]);

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
        update_post_meta($post_id, '_tm_show_time_slot', $time_slot);
        update_post_meta($post_id, '_tm_show_author', $author);
        update_post_meta($post_id, '_tm_show_sub_authors', $sub_authors);
        update_post_meta($post_id, '_tm_show_director', $director);
        update_post_meta($post_id, '_tm_show_associate_director', $associate_director);
        update_post_meta($post_id, '_tm_show_synopsis', $description);  // Description from SharePoint maps to synopsis
        update_post_meta($post_id, '_tm_show_show_dates', $show_dates_text);
        update_post_meta($post_id, '_tm_show_start_date', $start_date);
        update_post_meta($post_id, '_tm_show_end_date', $end_date);
        
        // Handle Program PDF URL - download and store locally
        if ($program_file_url) {
            error_log('[SHOWS_DEBUG] PDF field found: ' . substr($program_file_url, 0, 100));
            // Try to download the PDF to local storage
            $pdf_filename = sanitize_file_name($name . '-program-' . $sp_id . '.pdf');
            
            error_log('[SHOWS_DEBUG] Calling tm_sync_download_pdf with filename: ' . $pdf_filename);
            $pdf_path = tm_sync_download_pdf($program_file_url, $pdf_filename);
            error_log('[SHOWS_DEBUG] tm_sync_download_pdf returned: ' . ($pdf_path ? 'success - ' . $pdf_path : 'false'));
            
            if ($pdf_path) {
                // Create WordPress attachment for the PDF
                $pdf_url = tm_sync_get_pdf_url($pdf_filename);
                
                $attachment = [
                    'post_mime_type' => 'application/pdf',
                    'post_title'     => preg_replace('%\.[^.]+$%', '', basename($pdf_filename)),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];
                
                $attachment_id = wp_insert_attachment($attachment, $pdf_path, $post_id);
                
                if (!is_wp_error($attachment_id)) {
                    // Generate attachment metadata
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $pdf_path);
                    wp_update_attachment_metadata($attachment_id, $attach_data);
                    
                    // Generate PDF thumbnail for admin preview
                    tm_sync_generate_pdf_thumbnail($pdf_path, $attachment_id);
                    
                    // Store attachment ID and URL (use standard _tm_show_program meta for attachment ID)
                    update_post_meta($post_id, '_tm_show_program', $attachment_id);
                    update_post_meta($post_id, '_tm_show_program_url', $pdf_url);
                    error_log('[SHOWS_DEBUG] Created PDF attachment: ID=' . $attachment_id . ', URL=' . $pdf_url);
                } else {
                    // If attachment creation fails, just store the URL
                    update_post_meta($post_id, '_tm_show_program_url', $pdf_url);
                    error_log('[SHOWS_DEBUG] Failed to create attachment, stored URL: ' . $pdf_url);
                }
            } else {
                // Store SharePoint URL as fallback if local download fails
                // Users can still access PDF from SharePoint
                update_post_meta($post_id, '_tm_show_program_url', $program_file_url);
                error_log('[SHOWS_DEBUG] Stored fallback SharePoint URL: ' . substr($program_file_url, 0, 100));
            }
        } else {
            error_log('[SHOWS_DEBUG] No PDF field found in extracted fields');
            delete_post_meta($post_id, '_tm_show_program_url');
            delete_post_meta($post_id, '_tm_show_program_attachment_id');
        }
        
        // Sync SM Image if present
        if ($sm_image_url) {
            $attachment_id = tm_sync_download_and_attach_image(
                $post_id,
                $sm_image_url,
                '_tm_show_sm_image',
                'show-sm-image-' . $sp_id,
                'sm_image'
            );
            if ($attachment_id) {
                error_log('[SHOWS_DEBUG] Successfully attached SM image: attachment_id=' . $attachment_id);
            } else {
                // Store URL as fallback if download failed
                update_post_meta($post_id, '_tm_show_sm_image_url', $sm_image_url);
                error_log('[SHOWS_DEBUG] Stored fallback URL for SM image');
            }
        }
        
        // Link to season by name lookup - find the season post with matching name
        if ($season_lookup_name) {
            $season_post = get_posts([
                'post_type' => 'season',
                'title' => $season_lookup_name,
                'numberposts' => 1
            ]);
            if (!empty($season_post)) {
                update_post_meta($post_id, '_tm_show_season', $season_post[0]->ID);
            }
        }
        
        error_log('[SHOWS_DEBUG] Saved metadata for show: name=' . $name . ', post_id=' . $post_id);

        // Get access token for future image syncing if needed
        if (!class_exists('TM_Graph_Client')) {
            tm_sync_log('warning', 'TM_Graph_Client not available');
        } else {
            // Note: Show image fields not yet available in SharePoint
            // If/when SMImage field is added to Shows list, image syncing can be added here
        }

        tm_sync_log('debug', 'Finished processing show.', ['sp_id' => $sp_id, 'post_id' => $post_id]);
        error_log('[SHOWS_DEBUG] === FINISHED PROCESSING === Result: SUCCESS');
        return true;
    } catch (Exception $e) {
        error_log('[SHOWS_DEBUG] === EXCEPTION: ' . $e->getMessage() . ' ===');
        error_log('[SHOWS_DEBUG] Exception trace: ' . $e->getTraceAsString());
        tm_sync_log('error', 'Exception processing show item: ' . $e->getMessage(), ['item_id' => $item['id'] ?? 'unknown', 'trace' => $e->getTraceAsString()]);
        return false;
    } catch (Throwable $t) {
        error_log('[SHOWS_DEBUG] === THROWABLE: ' . $t->getMessage() . ' ===');
        error_log('[SHOWS_DEBUG] Throwable trace: ' . $t->getTraceAsString());
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
