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
        return ['user' => $user, 'csrf' => Session::csrf(), 'bootstrap_approved' => $bootstrapApproved];
    }
}
