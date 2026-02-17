<?php
if (!defined('ABSPATH')) exit;

/**
 * Is SMS debug mode enabled from plugin settings?
 */
function mst_sms_debug_enabled(): bool {
    return (int) mst_opt('sms_debug_mode', 0) === 1;
}

/**
 * Absolute path to plugin SMS log file.
 */
function mst_sms_debug_log_file_path(): string {
    $uploads = wp_upload_dir();
    $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
    if ($base_dir === '') {
        return '';
    }

    return trailingslashit($base_dir) . 'mst-logs/mst-sms.log';
}

/**
 * Debug logger to uploads/mst-logs/mst-sms.log (only when sms_debug_mode is ON).
 */
function mst_sms_debug_log(string $message, array $context = []): void {
    if (!mst_sms_debug_enabled()) {
        return;
    }

    $suffix = '';
    if (!empty($context)) {
        $encoded = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $encoded !== '') {
            $suffix = ' ' . $encoded;
        }
    }

    $line = sprintf(
        "[%s] %s%s\n",
        wp_date('Y-m-d H:i:s'),
        $message,
        $suffix
    );

    $file_path = mst_sms_debug_log_file_path();
    if ($file_path === '') {
        return;
    }

    $log_dir = dirname($file_path);
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    file_put_contents($file_path, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Read plugin options
 */
function mst_opt(string $key, $default = '') {
    $opts = get_option('mst_settings', []);
    if (!is_array($opts)) $opts = [];
    return $opts[$key] ?? $default;
}

/**
 * Admin phones array from settings (comma separated)
 */
function mst_admin_phones(): array {
    $raw = (string) mst_opt('admin_phones', '');
    $raw = str_replace(['|', ';'], ',', $raw);
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    // normalize digits (keep + if exists)
    $phones = [];
    foreach ($parts as $p) {
        $p = preg_replace('/\s+/', '', $p);
        if ($p !== '') $phones[] = $p;
    }
    return array_values(array_unique($phones));
}

/**
 * Should we send SMS notifications?
 */
function mst_sms_enabled(): bool {
    return (string) mst_opt('notify_channel', 'sms') === 'sms';
}

/**
 * Create provider instance (pattern)
 */
function mst_sms_provider() {
    if (!mst_sms_enabled()) return null;
    if (!class_exists('MST_SMS_Provider')) return null;

    $api_key   = (string) mst_opt('sms_api_key', '');
    $sender    = (string) mst_opt('sms_sender', '');
    $base_url  = (string) mst_opt('sms_base_url', ''); // optional

    if ($api_key === '') return null;

    try {
        return new MST_SMS_Provider([
            'api_key'  => $api_key,
            'sender'   => $sender,
            'base_url' => $base_url,
        ]);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Mark ticket SMS flag as sent (1).
 */
function mst_mark_ticket_sms_sent(int $ticket_id, string $column): void {
    if (!class_exists('MST_DB')) return;
    if (!in_array($column, ['admin_new_ticket_sms_sent', 'user_first_reply_sms_sent'], true)) return;

    global $wpdb;
    $tickets = MST_DB::tickets_table();
    $wpdb->update($tickets, [$column => 1], ['id' => $ticket_id], ['%d'], ['%d']);
}

/**
 * Send SMS by pattern name (event) with params
 */
function mst_sms_send_pattern(string $pattern_key, string $to, array $params = []): bool {
    if (!mst_sms_enabled()) {
        mst_sms_debug_log('Abort SMS send: channel is disabled.', ['event' => $pattern_key]);
        return false;
    }

    $provider = mst_sms_provider();
    if (!$provider) {
        mst_sms_debug_log('Abort SMS send: provider unavailable.', ['event' => $pattern_key]);
        return false;
    }

    // pattern mapping from settings
    $patterns = (array) mst_opt('sms_patterns', []);
    $pattern_code = $patterns[$pattern_key] ?? '';

    if (!$pattern_code) {
        mst_sms_debug_log('Pattern code missing for event.', [
            'event'    => $pattern_key,
            'receptor' => $to,
        ]);
        return false;
    }

    mst_sms_debug_log('Automatic SMS send requested.', [
        'event'    => $pattern_key,
        'receptor' => $to,
        'pattern'  => $pattern_code,
        'tokens'   => $params,
    ]);

    try {
        // We'll implement send_pattern in provider
        return (bool) $provider->send_pattern($to, $pattern_code, $params);
    } catch (\Throwable $e) {
        mst_sms_debug_log('SMS send threw exception.', [
            'event'   => $pattern_key,
            'message' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Helpers for ticket/user data
 */
function mst_user_mobile(int $user_id): string {
    // priority: user meta 'phone' or 'mobile' (you can adapt)
    $m = (string) get_user_meta($user_id, 'phone', true);
    if (!$m) $m = (string) get_user_meta($user_id, 'mobile', true);
    if (!$m) $m = (string) get_user_meta($user_id, 'billing_phone', true); // WooCommerce
    return trim($m);
}

/**
 * EVENT 1: Ticket created -> notify admins
 */
function mst_notify_ticket_created(int $ticket_id): void {
    if (!mst_sms_enabled()) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: channel disabled.', ['ticket_id' => $ticket_id]);
        return;
    }
    if (!class_exists('MST_DB')) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: MST_DB not loaded.', ['ticket_id' => $ticket_id]);
        return;
    }

    if ($ticket_id <= 0) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: invalid ticket_id.', [
            'event'     => 'ticket_created_admin',
            'ticket_id' => $ticket_id,
        ]);
        return;
    }

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: ticket not found.', ['ticket_id' => $ticket_id]);
        return;
    }
    if (!empty($ticket['admin_new_ticket_sms_sent'])) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: already sent.', [
            'ticket_id' => $ticket_id,
            'flag'      => (int) $ticket['admin_new_ticket_sms_sent'],
        ]);
        return;
    }

    $admins = mst_admin_phones();
    if (empty($admins)) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: no admin phones configured.', ['ticket_id' => $ticket_id]);
        return;
    }

    $params = [
        'token' => (string) $ticket_id,
    ];

    mst_sms_debug_log('Prepared automatic SMS payload for ticket_created_admin.', [
        'event'      => 'ticket_created_admin',
        'ticket_id'  => $ticket_id,
        'receptors'  => $admins,
        'pattern'    => ((array) mst_opt('sms_patterns', []))['ticket_created_admin'] ?? '',
        'tokens'     => $params,
    ]);

    $all_sent = true;
    foreach ($admins as $phone) {
        $sent = mst_sms_send_pattern('ticket_created_admin', $phone, $params);
        if (!$sent) {
            $all_sent = false;
        }
    }

    if ($all_sent) {
        mst_mark_ticket_sms_sent($ticket_id, 'admin_new_ticket_sms_sent');
    }
}

