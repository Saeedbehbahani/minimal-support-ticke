<?php
if (!defined('ABSPATH')) exit;

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

    if (!$pattern_code) return false;

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

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) return;
    if (!empty($ticket['admin_new_ticket_sms_sent'])) return;

    $admins = mst_admin_phones();
    if (empty($admins)) return;

    $params = [
        'ticket_id' => (string) $ticket_id,
        'subject'   => (string) $ticket['subject'],
        'status'    => (string) $ticket['status'],
    ];

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

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) return;
    if (!empty($ticket['user_first_reply_sms_sent'])) return;

    $phone = mst_user_mobile((int) $ticket['user_id']);
    if (!$phone) return;

    $params = [
        'ticket_id' => (string) $ticket_id,
        'subject'   => (string) $ticket['subject'],
        'status'    => (string) $ticket['status'],
    ];

    $sent = mst_sms_send_pattern('admin_replied_user', $phone, $params);
    if ($sent) {
        mst_mark_ticket_sms_sent($ticket_id, 'user_first_reply_sms_sent');
    }
}
