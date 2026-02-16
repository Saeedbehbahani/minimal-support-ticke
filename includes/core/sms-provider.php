<?php
if (!defined('ABSPATH')) exit;

/**
 * Main SMS Provider Wrapper
 * Supports Pattern SMS only.
 */
class MST_SMS_Provider {

    protected string $api_key;
    protected string $sender;
    protected string $base_url;

    protected $adapter = null;

    public function __construct(array $config = []) {

        $this->api_key  = (string)($config['api_key']  ?? '');
        $this->sender   = (string)($config['sender']   ?? '');
        $this->base_url = (string)($config['base_url'] ?? '');

        if (!class_exists('MST_SMS_Adapter_Pattern')) {
            throw new Exception('Pattern adapter not loaded.');
        }

        $this->adapter = new MST_SMS_Adapter_Pattern([
            'api_key'  => $this->api_key,
            'sender'   => $this->sender,
            'base_url' => $this->base_url,
        ]);
    }

    /**
     * Send pattern SMS
     */
    public function send_pattern(string $to, string $pattern_code, array $params = []): bool {

        if (!$this->adapter) return false;
        if ($to === '' || $pattern_code === '') return false;

        try {
            return (bool) $this->adapter->send_pattern($to, $pattern_code, $params);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
