<?php
if (!defined('ABSPATH')) exit;

/**
 * Register shortcodes
 */
function mst_register_shortcodes(): void {

    add_shortcode('mst_ticket_form', 'mst_sc_ticket_form');
    add_shortcode('mst_ticket_list', 'mst_sc_ticket_list');
    add_shortcode('mst_ticket_view', 'mst_sc_ticket_view');

    // Dedicated support pages
    add_shortcode('mst_support_list', 'mst_sc_ticket_list');
    add_shortcode('mst_support_new', 'mst_sc_ticket_form');

    // SaaS chat wrapper (topbar + ticket view)
    add_shortcode('mst_ticket_chat', 'mst_sc_ticket_chat');

    // Backward compatibility
    add_shortcode('mst_support', 'mst_sc_support');

    add_action('wp_footer', 'mst_render_sticky_new_ticket_button', 99);
}

/**
 * Global sticky CTA renderer (outside content/footer wrappers)
 */
function mst_render_sticky_new_ticket_button(): void {
    // STRICT: show ONLY on [mst_support_list]
    if (!mst_is_support_list_page()) return;

    $url = mst_get_new_ticket_url();
    if (empty($url)) return;

    echo '<div class="mst-sticky-backdrop" aria-hidden="true"></div>';
    echo '<div class="mst-sticky-wrap" aria-hidden="false">';
    echo '<a class="mst-sticky-new-ticket" href="' . esc_url($url) . '">';
    echo '<span class="mst-btn-inner">';
    echo '<span class="mst-btn-icon" aria-hidden="true">+</span>';
    echo '<span class="mst-btn-text">ارسال درخواست جدید</span>';
    echo '</span>';
    echo '</a>';
    echo '</div>';
}

/**
 * Shared front wrapper
 */
