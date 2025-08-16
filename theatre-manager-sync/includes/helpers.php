<?php
/**
 * Normalize and sanitize a SharePoint URL.
 * - Enforces https://
 * - Strips query/hash
 * - Trims trailing slash
 */
function tm_sync_normalize_sharepoint_url( $url ) {
    $url = trim( (string) $url );

    if ( $url === '' ) {
        return '';
    }

    // If scheme is missing, assume https.
    if ( preg_match( '#^https?://#i', $url ) !== 1 ) {
        $url = 'https://' . ltrim( $url, '/' );
    }

    // Basic sanitization
    $url = esc_url_raw( $url );

    // Remove trailing slash and query/hash parts
    $parts = wp_parse_url( $url );
    if ( empty( $parts['host'] ) ) {
        return '';
    }

    $normalized  = $parts['scheme'] . '://' . $parts['host'];
    if ( ! empty( $parts['path'] ) ) {
        $normalized .= rtrim( $parts['path'], '/' );
    }

    return $normalized;
}

function tm_sync_run_generic($cpt) {
    // Placeholder: Replace with actual sync logic
    return "Sync for {$cpt} completed successfully.";
}

$cpts = ['contributor', 'advertiser', 'board_member', 'sponsor', 'testimonial', 'season', 'show', 'cast'];

foreach ($cpts as $cpt) {
    add_action('wp_ajax_tm_sync_run_' . $cpt, function() use ($cpt) {
        check_ajax_referer('tm_sync_nonce');
        $message = tm_sync_run_generic($cpt);
        wp_send_json_success(['message' => $message]);
    });
}

add_action('wp_ajax_tm_sync_run_all', function() use ($cpts) {
    check_ajax_referer('tm_sync_nonce');
    $results = [];
    foreach ($cpts as $cpt) {
        $results[] = tm_sync_run_generic($cpt);
    }
    wp_send_json_success(['message' => implode("\n", $results)]);
});

function tm_sync_normalize_url($url) {
    return esc_url_raw(trim($url));
}

function tm_sync_download_image($url, $name) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;

    $file_array = [
        'name' => sanitize_file_name($name . '.jpg'),
        'tmp_name' => $tmp
    ];

    $id = media_handle_sideload($file_array, 0);
    if (is_wp_error($id)) return false;

    return $id;
}

