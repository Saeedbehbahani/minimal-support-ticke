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

        // Explicit token map when associative array contains token keys.
        if (array_key_exists('token', $params)) {
            $token = (string) $params['token'];
            if ($token !== '') {
                $body['token'] = $token;
            }

            for ($i = 2; $i <= 10; $i++) {
                $key = 'token' . $i;
                if (array_key_exists($key, $params) && (string) $params[$key] !== '') {
                    $body[$key] = (string) $params[$key];
                }
            }
        } else {
            // Backward-compatible positional mapping.
            $i = 1;
            foreach ($params as $value) {
                if ($i > 10) break;
                $key = $i === 1 ? 'token' : 'token' . $i;
                $body[$key] = (string) $value;
                $i++;
            }
        }

        if (function_exists('mst_sms_debug_log')) {
            mst_sms_debug_log('Kavenegar request body prepared.', [
                'endpoint_host' => (string) wp_parse_url($endpoint, PHP_URL_HOST),
                'body_fields'   => array_keys($body),
                'pattern'       => $pattern_code,
            ]);
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
            if (function_exists('mst_sms_debug_log')) {
                mst_sms_debug_log('Kavenegar request failed with WP_Error.', [
                    'pattern' => $pattern_code,
                    'error' => $response->get_error_message(),
                ]);
            }
            error_log('MST SMS send failed: WordPress HTTP request error.');
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $res_body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($res_body, true);
        $return_status = is_array($json) ? (int) ($json['return']['status'] ?? 0) : 0;


        if (function_exists('mst_sms_debug_log')) {
            mst_sms_debug_log('Kavenegar response received.', [
                'http_code'     => $code,
                'return_status' => $return_status,
                'pattern'       => $pattern_code,
            ]);
        }

        if ($code < 200 || $code >= 300) {
            error_log('MST SMS send failed (HTTP ' . $code . ').');
            return false;
        }

        if ($return_status === 200) {
            return true;
        }

        if (function_exists('mst_sms_debug_log')) {
            mst_sms_debug_log('Kavenegar returned non-success status.', [
                'return_status' => $return_status,
                'pattern'       => $pattern_code,
            ]);
        }

        return false;
    }
}
