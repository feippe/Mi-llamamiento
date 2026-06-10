<?php

class Session
{
    private const LIFETIME = 30 * 24 * 60 * 60; // 30 días

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $config = require base_path('app/config/config.php');
        $secure = (bool) $config['app']['cookie_secure'];

        ini_set('session.gc_maxlifetime', self::LIFETIME);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Renueva la expiración del cookie en cada request autenticado
        if (!empty($_SESSION['user_id'])) {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + self::LIFETIME,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function userId(): ?string
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function login(string $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
    }
}
