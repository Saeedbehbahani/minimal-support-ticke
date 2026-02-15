<?php
if (!defined('ABSPATH')) exit;

class MST_DB {

    const DB_VERSION = '1.0.0';

    public static function tickets_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'mst_tickets';
    }

    public static function messages_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'mst_ticket_messages';
    }

    public static function install(): void {
        self::create_tables();
        self::migrate();
        update_option('mst_db_version', MST_DB::DB_VERSION);
    }

    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $tickets  = self::tickets_table();
        $messages = self::messages_table();

        // Tickets table
        $sql1 = "CREATE TABLE {$tickets} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            admin_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL DEFAULT 'ticket',
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id  (user_id),
            KEY status   (status),
            KEY type     (type),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        // Messages table
        $sql2 = "CREATE TABLE {$messages} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT(20) UNSIGNED NOT NULL,
            sender_role VARCHAR(10) NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            message LONGTEXT NOT NULL,
            attachments LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY sender_role (sender_role),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql1);
        dbDelta($sql2);
    }

    /**
     * Lightweight migration for existing installs
     */
    public static function migrate(): void {
        global $wpdb;
        $tickets = self::tickets_table();

        // Enforce DEFAULT status (dbDelta doesn't reliably update DEFAULT)
        $wpdb->query("ALTER TABLE {$tickets} MODIFY status VARCHAR(30) NOT NULL DEFAULT 'open'");

        // Normalize invalid/empty statuses -> open
        $allowed = ['open','in_review','answered','closed'];
        $wpdb->query("UPDATE {$tickets} SET status='open' WHERE status IS NULL OR status=''");

        $placeholders = implode(',', array_fill(0, count($allowed), '%s'));
        $sql = "UPDATE {$tickets} SET status='open' WHERE status NOT IN ({$placeholders})";
        $wpdb->query($wpdb->prepare($sql, ...$allowed));

        // Fix updated_at if legacy rows have zero
        $wpdb->query("UPDATE {$tickets} SET updated_at = created_at WHERE updated_at='0000-00-00 00:00:00' OR updated_at IS NULL");
    }

    // Helpers
    public static function get_ticket(int $ticket_id): ?array {
        global $wpdb;
        $tickets = self::tickets_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tickets} WHERE id=%d", $ticket_id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_ticket_for_user(int $ticket_id, int $user_id): ?array {
        global $wpdb;
        $tickets = self::tickets_table();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tickets} WHERE id=%d AND user_id=%d", $ticket_id, $user_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_messages(int $ticket_id): array {
        global $wpdb;
        $messages = self::messages_table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$messages} WHERE ticket_id=%d ORDER BY id ASC", $ticket_id),
            ARRAY_A
        ) ?: [];
    }
}
