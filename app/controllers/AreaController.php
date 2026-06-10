<?php

class AreaController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare(
            'SELECT wai.*, wam.role, wam.status, wat.is_personal, wat.has_interviews, wat.has_ministering,
                    u.name AS unit_name, u.type AS unit_type
             FROM work_area_members wam
             JOIN work_area_instances wai ON wai.id = wam.work_area_id
             JOIN work_area_templates wat ON wat.id = wai.template_id
             LEFT JOIN units u ON u.id = wai.unit_id
             WHERE wam.user_id = ?
             ORDER BY wat.is_personal DESC, wam.status, wat.sort_order, wai.name'
        );
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll());
    }

    public function members(string $areaId): void
    {
        $user = $this->requireUser();
        if (!(new AccessService($this->db))->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT u.id, u.name, u.email,
                    IF(SUM(wam.role = "manager") > 0, "manager", "member") AS role,
                    MAX(COALESCE(c.can_interview, 0)) AS can_interview
             FROM work_area_members wam
             JOIN users u ON u.id = wam.user_id
             LEFT JOIN user_callings uc ON uc.id = wam.user_calling_id
             LEFT JOIN callings c ON c.id = uc.calling_id
             WHERE wam.work_area_id = ? AND wam.status = "active"
             GROUP BY u.id, u.name, u.email
             ORDER BY u.name'
        );
        $stmt->execute([$areaId]);
        Response::json($stmt->fetchAll());
    }
}
