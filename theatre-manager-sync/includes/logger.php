<?php
defined('ABSPATH') || exit;

const TM_SYNC_LOG_DIR = 'tm-sync/logs';
const TM_SYNC_LOG_RETENTION_DAYS = 30;

/**
 * Write a log entry to the TM Sync log file.
 *
 * @param string $level    Log level: debug, info, warning, error
 * @param string $message  Log message
 * @param array  $context  Optional context data
 */
function tm_sync_log($level, $message, array $context = []) {
    $uploads = wp_upload_dir();
    $log_dir = trailingslashit($uploads['basedir']) . TM_SYNC_LOG_DIR;

    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    $date = current_time('Y-m-d');
    $file = trailingslashit($log_dir) . "tm-sync-{$date}.log";

    $entry = [
        'timestamp' => current_time('mysql'),
        'level'     => strtoupper($level),
        'message'   => $message,
        'context'   => $context,
        'user_id'   => get_current_user_id(),
        'blog_id'   => get_current_blog_id(),
    ];

    $line = wp_json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Convenience wrappers
 */
function tm_log_debug($msg, $ctx = [])  { tm_sync_log('debug', $msg, $ctx); }
function tm_log_info($msg, $ctx = [])   { tm_sync_log('info', $msg, $ctx); }
function tm_log_warn($msg, $ctx = [])   { tm_sync_log('warning', $msg, $ctx); }
function tm_log_error($msg, $ctx = [])  { tm_sync_log('error', $msg, $ctx); }

/**
 * Log function execution time and result
 */
function tm_log_timed($label, callable $fn, array $ctx = []) {
    $start = microtime(true);
    tm_log_info("▶ {$label} started", $ctx);
    try {
        $result = $fn();
        $duration = round((microtime(true) - $start) * 1000, 2);
        tm_log_info("✔ {$label} completed in {$duration} ms", $ctx);
        return $result;
    } catch (Throwable $e) {
        $duration = round((microtime(true) - $start) * 1000, 2);
        tm_log_error("✖ {$label} failed in {$duration} ms", array_merge($ctx, [
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]));
        throw $e;
    }
}

/**
 * Prune old logs (optional: call daily via cron)
 */
function tm_sync_prune_logs() {
    $uploads = wp_upload_dir();
    $log_dir = trailingslashit($uploads['basedir']) . TM_SYNC_LOG_DIR;
    if (!is_dir($log_dir)) return;

    $files = glob($log_dir . '/tm-sync-*.log');
    $cutoff = time() - (TM_SYNC_LOG_RETENTION_DAYS * DAY_IN_SECONDS);

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}
if ( ! function_exists('tm_logger_file_for_date') ) {
    /**
     * Get the full path to the log file for a given date.
     *
     * @param string|null $date Format: 'Y-m-d'. Defaults to today.
     * @return string Absolute file path.
     */
    function tm_logger_file_for_date( $date = null ) {
        $date = $date ?: current_time('Y-m-d');
        $uploads = wp_upload_dir();
        $log_dir = trailingslashit($uploads['basedir']) . TM_SYNC_LOG_DIR;
        return trailingslashit($log_dir) . "tm-sync-{$date}.log";
    }
}
