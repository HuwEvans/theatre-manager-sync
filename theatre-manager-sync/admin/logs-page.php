<?php
defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';


class TM_Sync_Log_Table extends WP_List_Table {
    private $items_raw = [];

    public function __construct() {
        parent::__construct([
            'singular' => 'log_entry',
            'plural'   => 'log_entries',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'timestamp' => 'Timestamp',
            'level'     => 'Level',
            'channel'   => 'Channel',
            'message'   => 'Message',
            'context'   => 'Context',
        ];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];

        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->items_raw = tm_sync_read_log_entries();

        // Filter by level or channel
        if ( isset($_GET['level']) && $_GET['level'] !== '' ) {
            $level = strtolower(sanitize_text_field($_GET['level']));
            $this->items_raw = array_filter($this->items_raw, fn($e) => strtolower($e['level']) === $level);
        }
        if ( isset($_GET['channel']) && $_GET['channel'] !== '' ) {
            $channel = strtolower(sanitize_text_field($_GET['channel']));
            $this->items_raw = array_filter($this->items_raw, fn($e) => strtolower($e['channel']) === $channel);
        }

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($this->items_raw);

        $this->items = array_slice($this->items_raw, ($current_page - 1) * $per_page, $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'timestamp':
            case 'level':
            case 'channel':
            case 'message':
                return esc_html($item[$column_name]);
            case 'context':
                return '<pre style="white-space:pre-wrap;">' . esc_html(json_encode($item['context'], JSON_PRETTY_PRINT)) . '</pre>';
            default:
                return '';
        }
    }
}

/**
 * Read today's log file and parse entries.
 */
function tm_sync_read_log_entries() {
    $file = tm_logger_file_for_date(); // from logger.php
    if ( ! file_exists($file) ) return [];

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = [];

    foreach ($lines as $line) {
        $json = json_decode($line, true);
        if ( is_array($json) && isset($json['timestamp'], $json['level'], $json['message']) ) {
            $entries[] = $json;
        }
    }

    return array_reverse($entries); // newest first
}

/**
 * Render the Logs admin page.
 */
function tm_sync_page_logs() {
    tm_sync_cap_check();

    echo '<div class="wrap"><h1>TM Sync · Logs</h1>';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="tm-sync-logs">';
    echo '<select name="level"><option value="">All Levels</option>';
    foreach (['debug','info','notice','warning','error','critical'] as $lvl) {
        $selected = (isset($_GET['level']) && $_GET['level'] === $lvl) ? 'selected' : '';
        echo "<option value=\"$lvl\" $selected>" . ucfirst($lvl) . "</option>";
    }
    echo '</select>';

    echo '<select name="channel"><option value="">All Channels</option>';
    foreach (['auth','sync','scheduler','admin-ui','app'] as $ch) {
        $selected = (isset($_GET['channel']) && $_GET['channel'] === $ch) ? 'selected' : '';
        echo "<option value=\"$ch\" $selected>" . ucfirst($ch) . "</option>";
    }
    echo '</select>';

    submit_button('Filter', 'secondary', '', false);
    echo '</form>';

    $table = new TM_Sync_Log_Table();
    $table->prepare_items();
    $table->display();

    echo '</div>';
}