function mst_sc_wrap_page(string $variant, string $content): string {
    $variant = sanitize_html_class($variant);

    return '<div class="mst-app" data-mst-theme="light"><div class="mst-shell"><div class="mst-page mst-page--' . esc_attr($variant) . '">' . $content . '</div></div></div>';
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
 * Ticket list time label
 * - Up to 7 days: relative Persian label
 * - Older than 7 days: exact date + time based on WP timezone
 */
function mst_ticket_list_time_label(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return $datetime;

    $now  = (int) current_time('timestamp');
    $diff = max(1, $now - $timestamp);

    if ($diff > (7 * DAY_IN_SECONDS)) {
        return wp_date('Y/m/d - H:i', $timestamp, wp_timezone());
    }

    if ($diff < MINUTE_IN_SECONDS) return 'لحظاتی پیش';

    $units = [
        'روز'   => DAY_IN_SECONDS,
        'ساعت'  => HOUR_IN_SECONDS,
        'دقیقه' => MINUTE_IN_SECONDS,
    ];

    foreach ($units as $label => $seconds) {
        if ($diff >= $seconds) {
            $value = (int) floor($diff / $seconds);
            return sprintf('%s %s پیش', number_format_i18n($value), $label);
        }
    }

    return $datetime;
}

/**
 * Status icon (inline SVG)
 */
function mst_status_icon(string $status): string {
    $status = sanitize_key($status);

    $kind = 'attention';
    if (in_array($status, ['closed', 'resolved'], true)) {
        $kind = 'closed';
    } elseif (in_array($status, ['pending', 'waiting', 'answered', 'in_review'], true)) {
        $kind = 'pending';
    }

    if ($kind === 'closed') {
        return '<svg class="mst-status-icon mst-status-icon--closed" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7" fill="none" stroke="#34C759" stroke-width="1.8"/><path d="M6.6 10.2l2.1 2.2 4.7-4.7" fill="none" stroke="#34C759" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    if ($kind === 'pending') {
        return '<svg class="mst-status-icon mst-status-icon--pending" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7" fill="none" stroke="#F4A623" stroke-width="1.8"/><path d="M10 6.8v3.8l2.2 1.4" fill="none" stroke="#F4A623" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    return '<svg class="mst-status-icon mst-status-icon--attention" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7" fill="none" stroke="#FF3B30" stroke-width="1.8"/><path d="M10 6.3v5.1" fill="none" stroke="#FF3B30" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="13.6" r="0.9" fill="#FF3B30"/></svg>';
}

/**
 * Support page with view switcher (list/new)
 */
function mst_sc_support(): string {
    if (!is_user_logged_in()) {
        return mst_sc_wrap_page('support', '<div class="mst-alert">برای ارسال تیکت باید وارد حساب کاربری شوید.</div>');
    }

    $view = sanitize_key((string) mst_req('view', 'list'));
    if (!in_array($view, ['list', 'new'], true)) {
        $view = 'list';
    }

    $base_url = remove_query_arg('view');
    if (!$base_url) {
        $base_url = home_url('/');
    }

    $list_url = add_query_arg('view', 'list', $base_url);
    $new_url  = add_query_arg('view', 'new', $base_url);

    $content = ($view === 'new')
        ? mst_render_ticket_form($list_url)
        : mst_render_ticket_list();

    return mst_sc_wrap_page('support-' . $view, $content);
}

/**
 * Ticket form inner template
 */
function mst_render_ticket_form(string $list_url = ''): string {

    $list_url = $list_url ?: mst_get_page_url('mst_support_list_page_id', 'support');

    ob_start(); ?>

    <div class="mst-page mst-ticket-list-page mst-ticket-new-page">
    <section class="mst-card mst-card--surface mst-ticket-form" aria-label="فرم ارسال تیکت جدید">
        <header class="mst-card__header mst-card__header--stacked">
            <a class="mst-back-link" href="<?php echo esc_url($list_url); ?>">← بازگشت به تیکت‌ها</a>
        </header>

        <form class="mst-form" method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="mst_create_ticket">
            <?php wp_nonce_field('mst_create_ticket', '_mst_nonce'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($list_url); ?>">

            <label class="mst-label">دسته‌بندی</label>
            <select name="category" class="mst-input" required>
                <option value="" disabled selected>انتخاب دسته‌بندی</option>
                <option value="consultation">مشاوره</option>
                <option value="support">پشتیبانی</option>
            </select>

            <label class="mst-label">موضوع</label>
            <input type="text" name="subject" class="mst-input" required maxlength="255" placeholder="عنوان را بنویسید...">

            <label class="mst-label">پیام</label>
            <textarea name="message" class="mst-textarea" required rows="5" placeholder="پیام‌تان را بنویسید..."></textarea>

            <label class="mst-label">آپلود فایل</label>
            <label class="mst-dropzone" for="mst-attachments-upload">
                <span class="mst-dropzone__icon">＋</span>
                <span class="mst-dropzone__text">افزودن تصویر / فایل</span>
                <span class="mst-dropzone__hint">حداکثر ۳ فایل</span>
            </label>
            <input id="mst-attachments-upload" class="mst-input-file" type="file" name="attachments[]" multiple>

            <button type="submit" class="mst-btn mst-btn--primary mst-btn--full">ارسال تیکت</button>
        </form>
    </section>
    </div>

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
 * SaaS CHAT WRAPPER
 * usage: [mst_ticket_chat]
 */
function mst_sc_ticket_chat($atts = []): string {

    $atts = shortcode_atts([
        'title' => 'گفتگو',
    ], $atts);

    $back_url = mst_get_page_url('mst_support_list_page_id', 'support');

    $ticket_id = absint(mst_req('ticket_id'));

    ob_start(); ?>

    <div class="mst-page mst-ticket-chat-page">
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
    </div>

    <?php
    return mst_sc_wrap_page('chat', ob_get_clean());
}

/**
 * Ticket list inner template
 */
function mst_render_ticket_list(): string {

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

    $chat_url = mst_get_page_url('mst_ticket_chat_page_id', 'ticket-chat');
    $current_user = wp_get_current_user();
    $customer_name = $current_user instanceof WP_User && !empty($current_user->display_name)
        ? (string) $current_user->display_name
        : 'کاربر';

    ob_start(); ?>

    <div class="mst-page mst-ticket-list-page">
        <section class="mst-ticket-list-page__panel" aria-label="Ticket List">
            <header class="mst-ticket-list-page__header">
                <h3 class="mst-ticket-list-page__title">تیکت‌های من</h3>
            </header>

            <?php if (empty($tickets)): ?>
                <div class="mst-empty">هنوز تیکتی ثبت نکرده‌اید.</div>
            <?php else: ?>
                <div class="mst-ticket-list" role="list">
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $status = sanitize_key((string) ($t['status'] ?? 'open'));
                        $ticket_url = add_query_arg('ticket_id', (int) $t['id'], $chat_url);
                        ?>
                        <a class="mst-ticket-card" href="<?php echo esc_url($ticket_url); ?>" role="listitem" aria-label="<?php echo esc_attr(sprintf('Open ticket #%d', (int) $t['id'])); ?>">
                            <div class="mst-ticket-card__content">
                                <div class="mst-ticket-card__meta">
                                    <div class="mst-ticket-card__name-wrap">
                                        <span class="mst-ticket-card__name"><?php echo esc_html($customer_name); ?></span>
                                        <span class="mst-ticket-card__id">#<?php echo esc_html($t['id']); ?></span>
                                    </div>
                                    <span class="mst-ticket-card__time"><?php echo esc_html(mst_ticket_list_time_label((string) $t['updated_at'])); ?></span>
                                </div>
                                <h4 class="mst-ticket-card__subject"><?php echo esc_html($t['subject']); ?></h4>
                            </div>
                            <span class="mst-ticket-card__status" aria-label="<?php echo esc_attr(mst_status_label($status)); ?>">
                                <?php echo mst_status_icon($status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

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
                <?php
                    $role = sanitize_key((string) ($msg['sender_role'] ?? 'user'));
                    $timestamp = strtotime((string) ($msg['created_at'] ?? ''));
                    $datetime_label = $timestamp
                        ? wp_date('Y/m/d - H:i', $timestamp, wp_timezone())
                        : (string) ($msg['created_at'] ?? '');
                ?>
                <article class="mst-message mst-chat-message--<?php echo esc_attr($role); ?>">
                    <div class="mst-message__body">
                        <?php echo nl2br(esc_html($msg['message'])); ?>
                        <?php echo mst_render_attachments($msg['attachments'] ?? null); ?>
                    </div>

                    <div class="mst-message__meta"><?php echo esc_html($datetime_label); ?></div>
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
                    <?php if ($is_closed): ?>
                        باز کردن مجدد
                    <?php else: ?>
                        <span aria-hidden="true">✕</span>
                        <span>بستن تیکت</span>
                    <?php endif; ?>
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

                    <label class="mst-label">آپلود فایل</label>
                    <label class="mst-dropzone" for="mst-chat-attachments-upload">
                        <span class="mst-dropzone__icon">＋</span>
                        <span class="mst-dropzone__text">افزودن تصویر / فایل</span>
                        <span class="mst-dropzone__hint">حداکثر ۳ فایل</span>
                    </label>
                    <input id="mst-chat-attachments-upload" class="mst-input-file" type="file" name="attachments[]" multiple>

                    <button type="submit" class="mst-btn mst-btn--primary mst-btn--full">ارسال پاسخ</button>
                </form>
            </div>
        <?php endif; ?>

    </section>

    <?php
    $content = ob_get_clean();
    return $with_wrapper ? mst_sc_wrap_page('view', $content) : $content;
}
