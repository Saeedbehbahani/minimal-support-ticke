<?php
if (!defined('ABSPATH')) exit;

function mst_admin_render_ticket_view(): void {
    if (!mst_is_ticket_admin()) wp_die('Access denied', 403);

    global $wpdb;
    $tickets  = MST_DB::tickets_table();
    $messages = MST_DB::messages_table();

    $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
    if (!$ticket_id) {
        echo '<div class="wrap"><h2>مشاهده تیکت</h2><p>ticket_id نامعتبر است.</p></div>';
        return;
    }

    $ticket = MST_DB::get_ticket($ticket_id);
    if (!$ticket) {
        echo '<div class="wrap"><h2>مشاهده تیکت</h2><p>تیکت یافت نشد.</p></div>';
        return;
    }

    // Handle admin reply
    if (!empty($_POST['mst_admin_reply_submit'])) {
        check_admin_referer('mst_admin_reply', '_mst_nonce');

        $msg = sanitize_textarea_field(mst_req('message'));
        if ($msg !== '') {

            $now = current_time('mysql');

            $attachments = mst_handle_attachments_upload();
            $attachments_json = mst_attachments_to_json($attachments);

            $wpdb->insert($messages, [
                'ticket_id'   => $ticket_id,
                'sender_role' => 'admin',
                'sender_id'   => get_current_user_id(),
                'message'     => $msg,
                'attachments' => $attachments_json,
                'created_at'  => $now,
            ]);

            // update ticket status to answered by default when admin replies
            $wpdb->update($tickets, [
                'admin_id'    => get_current_user_id(),
                'status'      => 'answered',
                'updated_at'  => $now,
            ], ['id' => $ticket_id]);

            if (function_exists('mst_notify_admin_replied')) {
                mst_notify_admin_replied($ticket_id);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=mst_ticket_view&ticket_id=' . $ticket_id));
        exit;
    }

    // Handle status update
    if (!empty($_POST['mst_admin_status_submit'])) {
        check_admin_referer('mst_admin_status', '_mst_nonce');

        $new_status = mst_sanitize_status((string) mst_req('status'));
        if (!in_array($new_status, mst_allowed_statuses(), true)) {
            $new_status = 'open';
        }

        $now = current_time('mysql');

        $wpdb->update($tickets, [
            'status'     => $new_status,
            'updated_at' => $now,
        ], ['id' => $ticket_id]);

        wp_safe_redirect(admin_url('admin.php?page=mst_ticket_view&ticket_id=' . $ticket_id));
        exit;
    }

    $messages_rows = MST_DB::get_messages($ticket_id);

    $user = get_user_by('id', (int)$ticket['user_id']);
    $user_label = $user ? ($user->display_name . ' (#' . (int)$ticket['user_id'] . ')') : ('#' . (int)$ticket['user_id']);

    ?>
    <div class="wrap">
        <h1>تیکت #<?php echo esc_html($ticket_id); ?></h1>

        <div style="margin:10px 0; padding:12px; background:#fff; border:1px solid #e5e5e5; border-radius:8px;">
            <div><strong>کاربر:</strong> <?php echo esc_html($user_label); ?></div>
            <div style="margin-top:6px;"><strong>موضوع:</strong> <?php echo esc_html($ticket['subject']); ?></div>
            <div style="margin-top:6px;">
                <strong>وضعیت:</strong>
                <span class="mst-badge mst-<?php echo esc_attr($ticket['status']); ?>">
                    <?php echo esc_html(mst_status_label($ticket['status'])); ?>
                </span>
            </div>
            <div style="margin-top:6px;opacity:.8;font-size:12px;">
                ایجاد: <?php echo esc_html($ticket['created_at']); ?> |
                بروزرسانی: <?php echo esc_html($ticket['updated_at']); ?>
            </div>
        </div>

        <h2 style="margin-top:20px;">گفتگو</h2>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px;">
            <?php if (empty($messages_rows)): ?>
                <p>پیامی وجود ندارد.</p>
            <?php else: ?>
                <?php foreach ($messages_rows as $m): ?>
                    <div style="margin: 0 0 14px 0; padding: 12px; border-radius: 10px; border:1px solid #eee; <?php echo ($m['sender_role']==='admin') ? 'background:#f7fbff;' : 'background:#fafafa;'; ?>">
                        <div style="font-size:12px;opacity:.75;margin-bottom:6px;">
                            <strong><?php echo esc_html($m['sender_role']); ?></strong>
                            | <?php echo esc_html($m['created_at']); ?>
                        </div>
                        <div style="white-space:pre-wrap;"><?php echo esc_html($m['message']); ?></div>
                        <?php echo mst_render_attachments($m['attachments'] ?? null); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h2 style="margin-top:22px;">تغییر وضعیت</h2>
        <form method="post" style="background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px;max-width:520px;">
            <?php wp_nonce_field('mst_admin_status', '_mst_nonce'); ?>
            <select name="status">
                <?php foreach (mst_allowed_statuses() as $st): ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($ticket['status'], $st); ?>>
                        <?php echo esc_html(mst_status_label($st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary" name="mst_admin_status_submit" value="1">ذخیره وضعیت</button>
        </form>

        <h2 style="margin-top:22px;">پاسخ ادمین</h2>

        <?php if ($ticket['status'] === 'closed'): ?>
            <p style="color:#b00">این تیکت بسته است. اگر می‌خواهی پاسخ بدهی، اول وضعیت را باز کن.</p>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" style="background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px;max-width:720px;">
                <?php wp_nonce_field('mst_admin_reply', '_mst_nonce'); ?>

                <textarea name="message" rows="5" style="width:100%;" required></textarea>

                <div style="margin-top:10px;">
                    <label><strong>ضمیمه</strong> (حداکثر ۳ فایل)</label><br>
                    <input type="file" name="attachments[]" multiple>
                </div>

                <button class="button button-primary" name="mst_admin_reply_submit" value="1" style="margin-top:12px;">
                    ارسال پاسخ
                </button>
            </form>
        <?php endif; ?>

        <p style="margin-top:16px;">
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mst_tickets')); ?>">بازگشت به لیست</a>
        </p>
    </div>
    <?php
}
