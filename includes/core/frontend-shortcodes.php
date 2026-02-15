<?php
if (!defined('ABSPATH')) exit;

/**
 * Register shortcodes
 */
function mst_register_shortcodes(): void {

    add_shortcode('mst_ticket_form', 'mst_sc_ticket_form');
    add_shortcode('mst_ticket_list', 'mst_sc_ticket_list');
    add_shortcode('mst_ticket_view', 'mst_sc_ticket_view');

    // SaaS chat wrapper (topbar + ticket view)
    add_shortcode('mst_ticket_chat', 'mst_sc_ticket_chat');

    // Backward compatibility
    add_shortcode('mst_support', function () {
        return mst_sc_ticket_form() . mst_sc_ticket_list();
    });
}

/**
 * TICKET FORM
 */
function mst_sc_ticket_form(): string {

    if (!is_user_logged_in()) {
        return '<div class="mst-alert">برای ارسال تیکت باید وارد حساب کاربری شوید.</div>';
    }

    ob_start(); ?>

    <div class="mst-box">
        <h3 class="mst-title">ثبت تیکت جدید</h3>

        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="mst_create_ticket">
            <?php wp_nonce_field('mst_create_ticket', '_mst_nonce'); ?>

            <label class="mst-label">موضوع</label>
            <input type="text" name="subject" class="mst-input" required maxlength="255">

            <label class="mst-label">پیام</label>
            <textarea name="message" class="mst-textarea" required rows="5"></textarea>

            <label class="mst-label">ضمیمه (اختیاری - حداکثر ۳ فایل)</label>
            <input type="file" name="attachments[]" multiple>

            <button type="submit" class="mst-btn">ارسال تیکت</button>
        </form>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * SaaS CHAT WRAPPER
 * usage: [mst_ticket_chat]
 * back is hard-wired to page_id=11 for your site
 */
function mst_sc_ticket_chat($atts = []): string {

    $atts = shortcode_atts([
        'title' => 'گفتگو',
    ], $atts);

    // ✅ HARD FIX: Back goes to your tickets page: ?page_id=11
    $back_page_id = 11;
    $back_url = get_permalink($back_page_id);
    if (!$back_url) {
        $back_url = home_url('/?page_id=11');
    }

    $ticket_id = absint(mst_req('ticket_id'));

    if (!$ticket_id) {
        return '
        <div class="mst-app">
          <div class="mst-shell">
            <div class="mst-topbar">
              <a class="mst-back" href="'.esc_url($back_url).'">← بازگشت به تیکت‌ها</a>
              <div class="mst-topbar-title">'.esc_html($atts['title']).'</div>
            </div>
            <div class="mst-box">
              <div class="mst-alert">تیکتی انتخاب نشده.</div>
            </div>
          </div>
        </div>';
    }

    $chat = '<div class="mst-ticket-view">' . mst_sc_ticket_view() . '</div>';

    return '
    <div class="mst-app">
      <div class="mst-shell">
        <div class="mst-topbar">
          <a class="mst-back" href="'.esc_url($back_url).'">← بازگشت به تیکت‌ها</a>
          <div class="mst-topbar-title">'.esc_html($atts['title']).'</div>
        </div>
        '.$chat.'
      </div>
    </div>';
}

/**
 * TICKET LIST (User only) — SaaS Cards
 */
function mst_sc_ticket_list(): string {

    if (!is_user_logged_in()) return '';

    global $wpdb;
    $table   = MST_DB::tickets_table();
    $user_id = mst_user_id();

    $tickets = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, subject, status, updated_at
             FROM {$table}
             WHERE user_id=%d
             ORDER BY updated_at DESC",
            $user_id
        ),
        ARRAY_A
    );

    // ✅ Chat page (by slug). If not found => fallback to page_id=25 (adjust if needed)
    $chat_page = get_page_by_path('ticket-chat');
    if ($chat_page) {
        $chat_url = get_permalink($chat_page->ID);
    } else {
        // fallback: if your ticket-chat is actually page_id=25, this works with your current structure
        $chat_url = home_url('/?page_id=25');
    }

    ob_start(); ?>

    <div class="mst-box">
        <h3 class="mst-title">تیکت‌های من</h3>

        <?php if (empty($tickets)): ?>
            <div class="mst-empty">هنوز تیکتی ثبت نکردی.</div>
        <?php else: ?>

            <div class="mst-cards">
                <?php foreach ($tickets as $t): ?>
                    <?php $ticket_url = add_query_arg('ticket_id', (int)$t['id'], $chat_url); ?>

                    <div class="mst-card">
                        <div class="mst-card-top">
                            <div class="mst-card-id">#<?php echo esc_html($t['id']); ?></div>

                            <span class="mst-badge mst-<?php echo esc_attr($t['status']); ?>">
                                <?php echo esc_html(mst_status_label($t['status'])); ?>
                            </span>
                        </div>

                        <div class="mst-card-title">
                            <?php echo esc_html($t['subject']); ?>
                        </div>

                        <div class="mst-card-meta">
                            آخرین بروزرسانی: <?php echo esc_html($t['updated_at']); ?>
                        </div>

                        <div class="mst-card-actions">
                            <a class="mst-btn mst-btn-sm" href="<?php echo esc_url($ticket_url); ?>">
                                مشاهده گفتگو
                            </a>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * TICKET VIEW (Conversation)
 */
