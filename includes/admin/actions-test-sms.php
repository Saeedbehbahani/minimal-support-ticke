<?php
if (!defined('ABSPATH')) exit;

/**
 * Render Test SMS Page
 */
function mst_admin_render_test_sms_page(): void {
    if (!mst_is_ticket_admin()) wp_die('Access denied', 403);

    $result = null;

    if (!empty($_POST['mst_test_sms_submit'])) {
        check_admin_referer('mst_test_sms', '_mst_nonce');

        $to       = sanitize_text_field((string)($_POST['to'] ?? ''));
        $pattern  = sanitize_text_field((string)($_POST['pattern'] ?? ''));
        $token1   = sanitize_text_field((string)($_POST['token1'] ?? ''));
        $token2   = sanitize_text_field((string)($_POST['token2'] ?? ''));
        $token3   = sanitize_text_field((string)($_POST['token3'] ?? ''));

        $params = array_filter([$token1, $token2, $token3], function($v){
            return $v !== '';
        });

        if (function_exists('mst_sms_send_pattern')) {
            $sent = mst_sms_send_pattern_raw($to, $pattern, $params);
            $result = $sent ? 'success' : 'fail';
        } else {
            $result = 'fail';
        }
    }

    ?>
    <div class="wrap">
        <h1>تست پیامک Pattern</h1>

        <?php if ($result === 'success'): ?>
            <div class="notice notice-success"><p>پیامک با موفقیت ارسال شد.</p></div>
        <?php elseif ($result === 'fail'): ?>
            <div class="notice notice-error"><p>ارسال پیامک ناموفق بود. تنظیمات یا Pattern را بررسی کن.</p></div>
        <?php endif; ?>

        <form method="post" style="background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:16px;max-width:600px;">
            <?php wp_nonce_field('mst_test_sms', '_mst_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">شماره گیرنده</th>
                    <td>
                        <input type="text" name="to" class="regular-text" placeholder="0912..." required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Pattern Code</th>
                    <td>
                        <input type="text" name="pattern" class="regular-text" required>
                        <p class="description">مثال: verify_ticket</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Token 1</th>
                    <td><input type="text" name="token1" class="regular-text"></td>
                </tr>

                <tr>
                    <th scope="row">Token 2</th>
                    <td><input type="text" name="token2" class="regular-text"></td>
                </tr>

                <tr>
                    <th scope="row">Token 3</th>
                    <td><input type="text" name="token3" class="regular-text"></td>
                </tr>
            </table>

            <p>
                <button type="submit" name="mst_test_sms_submit" value="1" class="button button-primary">
                    ارسال تست
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Raw send for test page (bypass event mapping)
 */
function mst_sms_send_pattern_raw(string $to, string $pattern, array $params = []): bool {

    if (!class_exists('MST_SMS_Provider')) return false;

    $opts = get_option('mst_settings', []);
    if (!is_array($opts)) return false;

    $provider = new MST_SMS_Provider([
        'driver'   => 'pattern',
        'api_key'  => $opts['sms_api_key'] ?? '',
        'sender'   => $opts['sms_sender'] ?? '',
        'base_url' => $opts['sms_base_url'] ?? '',
    ]);

    try {
        return (bool)$provider->send_pattern($to, $pattern, $params);
    } catch (\Throwable $e) {
        return false;
    }
}