/**
 * EVENT 2: Admin replied -> notify user
 */
function mst_notify_admin_replied(int $ticket_id): void {
    mst_sms_debug_log('Enter admin_replied_user notification flow.', [
        'event'     => 'admin_replied_user',
        'ticket_id' => $ticket_id,
        'user_id'   => get_current_user_id(),
    ]);

    if (!mst_sms_enabled()) {
        mst_sms_debug_log('Abort admin_replied_user SMS: channel disabled.', ['ticket_id' => $ticket_id]);
        return;
    }
    if (!class_exists('MST_DB')) {
        mst_sms_debug_log('Abort admin_replied_user SMS: MST_DB not loaded.', ['ticket_id' => $ticket_id]);
        return;
    }

    if ($ticket_id <= 0) {
        mst_sms_debug_log('Abort admin_replied_user SMS: invalid ticket_id.', [
            'event'     => 'admin_replied_user',
            'ticket_id' => $ticket_id,
        ]);
        return;
    }

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) {
        mst_sms_debug_log('Abort admin_replied_user SMS: ticket not found.', ['ticket_id' => $ticket_id]);
        return;
    }
    if (!empty($ticket['user_first_reply_sms_sent'])) {
        mst_sms_debug_log('Abort admin_replied_user SMS: first-reply flag already set.', [
            'ticket_id' => $ticket_id,
            'flag'      => (int) $ticket['user_first_reply_sms_sent'],
        ]);
        return;
    }

    $is_agent = function_exists('mst_is_ticket_agent_user') ? mst_is_ticket_agent_user() : false;
    mst_sms_debug_log('Admin reply capability check.', [
        'ticket_id'              => $ticket_id,
        'manage_options'         => current_user_can('manage_options'),
        'manage_mst_tickets'     => current_user_can('manage_mst_tickets'),
        'mst_manage_tickets'     => current_user_can('mst_manage_tickets'),
        'is_ticket_agent_result' => $is_agent,
    ]);
    if (!$is_agent) {
        mst_sms_debug_log('Abort admin_replied_user SMS: actor is not admin/agent.', [
            'ticket_id' => $ticket_id,
            'user_id'   => get_current_user_id(),
        ]);
        return;
    }

    $phone = mst_user_mobile((int) $ticket['user_id']);
    if (!$phone) {
        mst_sms_debug_log('Abort admin_replied_user SMS: ticket owner phone not resolved.', [
            'ticket_id' => $ticket_id,
            'owner_id'  => (int) $ticket['user_id'],
        ]);
        return;
    }

    $params = [
        'token' => (string) $ticket_id,
    ];

    mst_sms_debug_log('Prepared automatic SMS payload for admin_replied_user.', [
        'event'      => 'admin_replied_user',
        'ticket_id'  => $ticket_id,
        'receptors'  => [$phone],
        'pattern'    => ((array) mst_opt('sms_patterns', []))['admin_replied_user'] ?? '',
        'tokens'     => $params,
    ]);

    $sent = mst_sms_send_pattern('admin_replied_user', $phone, $params);
    if ($sent) {
        mst_mark_ticket_sms_sent($ticket_id, 'user_first_reply_sms_sent');
        mst_sms_debug_log('admin_replied_user SMS sent and flag updated.', [
            'ticket_id' => $ticket_id,
            'phone'     => $phone,
        ]);
        return;
    }

    mst_sms_debug_log('admin_replied_user SMS failed. Flag left unchanged.', [
        'ticket_id' => $ticket_id,
        'phone'     => $phone,
    ]);
}
