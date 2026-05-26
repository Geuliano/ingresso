<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Helpers para respostas JSON.
 */
final class JsonResponse
{
    public static function send(array $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
