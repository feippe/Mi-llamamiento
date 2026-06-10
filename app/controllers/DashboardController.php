<?php

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $uid  = $user['id'];

        $tasks = $this->db->prepare(
            'SELECT t.*, wai.name AS area_name, u.name AS unit_name
             FROM tasks t
             JOIN work_area_instances wai ON wai.id = t.work_area_id
             LEFT JOIN units u ON u.id = wai.unit_id
             WHERE t.assigned_to = ? AND t.status != \'done\' AND t.deleted_at IS NULL
             ORDER BY
               t.due_date IS NULL ASC,
               t.due_date ASC,
               FIELD(t.status, \'in_progress\', \'pending\'),
               t.created_at DESC'
        );
        $tasks->execute([$uid]);

        $interviews = $this->db->prepare(
            'SELECT i.*, wai.name AS area_name, u.name AS unit_name, interviewer.name AS interviewer_name
             FROM interviews i
             JOIN work_area_instances wai ON wai.id = i.work_area_id
             LEFT JOIN units u ON u.id = wai.unit_id
             LEFT JOIN users interviewer ON interviewer.id = i.interviewer_id
             WHERE i.interviewer_id = ? AND i.completed = 0 AND i.deleted_at IS NULL
             ORDER BY i.scheduled_date ASC, i.created_at DESC'
        );
        $interviews->execute([$uid]);

        Response::json([
            'tasks'      => $tasks->fetchAll(),
            'interviews' => $interviews->fetchAll(),
        ]);
    }
}
