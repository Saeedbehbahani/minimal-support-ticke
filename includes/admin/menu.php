<?php
if (!defined('ABSPATH')) exit;

function mst_admin_register_menu(): void {

    $cap = 'manage_mst_tickets';
    if (!current_user_can($cap)) $cap = 'manage_options';

    add_menu_page(
        'سیستم تیکت',
        'تیکت‌ها',
        $cap,
        'mst_tickets',
        'mst_admin_router_tickets',
        'dashicons-sos',
        56
    );

    add_submenu_page(
        'mst_tickets',
        'لیست تیکت‌ها',
        'لیست تیکت‌ها',
        $cap,
        'mst_tickets',
        'mst_admin_router_tickets'
    );

    add_submenu_page(
        'mst_tickets',
        'تنظیمات',
        'تنظیمات',
        $cap,
        'mst_settings',
        'mst_admin_router_settings'
    );

    add_submenu_page(
        'mst_tickets',
        'تست پیامک',
        'تست پیامک',
        $cap,
        'mst_test_sms',
        'mst_admin_router_test_sms'
    );
}

function mst_admin_router_tickets(): void {
    if (!mst_is_ticket_admin()) {
        wp_die('Access denied', 403);
    }

    if (function_exists('mst_admin_render_tickets_list')) {
        mst_admin_render_tickets_list();
        return;
    }

    echo '<div class="wrap"><h2>تیکت‌ها</h2><p style="color:#b00">tickets-list.php لود نشده یا تابع تعریف نشده.</p></div>';
}

function mst_admin_router_settings(): void {
    if (!mst_is_ticket_admin()) {
        wp_die('Access denied', 403);
    }

    if (function_exists('mst_admin_render_settings')) {
        mst_admin_render_settings();
        return;
    }

    echo '<div class="wrap"><h2>تنظیمات</h2><p style="color:#b00">settings.php لود نشده یا تابع تعریف نشده.</p></div>';
}

function mst_admin_router_test_sms(): void {
    if (!mst_is_ticket_admin()) {
        wp_die('Access denied', 403);
    }

    if (function_exists('mst_admin_render_test_sms_page')) {
        mst_admin_render_test_sms_page();
        return;
    }

    echo '<div class="wrap"><h2>تست پیامک</h2><p style="color:#b00">actions-test-sms.php لود نشده یا تابع تعریف نشده.</p></div>';
}
