<?php

class TaskController extends Controller
{
    public function index(string $areaId): void
    {
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT t.*, u.nombre AS responsable_nombre
             FROM tasks t
             LEFT JOIN users u ON u.id = t.responsable_id
             WHERE t.area_instance_id = ? AND t.deleted_at IS NULL
             ORDER BY FIELD(t.estado, "pendiente", "en_progreso", "completada"), t.fecha_vencimiento IS NULL, t.fecha_vencimiento'
        );
        $stmt->execute([$areaId]);
        Response::json($stmt->fetchAll());
    }

    public function create(string $areaId): void
    {
        $user = $this->requireUser();
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $input = Request::json();
        if (empty($input['titulo'])) {
            Response::error('VALIDATION_ERROR', 'El título es obligatorio.', 422, ['titulo' => 'obligatorio']);
            return;
        }
        $id = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (id, area_instance_id, creador_id, responsable_id, titulo, descripcion, fecha_inicio, fecha_vencimiento, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $areaId, $user['id'], $input['responsable_id'] ?? null, $input['titulo'], $input['descripcion'] ?? null, $input['fecha_inicio'] ?? null, $input['fecha_vencimiento'] ?? null, $input['estado'] ?? 'pendiente', $now, $now]);
        Response::json($this->task($id), 201);
    }

    public function show(string $id): void
    {
        $task = $this->task($id);
        if (!$task || !$this->canAccessArea($task['area_instance_id'])) {
            Response::error('NOT_FOUND', 'La tarea no existe o no tienes acceso.', 404);
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM subtasks WHERE task_id = ? AND deleted_at IS NULL ORDER BY created_at');
        $stmt->execute([$id]);
        Response::json(['task' => $task, 'subtasks' => $stmt->fetchAll()]);
    }

    public function update(string $id): void
    {
        $task = $this->task($id);
        if (!$task || !$this->canEditTask($task)) {
            Response::error('FORBIDDEN', 'No puedes editar esta tarea.', 403);
            return;
        }
        $input = Request::json();
        $stmt = $this->db->prepare(
            'UPDATE tasks SET responsable_id = ?, titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_vencimiento = ?, estado = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            array_key_exists('responsable_id', $input) ? $input['responsable_id'] : $task['responsable_id'],
            $input['titulo'] ?? $task['titulo'],
            array_key_exists('descripcion', $input) ? $input['descripcion'] : $task['descripcion'],
            array_key_exists('fecha_inicio', $input) ? $input['fecha_inicio'] : $task['fecha_inicio'],
            array_key_exists('fecha_vencimiento', $input) ? $input['fecha_vencimiento'] : $task['fecha_vencimiento'],
            $input['estado'] ?? $task['estado'],
            now_utc(),
            $id,
        ]);
        Response::json($this->task($id));
    }

    public function delete(string $id): void
    {
        $task = $this->task($id);
        if (!$task || !$this->canEditTask($task)) {
            Response::error('FORBIDDEN', 'No puedes eliminar esta tarea.', 403);
            return;
        }
        $now = now_utc();
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('UPDATE tasks SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$now, $now, $id]);
        $stmt = $this->db->prepare('UPDATE subtasks SET deleted_at = ?, updated_at = ? WHERE task_id = ?');
        $stmt->execute([$now, $now, $id]);
        $this->db->commit();
        Response::json(null, 204);
    }

    public function createSubtask(string $taskId): void
    {
        $task = $this->task($taskId);
        if (!$task || !$this->canAccessArea($task['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes crear subtareas aquí.', 403);
            return;
        }
        $input = Request::json();
        if (empty($input['titulo'])) {
            Response::error('VALIDATION_ERROR', 'El título es obligatorio.', 422, ['titulo' => 'obligatorio']);
            return;
        }
        $id = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO subtasks (id, task_id, titulo, descripcion, fecha_inicio, fecha_fin, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $taskId, $input['titulo'], $input['descripcion'] ?? null, $input['fecha_inicio'] ?? null, $input['fecha_fin'] ?? null, $input['estado'] ?? 'pendiente', $now, $now]);
        Response::json($this->subtask($id), 201);
    }

    public function updateSubtask(string $id): void
    {
        $subtask = $this->subtask($id);
        $task = $subtask ? $this->task($subtask['task_id']) : null;
        if (!$task || !$this->canEditTask($task)) {
            Response::error('FORBIDDEN', 'No puedes editar esta subtarea.', 403);
            return;
        }
        $input = Request::json();
        $stmt = $this->db->prepare('UPDATE subtasks SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estado = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$input['titulo'] ?? $subtask['titulo'], $input['descripcion'] ?? $subtask['descripcion'], $input['fecha_inicio'] ?? $subtask['fecha_inicio'], $input['fecha_fin'] ?? $subtask['fecha_fin'], $input['estado'] ?? $subtask['estado'], now_utc(), $id]);
        Response::json($this->subtask($id));
    }

    public function deleteSubtask(string $id): void
    {
        $subtask = $this->subtask($id);
        $task = $subtask ? $this->task($subtask['task_id']) : null;
        if (!$task || !$this->canEditTask($task)) {
            Response::error('FORBIDDEN', 'No puedes eliminar esta subtarea.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE subtasks SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), now_utc(), $id]);
        Response::json(null, 204);
    }

    private function task(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function subtask(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subtasks WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function canEditTask(array $task): bool
    {
        $user = $this->requireUser();
        return $task['creador_id'] === $user['id'] || $this->canAccessArea($task['area_instance_id'], true) !== null;
    }
}
