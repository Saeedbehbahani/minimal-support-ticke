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

        <?php
        $deleted_count = isset($_GET['mst_deleted']) ? absint($_GET['mst_deleted']) : 0;
        $delete_error  = isset($_GET['mst_delete_error']) ? sanitize_text_field((string) $_GET['mst_delete_error']) : '';

        if ($deleted_count > 0):
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html(sprintf('%d تیکت با موفقیت حذف شد.', $deleted_count)); ?></p>
            </div>
            <?php
        endif;

        if ($delete_error !== ''):
            $error_message = 'عملیات حذف انجام نشد.';
            if ($delete_error === 'permission') {
                $error_message = 'شما دسترسی لازم برای حذف تیکت را ندارید.';
            } elseif ($delete_error === 'nonce') {
                $error_message = 'درخواست نامعتبر است. دوباره تلاش کنید.';
            }
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
        <?php endif; ?>

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
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mst_bulk_delete_tickets">
                <input type="hidden" name="page" value="mst_tickets">
                <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
                <?php wp_nonce_field('mst_bulk_delete_tickets', '_mst_bulk_delete_nonce'); ?>

                <div class="tablenav top" style="margin: 6px 0;">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">انتخاب عملیات دسته‌جمعی</label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="">کارهای دسته‌جمعی</option>
                            <option value="delete">حذف</option>
                        </select>
                        <button class="button action" type="submit" onclick="return confirm('از حذف تیکت‌های انتخاب‌شده مطمئن هستید؟');">اعمال</button>
                    </div>
                </div>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column check-column"><input type="checkbox" id="mst-select-all"></td>
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
                        $ticket_id = absint($r['id']);
                        $view_url = admin_url('admin.php?page=mst_ticket_view&ticket_id=' . $ticket_id);
                        $delete_url = wp_nonce_url(
                            admin_url('admin-post.php?action=mst_delete_ticket&ticket_id=' . $ticket_id . '&page=mst_tickets&status=' . rawurlencode($status)),
                            'mst_delete_ticket_' . $ticket_id,
                            '_mst_delete_nonce'
                        );
                        $user = get_user_by('id', (int)$r['user_id']);
                        $user_label = $user ? ($user->display_name . ' (#' . (int)$r['user_id'] . ')') : ('#' . (int)$r['user_id']);
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="ticket_ids[]" value="<?php echo esc_attr($ticket_id); ?>">
                            </th>
                            <td><?php echo esc_html($ticket_id); ?></td>
                            <td>
                                <strong><?php echo esc_html($r['subject']); ?></strong>
                                <div style="opacity:.8;font-size:12px;margin-top:4px;">
                                    کاربر: <?php echo esc_html($user_label); ?>
                                </div>
                                <div class="row-actions">
                                    <span class="view"><a href="<?php echo esc_url($view_url); ?>">مشاهده</a></span> |
                                    <span class="trash"><a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('از حذف این تیکت مطمئن هستید؟');">حذف</a></span>
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
            </form>

            <script>
                (function () {
                    const selectAll = document.getElementById('mst-select-all');
                    if (!selectAll) return;

                    selectAll.addEventListener('change', function () {
                        const checkboxes = document.querySelectorAll('input[name="ticket_ids[]"]');
                        checkboxes.forEach(function (checkbox) {
                            checkbox.checked = !!selectAll.checked;
                        });
                    });
                })();
            </script>

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

function mst_admin_tickets_list_redirect_url(array $args = []): string {
    $base_args = [
        'page' => 'mst_tickets',
    ];

    $status = isset($_REQUEST['status']) ? mst_sanitize_status((string) wp_unslash($_REQUEST['status'])) : '';
    if ($status !== '') {
        $base_args['status'] = $status;
    }

    return add_query_arg(array_merge($base_args, $args), admin_url('admin.php'));
}

add_action('admin_post_mst_delete_ticket', function (): void {
    if (!mst_is_ticket_admin()) {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'permission']));
        exit;
    }

    $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
    if ($ticket_id <= 0) {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'invalid']));
        exit;
    }

    check_admin_referer('mst_delete_ticket_' . $ticket_id, '_mst_delete_nonce');

    $deleted = MST_DB::delete_ticket($ticket_id);

    wp_safe_redirect(mst_admin_tickets_list_redirect_url($deleted ? ['mst_deleted' => 1] : ['mst_delete_error' => 'none']));
    exit;
});

add_action('admin_post_mst_bulk_delete_tickets', function (): void {
    if (!mst_is_ticket_admin()) {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'permission']));
        exit;
    }

    check_admin_referer('mst_bulk_delete_tickets', '_mst_bulk_delete_nonce');

    $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field((string) wp_unslash($_POST['bulk_action'])) : '';
    if ($bulk_action !== 'delete') {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'invalid']));
        exit;
    }

    $raw_ticket_ids = isset($_POST['ticket_ids']) ? (array) $_POST['ticket_ids'] : [];
    $ticket_ids = array_values(array_unique(array_filter(array_map('absint', $raw_ticket_ids))));

    if (empty($ticket_ids)) {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'none']));
        exit;
    }

    $deleted_count = 0;
    foreach ($ticket_ids as $ticket_id) {
        if (MST_DB::delete_ticket($ticket_id)) {
            $deleted_count++;
        }
    }

    if ($deleted_count > 0) {
        wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_deleted' => $deleted_count]));
        exit;
    }

    wp_safe_redirect(mst_admin_tickets_list_redirect_url(['mst_delete_error' => 'none']));
    exit;
});
