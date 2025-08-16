<?php
defined('ABSPATH') || exit;

if ( ! defined('TM_SYNC_CAP') ) {
    define('TM_SYNC_CAP', 'manage_options');
}

// Option names (one for non-sensitive settings, one for secret).
const TM_SYNC_AUTH_OPTION        = 'tm_sync_auth';             // array: tenant_id, client_id, site_id
const TM_SYNC_CLIENT_SECRET_OPT  = 'tm_sync_client_secret';    // string: client secret (autoload = no)

/**
 * Ensure the secret option exists with autoload disabled.
 * Call early (e.g., on admin_init) to guarantee storage characteristics.
 */
add_action('admin_init', function () {
    if ( get_option(TM_SYNC_CLIENT_SECRET_OPT, null) === null ) {
        add_option(TM_SYNC_CLIENT_SECRET_OPT, '', '', 'no'); // autoload 'no'
    }
});

/**
 * AUTH PAGE RENDER
 */
function tm_sync_page_auth() {
    tm_sync_cap_check();

    $opts          = get_option(TM_SYNC_AUTH_OPTION, []);
    $tenant_id     = isset($opts['tenant_id'])     ? (string) $opts['tenant_id']     : '';
    $client_id     = isset($opts['client_id'])     ? (string) $opts['client_id']     : '';
    $site_id       = isset($opts['site_id'])       ? (string) $opts['site_id']       : '';
    $sharepoint_url= isset($opts['sharepoint_url'])? (string) $opts['sharepoint_url']: ''; // ← NEW
    $has_secret    = (string) get_option(TM_SYNC_CLIENT_SECRET_OPT, '') !== '';

    if ( isset($_GET['updated']) && $_GET['updated'] === '1' ) {
        echo '<div class="notice notice-success is-dismissible"><p>'
           . esc_html__('Auth settings saved.', 'theatre-manager-sync')
           . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'TM Sync · Auth', 'theatre-manager-sync' ); ?></h1>
        <p><?php echo esc_html__( 'Configure Microsoft Graph app-only credentials. These are used to obtain an access token for SharePoint (Sites.Selected) synchronization.', 'theatre-manager-sync' ); ?></p>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="tm_sync_save_auth">
            <?php wp_nonce_field( 'tm_sync_save_auth', 'tm_sync_auth_nonce' ); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tm-sync-tenant"><?php echo esc_html__( 'Microsoft Tenant', 'theatre-manager-sync' ); ?></label></th>
                        <td>
                            <input name="tenant_id" id="tm-sync-tenant" type="text" class="regular-text"
                                   value="<?php echo esc_attr( $tenant_id ); ?>"
                                   placeholder="contoso.onmicrosoft.com or 11111111-2222-3333-4444-555555555555" required>
                            <p class="description"><?php echo esc_html__( 'Your Azure AD tenant domain or GUID.', 'theatre-manager-sync' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="tm-sync-client-id"><?php echo esc_html__( 'Client ID (Application ID)', 'theatre-manager-sync' ); ?></label></th>
                        <td>
                            <input name="client_id" id="tm-sync-client-id" type="text" class="regular-text"
                                   value="<?php echo esc_attr( $client_id ); ?>" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="tm-sync-client-secret"><?php echo esc_html__( 'Client Secret', 'theatre-manager-sync' ); ?></label></th>
                        <td>
                            <input name="client_secret" id="tm-sync-client-secret" type="password" class="regular-text"
                                   value="" placeholder="<?php echo $has_secret ? esc_attr__('******** (saved)', 'theatre-manager-sync') : ''; ?>">
                            <p class="description"><?php echo esc_html__( 'Leave blank to keep the currently saved secret. Enter a new value to replace.', 'theatre-manager-sync' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="tm-sync-site-id"><?php echo esc_html__( 'SharePoint Site ID', 'theatre-manager-sync' ); ?></label></th>
                        <td>
                            <input name="site_id" id="tm-sync-site-id" type="text" class="regular-text"
                                   value="<?php echo esc_attr( $site_id ); ?>"
                                   placeholder="{hostname},{site-collection-id},{site-id}">
                            <p class="description"><?php echo esc_html__( 'Graph Site ID to target for sync (optional if you prefer to use URL only).', 'theatre-manager-sync' ); ?></p>
                        </td>
                    </tr>

                    <!-- NEW: SharePoint URL -->
                    <tr>
                        <th scope="row"><label for="tm-sync-sp-url"><?php echo esc_html__( 'SharePoint URL', 'theatre-manager-sync' ); ?></label></th>
                        <td>
                            <input name="sharepoint_url" id="tm-sync-sp-url" type="url" class="regular-text"
                                   value="<?php echo esc_attr( $sharepoint_url ); ?>"
                                   placeholder="https://contoso.sharepoint.com/sites/MySite">
                            <p class="description">
                                <?php echo esc_html__( 'Full SharePoint site URL used for reference or discovery (e.g., to derive Site ID).', 'theatre-manager-sync' ); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Save Changes', 'theatre-manager-sync' ) ); ?>

            <hr>

            <h2><?php echo esc_html__( 'Test Token', 'theatre-manager-sync' ); ?></h2>
            <p><?php echo esc_html__( 'Click to attempt generating an app-only access token using the saved Tenant, Client ID, and Client Secret.', 'theatre-manager-sync' ); ?></p>
            <p>
                <button type="button" class="button button-secondary" id="tm-sync-test-token">
                    <?php echo esc_html__( 'Test Token Generation', 'theatre-manager-sync' ); ?>
                </button>
                <span id="tm-sync-test-status" style="margin-left:8px;"></span>
            </p>
        </form>
    </div>
    <?php
}

/**
 * SAVE HANDLER
 * Uses admin-post to process and store settings.
 */
add_action('admin_post_tm_sync_save_auth', function () {
    if ( ! current_user_can(TM_SYNC_CAP) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'theatre-manager-sync' ) );
    }
    check_admin_referer('tm_sync_save_auth', 'tm_sync_auth_nonce');

    $tenant_id      = isset($_POST['tenant_id'])      ? sanitize_text_field( wp_unslash($_POST['tenant_id']) ) : '';
    $client_id      = isset($_POST['client_id'])      ? sanitize_text_field( wp_unslash($_POST['client_id']) ) : '';
    $site_id        = isset($_POST['site_id'])        ? sanitize_text_field( wp_unslash($_POST['site_id']) )   : '';
    $sharepoint_url = isset($_POST['sharepoint_url']) ? tm_sync_normalize_sharepoint_url( wp_unslash($_POST['sharepoint_url']) ) : '';
    $secret_in      = isset($_POST['client_secret'])  ? (string) wp_unslash($_POST['client_secret']) : '';

    // Update non-sensitive bundle
    $payload = [
        'tenant_id'      => $tenant_id,
        'client_id'      => $client_id,
        'site_id'        => $site_id,
        'sharepoint_url' => $sharepoint_url, // ← NEW
    ];
    update_option(TM_SYNC_AUTH_OPTION, $payload);

    // Update secret if provided (keep autoload=no)
    if ( $secret_in !== '' ) {
        if ( get_option(TM_SYNC_CLIENT_SECRET_OPT, null) === null ) {
            add_option(TM_SYNC_CLIENT_SECRET_OPT, $secret_in, '', 'no');
        } else {
            update_option(TM_SYNC_CLIENT_SECRET_OPT, $secret_in, false);
        }
    }

    $url = add_query_arg(['page' => 'tm-sync', 'updated' => '1'], admin_url('admin.php'));
    wp_safe_redirect($url);
    exit;
});

