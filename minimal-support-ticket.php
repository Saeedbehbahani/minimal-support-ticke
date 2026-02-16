<?php
/*
Plugin Name: Minimal Support Ticket
Description: Minimal RTL support ticket system with configurable SMS pattern notifications.
Version: 1.0.0
Author: Bilioneri
Text Domain: minimal-support-ticket
*/

if (!defined('ABSPATH')) exit;

define('MST_VERSION', '1.0.0');
define('MST_SLUG', 'minimal-support-ticket');
define('MST_PATH', plugin_dir_path(__FILE__));
define('MST_URL', plugin_dir_url(__FILE__));

final class MST_Plugin {

    /** @var MST_Plugin|null */
    private static $instance = null;

    /**
     * Singleton instance
     * @return MST_Plugin
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    /**
     * Load required files
     */
    private function includes() {
        // Core
        $this->safe_require('includes/core/helpers.php');
        $this->safe_require('includes/core/db.php');
        $this->safe_require('includes/core/capabilities.php');
        $this->safe_require('includes/core/attachments.php');

        // ✅ IMPORTANT: load SMS classes BEFORE notifications-sms.php
        $this->safe_require('includes/core/sms-adapter-pattern.php');
        $this->safe_require('includes/core/sms-provider.php');
        $this->safe_require('includes/core/notifications-sms.php');

        $this->safe_require('includes/core/frontend-shortcodes.php');
        $this->safe_require('includes/core/frontend-handlers.php');

        // Admin (only in wp-admin)
        if (is_admin()) {
            $this->safe_require('includes/admin/menu.php');
            $this->safe_require('includes/admin/tickets-list.php');
            $this->safe_require('includes/admin/ticket-view.php');
            $this->safe_require('includes/admin/settings.php');
            $this->safe_require('includes/admin/actions-test-sms.php');
        }
    }

    /**
     * Safe require_once by relative path
     * @param string $rel
     * @return void
     */
    private function safe_require($rel) {
        $path = MST_PATH . ltrim((string)$rel, '/');
        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Activation hook
     */
    public static function activate() {
        // Capabilities
        if (function_exists('mst_add_caps')) {
            mst_add_caps();
        } else {
            // fallback: add cap to administrator anyway
            $role = get_role('administrator');
            if ($role && !$role->has_cap('manage_mst_tickets')) {
                $role->add_cap('manage_mst_tickets');
            }
        }

        // DB install/migrate
        if (class_exists('MST_DB')) {
            if (method_exists('MST_DB', 'install')) {
                MST_DB::install();
            } elseif (method_exists('MST_DB', 'create_tables')) {
                MST_DB::create_tables();
            }
        }
    }

    /**
     * Boot plugin hooks
     */
    public function boot() {
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        // Register shortcodes + handlers
        if (function_exists('mst_register_shortcodes')) {
            add_action('init', 'mst_register_shortcodes');
        }
        if (function_exists('mst_register_front_handlers')) {
            add_action('init', 'mst_register_front_handlers');
        }

        // Admin menu
        if (is_admin() && function_exists('mst_admin_register_menu')) {
            add_action('admin_menu', 'mst_admin_register_menu');
        }

        // Keep DB migrated on version changes (safe)
        add_action('admin_init', function () {
            $stored = get_option('mst_db_version');

            if (!$stored || version_compare((string)$stored, MST_VERSION, '<')) {
                if (class_exists('MST_DB') && method_exists('MST_DB', 'install')) {
                    MST_DB::install();
                }
                update_option('mst_db_version', MST_VERSION);
            }
        });
    }

    /**
     * Front assets (only when shortcodes exist on current page)
     */
    public function enqueue_front() {
        // فقط وقتی شورتکدهای تیکت روی صفحه هست، CSS/JS لود بشه
        $frontend_shortcodes = ['mst_ticket_form', 'mst_ticket_list', 'mst_ticket_view', 'mst_ticket_chat', 'mst_support', 'mst_support_list', 'mst_support_new'];

        if (!function_exists('mst_page_has_shortcodes') || !mst_page_has_shortcodes($frontend_shortcodes)) {
            return;
        }

        wp_enqueue_style('mst-frontend', MST_URL . 'assets/css/mst-frontend.css', [], MST_VERSION);

        // اگر بعداً JS لازم شد:
        // wp_enqueue_script('mst-frontend', MST_URL . 'assets/js/frontend.js', ['jquery'], MST_VERSION, true);
    }

    /**
     * Admin assets (only on plugin pages)
     * @param string $hook
     */
    public function enqueue_admin($hook) {
        // فقط صفحات خود افزونه
        if (strpos((string)$hook, 'mst_') === false) return;

        wp_enqueue_style('mst-admin', MST_URL . 'assets/css/admin.css', [], MST_VERSION);
    }
}

MST_Plugin::instance();

