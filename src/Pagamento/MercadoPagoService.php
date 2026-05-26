<?php

declare(strict_types=1);

namespace App\Pagamento;

/**
 * Wrapper leve para a API REST do Mercado Pago.
 * Usa cURL diretamente — sem dependência do SDK oficial.
 *
 * Documentação de referência:
 *   https://www.mercadopago.com.br/developers/pt/reference
 */
class MercadoPagoService
{
    private const API_BASE = 'https://api.mercadopago.com';

    public function __construct(
        private readonly string $accessToken,
        private readonly string $publicKey,
        private readonly string $webhookSecret = '',
    ) {}

    // -------------------------------------------------------------------------
    // Pagamentos
    // -------------------------------------------------------------------------

    /**
     * Cria um pagamento no Mercado Pago.
     *
     * @param  array  $payload  Payload conforme a API /v1/payments
     * @param  string $idempotencyKey  Chave única para evitar cobranças duplicadas
     * @return array{code: int, body: array}
     */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        return $this->post('/v1/payments', $payload, $idempotencyKey);
    }

    /**
     * Consulta um pagamento pelo ID retornado pelo Mercado Pago.
     *
     * @return array{code: int, body: array}
     */
    public function getPayment(int $paymentId): array
    {
        return $this->get("/v1/payments/{$paymentId}");
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    /**
     * Verifica a assinatura HMAC-SHA256 enviada pelo MP no cabeçalho x-signature.
     *
     * Formato do header:
     *   ts=<timestamp>,v1=<hash>
     *
     * @see https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
     */
    public function verifyWebhookSignature(
        string $xSignatureHeader,
        string $xRequestId,
        string $dataId,
    ): bool {
        if ($this->webhookSecret === '') {
            return true; // sem secret configurado, aceita tudo (apenas em dev)
        }

        // Extrai ts e v1 do header
        $parts = [];
        foreach (explode(',', $xSignatureHeader) as $part) {
            [$k, $v] = explode('=', trim($part), 2) + ['', ''];
            $parts[$k] = $v;
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if ($ts === '' || $v1 === '') {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $this->webhookSecret);

        return hash_equals($expected, $v1);
    }

    // -------------------------------------------------------------------------
    // Helpers de montagem de payload
    // -------------------------------------------------------------------------

    /**
     * Monta o payload de pagamento por cartão de crédito/débito.
     *
     * @param  array  $formData   Dados vindos do Payment Brick
     * @param  float  $amount     Valor total do pedido
     * @param  string $pedidoUid  UID interno do pedido (usado como external_reference)
     * @param  string $payerEmail Email do pagador
     * @param  string $payerCpf   CPF do pagador (apenas dígitos)
     * @param  string $payerName  Nome do pagador
     */
    public function buildCardPayload(
        array $formData,
        float $amount,
        string $pedidoUid,
        string $payerEmail,
        string $payerCpf,
        string $payerName,
    ): array {
        return [
            'transaction_amount' => $amount,
            'token'              => $formData['token'] ?? '',
            'description'        => 'Ingresso Pulse Festival',
            'installments'       => (int) ($formData['installments'] ?? 1),
            'payment_method_id'  => $formData['payment_method_id'] ?? '',
            'issuer_id'          => isset($formData['issuer_id']) ? (int) $formData['issuer_id'] : null,
            'external_reference' => $pedidoUid,
            'payer'              => [
                'email'          => $payerEmail,
                'first_name'     => $this->extractFirstName($payerName),
                'last_name'      => $this->extractLastName($payerName),
                'identification' => [
                    'type'   => 'CPF',
                    'number' => $payerCpf,
                ],
            ],
        ];
    }

    /**
     * Monta o payload de pagamento por PIX.
     */
    public function buildPixPayload(
        float $amount,
        string $pedidoUid,
        string $payerEmail,
        string $payerCpf,
        string $payerName,
    ): array {
        return [
            'transaction_amount' => $amount,
            'description'        => 'Ingresso Pulse Festival',
            'payment_method_id'  => 'pix',
            'external_reference' => $pedidoUid,
            'payer'              => [
                'email'          => $payerEmail,
                'first_name'     => $this->extractFirstName($payerName),
                'last_name'      => $this->extractLastName($payerName),
                'identification' => [
                    'type'   => 'CPF',
                    'number' => $payerCpf,
                ],
            ],
        ];
    }

    /**
     * Extrai dados do PIX da resposta do MP após criação de pagamento.
     *
     * @return array{qr_code: string, qr_code_base64: string, expiration: string|null}
     */
    public function extractPixData(array $mpResponseBody): array
    {
        $poi = $mpResponseBody['point_of_interaction'] ?? [];
        $txData = $poi['transaction_data'] ?? [];

        return [
            'qr_code'        => $txData['qr_code'] ?? '',
            'qr_code_base64' => $txData['qr_code_base64'] ?? '',
            'expiration'     => $mpResponseBody['date_of_expiration'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    private function post(string $path, array $body, string $idempotencyKey = ''): array
    {
        $url = self::API_BASE . $path;
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($idempotencyKey !== '') {
            $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            return ['code' => 0, 'body' => ['message' => 'cURL error: ' . $error]];
        }

        return ['code' => $httpCode, 'body' => json_decode((string) $response, true) ?? []];
    }

    private function get(string $path): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $httpCode, 'body' => json_decode((string) $response, true) ?? []];
    }

    // -------------------------------------------------------------------------
    // Utilitários
    // -------------------------------------------------------------------------

    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[0] ?? '';
    }

    private function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[1] ?? '';
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