/**
 * AJAX: Test token generation using saved credentials (client credentials flow).
 */
add_action('wp_ajax_tm_sync_test_token', function () {
    if ( ! current_user_can(TM_SYNC_CAP) ) {
        wp_send_json_error(['message' => __('Not authorized.', 'theatre-manager-sync')], 403);
    }
    check_ajax_referer('tm_sync_test_token', 'nonce');

    $opts      = get_option(TM_SYNC_AUTH_OPTION, []);
    $tenant    = isset($opts['tenant_id']) ? (string) $opts['tenant_id'] : '';
    $client_id = isset($opts['client_id']) ? (string) $opts['client_id'] : '';
    $secret    = (string) get_option(TM_SYNC_CLIENT_SECRET_OPT, '');

    if ( $tenant === '' || $client_id === '' || $secret === '' ) {
        tm_log_error('Token test failed: missing credentials', [
            'tenant'    => $tenant,
            'client_id' => $client_id,
        ], ['channel' => 'auth']);

        wp_send_json_error([
            'message' => __('Please save Tenant, Client ID, and Client Secret first.', 'theatre-manager-sync')
        ]);
    }

    $token_url = "https://login.microsoftonline.com/" . rawurlencode($tenant) . "/oauth2/v2.0/token";

    $args = [
        'timeout' => 20,
        'headers' => ['Accept' => 'application/json'],
        'body' => [
            'client_id'     => $client_id,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'https://graph.microsoft.com/.default',
        ],
    ];

    $resp = wp_remote_post($token_url, $args);
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $json = json_decode($body, true);

    if ( $code === 200 && ! empty($json['access_token']) ) {
        $expires = $json['expires_in'] ?? null;
        $tail    = substr($json['access_token'], -8);

        tm_log_info('Token test succeeded', [
            'tenant'     => $tenant,
            'client_id'  => $client_id,
            'expires_in' => $expires,
            'token_tail' => $tail,
        ], ['channel' => 'auth']);

        wp_send_json_success([
            'message'     => __('Token generated successfully.', 'theatre-manager-sync'),
            'expires_in'  => $expires,
            'token_tail'  => $tail,
            'token_type'  => $json['token_type'] ?? 'Bearer',
        ]);
    } else {
        $error_msg = $json['error_description'] ?? $body ?? 'Unknown error';

        tm_log_error('Token test failed', [
            'tenant'    => $tenant,
            'client_id' => $client_id,
            'error'     => $error_msg,
            'status'    => $code,
        ], ['channel' => 'auth']);

        wp_send_json_error([
            'message' => sprintf(__('Token request failed (%d): %s', 'theatre-manager-sync'), $code, $error_msg)
        ]);
    }
});

