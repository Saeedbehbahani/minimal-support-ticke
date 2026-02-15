<?php
if (!defined('ABSPATH')) exit;

function mst_admin_render_tickets_list(): void {
    if (!mst_is_ticket_admin()) wp_die('Access denied', 403);

    global $wpdb;
    $tickets  = MST_DB::tickets_table();
    $messages = MST_DB::messages_table();

    $status = isset($_GET['status']) ? mst_sanitize_status((string)$_GET['status']) : '';
    $where  = "WHERE 1=1";
    $args   = [];

    if ($status && in_array($status, mst_allowed_statuses(), true)) {
        $where .= " AND t.status=%s";
        $args[] = $status;
    }

    // Pull tickets + message count
    $sql = "
        SELECT 
            t.id, t.user_id, t.subject, t.status, t.updated_at, t.created_at,
            (SELECT COUNT(*) FROM {$messages} m WHERE m.ticket_id = t.id) AS msg_count
        FROM {$tickets} t
        {$where}
        ORDER BY t.updated_at DESC
        LIMIT 200
    ";

    $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

    ?>
    <div class="wrap">
        <h1>تیکت‌ها</h1>

        <form method="get" style="margin: 12px 0;">
            <input type="hidden" name="page" value="mst_tickets">
            <select name="status">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach (mst_allowed_statuses() as $st): ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($status, $st); ?>>
                        <?php echo esc_html(mst_status_label($st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button">فیلتر</button>
        </form>

        <?php if (empty($rows)): ?>
            <p>هیچ تیکتی یافت نشد.</p>
        <?php else: ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:80px">ID</th>
                        <th>موضوع</th>
                        <th style="width:120px">وضعیت</th>
                        <th style="width:120px">پیام‌ها</th>
                        <th style="width:180px">آخرین آپدیت</th>
                        <th style="width:120px">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $view_url = admin_url('admin.php?page=mst_ticket_view&ticket_id=' . (int)$r['id']);
                    $user = get_user_by('id', (int)$r['user_id']);
                    $user_label = $user ? ($user->display_name . ' (#' . (int)$r['user_id'] . ')') : ('#' . (int)$r['user_id']);
                    ?>
                    <tr>
                        <td><?php echo esc_html($r['id']); ?></td>
                        <td>
                            <strong><?php echo esc_html($r['subject']); ?></strong>
                            <div style="opacity:.8;font-size:12px;margin-top:4px;">
                                کاربر: <?php echo esc_html($user_label); ?>
                            </div>
                        </td>
                        <td>
                            <span class="mst-badge mst-<?php echo esc_attr($r['status']); ?>">
                                <?php echo esc_html(mst_status_label($r['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($r['msg_count']); ?></td>
                        <td><?php echo esc_html($r['updated_at']); ?></td>
                        <td>
                            <a class="button button-primary" href="<?php echo esc_url($view_url); ?>">مشاهده</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:10px;opacity:.75">نمایش حداکثر ۲۰۰ مورد آخر.</p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Register hidden admin page for ticket view
 * Called from admin_menu if ticket-view.php exists
 */
add_action('admin_menu', function () {
    if (!mst_is_ticket_admin()) return;

    add_submenu_page(
        null, // hidden
        'مشاهده تیکت',
        'مشاهده تیکت',
        'manage_mst_tickets',
        'mst_ticket_view',
        function () {
            if (function_exists('mst_admin_render_ticket_view')) {
                mst_admin_render_ticket_view();
                return;
            }
            echo '<div class="wrap"><h2>مشاهده تیکت</h2><p style="color:#b00">ticket-view.php لود نشده.</p></div>';
        }
    );
});
