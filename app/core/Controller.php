<?php

abstract class Controller
{
    private ?PDO $connection = null;

    public function __get(string $name): mixed
    {
        if ($name === 'db') {
            return $this->db();
        }
        trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_NOTICE);
        return null;
    }

    protected function db(): PDO
    {
        if (!$this->connection) {
            $this->connection = Database::connection();
        }
        return $this->connection;
    }

    protected function currentUser(): ?array
    {
        $userId = Session::userId();
        if (!$userId) {
            return null;
        }
        $stmt = $this->db()->prepare('SELECT id, name, email FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    protected function requireUser(): array
    {
        $user = $this->currentUser();
        if (!$user) {
            Response::error('UNAUTHENTICATED', 'Debes iniciar sesión.', 401);
            exit;
        }
        return $user;
    }

    protected function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? Request::input()['_csrf'] ?? null;
        if (!Session::verifyCsrf($token)) {
            Response::error('CSRF', 'Token de seguridad inválido.', 419);
            exit;
        }
    }
}
