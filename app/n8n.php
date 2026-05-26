<?php

declare(strict_types=1);

function n8n_settings(): array
{
    $config = app_config();
    $defaults = [
        'enabled' => false,
        'webhook_url' => 'https://n8n.notbad.com.br/webhook/7c8c5898-ebf5-46e3-900d-ea21e001a224',
        'auth_token' => '',
        'signing_secret' => '',
        'timeout' => 5,
    ];

    return array_merge($defaults, $config['n8n'] ?? []);
}

function n8n_debug_enabled(): bool
{
    return filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL)
        || filter_var(env('N8N_DEBUG_LOG', false), FILTER_VALIDATE_BOOL);
}

function n8n_mask_recipient(string $recipient): string
{
    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        [$name, $domain] = explode('@', $recipient, 2);
        return substr($name, 0, 2) . '***@' . $domain;
    }

    $digits = preg_replace('/\D+/', '', $recipient) ?? '';
    if (strlen($digits) <= 4) {
        return '***';
    }

    return substr($digits, 0, 4) . str_repeat('*', max(0, strlen($digits) - 8)) . substr($digits, -4);
}

function n8n_log_dispatch(string $message, array $context = []): void
{
    if (!n8n_debug_enabled()) {
        return;
    }

    $suffix = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    error_log('[ingresso n8n] ' . $message . $suffix);
}

function dispatch_code_to_n8n(string $channel, string $recipient, string $code, string $purpose, array $context = []): bool
{
    $settings = n8n_settings();

    if (!$settings['enabled']) {
        // In dev we allow skipping the dispatch to avoid blocking flows.
        n8n_log_dispatch('dispatch desativado por N8N_ENABLED=false', [
            'channel' => $channel,
            'purpose' => $purpose,
            'recipient' => n8n_mask_recipient($recipient),
        ]);
        return true;
    }

    if (!in_array($channel, ['email', 'whatsapp'], true)) {
        throw new InvalidArgumentException('Canal invalido para envio.');
    }

    if ($recipient === '' || $code === '') {
        throw new InvalidArgumentException('Destino e codigo sao obrigatorios.');
    }

    if (empty($settings['webhook_url'])) {
        error_log('N8N_WEBHOOK_URL nao configurada.');
        return false;
    }

    $requestId = bin2hex(random_bytes(8));
    $payload = [
        'channel' => $channel,
        'recipient' => $recipient,
        'code' => $code,
        'purpose' => $purpose,
        'expires_at' => $context['expires_at'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'name' => $context['name'] ?? null,
        'email' => $context['email'] ?? null,
        'whatsapp' => $context['whatsapp'] ?? null,
        'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'request_id' => $requestId,
        'requested_at' => gmdate('c'),
    ];

    $payload = array_filter($payload, static function ($value) {
        return $value !== null && $value !== '';
    });

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        error_log('Falha ao codificar payload para n8n.');
        return false;
    }

    $headers = [
        'Content-Type: application/json',
        'User-Agent: ingresso-code-dispatch/1.0',
    ];

    $secret = $settings['signing_secret'] ?: '';
    if ($secret !== '') {
        $headers[] = 'X-Code-Signature: ' . hash_hmac('sha256', $body, $secret);
    }

    if (!empty($settings['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . $settings['auth_token'];
    }

    $curl = curl_init($settings['webhook_url']);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => (int) $settings['timeout'],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        error_log(sprintf(
            'Erro ao enviar para n8n request_id=%s channel=%s purpose=%s recipient=%s: %s',
            $requestId,
            $channel,
            $purpose,
            n8n_mask_recipient($recipient),
            curl_error($curl)
        ));
        curl_close($curl);
        return false;
    }

    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($status < 200 || $status >= 300) {
        error_log(sprintf(
            'n8n retornou status %d request_id=%s channel=%s purpose=%s recipient=%s: %s',
            $status,
            $requestId,
            $channel,
            $purpose,
            n8n_mask_recipient($recipient),
            substr($response, 0, 500)
        ));
        return false;
    }

    n8n_log_dispatch('dispatch aceito pelo webhook', [
        'request_id' => $requestId,
        'status' => $status,
        'channel' => $channel,
        'purpose' => $purpose,
        'recipient' => n8n_mask_recipient($recipient),
        'response' => substr($response, 0, 300),
    ]);

    return true;
}
