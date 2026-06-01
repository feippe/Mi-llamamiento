<?php

class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '/' && substr($uri, 0, strlen($script)) === $script) {
            $uri = substr($uri, strlen($script));
        }
        return '/' . trim($uri, '/');
    }

    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
