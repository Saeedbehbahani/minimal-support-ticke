<?php
if (!defined('ABSPATH')) exit;

/**
 * Register front handlers (admin-post)
 */
function mst_register_front_handlers(): void {

    add_action('admin_post_mst_create_ticket', 'mst_handle_create_ticket');
    add_action('admin_post_mst_reply_ticket',  'mst_handle_reply_ticket');
    add_action('admin_post_mst_toggle_status', 'mst_handle_toggle_status');

    add_action('admin_post_nopriv_mst_create_ticket', 'mst_block_nopriv');
    add_action('admin_post_nopriv_mst_reply_ticket',  'mst_block_nopriv');
    add_action('admin_post_nopriv_mst_toggle_status', 'mst_block_nopriv');
}

function mst_block_nopriv() {
    wp_die('Unauthorized', 401);
}

/**
 * CREATE TICKET
 */
function mst_handle_create_ticket(): void {

    if (!is_user_logged_in()) wp_die('Unauthorized', 401);
    if (!wp_verify_nonce($_POST['_mst_nonce'] ?? '', 'mst_create_ticket')) {
        wp_die('Invalid nonce', 403);
    }

    $subject = sanitize_text_field(mst_req('subject'));
    $message = sanitize_textarea_field(mst_req('message'));
    $category = sanitize_key((string) mst_req('category'));
    $allowed_categories = ['consultation', 'support'];
    if (!in_array($category, $allowed_categories, true)) {
        $category = 'support';
    }

    if ($subject === '' || $message === '') {
        mst_redirect_back();
    }

    global $wpdb;
    $tickets  = MST_DB::tickets_table();
    $messages = MST_DB::messages_table();

    $now = current_time('mysql');
    $user_id = mst_user_id();

    $wpdb->insert($tickets, [
        'user_id'    => $user_id,
        'admin_id'   => 0,
        'type'       => $category,
        'subject'    => $subject,
        'status'     => 'open',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $ticket_id = (int) $wpdb->insert_id;

    if ($ticket_id) {

        $attachments = mst_handle_attachments_upload();
        $attachments_json = mst_attachments_to_json($attachments);

        $wpdb->insert($messages, [
            'ticket_id'   => $ticket_id,
            'sender_role' => 'user',
            'sender_id'   => $user_id,
            'message'     => $message,
            'attachments' => $attachments_json,
            'created_at'  => $now,
        ]);

        // SMS → notify admins
        if (function_exists('mst_notify_ticket_created')) {
            mst_notify_ticket_created($ticket_id);
        }
    }

    $redirect_to = esc_url_raw((string) mst_req('redirect_to'));
    if (!$redirect_to) {
        $redirect_to = add_query_arg('view', 'list', remove_query_arg('view'));
    }

    wp_safe_redirect($redirect_to);
    exit;
}

/**
 * USER REPLY
 */
function mst_handle_reply_ticket(): void {

    if (!is_user_logged_in()) wp_die('Unauthorized', 401);
    if (!wp_verify_nonce($_POST['_mst_nonce'] ?? '', 'mst_reply_ticket')) {
        wp_die('Invalid nonce', 403);
    }

    $ticket_id = absint(mst_req('ticket_id'));
    $message   = sanitize_textarea_field(mst_req('message'));

    if (!$ticket_id || $message === '') {
        mst_redirect_back();
    }

    $ticket = MST_DB::get_ticket_for_user($ticket_id, mst_user_id());
    if (!$ticket) wp_die('Forbidden', 403);
    if ($ticket['status'] === 'closed') mst_redirect_back();

    global $wpdb;
    $tickets  = MST_DB::tickets_table();
    $messages = MST_DB::messages_table();

    $now = current_time('mysql');

    $attachments = mst_handle_attachments_upload();
    $attachments_json = mst_attachments_to_json($attachments);

    $wpdb->insert($messages, [
        'ticket_id'   => $ticket_id,
        'sender_role' => 'user',
        'sender_id'   => mst_user_id(),
        'message'     => $message,
        'attachments' => $attachments_json,
        'created_at'  => $now,
    ]);

    // when user replies → reopen if needed
    $wpdb->update($tickets, [
        'status'     => 'open',
        'updated_at' => $now,
    ], ['id' => $ticket_id]);

    mst_redirect_back();
}

/**
 * TOGGLE STATUS (User side)
 */
function mst_handle_toggle_status(): void {

    if (!is_user_logged_in()) wp_die('Unauthorized', 401);
    if (!wp_verify_nonce($_POST['_mst_nonce'] ?? '', 'mst_toggle_status')) {
        wp_die('Invalid nonce', 403);
    }

    $ticket_id = absint(mst_req('ticket_id'));
    $next_status = mst_sanitize_status(mst_req('next_status'));

    if (!$ticket_id || !in_array($next_status, ['open','closed'], true)) {
        mst_redirect_back();
    }

    $ticket = MST_DB::get_ticket_for_user($ticket_id, mst_user_id());
    if (!$ticket) wp_die('Forbidden', 403);

    global $wpdb;
    $tickets = MST_DB::tickets_table();
    $now = current_time('mysql');

    $wpdb->update($tickets, [
        'status'     => $next_status,
        'updated_at' => $now,
    ], ['id' => $ticket_id]);

    mst_redirect_back();
}
