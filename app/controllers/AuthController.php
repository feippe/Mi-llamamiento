<?php

class AuthController extends Controller
{
    public function csrf(): void
    {
        Response::json($this->authPayload());
    }

    public function register(): void
    {
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Response::error('VALIDATION', 'Nombre, email válido y contraseña de 8 caracteres son obligatorios.', 422);
            return;
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('EMAIL_EXISTS', 'Ese email ya está registrado.', 422);
            return;
        }
        $id = uuid();
        $stmt = $this->db->prepare('INSERT INTO users (id, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), now_utc(), now_utc()]);
        (new AccessService($this->db))->ensurePersonalArea(['id' => $id]);
        Session::login($id);
        Response::json($this->authPayload(), 201);
    }

    public function login(): void
    {
        $input = Request::json();
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('INVALID_LOGIN', 'Email o contraseña incorrectos.', 401);
            return;
        }
        Session::login($user['id']);
        Response::json($this->authPayload());
    }

    public function logout(): void
    {
        $this->requireCsrf();
        Session::logout();
        Response::json(['ok' => true]);
    }

    public function forgotPassword(): void
    {
        $input = Request::json();
        $email = strtolower(trim($input['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('VALIDATION', 'Ingresa un email válido.', 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalida solicitudes previas sin usar para que solo el último enlace sirva.
            $this->db->prepare('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL')
                ->execute([now_utc(), $user['id']]);

            $token   = bin2hex(random_bytes(32));
            $expires = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hora
            $this->db->prepare('INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([uuid(), $user['id'], hash('sha256', $token), $expires, now_utc()]);

            $link = $this->appBaseUrl() . '/?reset=' . $token;
            (new MailService())->sendPasswordReset($user['email'], $user['name'], $link);
        }

        // Respuesta genérica: no revela si el email existe (evita enumeración).
        Response::json(['ok' => true]);
    }

    public function resetPassword(): void
    {
        $input    = Request::json();
        $token    = (string) ($input['token'] ?? '');
        $password = $input['password'] ?? '';

        if ($token === '' || strlen($password) < 8) {
            Response::error('VALIDATION', 'La contraseña debe tener al menos 8 caracteres.', 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = ? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row || $row['used_at'] !== null || strtotime($row['expires_at'] . ' UTC') < time()) {
            Response::error('INVALID_TOKEN', 'El enlace de recuperación no es válido o ya expiró. Solicita uno nuevo.', 400);
            return;
        }

        $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), now_utc(), $row['user_id']]);
        $this->db->prepare('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?')
            ->execute([now_utc(), $row['id']]);

        // Inicia sesión automáticamente tras restablecer.
        Session::login($row['user_id']);
        Response::json($this->authPayload());
    }

    private function appBaseUrl(): string
    {
        $config = require base_path('app/config/config.php');
        if (!empty($config['app']['url'])) {
            return rtrim($config['app']['url'], '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    private function authPayload(): array
    {
        $user = $this->currentUser();
        $bootstrapApproved = false;
        if ($user) {
            $service = new AccessService($this->db);
            $selfApproved = $service->approveSelfApprovablePendingRequestsForUser($user['id']);
            $bootstrapApproved = $selfApproved > 0 || $service->bootstrapFirstStakePresidencyRequestForUser($user['id']);
            $user = $this->currentUser();
        }
        $config = require base_path('app/config/config.php');
        return [
            'user'              => $user,
            'csrf'              => Session::csrf(),
            'bootstrap_approved'=> $bootstrapApproved,
            'vapidPublicKey'    => $config['vapid']['public_key'],
        ];
    }
}
