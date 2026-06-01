<?php

class AuthController extends Controller
{
    public function google(): void
    {
        $config = require __DIR__ . '/../config/config.php';
        $input = Request::json();
        $timezone = $input['timezone'] ?? 'America/Buenos_Aires';

        if (!$config['auth']['allow_dev_login'] && empty($input['google_token'])) {
            Response::error('GOOGLE_TOKEN_REQUIRED', 'El token de Google es obligatorio.', 422);
            return;
        }

        $email = $input['email'] ?? null;
        $name = $input['nombre'] ?? null;
        $googleId = $input['google_id'] ?? null;

        if ($config['auth']['allow_dev_login'] && (!$email || !$name)) {
            $email = 'demo@mi-llamamiento.local';
            $name = 'Usuario Demo';
            $googleId = 'dev-demo';
        }

        if (!$email || !$name) {
            Response::error('AUTH_NOT_CONFIGURED', 'Configura Google OAuth o usa APP_ENV=development para login de prueba.', 422);
            return;
        }

        $this->db->beginTransaction();
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $created = false;

        if (!$user) {
            $user = [
                'id' => uuid(),
                'google_id' => $googleId,
                'email' => $email,
                'nombre' => $name,
                'foto_url' => $input['foto_url'] ?? null,
                'stake_id' => $input['stake_id'] ?? 1,
                'unit_id' => $input['unit_id'] ?? 1,
                'timezone' => $timezone,
            ];
            $stmt = $this->db->prepare(
                'INSERT INTO users (id, google_id, email, nombre, foto_url, stake_id, unit_id, timezone, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $user['google_id'], $user['email'], $user['nombre'], $user['foto_url'], $user['stake_id'], $user['unit_id'], $user['timezone'], now_utc(), now_utc()]);
            $this->createPersonalArea($user);
            $created = true;
        }

        $sessionId = uuid();
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare('INSERT INTO sessions (id, user_id, token, device_info, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$sessionId, $user['id'], $token, $_SERVER['HTTP_USER_AGENT'] ?? null, now_utc()]);
        $this->db->commit();

        Response::json(['token' => $token, 'user' => $user, 'perfil_completo' => !empty($user['stake_id']) && !empty($user['unit_id'])], $created ? 201 : 200);
    }

    public function logout(): void
    {
        $this->requireUser();
        $token = Request::bearerToken();
        $stmt = $this->db->prepare('UPDATE sessions SET revoked_at = ? WHERE token = ?');
        $stmt->execute([now_utc(), $token]);
        Response::json(null, 204);
    }

    public function me(): void
    {
        Response::json($this->requireUser());
    }

    private function createPersonalArea(array $user): void
    {
        $areaId = uuid();
        $memberId = uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO service_area_instances (id, owner_user_id, es_personal, nombre, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?, ?)'
        );
        $stmt->execute([$areaId, $user['id'], 'Personal', $now, $now]);
        $stmt = $this->db->prepare(
            'INSERT INTO area_members (id, area_instance_id, user_id, rol, estado, origen, created_at, updated_at)
             VALUES (?, ?, ?, "propietario", "activo", "invitacion", ?, ?)'
        );
        $stmt->execute([$memberId, $areaId, $user['id'], $now, $now]);
    }
}
