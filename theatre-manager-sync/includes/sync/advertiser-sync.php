<?php

tm_sync_log('INFO','Advertiser-Sync File loading');

function tm_sync_advertisers($dry_run = false) {
	
   tm_sync_log('INFO',"tm_sync_advertisers called. Dry run: " . ($dry_run ? 'Yes' : 'No'));

    if (!class_exists('TM_Graph_Client')) {
        tm_sync_log('INFO', 'TM_Graph_Client not found.');
        return 'Sync failed: Graph client missing.';
    }


    $client = new TM_Graph_Client();
    $list_name = 'Advertisers';
    $items = $client->get_list_items($list_name);

    if (!$items || !is_array($items)) {
        tm_sync_log('error', 'Failed to fetch items from SharePoint list.', ['list' => $list_name]);
        return 'Sync failed: No items returned.';
    }

    tm_sync_log('info', 'Advertiser sync started.', ['dry_run' => $dry_run, 'item_count' => count($items)]);

    $count = 0;

    foreach ($items as $item) {
        $sp_id = $item['id'] ?? null;
        $title = trim($item['Title'] ?? '');
        $logo_url = $item['Logo'] ?? '';
        $banner_url = $item['Banner'] ?? '';
        $website = isset($item['Website']) ? tm_sync_normalize_url($item['Website']) : '';
        $is_restaurant = $item['IsRestaurant'] ?? '';

        if (!$title || !$sp_id) {
            tm_sync_log('warning', 'Skipped advertiser with missing title or ID.', ['item' => $item]);
            continue;
        }

        $existing = get_posts([
            'post_type' => 'advertiser',
            'meta_key' => '_tm_sp_id',
            'meta_value' => $sp_id,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        if ($dry_run) {
            tm_sync_log('debug', 'Dry-run: Would sync advertiser.', ['title' => $title, 'sp_id' => $sp_id]);
            $count++;
            continue;
        }

        $post_data = [
            'post_title' => $title,
            'post_type' => 'advertiser',
            'post_status' => 'publish'
        ];

        if ($existing) {
            $post_id = $existing[0]->ID;
            wp_update_post(array_merge($post_data, ['ID' => $post_id]));
            tm_sync_log('info', 'Updated advertiser.', ['title' => $title, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        } else {
            $post_id = wp_insert_post($post_data);
            update_post_meta($post_id, '_tm_sp_id', $sp_id);
            tm_sync_log('info', 'Created advertiser.', ['title' => $title, 'sp_id' => $sp_id, 'post_id' => $post_id]);
        }

        update_post_meta($post_id, '_tm_name', $title);
        update_post_meta($post_id, '_tm_website', $website);
        update_post_meta($post_id, '_tm_restaurant', $is_restaurant);

        if ($logo_url) {
            $logo_id = tm_sync_download_image($logo_url, "logo-{$sp_id}");
            if ($logo_id) {
                update_post_meta($post_id, '_tm_logo', $logo_id);
                tm_sync_log('debug', 'Logo image downloaded.', ['url' => $logo_url, 'attachment_id' => $logo_id]);
            }
        }

        if ($banner_url) {
            $banner_id = tm_sync_download_image($banner_url, "banner-{$sp_id}");
            if ($banner_id) {
                update_post_meta($post_id, '_tm_banner', $banner_id);
                tm_sync_log('debug', 'Banner image downloaded.', ['url' => $banner_url, 'attachment_id' => $banner_id]);
            }
        }

        $count++;
    }

    $summary = $dry_run
        ? "Dry-run complete: {$count} advertiser(s) would be synced."
        : "Sync complete: {$count} advertiser(s) synced.";

    tm_sync_log('info', 'Advertiser sync completed.', ['dry_run' => $dry_run, 'synced_count' => $count]);

    return $summary;
}
