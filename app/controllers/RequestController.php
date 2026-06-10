<?php

class RequestController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->query(
            'SELECT ar.*, uc.user_id, requester.name AS requester_name, requester.email AS requester_email, c.name AS calling_name, o.name AS organization_name, u.name AS unit_name
             FROM access_requests ar
             JOIN user_callings uc ON uc.id = ar.user_calling_id
             JOIN users requester ON requester.id = uc.user_id
             JOIN callings c ON c.id = uc.calling_id
             JOIN organizations o ON o.id = c.organization_id
             JOIN units u ON u.id = uc.unit_id
             WHERE ar.status = "pending"
             ORDER BY ar.requested_at DESC'
        );
        $service = new AccessService($this->db);
        $visible = array_values(array_filter($stmt->fetchAll(), fn ($request) => $service->canApprove($user['id'], $request['user_calling_id'])));
        Response::json($visible);
    }

    public function approve(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        try {
            (new AccessService($this->db))->approveRequest($id, $user['id']);
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::error('APPROVE_FAILED', $e->getMessage(), 403);
        }
    }

    public function reject(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        try {
            (new AccessService($this->db))->rejectRequest($id, $user['id']);
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::error('REJECT_FAILED', $e->getMessage(), 403);
        }
    }
}
