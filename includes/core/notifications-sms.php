<?php
if (!defined('ABSPATH')) exit;

/**
 * Is SMS debug mode enabled from plugin settings?
 */
function mst_sms_debug_enabled(): bool {
    return (int) mst_opt('sms_debug_mode', 0) === 1;
}

/**
 * Store diagnostic logs in the system temporary directory, outside public uploads.
 * The per-site directory name and restrictive permissions reduce accidental exposure.
 */
function mst_sms_debug_log_file_path(): string {
    $base_dir = function_exists('sys_get_temp_dir') ? (string) sys_get_temp_dir() : '';
    if ($base_dir === '' || !is_dir($base_dir) || !is_writable($base_dir)) {
        return '';
    }

    $site_secret = function_exists('wp_salt') ? (string) wp_salt('auth') : (string) ABSPATH;
    $site_key = substr(hash('sha256', $site_secret), 0, 20);

    return trailingslashit($base_dir) . 'mst-private-logs-' . $site_key . '/mst-sms.log';
}

/**
 * Remove the legacy log previously written under public uploads.
 * It could contain phone numbers, SMS tokens, provider responses, or an API key in its URL.
 */
function mst_sms_cleanup_legacy_log(): void {
    if (!function_exists('wp_upload_dir')) {
        return;
    }

    $uploads = wp_upload_dir();
    $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
    if ($base_dir === '') {
        return;
    }

    $legacy_dir = trailingslashit($base_dir) . 'mst-logs';
    $legacy_file = trailingslashit($legacy_dir) . 'mst-sms.log';

    if (is_file($legacy_file) && is_writable($legacy_dir)) {
        @unlink($legacy_file);
    }

    if (is_dir($legacy_dir)) {
        @rmdir($legacy_dir);
    }
}

add_action('plugins_loaded', 'mst_sms_cleanup_legacy_log', 1);

/**
 * Write sanitized diagnostics to a private temporary file (only when debug mode is ON).
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

    if (!is_dir($log_dir) || !is_writable($log_dir)) {
        return;
    }

    @chmod($log_dir, 0700);
    $written = @file_put_contents($file_path, $line, FILE_APPEND | LOCK_EX);
    if ($written !== false) {
        @chmod($file_path, 0600);
    }
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
            'event' => $pattern_key,
        ]);
        return false;
    }

    mst_sms_debug_log('Automatic SMS send requested.', [
        'event'   => $pattern_key,
        'pattern' => $pattern_code,
    ]);

    try {
        // We'll implement send_pattern in provider
        return (bool) $provider->send_pattern($to, $pattern_code, $params);
    } catch (\Throwable $e) {
        mst_sms_debug_log('SMS send threw exception.', [
            'event'   => $pattern_key,
            'exception' => get_class($e),
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
 * Normalize Iranian mobile numbers to 09XXXXXXXXX format.
 */
function mst_normalize_iranian_phone(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $has_plus = strpos($raw, '+') === 0;
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits) || $digits === '') {
        return '';
    }

    $phone = $has_plus ? ('+' . $digits) : $digits;
    if (strpos($phone, '+98') === 0) {
        $phone = '0' . substr($phone, 3);
    } elseif (strpos($phone, '0098') === 0) {
        $phone = '0' . substr($phone, 4);
    } elseif (strpos($phone, '98') === 0) {
        $phone = '0' . substr($phone, 2);
    }

    if (!preg_match('/^09\d{9}$/', $phone)) {
        return '';
    }

    return $phone;
}

/**
 * Mask potential phone value for debug logs.
 */
function mst_mask_phone_value(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }

    return substr($value, 0, 3) . str_repeat('*', max(1, $len - 5)) . substr($value, -2);
}

/**
 * Resolve user phone by checking common meta keys and username fallback.
 */
