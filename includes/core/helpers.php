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
