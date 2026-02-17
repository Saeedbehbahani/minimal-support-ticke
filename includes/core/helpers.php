<?php
if (!defined('ABSPATH')) exit;

/**
 * Check if current page content has any of the given shortcodes
 */
function mst_page_has_shortcodes(array $shortcodes): bool {
    if (is_admin()) return false;

    global $post;
    if (!($post instanceof WP_Post)) return false;

    $content = (string) $post->post_content;
    if ($content === '') return false;

    foreach ($shortcodes as $sc) {
        if (has_shortcode($content, $sc)) return true;
    }
    return false;
}

/**
 * Resolve all runtime-registered shortcodes that belong to MST.
 *
 * Preferred ownership check: callback source file inside MST_PATH.
 * Fallback ownership check: shortcode tag prefix "mst_".
 */
function mst_get_plugin_shortcodes(): array {
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    global $shortcode_tags;
    $cache = [];

    if (!is_array($shortcode_tags) || empty($shortcode_tags)) {
        return $cache;
    }

    $plugin_base = wp_normalize_path((string) realpath(MST_PATH));

    foreach ($shortcode_tags as $tag => $callback) {
        if (!is_string($tag) || $tag === '') {
            continue;
        }

        $belongs_to_plugin = false;

        try {
            $ref = null;

            if (is_string($callback) && function_exists($callback)) {
                $ref = new ReflectionFunction($callback);
            } elseif ($callback instanceof Closure) {
                $ref = new ReflectionFunction($callback);
            } elseif (is_array($callback) && count($callback) === 2) {
                $target = $callback[0];
                $method = $callback[1];

                if ((is_object($target) || is_string($target)) && is_string($method) && method_exists($target, $method)) {
                    $ref = new ReflectionMethod($target, $method);
                }
            }

            if ($ref) {
                $file = $ref->getFileName();
                if (is_string($file) && $file !== '') {
                    $file_path = wp_normalize_path((string) realpath($file));
                    if ($plugin_base && $file_path && strpos($file_path, $plugin_base) === 0) {
                        $belongs_to_plugin = true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore reflection errors and allow fallback ownership check.
        }

        if (!$belongs_to_plugin && strpos($tag, 'mst_') === 0) {
            $belongs_to_plugin = true;
        }

        if ($belongs_to_plugin) {
            $cache[] = $tag;
        }
    }

    $cache = array_values(array_unique($cache));
    return $cache;
}

/**
 * Detect whether current singular content contains any MST shortcode.
 */
function mst_current_post_has_plugin_shortcode(): bool {
    if (is_admin() || !is_singular()) return false;

    $post = get_post();
    if (!($post instanceof WP_Post)) return false;

    $content = (string) $post->post_content;
    if ($content === '') return false;

    $shortcodes = mst_get_plugin_shortcodes();
    if (empty($shortcodes)) return false;

    $regex = get_shortcode_regex($shortcodes);
    return (bool) preg_match('/' . $regex . '/s', $content);
}

/**
 * Add fullscreen class when current content has MST shortcodes.
 */
function mst_filter_body_class_for_shortcode_pages(array $classes): array {
    if (mst_current_post_has_plugin_shortcode()) {
        $classes[] = 'mst-fullscreen';
    }
    return array_values(array_unique($classes));
}

/**
 * Hide theme/footer containers only on shortcode pages.
 */
function mst_print_fullscreen_footer_css(): void {
    if (!mst_current_post_has_plugin_shortcode()) {
        return;
    }

    echo '<style id="mst-fullscreen-footer-hide">';
    echo 'body.mst-fullscreen footer,';
    echo 'body.mst-fullscreen .site-footer,';
    echo 'body.mst-fullscreen #colophon,';
    echo 'body.mst-fullscreen .footer,';
    echo 'body.mst-fullscreen .elementor-location-footer{display:none!important;}';
    echo 'body.mst-fullscreen{margin-bottom:0!important;padding-bottom:0!important;min-height:100vh;}';
    echo 'body.mst-fullscreen #page,body.mst-fullscreen .site,body.mst-fullscreen .site-content{margin-bottom:0!important;padding-bottom:0!important;min-height:100vh;}';
    echo '</style>';
}

/**
 * Current user id helper
 */
function mst_user_id(): int {
    return (int) get_current_user_id();
}

/**
 * Is ticket admin (administrator with manage_mst_tickets cap)
 */
function mst_is_ticket_admin(): bool {
    return current_user_can('manage_mst_tickets');
}

/**
 * Check if current user can act as ticket admin/agent for replies.
 */
function mst_is_ticket_agent_user(?WP_User $user = null): bool {
    if (!$user) {
        $user = wp_get_current_user();
    }

    if (!($user instanceof WP_User) || 0 === (int) $user->ID) {
        return false;
    }

    $roles = is_array($user->roles) ? $user->roles : [];

    if (current_user_can('manage_options')) {
        return true;
    }

    if (in_array('administrator', $roles, true)) {
        return true;
    }

    if (current_user_can('manage_mst_tickets') || current_user_can('mst_manage_tickets')) {
        return true;
    }

    return false;
}

/**
 * Safe redirect back
 */
function mst_redirect_back(string $fallback = ''): void {
    $url = wp_get_referer();
    if (!$url) $url = $fallback ?: home_url('/');
    wp_safe_redirect($url);
    exit;
}

/**
 * Allowed statuses (single source of truth)
 */
function mst_allowed_statuses(): array {
    return ['open', 'in_review', 'answered', 'closed'];
}

/**
 * Human label for status (FA)
 */
function mst_status_label(string $status): string {
    $map = [
        'open'      => 'باز',
        'in_review' => 'در بررسی',
        'answered'  => 'پاسخ داده شد',
        'closed'    => 'بسته',
    ];
    return $map[$status] ?? $status;
}

/**
 * Sanitize status value
 */
function mst_sanitize_status(string $status): string {
    $status = strtolower(trim($status));
    return in_array($status, mst_allowed_statuses(), true) ? $status : 'open';
}

/**
 * Admin-post URL helper
 */
function mst_post_url(string $action): string {
    return esc_url(admin_url('admin-post.php?action=' . rawurlencode($action)));
}

/**
 * Read request var (POST/GET) safely
 */
function mst_req(string $key, $default = '') {
    if (isset($_POST[$key])) return wp_unslash($_POST[$key]);
    if (isset($_GET[$key]))  return wp_unslash($_GET[$key]);
    return $default;
}

/**
 * Read plugin page map option
 */
function mst_get_pages_option(): array {
    $pages = get_option('mst_pages', []);
    return is_array($pages) ? $pages : [];
}

/**
 * Get plugin page id from mst_pages option
 */
function mst_get_page_id(string $key): int {
    $pages = mst_get_pages_option();
    return isset($pages[$key]) ? absint($pages[$key]) : 0;
}

/**
 * Resolve plugin page URL by stored id, then by optional slug fallback
 */
function mst_get_page_url(string $key, string $fallback_slug = ''): string {
    $page_id = mst_get_page_id($key);
    if ($page_id > 0) {
        $url = get_permalink($page_id);
        if ($url) return $url;
    }

    if ($fallback_slug !== '') {
        $page = get_page_by_path($fallback_slug, OBJECT, 'page');
        if ($page instanceof WP_Post) {
            $url = get_permalink($page->ID);
            if ($url) return $url;
        }
    }

    return home_url('/');
}

/**
 * Resolve "new ticket" URL
 */
function mst_get_new_ticket_url(): string {
    return mst_get_page_url('mst_support_new_page_id', 'new-ticket');
}

/**
 * Current URL helper (front-end safe)
 */
function mst_current_url(): string {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $request_uri = '/' . ltrim($request_uri, '/');

    return home_url($request_uri);
}

/**
 * Append redirect_to param when needed.
 */
function mst_append_redirect_to_url(string $url, string $redirect_url): string {
    if ($url === '') {
        return $url;
    }

    if ($redirect_url === '') {
        return $url;
    }

    $existing_redirect = wp_parse_url($url, PHP_URL_QUERY);
    if (is_string($existing_redirect) && strpos($existing_redirect, 'redirect_to=') !== false) {
        return $url;
    }

    return add_query_arg('redirect_to', $redirect_url, $url);
}

/**
 * Authentication/login settings used by frontend CTA.
 */
function mst_get_login_cta_settings(): array {
    $opts = get_option('mst_settings', []);
    $opts = is_array($opts) ? $opts : [];

    $mode = sanitize_key((string) ($opts['login_display_mode'] ?? 'url'));
    if (!in_array($mode, ['url', 'page', 'shortcode'], true)) {
        $mode = 'url';
    }

    return [
        'login_display_mode' => $mode,
        'login_url' => esc_url_raw((string) ($opts['login_url'] ?? '')),
        'login_page_id' => absint($opts['login_page_id'] ?? 0),
        'login_shortcode' => wp_kses_post((string) ($opts['login_shortcode'] ?? '')),
        'login_button_text' => sanitize_text_field((string) ($opts['login_button_text'] ?? 'ورود به سایت')),
        'login_helper_text' => sanitize_text_field((string) ($opts['login_helper_text'] ?? '')),
    ];
}

/**
 * Render login CTA for guest users.
 */
function mst_render_login_cta(): string {
    if (is_user_logged_in()) {
        return '';
    }

    $settings = mst_get_login_cta_settings();
    $redirect_url = mst_current_url();
    $target_url = '';
    $button_text = $settings['login_button_text'] !== '' ? $settings['login_button_text'] : 'ورود به سایت';
    $button_text = (string) apply_filters('mst_login_button_label', $button_text);

    if ($settings['login_display_mode'] === 'shortcode' && $settings['login_shortcode'] !== '') {
        $shortcode_html = do_shortcode($settings['login_shortcode']);
        $helper_html = $settings['login_helper_text'] !== ''
            ? '<p class="mst-login-cta__helper">' . esc_html($settings['login_helper_text']) . '</p>'
            : '';

        $html = '<div class="mst-login-cta mst-login-cta--shortcode">' . $helper_html . $shortcode_html . '</div>';
        return (string) apply_filters('mst_login_cta_html', $html, $settings);
    }

    if ($settings['login_display_mode'] === 'page' && $settings['login_page_id'] > 0) {
        $permalink = get_permalink($settings['login_page_id']);
        if (is_string($permalink) && $permalink !== '') {
            $target_url = mst_append_redirect_to_url($permalink, $redirect_url);
        }
    }

    if ($target_url === '' && $settings['login_display_mode'] === 'url' && $settings['login_url'] !== '') {
        $target_url = mst_append_redirect_to_url($settings['login_url'], $redirect_url);
    }

    if ($target_url === '') {
        $target_url = wp_login_url($redirect_url);
    }

    $helper_html = $settings['login_helper_text'] !== ''
        ? '<p class="mst-login-cta__helper">' . esc_html($settings['login_helper_text']) . '</p>'
        : '';

    $html = sprintf(
        '<div class="mst-login-cta">%1$s<a class="mst-login-gate__btn" href="%2$s">%3$s</a></div>',
        $helper_html,
        esc_url($target_url),
        esc_html($button_text)
    );

    return (string) apply_filters('mst_login_cta_html', $html, $settings);
}

/**
 * Resolve login URL for all supported site configurations.
 */
function mst_get_login_url(string $redirect_url): string {
    $settings = mst_get_login_cta_settings();
    $mode = $settings['login_display_mode'];
    $redirect_url = $redirect_url !== '' ? wp_validate_redirect($redirect_url, home_url('/')) : home_url('/');
    $url = '';

    if ($mode === 'page' && $settings['login_page_id'] > 0) {
        $page_url = get_permalink($settings['login_page_id']);
        if (is_string($page_url) && $page_url !== '') {
            $url = $page_url;
        }
    }

    if ($url === '' && $mode === 'url' && $settings['login_url'] !== '') {
        $url = $settings['login_url'];
    }

    if (function_exists('wc_get_page_permalink') && function_exists('wc_get_page_id')) {
        $myaccount_id = (int) wc_get_page_id('myaccount');
        if ($myaccount_id > 0) {
            $wc_url = wc_get_page_permalink('myaccount');
            if (is_string($wc_url) && $wc_url !== '') {
                $url = $wc_url;
            }
        }
    }

    if ($url === '') {
        $opts = get_option('mst_settings', []);
        $opts = is_array($opts) ? $opts : [];
        $page_id = isset($opts['mst_login_page_id']) ? absint($opts['mst_login_page_id']) : 0;

        if ($page_id > 0) {
            $custom_url = get_permalink($page_id);
            if (is_string($custom_url) && $custom_url !== '') {
                $url = $custom_url;
            }
        }
    }

    if ($url === '') {
        $url = wp_login_url($redirect_url);
    } else {
        $url = mst_append_redirect_to_url($url, $redirect_url);
    }

    $url = apply_filters('mst_login_url', $url, $redirect_url);

    return is_string($url) && $url !== '' ? $url : wp_login_url($redirect_url);
}

/**
 * Detect support list page by shortcode presence on singular pages.
 */
function mst_is_support_list_page(): bool {
    if (!is_singular()) return false;

    $post = get_post();
    if (!($post instanceof WP_Post)) return false;

    return has_shortcode((string) $post->post_content, 'mst_support_list');
}
