<?php

class PushController extends Controller
{
    public function subscribe(): void
    {
        $this->requireCsrf();
        $user  = $this->requireUser();
        $input = Request::json();

        $endpoint = trim($input['endpoint'] ?? '');
        $p256dh   = trim($input['p256dh']   ?? '');
        $auth     = trim($input['auth']      ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            Response::error('VALIDATION', 'Suscripción inválida.', 422);
            return;
        }

        // Upsert: update keys if endpoint already exists for this user, insert otherwise
        $stmt = $this->db->prepare(
            'INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth)'
        );
        $stmt->execute([uuid(), $user['id'], $endpoint, $p256dh, $auth, now_utc()]);

        Response::json(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        $this->requireCsrf();
        $user  = $this->requireUser();
        $input = Request::json();
        $endpoint = trim($input['endpoint'] ?? '');
        if ($endpoint !== '') {
            $stmt = $this->db->prepare(
                'DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?'
            );
            $stmt->execute([$user['id'], $endpoint]);
        }
        Response::json(['ok' => true]);
    }
}
