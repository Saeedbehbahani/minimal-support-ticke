<?php
if (!defined('ABSPATH')) exit;

function mst_admin_render_settings(): void {
    if (!mst_is_ticket_admin()) wp_die('Access denied', 403);

    $opts = get_option('mst_settings', []);
    if (!is_array($opts)) $opts = [];

    // Defaults
    $opts = wp_parse_args($opts, [
        'notify_channel' => 'sms', // sms|off
        'admin_phones'   => '',
        'sms_api_key'    => '',
        'sms_sender'     => '',
        'sms_base_url'   => '',
        'sms_debug_mode' => 0,

        // pattern codes
        'sms_patterns'   => [
            'ticket_created_admin' => '',
            'admin_replied_user'   => '',
        ],
    ]);

    $opts['sms_patterns'] = wp_parse_args(is_array($opts['sms_patterns']) ? $opts['sms_patterns'] : [], [
        'ticket_created_admin' => '',
        'admin_replied_user'   => '',
    ]);

    // Save
    if (!empty($_POST['mst_settings_submit'])) {
        check_admin_referer('mst_save_settings', '_mst_nonce');

        $notify_channel = sanitize_key((string)($_POST['notify_channel'] ?? 'sms'));
        if (!in_array($notify_channel, ['sms','off'], true)) $notify_channel = 'sms';

        $admin_phones = sanitize_text_field((string)($_POST['admin_phones'] ?? ''));

        $sms_api_key  = sanitize_text_field((string)($_POST['sms_api_key'] ?? ''));
        $sms_sender   = sanitize_text_field((string)($_POST['sms_sender'] ?? ''));
        $sms_base_url = esc_url_raw((string)($_POST['sms_base_url'] ?? ''));
        $sms_debug_mode = empty($_POST['sms_debug_mode']) ? 0 : 1;

        $patterns_in = $_POST['sms_patterns'] ?? [];
        if (!is_array($patterns_in)) $patterns_in = [];

        $patterns = [
            'ticket_created_admin' => sanitize_text_field((string)($patterns_in['ticket_created_admin'] ?? '')),
            'admin_replied_user'   => sanitize_text_field((string)($patterns_in['admin_replied_user'] ?? '')),
        ];

        $new = [
            'notify_channel' => $notify_channel,
            'admin_phones'   => $admin_phones,
            'sms_api_key'    => $sms_api_key,
            'sms_sender'     => $sms_sender,
            'sms_base_url'   => $sms_base_url,
            'sms_debug_mode' => $sms_debug_mode,
            'sms_patterns'   => $patterns,
        ];

        update_option('mst_settings', $new);

        $opts = wp_parse_args($new, $opts);

        echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>تنظیمات سیستم تیکت</h1>

        <form method="post">
            <?php wp_nonce_field('mst_save_settings', '_mst_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">نوتیفیکیشن</th>
                    <td>
                        <label style="margin-inline-end:12px;">
                            <input type="radio" name="notify_channel" value="sms" <?php checked($opts['notify_channel'], 'sms'); ?>>
                            روشن (SMS)
                        </label>
                        <label>
                            <input type="radio" name="notify_channel" value="off" <?php checked($opts['notify_channel'], 'off'); ?>>
                            خاموش
                        </label>
                        <p class="description">فعلاً فقط SMS پشتیبانی می‌شود.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">شماره‌های ادمین</th>
                    <td>
                        <input type="text" name="admin_phones" value="<?php echo esc_attr($opts['admin_phones']); ?>" class="regular-text" placeholder="مثال: 0912..., 0935...">
                        <p class="description">با کاما جدا کن. هر تیکت جدید به این شماره‌ها پیامک می‌شود.</p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2>تنظیمات SMS (Pattern)</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="sms_api_key" value="<?php echo esc_attr($opts['sms_api_key']); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">Sender</th>
                    <td>
                        <input type="text" name="sms_sender" value="<?php echo esc_attr($opts['sms_sender']); ?>" class="regular-text" placeholder="مثال: 1000xxxx">
                        <p class="description">در بعضی سرویس‌ها اجباری نیست. اگر سرویس شما لازم دارد وارد کن.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Base URL (اختیاری)</th>
                    <td>
                        <input type="text" name="sms_base_url" value="<?php echo esc_attr($opts['sms_base_url']); ?>" class="regular-text" placeholder="اگر Provider شما نیاز دارد">
                    </td>
                </tr>

                <tr>
                    <th scope="row">SMS Debug Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="sms_debug_mode" value="1" <?php checked((int) $opts['sms_debug_mode'], 1); ?>>
                            فعال‌سازی لاگ پیامکی در wp-content/uploads/mst-logs/mst-sms.log
                        </label>
                    </td>
                </tr>
            </table>

            <hr>

            <h2>Pattern Codes</h2>
            <p class="description">کد Pattern هر رویداد را وارد کن.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">ثبت تیکت (به ادمین)</th>
                    <td>
                        <input type="text" name="sms_patterns[ticket_created_admin]" value="<?php echo esc_attr($opts['sms_patterns']['ticket_created_admin']); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">پاسخ ادمین (به کاربر)</th>
                    <td>
                        <input type="text" name="sms_patterns[admin_replied_user]" value="<?php echo esc_attr($opts['sms_patterns']['admin_replied_user']); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <?php if ((int) $opts['sms_debug_mode'] === 1 && function_exists('mst_sms_debug_log_file_path')): ?>
                <?php
                $log_path = mst_sms_debug_log_file_path();
                $log_tail = '';

                if (is_string($log_path) && $log_path !== '' && file_exists($log_path)) {
                    $lines = @file($log_path, FILE_IGNORE_NEW_LINES);
                    if (is_array($lines)) {
                        $last_lines = array_slice($lines, -300);
                        $log_tail = implode("\n", $last_lines);
                    }
                }
                ?>
                <hr>
                <h2>SMS Debug Log Viewer</h2>
                <p class="description">آخرین حدود ۳۰۰ خط از لاگ محلی.</p>
                <pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:380px;overflow:auto;"><?php echo esc_html($log_tail !== '' ? $log_tail : 'No logs yet.'); ?></pre>
            <?php endif; ?>

            <p>
                <button type="submit" name="mst_settings_submit" value="1" class="button button-primary">
                    ذخیره تنظیمات
                </button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mst_test_sms')); ?>">رفتن به تست پیامک</a>
            </p>
        </form>
    </div>
    <?php
}