function mst_sc_ticket_view(): string {

    if (!is_user_logged_in()) return '';

    $ticket_id = absint(mst_req('ticket_id'));
    if (!$ticket_id) return '';

    $ticket = MST_DB::get_ticket_for_user($ticket_id, mst_user_id());
    if (!$ticket) {
        return '<div class="mst-alert">دسترسی به این تیکت مجاز نیست.</div>';
    }

    $messages  = MST_DB::get_messages($ticket_id);
    $is_closed = ($ticket['status'] === 'closed');

    ob_start(); ?>

    <div class="mst-box">

        <div class="mst-ticket-head">
            <div>
                <strong>#<?php echo esc_html($ticket_id); ?></strong>
                <?php echo esc_html($ticket['subject']); ?>
            </div>

            <div>
                <span class="mst-badge mst-<?php echo esc_attr($ticket['status']); ?>">
                    <?php echo esc_html(mst_status_label($ticket['status'])); ?>
                </span>
            </div>
        </div>

        <div class="mst-thread">
            <?php foreach ($messages as $msg): ?>
                <div class="mst-msg <?php echo esc_attr($msg['sender_role']); ?>">
                    <div class="mst-msg-meta">
                        <?php echo ($msg['sender_role'] === 'admin') ? 'پشتیبانی' : 'شما'; ?>
                        |
                        <?php echo esc_html($msg['created_at']); ?>
                    </div>

                    <div class="mst-msg-body">
                        <?php echo nl2br(esc_html($msg['message'])); ?>
                        <?php echo mst_render_attachments($msg['attachments'] ?? null); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mst-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mst_toggle_status">
                <?php wp_nonce_field('mst_toggle_status', '_mst_nonce'); ?>
                <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket_id); ?>">
                <input type="hidden" name="next_status" value="<?php echo esc_attr($is_closed ? 'open' : 'closed'); ?>">

                <button type="submit" class="mst-btn mst-btn-secondary">
                    <?php echo $is_closed ? 'باز کردن مجدد' : 'بستن تیکت'; ?>
                </button>
            </form>
        </div>

        <?php if (!$is_closed): ?>
            <div class="mst-reply">
                <form method="post"
                      action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      enctype="multipart/form-data">

                    <input type="hidden" name="action" value="mst_reply_ticket">
                    <?php wp_nonce_field('mst_reply_ticket', '_mst_nonce'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket_id); ?>">

                    <label class="mst-label">پاسخ شما</label>
                    <textarea name="message" class="mst-textarea" required rows="4"></textarea>

                    <label class="mst-label">ضمیمه</label>
                    <input type="file" name="attachments[]" multiple>

                    <button type="submit" class="mst-btn">ارسال پاسخ</button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <?php
    return ob_get_clean();
}