function mst_resolve_user_phone(int $user_id): string {
    $meta_keys = [
        'digits_phone', 'digits_mobile', 'digits_phonenumber', 'digits_user_phone',
        'mobile', 'user_mobile', 'phone', 'user_phone',
        'billing_phone',
    ];

    $checked_values = [];
    $resolved_phone = '';
    $resolved_source = '';

    foreach ($meta_keys as $meta_key) {
        $raw_value = (string) get_user_meta($user_id, $meta_key, true);
        $checked_values[$meta_key] = mst_mask_phone_value($raw_value);
        $normalized = mst_normalize_iranian_phone($raw_value);
        if ($normalized !== '') {
            $resolved_phone = $normalized;
            $resolved_source = 'user_meta:' . $meta_key;
            break;
        }
    }

    $user = get_userdata($user_id);
    $login_masked = '';
    if ($user && !empty($user->user_login)) {
        $login_raw = (string) $user->user_login;
        $login_masked = mst_mask_phone_value($login_raw);

        if ($resolved_phone === '') {
            $login_normalized = mst_normalize_iranian_phone($login_raw);
            if ($login_normalized !== '') {
                $resolved_phone = $login_normalized;
                $resolved_source = 'user_login';
            }
        }
    }

    $context = [
        'checked_meta' => $checked_values,
        'checked_keys' => $meta_keys,
        'user_login'   => $login_masked,
        'source'       => $resolved_source,
    ];

    $filtered_phone = apply_filters('mst_user_phone', $resolved_phone, $user_id, $context);
    if (!is_string($filtered_phone)) {
        $filtered_phone = '';
    }

    $resolved_phone = mst_normalize_iranian_phone($filtered_phone);
    $context['source'] = ($resolved_phone !== '' && $context['source'] === '') ? 'filter' : $context['source'];

    $GLOBALS['mst_last_phone_resolution'] = $context;

    return $resolved_phone;
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
        mst_sms_debug_log('Skip SMS due to flag guard (already sent).', [
            'event'             => 'ticket_created_admin',
            'ticket_id'         => $ticket_id,
            'prevented_by_flag' => 'admin_new_ticket_sms_sent',
            'flag_value'        => (int) $ticket['admin_new_ticket_sms_sent'],
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
        'recipient_count' => count($admins),
        'pattern'         => ((array) mst_opt('sms_patterns', []))['ticket_created_admin'] ?? '',
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
        mst_sms_debug_log('Skip SMS due to flag guard (already sent).', [
            'event'             => 'admin_replied_user',
            'ticket_id'         => $ticket_id,
            'prevented_by_flag' => 'user_first_reply_sms_sent',
            'flag_value'        => (int) $ticket['user_first_reply_sms_sent'],
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

    $owner_id = (int) $ticket['user_id'];
    $phone = mst_resolve_user_phone($owner_id);
    if (!$phone) {
        $resolution_context = isset($GLOBALS['mst_last_phone_resolution']) && is_array($GLOBALS['mst_last_phone_resolution'])
            ? $GLOBALS['mst_last_phone_resolution']
            : [];

        mst_sms_debug_log('Abort admin_replied_user SMS: ticket owner phone not resolved.', [
            'ticket_id' => $ticket_id,
            'owner_id'  => $owner_id,
            'checked_keys' => $resolution_context['checked_keys'] ?? [],
            'checked_meta' => $resolution_context['checked_meta'] ?? [],
            'user_login' => $resolution_context['user_login'] ?? '',
        ]);
        return;
    }

    $params = [
        'token' => (string) $ticket_id,
    ];

    mst_sms_debug_log('Prepared automatic SMS payload for admin_replied_user.', [
        'event'      => 'admin_replied_user',
        'ticket_id'  => $ticket_id,
        'recipient_count' => 1,
        'pattern'         => ((array) mst_opt('sms_patterns', []))['admin_replied_user'] ?? '',
    ]);

    $sent = mst_sms_send_pattern('admin_replied_user', $phone, $params);
    if ($sent) {
        mst_mark_ticket_sms_sent($ticket_id, 'user_first_reply_sms_sent');
        mst_sms_debug_log('admin_replied_user SMS sent and flag updated.', [
            'ticket_id' => $ticket_id,
            'sms_sent'  => true,
        ]);
        return;
    }

    mst_sms_debug_log('admin_replied_user SMS failed. Flag left unchanged.', [
        'ticket_id' => $ticket_id,
        'sms_sent'  => false,
    ]);
}
