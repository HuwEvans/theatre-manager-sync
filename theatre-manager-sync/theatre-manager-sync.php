<?php
/**
 * Plugin Name: Theatre Manager Sync
 * Description: Sync SharePoint lists to Theatre Manager CPTs (one-way) via Microsoft Graph (App-only, Sites.Selected).
 * Version: 2.5
 * Author: Huw Evans
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: theatre-manager
 * Text Domain: theatre-manager-sync
 */

defined('ABSPATH') || exit;

// Enable debug logging for the sync plugin
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Public version constant for the sync plugin
if ( ! defined('THEATRE_MANAGER_SYNC_VERSION') ) {
    define('THEATRE_MANAGER_SYNC_VERSION', '2.5');
}

// Define plugin paths
define('TMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
//define('TM_PLUGIN_URL', plugin_dir_url(__FILE__));
		require_once TMS_PLUGIN_DIR . 'includes/logger.php';
		require_once TMS_PLUGIN_DIR . 'admin/admin-menu.php';
		require_once TMS_PLUGIN_DIR . 'includes/helpers.php';
		// Load sync handlers first to make folder-discovery functions available
		require_once plugin_dir_path(__FILE__) . 'includes/sync/sync-handlers.php';
		require_once plugin_dir_path(__FILE__) . 'includes/api/class-tm-graph-client.php';
		// Now load settings page (which uses folder-discovery functions)
		require_once TMS_PLUGIN_DIR . 'admin/settings-page.php';
		require_once TMS_PLUGIN_DIR . 'admin/logs-page.php';		
		require_once TMS_PLUGIN_DIR . 'admin/admin-sync.php';

	
if ( ! function_exists('tm_sync_required_cpts') ) {
    /**
     * Return the list of CPT slugs this plugin requires.
     * Filterable so other plugins/sites can add/remove CPTs.
     *
     * @return string[]
     */
    function tm_sync_required_cpts() {
        $required = [
            'contributor',
            'advertiser',
            'board_member',
            'cast',
            'season',
            'show',
        ];

        /**
         * Filter the required CPT slugs for Theatre Manager Sync.
         *
         * @param string[] $required
         */
        return (array) apply_filters('tm_sync_required_cpts', $required);
    }
}

if ( ! function_exists('tm_sync_missing_cpts') ) {
    /**
     * Check which required CPTs are missing.
     * Call this AFTER 'init' (or at a higher priority) so CPTs have been registered.
     *
     * @return string[] Missing CPT slugs (empty array if all present).
     */
    function tm_sync_missing_cpts() {
        $missing = [];
        foreach ( tm_sync_required_cpts() as $slug ) {
            if ( ! post_type_exists($slug) ) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }
}

if ( ! function_exists('tm_sync_ready') ) {
    /**
     * Are all CPT dependencies satisfied?
     *
     * @return bool
     */
    function tm_sync_ready() {
        // Store the result in a static cache for the current request.
        static $cached = null;
        if ( $cached !== null ) {
            return $cached;
        }
        // Safe default before 'init': assume not ready.
        if ( ! did_action('init') ) {
            return false;
        }
        $cached = ( tm_sync_missing_cpts() === [] );
        return $cached;
    }
}

/**
 * Schedule the CPT check after most CPTs are typically registered.
 * Using priority 30 to run after typical 'init' priority 10 registrations.
 */
add_action('init', function () {
    $missing = tm_sync_missing_cpts();

    // Persist for debugging/visibility (optional).
    if ( empty($missing) ) {
        delete_option('tm_sync_missing_cpts');
    } else {
        update_option('tm_sync_missing_cpts', array_values($missing), false);
    }

    // If missing, show an admin notice to privileged users.
    if ( ! empty($missing) ) {
        add_action('admin_notices', function () use ($missing) {
            if ( ! current_user_can('manage_options') ) {
                return;
            }

            $title = esc_html__('Theatre Manager Sync: Required CPTs Missing', 'theatre-manager-sync');
            $list  = '<code>' . esc_html(implode(', ', $missing)) . '</code>';

            echo '<div class="notice notice-error"><p><strong>' . $title . '</strong><br>'
               . wp_kses_post(sprintf(
                   /* translators: %s is a comma-separated list of CPT slugs. */
                   __('The plugin detected the following custom post types are not registered: %s. Features depending on them are disabled until they are available.', 'theatre-manager-sync'),
                   $list
               ))
               . '</p></div>';
        });
    }
}, 30);

/**
 * Example: Only bootstrap your plugin after CPTs are confirmed available.
 * This protects CPT-dependent code paths.
 */
add_action('plugins_loaded', function () {
    // Optional: also ensure the parent plugin/symbols exist before moving on.
    if ( ! defined('THEATRE_MANAGER_VERSION') ) {
        // Add your admin notice here if you haven’t already.
        return;
    }

    // Defer actual bootstrap until after our CPT check ran (init @ 30).
    add_action('init', function () {
        if ( ! tm_sync_ready() ) {
            // Don’t register CPT-dependent hooks, schedules, admin pages, etc.
            return;
        }

        // ✅ All good—boot your plugin now.
        tm_sync_bootstrap();
    }, 40);
}, 0);

/**
 * Your real bootstrap function.
 */
if ( ! function_exists('tm_sync_bootstrap') ) {
    function tm_sync_bootstrap() {

        // Everything here can safely assume the required CPTs exist.
    }
}

add_action('admin_init', function() {
    if (isset($_GET['force_advertiser_sync'])) {
        tm_sync_log('INFO', 'Forced advertiser sync via admin_init');
        $summary = tm_sync_advertisers(false);
        tm_sync_log('INFO', 'Forced sync summary: ' . $summary);
        echo $summary;
        exit;
    }
});
