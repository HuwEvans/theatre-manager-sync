<?php
class TM_Graph_Client {
    private $access_token;
    private $site_id;
    private $tenant_id;
    private $client_id;

    public function __construct() {
        // Get auth settings from options
        $opts = get_option('tm_sync_auth', []);
        
        $this->tenant_id = $opts['tenant_id'] ?? null;
        $this->client_id = $opts['client_id'] ?? null;
        $this->site_id = $opts['site_id'] ?? null;
        
        // Don't load cached token here - we'll get a fresh one when needed
        $this->access_token = null;
        
        // Log initialization for debugging
        error_log('[TM_Graph_Client] Constructor: SiteID=' . ($this->site_id ? $this->site_id : 'EMPTY') .
                  ', TenantID=' . ($this->tenant_id ? 'SET' : 'EMPTY') .
                  ', ClientID=' . ($this->client_id ? 'SET' : 'EMPTY'));
    }

    private function request($endpoint) {
        // Ensure we have a token
        $token = $this->get_access_token();
        if (!$token) {
            error_log('[TM_Graph_Client] Cannot make request - no access token available');
            return null;
        }

        $url = "https://graph.microsoft.com/v1.0/$endpoint";
        error_log('[TM_Graph_Client] Making request to: ' . $endpoint);
        error_log('[TM_Graph_Client] Full URL: ' . $url);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[TM_Graph_Client] Request error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            error_log('[TM_Graph_Client] Request failed (HTTP ' . $code . '): ' . $body);
            return null;
        }

