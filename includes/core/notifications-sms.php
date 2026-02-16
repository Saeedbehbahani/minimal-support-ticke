<?php
if (!defined('ABSPATH')) exit;

/**
 * Debug logger (only when WP_DEBUG is enabled).
 */
function mst_sms_debug_log(string $message, array $context = []): void {
    if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
        return;
    }

    $suffix = '';
    if (!empty($context)) {
        $encoded = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $encoded !== '') {
            $suffix = ' ' . $encoded;
        }
    }

    error_log('[MST SMS DEBUG] ' . $message . $suffix);
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
    if (!mst_sms_enabled()) return false;

    $provider = mst_sms_provider();
    if (!$provider) return false;

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
    if (!mst_sms_enabled()) return;
    if (!class_exists('MST_DB')) return;

    if ($ticket_id <= 0) {
        mst_sms_debug_log('Abort ticket_created_admin SMS: invalid ticket_id.', [
            'event'     => 'ticket_created_admin',
            'ticket_id' => $ticket_id,
        ]);
        return;
    }

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) return;
    if (!empty($ticket['admin_new_ticket_sms_sent'])) return;

    $admins = mst_admin_phones();
    if (empty($admins)) return;

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
    if (!mst_sms_enabled()) return;
    if (!class_exists('MST_DB')) return;

    if ($ticket_id <= 0) {
        mst_sms_debug_log('Abort admin_replied_user SMS: invalid ticket_id.', [
            'event'     => 'admin_replied_user',
            'ticket_id' => $ticket_id,
        ]);
        return;
    }

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) return;
    if (!empty($ticket['user_first_reply_sms_sent'])) return;

    $phone = mst_user_mobile((int) $ticket['user_id']);
    if (!$phone) return;

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
    }
}
