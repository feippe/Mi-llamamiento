<?php

class TaskController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $areaId = $_GET['work_area_id'] ?? '';
        $service = new AccessService($this->db);
        if (!$service->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT t.*, creator.name AS created_by_name, assignee.name AS assigned_to_name
             FROM tasks t
             JOIN users creator ON creator.id = t.created_by
             LEFT JOIN users assignee ON assignee.id = t.assigned_to
             WHERE t.work_area_id = ? AND t.deleted_at IS NULL
             ORDER BY
               t.due_date IS NULL ASC,
               t.due_date DESC,
               t.start_date IS NULL ASC,
               t.start_date ASC,
               t.assigned_to IS NOT NULL ASC,
               FIELD(t.status, "in_progress", "pending", "done"),
               t.created_at DESC'
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
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            Response::error('VALIDATION', 'El título es obligatorio.', 422);
            return;
        }
        $assignedTo = ($input['assigned_to'] ?? '') ?: null;
        $id = uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (id, work_area_id, title, description, start_date, due_date, status, assigned_to, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $areaId,
            $title,
            trim($input['description'] ?? '') ?: null,
            ($input['start_date'] ?? '') ?: null,
            ($input['due_date'] ?? '') ?: null,
            $input['status'] ?? 'pending',
            $assignedTo,
            $user['id'],
            $user['id'],
            now_utc(),
            now_utc(),
        ]);

        // Push notifications (non-personal areas only)
        $push = new PushService($this->db);
        if (!$push->isPersonalArea($areaId)) {
            if ($assignedTo) {
                $push->sendToUser($assignedTo, 'Nueva tarea asignada', "Se te asignó: $title");
            } else {
                $push->sendToAreaMembers($areaId, $user['id'], 'Nueva tarea sin responsable', $title);
            }
        }

        Response::json(['id' => $id], 201);
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $task = $this->task($id);
        if (!$task || !(new AccessService($this->db))->canAccessArea($user['id'], $task['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso a la tarea.', 403);
            return;
        }
        $input = Request::json();
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            Response::error('VALIDATION', 'El título es obligatorio.', 422);
            return;
        }
        $newAssigned = ($input['assigned_to'] ?? '') ?: null;
        $newStatus   = $input['status'] ?? 'pending';
        $oldAssigned = $task['assigned_to'] ?: null;
        $oldStatus   = $task['status'];

        $stmt = $this->db->prepare(
            'UPDATE tasks SET title = ?, description = ?, start_date = ?, due_date = ?, status = ?, assigned_to = ?, updated_by = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            $title,
            trim($input['description'] ?? '') ?: null,
            ($input['start_date'] ?? '') ?: null,
            ($input['due_date'] ?? '') ?: null,
            $newStatus,
            $newAssigned,
            $user['id'],
            now_utc(),
            $id,
        ]);

        // Push notifications (non-personal areas only)
        $push = new PushService($this->db);
        if (!$push->isPersonalArea($task['work_area_id'])) {
            $statusLabels = ['pending' => 'Pendiente', 'in_progress' => 'En curso', 'done' => 'Hecha'];

            if ($newAssigned && $newAssigned !== $oldAssigned) {
                // New user assigned
                $push->sendToUser($newAssigned, 'Tarea asignada', "Se te asignó: $title");
            } elseif ($newStatus !== $oldStatus && $newAssigned) {
                // Status changed for existing assignee
                $label = $statusLabels[$newStatus] ?? $newStatus;
                $push->sendToUser($newAssigned, 'Estado de tarea actualizado', "$title → $label");
            }
        }

        Response::json(['ok' => true]);
    }

    public function delete(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $task = $this->task($id);
        if (!$task || !(new AccessService($this->db))->canAccessArea($user['id'], $task['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso a la tarea.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE tasks SET deleted_at = ?, updated_by = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), $user['id'], now_utc(), $id]);
        Response::json(['ok' => true]);
    }

    private function task(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
