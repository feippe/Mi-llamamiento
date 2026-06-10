<?php

class Response
{
    public static function json(mixed $data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $code, string $message, int $status = 400, array $fields = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => compact('code', 'message', 'fields')], JSON_UNESCAPED_UNICODE);
    }

    public static function view(string $file): void
    {
        require base_path('app/views/' . $file);
    }
}
