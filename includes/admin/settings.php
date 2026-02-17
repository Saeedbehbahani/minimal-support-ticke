<?php
if (!defined('ABSPATH')) exit;

function mst_get_auth_settings_defaults(): array {
    return [
        'login_display_mode' => 'url',
        'login_url' => '',
        'login_page_id' => 0,
        'login_shortcode' => '',
        'login_button_text' => 'ورود به سایت',
        'login_helper_text' => '',
    ];
}

function mst_register_auth_settings_fields(): void {
    register_setting('mst_settings_auth_group', 'mst_settings', [
        'type' => 'array',
        'sanitize_callback' => 'mst_sanitize_auth_settings_option',
        'default' => mst_get_auth_settings_defaults(),
    ]);

    add_settings_section(
        'mst_auth_login_section',
        'Authentication / Login',
        '__return_empty_string',
        'mst_settings_auth'
    );

    add_settings_field('login_display_mode', 'Login Display Mode', 'mst_render_field_login_display_mode', 'mst_settings_auth', 'mst_auth_login_section');
    add_settings_field('login_url', 'Login URL', 'mst_render_field_login_url', 'mst_settings_auth', 'mst_auth_login_section');
    add_settings_field('login_page_id', 'Login Page', 'mst_render_field_login_page_id', 'mst_settings_auth', 'mst_auth_login_section');
    add_settings_field('login_shortcode', 'Login Shortcode', 'mst_render_field_login_shortcode', 'mst_settings_auth', 'mst_auth_login_section');
    add_settings_field('login_button_text', 'Login Button Text', 'mst_render_field_login_button_text', 'mst_settings_auth', 'mst_auth_login_section');
    add_settings_field('login_helper_text', 'Login Helper Text', 'mst_render_field_login_helper_text', 'mst_settings_auth', 'mst_auth_login_section');
}
add_action('admin_init', 'mst_register_auth_settings_fields');

function mst_sanitize_auth_settings_option($input): array {
    $existing = get_option('mst_settings', []);
    $existing = is_array($existing) ? $existing : [];
    $defaults = mst_get_auth_settings_defaults();
    $input = is_array($input) ? $input : [];

    $mode = sanitize_key((string) ($input['login_display_mode'] ?? $defaults['login_display_mode']));
    if (!in_array($mode, ['url', 'page', 'shortcode'], true)) {
        $mode = $defaults['login_display_mode'];
    }

    $auth = [
        'login_display_mode' => $mode,
        'login_url' => esc_url_raw((string) ($input['login_url'] ?? '')),
        'login_page_id' => absint($input['login_page_id'] ?? 0),
        'login_shortcode' => wp_kses_post((string) ($input['login_shortcode'] ?? '')),
        'login_button_text' => sanitize_text_field((string) ($input['login_button_text'] ?? $defaults['login_button_text'])),
        'login_helper_text' => sanitize_text_field((string) ($input['login_helper_text'] ?? '')),
    ];

    return array_merge($existing, $auth);
}

function mst_get_auth_settings_values(): array {
    $opts = get_option('mst_settings', []);
    $opts = is_array($opts) ? $opts : [];

    return wp_parse_args($opts, mst_get_auth_settings_defaults());
}

function mst_render_field_login_display_mode(): void {
    $opts = mst_get_auth_settings_values();
    ?>
    <fieldset>
        <label><input type="radio" name="mst_settings[login_display_mode]" value="url" <?php checked($opts['login_display_mode'], 'url'); ?>> URL</label><br>
        <label><input type="radio" name="mst_settings[login_display_mode]" value="page" <?php checked($opts['login_display_mode'], 'page'); ?>> Page</label><br>
        <label><input type="radio" name="mst_settings[login_display_mode]" value="shortcode" <?php checked($opts['login_display_mode'], 'shortcode'); ?>> Shortcode</label>
    </fieldset>
    <?php
}

function mst_render_field_login_url(): void {
    $opts = mst_get_auth_settings_values();
    echo '<input type="url" class="regular-text" name="mst_settings[login_url]" value="' . esc_attr((string) $opts['login_url']) . '" placeholder="https://example.com/login">';
}

function mst_render_field_login_page_id(): void {
    $opts = mst_get_auth_settings_values();
    wp_dropdown_pages([
        'name' => 'mst_settings[login_page_id]',
        'selected' => absint($opts['login_page_id']),
        'show_option_none' => '— Select —',
        'option_none_value' => '0',
    ]);
}

function mst_render_field_login_shortcode(): void {
    $opts = mst_get_auth_settings_values();
    echo '<textarea class="large-text" rows="4" name="mst_settings[login_shortcode]">' . esc_textarea((string) $opts['login_shortcode']) . '</textarea>';
}

function mst_render_field_login_button_text(): void {
    $opts = mst_get_auth_settings_values();
    echo '<input type="text" class="regular-text" name="mst_settings[login_button_text]" value="' . esc_attr((string) $opts['login_button_text']) . '">';
}

function mst_render_field_login_helper_text(): void {
    $opts = mst_get_auth_settings_values();
    echo '<input type="text" class="regular-text" name="mst_settings[login_helper_text]" value="' . esc_attr((string) $opts['login_helper_text']) . '">';
}

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
        'mst_login_page_id' => 0,

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
        $mst_login_page_id = absint($_POST['mst_login_page_id'] ?? 0);

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
            'mst_login_page_id' => $mst_login_page_id,
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

                <tr>
                    <th scope="row">Login Page (Optional)</th>
                    <td>
                        <input type="number" min="0" name="mst_login_page_id" value="<?php echo esc_attr((string) absint($opts['mst_login_page_id'])); ?>" class="regular-text" placeholder="Page ID">
                        <p class="description">اگر برگه ورود سفارشی دارید، Page ID را وارد کنید تا ورود کاربران همیشه به همان صفحه هدایت شود.</p>
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

        <hr>

        <form method="post" action="options.php">
            <?php
            settings_fields('mst_settings_auth_group');
            do_settings_sections('mst_settings_auth');
            submit_button('ذخیره تنظیمات ورود');
            ?>
        </form>
    </div>
    <?php
}
