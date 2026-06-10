<?php

class ProfileController extends Controller
{
    public function show(): void
    {
        $user = $this->requireUser();
        Response::json([
            'user' => $user,
            'can_manage_settings' => (new AccessService($this->db))->hasAuthorityScope($user['id'], 'stake', null),
        ]);
    }

    public function update(): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('VALIDATION', 'Nombre y email válido son obligatorios.', 422);
            return;
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            Response::error('VALIDATION', 'La nueva contraseña debe tener al menos 8 caracteres.', 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$user['id']]);
        $stored = $stmt->fetch();
        if (!$stored) {
            Response::error('UNAUTHENTICATED', 'Debes iniciar sesión.', 401);
            return;
        }

        $sensitiveChange = $email !== strtolower($stored['email']) || $newPassword !== '';
        if ($sensitiveChange && !password_verify($currentPassword, $stored['password_hash'])) {
            Response::error('PASSWORD_REQUIRED', 'La contraseña actual es obligatoria para cambiar email o contraseña.', 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ? AND deleted_at IS NULL');
        $stmt->execute([$email, $user['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('EMAIL_EXISTS', 'Ese email ya está registrado.', 422);
            return;
        }

        if ($newPassword !== '') {
            $stmt = $this->db->prepare('UPDATE users SET name = ?, email = ?, password_hash = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$name, $email, password_hash($newPassword, PASSWORD_DEFAULT), now_utc(), $user['id']]);
        } else {
            $stmt = $this->db->prepare('UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$name, $email, now_utc(), $user['id']]);
        }

        Response::json(['user' => $this->currentUser()]);
    }
}
