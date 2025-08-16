<?php

function tm_sync_page_details() {
    tm_sync_cap_check();

    echo '<div class="wrap"><h1>TM Sync · Details</h1>';
    echo '<p>Environment details, mappings summary, versions, and health checks.</p>';

    $opts      = get_option(TM_SYNC_AUTH_OPTION, []);
    $tenant    = isset($opts['tenant_id']) ? $opts['tenant_id'] : '';
    $client_id = isset($opts['client_id']) ? $opts['client_id'] : '';
    $site_id   = isset($opts['site_id'])   ? $opts['site_id']   : '';
    $secret    = get_option(TM_SYNC_CLIENT_SECRET_OPT, '');

    $expected_lists = [
        'Contributors',
        'Advertisers',
        'Board Members',
        'Sponsors',
        'Testimonials',
        'Seasons',
        'Shows',
        'Cast',
    ];

    if ( ! $tenant || ! $client_id || ! $secret || ! $site_id ) {
        echo '<div class="notice notice-error"><p>Missing authentication details. Please configure Tenant, Client ID, Secret, and Site ID in the Auth tab.</p></div>';
        return;
    }

    // Get access token
    $token_url = "https://login.microsoftonline.com/" . rawurlencode($tenant) . "/oauth2/v2.0/token";
    $token_args = [
        'body' => [
            'client_id'     => $client_id,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'https://graph.microsoft.com/.default',
        ],
    ];
    $token_resp = wp_remote_post($token_url, $token_args);
    $token_data = json_decode(wp_remote_retrieve_body($token_resp), true);
    $access_token = $token_data['access_token'] ?? '';

    if ( ! $access_token ) {
        echo '<div class="notice notice-error"><p>Failed to retrieve access token.</p></div>';
        tm_log_error('Details page: failed to retrieve access token', ['response' => $token_data], ['channel' => 'auth']);
        return;
    }

    // Fetch SharePoint lists
    $lists_url = "https://graph.microsoft.com/v1.0/sites/" . rawurlencode($site_id) . "/lists";
    $lists_resp = wp_remote_get($lists_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Accept'        => 'application/json',
        ],
    ]);
    $lists_data = json_decode(wp_remote_retrieve_body($lists_resp), true);
    $available_lists = [];

    if ( isset($lists_data['value']) && is_array($lists_data['value']) ) {
        foreach ( $lists_data['value'] as $list ) {
            $available_lists[] = $list['name'];
        }
    } else {
        echo '<div class="notice notice-error"><p>Failed to retrieve SharePoint lists.</p></div>';
        tm_log_error('Details page: failed to retrieve SharePoint lists', ['response' => $lists_data], ['channel' => 'auth']);
        return;
    }

    // Compare and display
    echo '<h2>Expected SharePoint Lists</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>List Name</th><th>Status</th></tr></thead><tbody>';

    foreach ( $expected_lists as $list_name ) {
        $exists = in_array($list_name, $available_lists, true);
        $status = $exists ? '✅ Exists' : '❌ Missing';
        echo '<tr><td>' . esc_html($list_name) . '</td><td>' . esc_html($status) . '</td></tr>';
    }

    echo '</tbody></table></div>';

    tm_log_info('Details page: checked SharePoint list existence', [
        'expected'  => $expected_lists,
        'available' => $available_lists,
    ], ['channel' => 'admin-ui']);
}


?>