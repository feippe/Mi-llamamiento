<?php

class InterviewController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $areaId = $_GET['work_area_id'] ?? '';
        if (!(new AccessService($this->db))->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT i.*, u.name AS interviewer_name
             FROM interviews i
             LEFT JOIN users u ON u.id = i.interviewer_id
             WHERE i.work_area_id = ? AND i.deleted_at IS NULL
             ORDER BY i.completed ASC, i.scheduled_date ASC, i.created_at DESC'
        );
        $stmt->execute([$areaId]);
        Response::json($stmt->fetchAll());
    }

    public function create(): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $input = Request::json();
        $areaId = $input['work_area_id'] ?? '';
        if (!(new AccessService($this->db))->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $interviewee = trim($input['interviewee'] ?? '');
        if ($interviewee === '') {
            Response::error('VALIDATION', 'El nombre del entrevistado es obligatorio.', 422);
            return;
        }
        $date = ($input['scheduled_date'] ?? '') ?: null;
        $id = uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO interviews
             (id, work_area_id, interviewee, scheduled_date, scheduled_time, interviewer_id, notes, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $areaId,
            $interviewee,
            $date,
            ($input['scheduled_time'] ?? '') ?: null,
            ($input['interviewer_id'] ?? '') ?: null,
            trim($input['notes'] ?? '') ?: null,
            $user['id'],
            $user['id'],
            now_utc(),
            now_utc(),
        ]);
        Response::json(['id' => $id], 201);
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $interview = $this->findInterview($id);
        if (!$interview || !(new AccessService($this->db))->canAccessArea($user['id'], $interview['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso a la entrevista.', 403);
            return;
        }
        $input = Request::json();
        $interviewee = trim($input['interviewee'] ?? '');
        if ($interviewee === '') {
            Response::error('VALIDATION', 'El nombre del entrevistado es obligatorio.', 422);
            return;
        }
        $date = ($input['scheduled_date'] ?? '') ?: null;
        $completed = isset($input['completed']) ? (int) $input['completed'] : (int) $interview['completed'];
        $completedAt = $completed ? ($interview['completed_at'] ?: now_utc()) : null;

        $stmt = $this->db->prepare(
            'UPDATE interviews
             SET interviewee = ?, scheduled_date = ?, scheduled_time = ?, interviewer_id = ?,
                 notes = ?, completed = ?, completed_at = ?, updated_by = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $interviewee,
            $date,
            ($input['scheduled_time'] ?? '') ?: null,
            ($input['interviewer_id'] ?? '') ?: null,
            trim($input['notes'] ?? '') ?: null,
            $completed,
            $completedAt,
            $user['id'],
            now_utc(),
            $id,
        ]);
        Response::json(['ok' => true]);
    }

    public function delete(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $interview = $this->findInterview($id);
        if (!$interview || !(new AccessService($this->db))->canAccessArea($user['id'], $interview['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso a la entrevista.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'UPDATE interviews SET deleted_at = ?, updated_by = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([now_utc(), $user['id'], now_utc(), $id]);
        Response::json(['ok' => true]);
    }

    private function findInterview(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM interviews WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