/**
 * Enqueue a tiny JS only on the Auth screen to handle Test Token button.
 * Hook this in your menu registration after capturing $auth_hook:
 *
 * add_action("load-$auth_hook", 'tm_sync_load_auth_screen');
 */
function tm_sync_load_auth_screen() {
    // Enqueue WordPress' built-in spinner styles (optional)
    wp_enqueue_style('common');

    // Enqueue a lightweight inline script (fine for simple pages).
    add_action('admin_print_footer_scripts', function () {
        $nonce = wp_create_nonce('tm_sync_test_token');
        $ajax  = admin_url('admin-ajax.php');
        ?>
        <script>
        (function(){
            const btn = document.getElementById('tm-sync-test-token');
            const out = document.getElementById('tm-sync-test-status');
            if (!btn || !out) return;

            function setStatus(html, isError) {
                out.innerHTML = html;
                out.style.color = isError ? '#b32d2e' : '#1d412f';
            }

            btn.addEventListener('click', function(){
                setStatus('<?php echo esc_js(__('Testing…', 'theatre-manager-sync')); ?> <span class="spinner is-active" style="float:none;"></span>', false);

                window.fetch('<?php echo esc_url($ajax); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({
                        action: 'tm_sync_test_token',
                        nonce: '<?php echo esc_js($nonce); ?>'
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (!data) throw new Error('No response');
                    if (data.success) {
                        const expires = data.data.expires_in ? ` (expires in ${data.data.expires_in}s)` : '';
                        const tail = data.data.token_tail ? ` • token …${data.data.token_tail}` : '';
                        setStatus('<?php echo esc_js(__('✅ Token generated', 'theatre-manager-sync')); ?>' + expires + tail, false);
                    } else {
                        setStatus('<?php echo esc_js(__('❌ Failed:', 'theatre-manager-sync')); ?> ' + (data.data && data.data.message ? data.data.message : 'Unknown error'), true);
                    }
                })
                .catch(err => {
                    setStatus('<?php echo esc_js(__('❌ Error:', 'theatre-manager-sync')); ?> ' + err.message, true);
                });
            });
        })();
        </script>
        <?php
    });
}
