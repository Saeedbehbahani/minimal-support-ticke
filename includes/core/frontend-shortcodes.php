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

    // New dedicated shortcodes
    add_shortcode('mst_support_list', 'mst_sc_support_list');
    add_shortcode('mst_support_new', 'mst_sc_support_new');

    // Backward compatibility: support page stays list-only
    add_shortcode('mst_support', 'mst_sc_support_list');
}

/**
 * Shared front wrapper
 */
function mst_sc_wrap_page(string $variant, string $content): string {
    $variant = sanitize_html_class($variant);

    return '<mst-app class="mst-app"><mst-shell class="mst-shell"><mst-page class="mst-page mst-page--' . esc_attr($variant) . '">' . $content . '</mst-page></mst-shell></mst-app>';
}

/**
 * Find URL of first published page containing a shortcode
 */
function mst_find_page_url_by_shortcode(string $shortcode, string $fallback = ''): string {
    static $cache = [];

    $key = trim($shortcode);
    if ($key === '') {
        return $fallback;
    }

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ]);

    foreach ($pages as $page_id) {
        $content = (string) get_post_field('post_content', $page_id);
        if ($content !== '' && has_shortcode($content, $key)) {
            $url = get_permalink((int) $page_id);
            if ($url) {
                $cache[$key] = $url;
                return $url;
            }
        }
    }

    $cache[$key] = $fallback;
    return $fallback;
}

/**
 * Relative time label (FA)
 */
function mst_relative_time_label(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return $datetime;

    $diff = max(1, current_time('timestamp') - $timestamp);

    if ($diff < MINUTE_IN_SECONDS) return 'لحظاتی پیش';

    $units = [
        'سال'   => YEAR_IN_SECONDS,
        'ماه'   => MONTH_IN_SECONDS,
        'هفته'  => WEEK_IN_SECONDS,
        'روز'   => DAY_IN_SECONDS,
        'ساعت'  => HOUR_IN_SECONDS,
        'دقیقه' => MINUTE_IN_SECONDS,
    ];

    foreach ($units as $label => $seconds) {
        if ($diff >= $seconds) {
            $value = (int) floor($diff / $seconds);
            return sprintf('%d %s پیش', $value, $label);
        }
    }

    return $datetime;
}

/**
 * Status icon (monochrome)
 */
function mst_status_icon(string $status): string {
    if ($status === 'closed') return '✓';
    if ($status === 'answered') return '!';
    return '◷';
}

/**
 * Ticket form inner template
 */