        $data = json_decode($body, true);
        error_log('[TM_Graph_Client] Request succeeded, response type: ' . gettype($data));
        error_log('[TM_Graph_Client] Response value count: ' . (isset($data['value']) ? count($data['value']) : 'N/A'));
        return $data;
    }

    private function get_access_token() {
        // Return cached token if available
        if ($this->access_token) {
            return $this->access_token;
        }

        error_log('[TM_Graph_Client] Attempting to obtain new access token');

        // Get credentials
        $opts = get_option('tm_sync_auth', []);
        $tenant = $opts['tenant_id'] ?? null;
        $client_id = $opts['client_id'] ?? null;
        $secret = get_option('tm_sync_client_secret');

        if (!$tenant || !$client_id || !$secret) {
            error_log('[TM_Graph_Client] Missing credentials: tenant=' . ($tenant ? 'SET' : 'EMPTY') .
                      ', client_id=' . ($client_id ? 'SET' : 'EMPTY') . ', secret=' . ($secret ? 'SET' : 'EMPTY'));
            return null;
        }

        $token_url = "https://login.microsoftonline.com/" . rawurlencode($tenant) . "/oauth2/v2.0/token";

        $response = wp_remote_post($token_url, [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $secret,
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('[TM_Graph_Client] Token request error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['access_token'])) {
            error_log('[TM_Graph_Client] Token request failed (HTTP ' . $code . '): ' . 
                      ($body['error_description'] ?? $body['error'] ?? 'Unknown error'));
            return null;
        }

        $this->access_token = $body['access_token'];
        error_log('[TM_Graph_Client] Token obtained successfully, expires in ' . ($body['expires_in'] ?? 'unknown') . ' seconds');

        return $this->access_token;
    }

    public function get_list_items($list_name) {
        error_log('[TM_Graph_Client] get_list_items called for: ' . $list_name);

        if (!$this->site_id) {
            error_log('[TM_Graph_Client] Missing Site ID');
            return null;
        }

        // Resolve list ID from name first
        $list_id = $this->resolve_list_id($list_name);
        if (!$list_id) {
            error_log('[TM_Graph_Client] Failed to resolve list ID for: ' . $list_name);
            return null;
        }

        error_log('[TM_Graph_Client] Resolved list ID: ' . $list_id);

        // Build endpoint with explicit field selection based on list type
        // SharePoint's Graph API requires explicit field names in the select parameter
        $fields = $this->get_fields_for_list($list_name);
        
        // Build field list - do NOT urlencode individual field names
        // SharePoint handles special characters in field names without encoding
        $field_list = implode(',', $fields);
        
        // Build the expand=fields($select=...) parameter
        // DO NOT URL-encode the entire parameter - Graph API expects literal syntax
        // Format: expand=fields($select=field1,field2,field3)
        $endpoint = "sites/{$this->site_id}/lists/{$list_id}/items?expand=fields(\$select=" . $field_list . ")";
        
        error_log('[TM_Graph_Client] Using endpoint with fields: ' . $list_name);
        error_log('[TM_Graph_Client] Requesting fields: ' . implode(', ', $fields));
        error_log('[TM_Graph_Client] Full endpoint: ' . $endpoint);
        
        $data = $this->request($endpoint);
        
        if (!$data) {
            error_log('[TM_Graph_Client] No data returned from endpoint with field selection, trying fallback without field selection');
            
            // Fallback: try without explicit field selection
            $fallback_endpoint = "sites/{$this->site_id}/lists/{$list_id}/items?expand=fields";
            error_log('[TM_Graph_Client] Trying fallback endpoint: ' . $fallback_endpoint);
            $data = $this->request($fallback_endpoint);
            
            if ($data) {
                error_log('[TM_Graph_Client] Fallback succeeded - returned data');
            } else {
                error_log('[TM_Graph_Client] Fallback also failed - no data returned');
                return null;
            }
        }
        
        if (!$data) {
            error_log('[TM_Graph_Client] No data returned from endpoint');
            return null;
        }
        
        error_log('[TM_Graph_Client] Successfully fetched items, count: ' . count($data['value'] ?? []));
        return $data['value'] ?? [];
    }

    public function get_access_token_public() {
        return $this->get_access_token();
    }

    private function resolve_list_id($list_name) {
        error_log('[TM_Graph_Client] Resolving list ID for: ' . $list_name);
        
        $endpoint = "sites/{$this->site_id}/lists";
        $lists = $this->request($endpoint);
        
        if (!$lists || !isset($lists['value'])) {
            error_log('[TM_Graph_Client] Failed to fetch lists from SharePoint');
            return null;
        }
        
        error_log('[TM_Graph_Client] Found ' . count($lists['value']) . ' lists in SharePoint');
        
        // Log all available lists for debugging
        $available_lists = [];
        foreach ($lists['value'] ?? [] as $list) {
            $available_lists[] = $list['name'];
        }
        error_log('[TM_Graph_Client] Available lists: ' . implode(', ', $available_lists));
        
        foreach ($lists['value'] ?? [] as $list) {
            if ($list['name'] === $list_name) {
                error_log('[TM_Graph_Client] Found matching list: ' . $list['id']);
                return $list['id'];
            }
        }
        
        error_log('[TM_Graph_Client] List name not found in SharePoint: ' . $list_name);
        // Log a suggestion of what was found
        error_log('[TM_Graph_Client] Searched for: "' . $list_name . '", but lists are: ' . implode(', ', $available_lists));
        return null;
    }

    /**
     * Download file from SharePoint using the Graph API
     * Uses the /content endpoint which properly handles downloads
     * 
     * @param string $file_url - The SharePoint file URL (server-relative or full)
     * @return string|null - File content as binary string, or null on error
     */
    public function download_sharepoint_file($file_url) {
        if (empty($file_url)) {
            error_log('[TM_Graph_Client] download_sharepoint_file: Empty URL provided');
            return null;
        }

        $token = $this->get_access_token();
        if (!$token) {
            error_log('[TM_Graph_Client] download_sharepoint_file: Failed to get access token');
            return null;
        }

        // Extract server-relative path from full URL if needed
        // Full URL: https://miltonplayers.sharepoint.com/Image%20Media/Advertisers/file.png
        // Server-relative: /Image%20Media/Advertisers/file.png
        $path = $file_url;
        if (strpos($file_url, 'http') === 0) {
            // It's a full URL, extract the path part
            $parts = parse_url($file_url);
            $path = $parts['path'] ?? '';
        }

        // Ensure path starts with /
        if (!empty($path) && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        if (empty($path)) {
            error_log('[TM_Graph_Client] download_sharepoint_file: Could not extract path from URL: ' . $file_url);
            return null;
        }

        // The Graph API endpoint for downloading drive items
        // Format: /sites/{site-id}/drive/root:{path}:/content
        $graph_url = 'https://graph.microsoft.com/v1.0/sites/' . $this->site_id . '/drive/root:' . $path . ':/content';

        error_log('[TM_Graph_Client] Downloading file via Graph API. Path: ' . $path . ' URL: ' . $graph_url);

        $response = wp_remote_get($graph_url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            error_log('[TM_Graph_Client] download_sharepoint_file error: ' . $response->get_error_message());
            return null;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('[TM_Graph_Client] download_sharepoint_file HTTP ' . $http_code . ': ' . substr($body, 0, 300));
            return null;
        }

        $file_content = wp_remote_retrieve_body($response);
        if (empty($file_content)) {
            error_log('[TM_Graph_Client] download_sharepoint_file: Empty response body');
            return null;
        }

        error_log('[TM_Graph_Client] File downloaded successfully, size: ' . strlen($file_content) . ' bytes');
        return $file_content;
    }

    /**
     * Get field names for a specific SharePoint list
     * Returns the exact field names that should be expanded for each CPT
     * 
     * @param string $list_name - The SharePoint list name
     * @return array - Array of field names to expand
     */
    private function get_fields_for_list($list_name) {
        // Map list names to their required fields
        // NOTE: Use INTERNAL field names, not display names!
        // SharePoint fields like "ShowName" might be stored as "field_2" internally
        // URL-encoded names like "Sub-Authors" become "Sub_x002d_Authors"
        $field_map = [
            'Advertisers' => ['Title', 'Company', 'Contact', 'Website', 'Logo', 'Description'],
            'Board Members' => ['Title', 'Name', 'Position', 'Photo', 'Bio', 'Contact', 'Email'],
            'Cast' => ['Title', 'field_2', 'field_3', 'ShowIDLookup', 'ShowIDLookup_x003a_ShowName', 'Headshot'],
            'Contributors' => ['Title', 'Name', 'Company', 'Tier', 'DonationDate', 'DonationAmount', 'Contact', 'Email'],
            'Seasons' => ['Title', 'SeasonName', 'StartDate', 'EndDate', 'IsCurrentSeason', 'IsUpcomingSeason', 'WebsiteBanner', '3-upFront', '3-upBack', 'SMSquare', 'SMPortrait', 'Description'],
            'Shows' => ['Title', 'field_2', 'TimeSlot', 'Author', 'Sub_x002d_Authors', 'field_4', 'field_5', 'SMImage', 'field_6', 'field_7', 'field_8', 'field_9', 'ProgramFileURL', 'SeasonIDLookup', 'SeasonIDLookup_x003a_SeasonName'],
            'Sponsors' => ['Title', 'Company', 'SponsorshipLevel', 'Website', 'Logo', 'Contact', 'Email'],
            'Testimonials' => ['Title', 'Comment', 'RatingNumber', 'Author', 'AuthorTitle', 'AuthorCompany', 'Date', 'Approved'],
        ];
        
        // Return fields for this list, or default to Title if not found
        $fields = $field_map[$list_name] ?? ['Title'];
        error_log('[TM_Graph_Client] Field map for ' . $list_name . ': ' . json_encode($fields));
        return $fields;
    }
}
