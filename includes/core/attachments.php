<?php
if (!defined('ABSPATH')) exit;

/**
 * Allowed mime types for attachments
 * (minimal + safe)
 */
function mst_allowed_mimes(): array {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
        'pdf'          => 'application/pdf',
        'zip'          => 'application/zip',
        'rar'          => 'application/x-rar-compressed',
        'txt'          => 'text/plain',
    ];
}

/**
 * Upload attachments from an <input type="file" name="attachments[]"> field
 * Returns array of uploaded files meta; each item: [url, file, type, size, name]
 */
function mst_handle_attachments_upload(string $field = 'attachments', int $max_files = 3, int $max_bytes = 5_242_880): array {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }

    $files = $_FILES[$field];
    if (empty($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $count = count($files['name']);
    if ($count <= 0) return [];

    $allowed_mimes = mst_allowed_mimes();
    $results = [];

    // Prepare upload overrides
    $overrides = [
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ];

    require_once ABSPATH . 'wp-admin/includes/file.php';

    for ($i = 0; $i < $count && count($results) < $max_files; $i++) {
        $name = $files['name'][$i] ?? '';
        $type = $files['type'][$i] ?? '';
        $tmp  = $files['tmp_name'][$i] ?? '';
        $err  = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $size = (int)($files['size'][$i] ?? 0);

        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) continue;
        if (!$tmp || !is_uploaded_file($tmp)) continue;

        // size limit
        if ($size <= 0 || $size > $max_bytes) continue;

        $single = [
            'name'     => $name,
            'type'     => $type,
            'tmp_name' => $tmp,
            'error'    => $err,
            'size'     => $size,
        ];

        // wp_handle_upload validates mime using mimes map; type in $_FILES is not trusted
        $uploaded = wp_handle_upload($single, $overrides);

        if (!empty($uploaded['error'])) {
            continue;
        }

        $results[] = [
            'url'  => $uploaded['url'],
            'file' => $uploaded['file'],
            'type' => $uploaded['type'] ?? '',
            'size' => @filesize($uploaded['file']) ?: $size,
            'name' => sanitize_file_name($name),
        ];
    }

    return $results;
}

/**
 * Encode attachments array to JSON for DB storage
 */
function mst_attachments_to_json(array $attachments): ?string {
    if (empty($attachments)) return null;
    return wp_json_encode(array_values($attachments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Decode attachments JSON from DB
 */
function mst_attachments_from_json(?string $json): array {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Render attachments links (minimal)
 */
function mst_render_attachments(?string $json): string {
    $atts = mst_attachments_from_json($json);
    if (empty($atts)) return '';

    $out = '<div class="mst-attachments">';
    foreach ($atts as $a) {
        $url  = isset($a['url']) ? esc_url($a['url']) : '';
        $name = isset($a['name']) ? esc_html($a['name']) : 'file';
        if (!$url) continue;
        $out .= '<a class="mst-attachment" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>';
    }
    $out .= '</div>';

    return $out;
}
