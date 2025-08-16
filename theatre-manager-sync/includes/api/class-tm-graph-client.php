<?php
class TM_Graph_Client {
    private $access_token;
    private $site_id;

    public function __construct() {
        $this->access_token = get_option('tm_sync_access_token'); // or however you store it
        $this->site_id = get_option('tm_sync_site_id');
    }

    private function request($endpoint) {
        $response = wp_remote_get("https://graph.microsoft.com/v1.0/$endpoint", [
            'headers' => [
                'Authorization' => "Bearer {$this->access_token}",
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) return null;
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function get_list_items($list_name) {
        if (!$this->site_id || !$this->access_token) return null;

        // You may need to resolve list ID from name first
        $list_id = $this->resolve_list_id($list_name);
        if (!$list_id) return null;

        $endpoint = "sites/{$this->site_id}/lists/{$list_id}/items?expand=fields";
        $data = $this->request($endpoint);
        return $data['value'] ?? [];
    }

    private function resolve_list_id($list_name) {
        $endpoint = "sites/{$this->site_id}/lists";
        $lists = $this->request($endpoint);
        foreach ($lists['value'] ?? [] as $list) {
            if ($list['name'] === $list_name) return $list['id'];
        }
        return null;
    }
}
