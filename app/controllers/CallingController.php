<?php

class CallingController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare(
            'SELECT uc.*, c.name AS calling_name, o.name AS organization_name, o.scope_type, u.name AS unit_name
             FROM user_callings uc
             JOIN callings c ON c.id = uc.calling_id
             JOIN organizations o ON o.id = c.organization_id
             JOIN units u ON u.id = uc.unit_id
             WHERE uc.user_id = ? AND uc.status <> "removed"
             ORDER BY uc.requested_at DESC'
        );
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll());
    }

    public function create(): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $input = Request::json();
        try {
            $result = (new AccessService($this->db))->requestCalling($user['id'], (int) $input['calling_id'], (int) $input['unit_id']);
            Response::json($result, 201);
        } catch (Throwable $e) {
            Response::error('CALLING_REQUEST_FAILED', $e->getMessage(), 422);
        }
    }

    public function remove(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $stmt = $this->db->prepare('SELECT * FROM user_callings WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        if (!$stmt->fetch()) {
            Response::error('NOT_FOUND', 'Llamamiento no encontrado.', 404);
            return;
        }
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('UPDATE user_callings SET status = "removed", resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$user['id'], now_utc(), $id]);
        $stmt = $this->db->prepare('UPDATE work_area_members SET status = "removed", updated_at = ? WHERE user_calling_id = ?');
        $stmt->execute([now_utc(), $id]);
        $stmt = $this->db->prepare('UPDATE access_requests SET status = "rejected", resolved_by = ?, resolved_at = ? WHERE user_calling_id = ? AND status = "pending"');
        $stmt->execute([$user['id'], now_utc(), $id]);
        $this->db->commit();
        Response::json(['ok' => true]);
    }
}