function mst_render_ticket_form(string $list_url = ''): string {

    if ($list_url === '') {
        $list_url = mst_find_page_url_by_shortcode('mst_support_list');
        if ($list_url === '') {
            $list_url = mst_find_page_url_by_shortcode('mst_support', home_url('/'));
        }
    }

    ob_start(); ?>

    <section class="mst-card mst-card--surface mst-ticket-form">
        <header class="mst-card__header mst-card__header--stacked">
            <a class="mst-back-link" href="<?php echo esc_url($list_url); ?>">← بازگشت به لیست تیکت‌ها</a>
            <h3 class="mst-title">ثبت تیکت جدید</h3>
        </header>

        <form class="mst-form" method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="mst_create_ticket">
            <?php wp_nonce_field('mst_create_ticket', '_mst_nonce'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($list_url); ?>">

            <label class="mst-label">دسته‌بندی</label>
            <select name="category" class="mst-input" required>
                <option value="" disabled selected>یک دسته‌بندی را انتخاب کنید</option>
                <option value="consultation">مشاوره</option>
                <option value="support">پشتیبانی</option>
            </select>

            <label class="mst-label">موضوع</label>
            <input type="text" name="subject" class="mst-input" required maxlength="255" placeholder="مثال: مشکل در پرداخت سفارش">

            <label class="mst-label">متن پیام</label>
            <textarea name="message" class="mst-textarea" required rows="5" placeholder="جزئیات مشکل را کامل بنویسید"></textarea>

            <label class="mst-label">آپلود فایل</label>
            <label class="mst-dropzone" for="mst-attachments-upload">
                <span class="mst-dropzone__icon">＋</span>
                <span class="mst-dropzone__text">افزودن اسکرین‌شات / فایل</span>
                <span class="mst-dropzone__hint">حداکثر ۳ فایل</span>
            </label>
            <input id="mst-attachments-upload" class="mst-input-file" type="file" name="attachments[]" multiple>

            <button type="submit" class="mst-btn mst-btn--primary mst-btn--full">ارسال تیکت</button>
        </form>
    </section>

    <?php
    return ob_get_clean();
}

/**
 * TICKET FORM
 */
function mst_sc_ticket_form(): string {

    if (!is_user_logged_in()) {
        return mst_sc_wrap_page('form', '<div class="mst-alert">برای ارسال تیکت باید وارد حساب کاربری شوید.</div>');
    }

    return mst_sc_wrap_page('form', mst_render_ticket_form());
}

/**
 * Dedicated support new-ticket page
 */
function mst_sc_support_new(): string {
    if (!is_user_logged_in()) {
        return mst_sc_wrap_page('support-new', '<div class="mst-alert">برای ارسال تیکت باید وارد حساب کاربری شوید.</div>');
    }

    return mst_sc_wrap_page('support-new', mst_render_ticket_form());
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

    // Prefer dedicated support list page URL, then legacy support page, then fallback.
    $back_url = mst_find_page_url_by_shortcode('mst_support_list');
    if ($back_url === '') {
        $back_url = mst_find_page_url_by_shortcode('mst_support');
    }
    if ($back_url === '') {
        $back_page_id = 11;
        $back_url = get_permalink($back_page_id);
    }
    if (!$back_url) {
        $back_url = home_url('/');
    }

    $ticket_id = absint(mst_req('ticket_id'));

    ob_start(); ?>

    <header class="mst-chat-topbar">
        <a class="mst-btn mst-btn--ghost mst-chat-topbar__back" href="<?php echo esc_url($back_url); ?>">← بازگشت به تیکت‌ها</a>
        <div class="mst-chat-topbar__title"><?php echo esc_html($atts['title']); ?></div>
    </header>

    <?php if (!$ticket_id): ?>
        <section class="mst-card mst-card--surface">
            <div class="mst-alert">تیکتی انتخاب نشده.</div>
        </section>
    <?php else: ?>
        <div class="mst-chat-view"><?php echo mst_sc_ticket_view(false); ?></div>
    <?php endif; ?>

    <?php
    return mst_sc_wrap_page('chat', ob_get_clean());
}

/**
 * Ticket list inner template
 */
function mst_render_ticket_list(string $new_url = ''): string {

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

    if ($new_url === '') {
        $new_url = mst_find_page_url_by_shortcode('mst_support_new');
    }

    if ($new_url === '') {
        $new_url = home_url('/');
    }

    // Chat page (by slug). If not found => fallback to page_id=25 (adjust if needed)
    $chat_page = get_page_by_path('ticket-chat');
    if ($chat_page) {
        $chat_url = get_permalink($chat_page->ID);
    } else {
        $chat_url = home_url('/?page_id=25');
    }

    ob_start(); ?>

    <section class="mst-card mst-card--surface mst-ticket-list-page">
        <header class="mst-card__header">
            <h3 class="mst-title">تیکت‌های من</h3>
            <a class="mst-btn mst-btn--primary" href="<?php echo esc_url($new_url); ?>">ثبت تیکت جدید</a>
        </header>

        <?php if (empty($tickets)): ?>
            <div class="mst-empty">هنوز تیکتی ثبت نکردی.</div>
        <?php else: ?>
            <div class="mst-ticket-list" role="list">
                <?php foreach ($tickets as $t): ?>
                    <?php
                    $status = sanitize_key((string) ($t['status'] ?? 'open'));
                    $ticket_url = add_query_arg('ticket_id', (int) $t['id'], $chat_url);
                    ?>
                    <article class="mst-ticket-item" role="listitem">
                        <a class="mst-ticket-item__link" href="<?php echo esc_url($ticket_url); ?>">
                            <div class="mst-ticket-item__head">
                                <span class="mst-ticket-item__id">#<?php echo esc_html($t['id']); ?></span>
                                <span class="mst-ticket-item__time"><?php echo esc_html(mst_relative_time_label((string) $t['updated_at'])); ?></span>
                            </div>

                            <h4 class="mst-ticket-item__title"><?php echo esc_html($t['subject']); ?></h4>

                            <div class="mst-ticket-item__foot">
                                <span class="mst-ticket-item__status"><?php echo esc_html(mst_status_label($status)); ?></span>
                                <span class="mst-ticket-item__status-icon" aria-hidden="true"><?php echo esc_html(mst_status_icon($status)); ?></span>
                            </div>

                            <span class="mst-ticket-item__view-btn">مشاهده</span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
    return ob_get_clean();
}

/**
 * TICKET LIST (User only)
 */
function mst_sc_ticket_list(): string {

    if (!is_user_logged_in()) return '';

    return mst_sc_wrap_page('list', mst_render_ticket_list());
}

/**
 * Dedicated support list page
 */
function mst_sc_support_list(): string {

    if (!is_user_logged_in()) {
        return mst_sc_wrap_page('support-list', '<div class="mst-alert">برای ارسال تیکت باید وارد حساب کاربری شوید.</div>');
    }

    return mst_sc_wrap_page('support-list', mst_render_ticket_list());
}

/**
 * TICKET VIEW (Conversation)
 *
 * Important:
 * - As shortcode callback, WP passes ($atts, $content)
 * - We also call mst_sc_ticket_view(false) from chat wrapper to avoid double wrapper
 */
function mst_sc_ticket_view($atts = [], $with_wrapper = true): string {

    // If called like mst_sc_ticket_view(false)
    if (is_bool($atts)) {
        $with_wrapper = $atts;
        $atts = [];
    }

    if (!is_user_logged_in()) return '';

    $ticket_id = absint(mst_req('ticket_id'));
    if (!$ticket_id) return '';

    $ticket = MST_DB::get_ticket_for_user($ticket_id, mst_user_id());
    if (!$ticket) {
        $content = '<div class="mst-alert">دسترسی به این تیکت مجاز نیست.</div>';
        return $with_wrapper ? mst_sc_wrap_page('view', $content) : $content;
    }

    $messages  = MST_DB::get_messages($ticket_id);
    $is_closed = ((string)($ticket['status'] ?? '') === 'closed');
    $status    = sanitize_key((string) ($ticket['status'] ?? 'open'));

    ob_start(); ?>

    <section class="mst-card mst-card--surface mst-chat">

        <header class="mst-chat-head">
            <div class="mst-chat-head__subject">
                <strong>#<?php echo esc_html($ticket_id); ?></strong>
                <?php echo esc_html($ticket['subject']); ?>
            </div>

            <div>
                <span class="mst-badge mst-badge--<?php echo esc_attr($status); ?>">
                    <?php echo esc_html(mst_status_label($status)); ?>
                </span>
            </div>
        </header>

        <div class="mst-thread" aria-live="polite">
            <?php foreach ($messages as $msg): ?>
                <?php $role = sanitize_key((string) ($msg['sender_role'] ?? 'user')); ?>
                <article class="mst-message mst-message--<?php echo esc_attr($role); ?>">
                    <div class="mst-message__meta">
                        <?php echo ($role === 'admin') ? 'پشتیبانی' : 'شما'; ?>
                        |
                        <?php echo esc_html($msg['created_at']); ?>
                    </div>

                    <div class="mst-message__body">
                        <?php echo nl2br(esc_html($msg['message'])); ?>
                        <?php echo mst_render_attachments($msg['attachments'] ?? null); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="mst-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mst_toggle_status">
                <?php wp_nonce_field('mst_toggle_status', '_mst_nonce'); ?>
                <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket_id); ?>">
                <input type="hidden" name="next_status" value="<?php echo esc_attr($is_closed ? 'open' : 'closed'); ?>">

                <button type="submit" class="mst-btn mst-btn--secondary">
                    <?php echo $is_closed ? 'باز کردن مجدد' : 'بستن تیکت'; ?>
                </button>
            </form>
        </div>

        <?php if (!$is_closed): ?>
            <div class="mst-composer">
                <form class="mst-form" method="post"
                      action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      enctype="multipart/form-data">

                    <input type="hidden" name="action" value="mst_reply_ticket">
                    <?php wp_nonce_field('mst_reply_ticket', '_mst_nonce'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket_id); ?>">

                    <label class="mst-label">پاسخ شما</label>
                    <textarea name="message" class="mst-textarea" required rows="4"></textarea>

                    <label class="mst-label">ضمیمه</label>
                    <input class="mst-input-file" type="file" name="attachments[]" multiple>

                    <button type="submit" class="mst-btn mst-btn--primary">ارسال پاسخ</button>
                </form>
            </div>
        <?php endif; ?>

    </section>

    <?php
    $content = ob_get_clean();
    return $with_wrapper ? mst_sc_wrap_page('view', $content) : $content;
}
