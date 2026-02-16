<?php
if (!defined('ABSPATH')) exit;

/**
 * Pattern SMS Adapter (Kavenegar compatible)
 *
 * API Example:
 * POST https://api.kavenegar.com/v1/{API_KEY}/verify/lookup.json
 *
 * Body:
 *   receptor=0912...
 *   token=...
 *   token2=...
 *   token3=...
 *   template=pattern_code
 */

class MST_SMS_Adapter_Pattern {

    protected string $api_key;
    protected string $sender;
    protected string $base_url;

    public function __construct(array $config = []) {
        $this->api_key  = (string)($config['api_key']  ?? '');
        $this->sender   = (string)($config['sender']   ?? '');
        $this->base_url = (string)($config['base_url'] ?? '');
    }

    /**
     * Send Pattern SMS
     */
    public function send_pattern(string $to, string $pattern_code, array $params = []): bool {

        if ($this->api_key === '' || $to === '' || $pattern_code === '') {
            return false;
        }

        // Default Kavenegar endpoint
        $endpoint = $this->base_url ?: 'https://api.kavenegar.com/v1/' . $this->api_key . '/verify/lookup.json';

        $body = [
            'receptor' => $to,
            'template' => $pattern_code,
        ];

        // Map params to token, token2, token3...
        $i = 1;
        foreach ($params as $value) {
            if ($i > 10) break; // limit
            $key = $i === 1 ? 'token' : 'token' . $i;
            $body[$key] = (string) $value;
            $i++;
        }

        $args = [
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            error_log('MST SMS send failed (WP_Error): ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('MST SMS send failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body($response));
            return false;
        }

        $res_body = wp_remote_retrieve_body($response);
        if (!$res_body) return false;

        $json = json_decode($res_body, true);
        if (!is_array($json)) return false;

        // Kavenegar success structure
        if (isset($json['return']['status']) && (int)$json['return']['status'] === 200) {
            return true;
        }

        return false;
    }
}
