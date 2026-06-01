<?php

class Response
{
    public static function json($data = null, int $status = 200, array $meta = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($status === 204) {
            return;
        }
        echo json_encode(['data' => $data, 'meta' => (object) $meta], JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $code, string $message, int $status = 400, array $fields = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
