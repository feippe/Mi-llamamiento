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
        $date        = ($input['scheduled_date'] ?? '') ?: null;
        $interviewer = ($input['interviewer_id'] ?? '') ?: null;
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
            $interviewer,
            trim($input['notes'] ?? '') ?: null,
            $user['id'],
            $user['id'],
            now_utc(),
            now_utc(),
        ]);

        // Push notifications (non-personal areas only)
        $push = new PushService($this->db);
        if (!$push->isPersonalArea($areaId)) {
            if ($interviewer) {
                $dateStr = $date ? " · $date" : '';
                $push->sendToUser($interviewer, 'Entrevista asignada', "$interviewee$dateStr");
            } else {
                $push->sendToAreaMembers($areaId, $user['id'], 'Nueva entrevista sin entrevistador', $interviewee);
            }
        }

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
        $newDate        = ($input['scheduled_date'] ?? '') ?: null;
        $newTime        = ($input['scheduled_time'] ?? '') ?: null;
        $newInterviewer = ($input['interviewer_id'] ?? '') ?: null;
        $newCompleted   = isset($input['completed']) ? (int) $input['completed'] : (int) $interview['completed'];
        $completedAt    = $newCompleted ? ($interview['completed_at'] ?: now_utc()) : null;

        $oldDate        = $interview['scheduled_date'] ?: null;
        $oldTime        = $interview['scheduled_time']  ?: null;
        $oldInterviewer = $interview['interviewer_id']  ?: null;
        $oldCompleted   = (int) $interview['completed'];

        $stmt = $this->db->prepare(
            'UPDATE interviews
             SET interviewee = ?, scheduled_date = ?, scheduled_time = ?, interviewer_id = ?,
                 notes = ?, completed = ?, completed_at = ?, updated_by = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $interviewee,
            $newDate,
            $newTime,
            $newInterviewer,
            trim($input['notes'] ?? '') ?: null,
            $newCompleted,
            $completedAt,
            $user['id'],
            now_utc(),
            $id,
        ]);

        // Push notifications (non-personal areas only)
        $push = new PushService($this->db);
        if (!$push->isPersonalArea($interview['work_area_id'])) {
            if ($newInterviewer && $newInterviewer !== $oldInterviewer) {
                // Newly assigned interviewer
                $dateStr = $newDate ? " · $newDate" : '';
                $push->sendToUser($newInterviewer, 'Entrevista asignada', "$interviewee$dateStr");
            } elseif ($newInterviewer && ($newDate !== $oldDate || $newTime !== $oldTime)) {
                // Same interviewer but date/time changed
                $timeStr = ($newTime && $newTime !== '00:00:00') ? ' ' . substr($newTime, 0, 5) : '';
                $push->sendToUser($newInterviewer, 'Entrevista reprogramada', "$interviewee · " . ($newDate ?? 'sin fecha') . $timeStr);
            }
            if ($newCompleted !== $oldCompleted && $newInterviewer) {
                $label = $newCompleted ? 'marcada como realizada' : 'marcada como pendiente';
                $push->sendToUser($newInterviewer, 'Entrevista actualizada', "$interviewee: $label");
            }
        }

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
