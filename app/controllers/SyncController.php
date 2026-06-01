<?php

class SyncController extends Controller
{
    private array $syncTables = [
        'tasks' => ['id', 'area_instance_id', 'creador_id', 'responsable_id', 'titulo', 'descripcion', 'fecha_inicio', 'fecha_vencimiento', 'estado', 'created_at', 'updated_at', 'deleted_at'],
        'subtasks' => ['id', 'task_id', 'titulo', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado', 'created_at', 'updated_at', 'deleted_at'],
        'ministering_pairs' => ['id', 'area_instance_id', 'integrante_1', 'integrante_2', 'responsable_id', 'created_at', 'updated_at', 'deleted_at'],
        'ministering_interviews' => ['id', 'pair_id', 'estado', 'agendada_para', 'agendada_por', 'fecha_realizada', 'trimestre', 'created_at', 'updated_at', 'deleted_at'],
        'leadership_interviews' => ['id', 'area_instance_id', 'creador_id', 'persona', 'motivo', 'fecha_objetivo', 'estado', 'created_at', 'updated_at', 'deleted_at'],
    ];

    public function notifications(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll());
    }

    public function readNotification(string $id): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare('UPDATE notifications SET leida = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Response::json(['id' => $id, 'leida' => true]);
    }

    public function push(): void
    {
        $user = $this->requireUser();
        $input = Request::json();
        $applied = [];
        $conflicts = [];

        foreach (($input['changes'] ?? []) as $change) {
            if (empty($change['id']) || empty($change['entidad']) || empty($change['entidad_id']) || !isset($this->syncTables[$change['entidad']])) {
                continue;
            }
            $table = $change['entidad'];
            $payload = $change['payload'] ?? [];
            $clientTime = iso_to_mysql($change['client_timestamp'] ?? null) ?? now_utc();
            $server = $this->findRecord($table, $change['entidad_id']);

            if ($server && !empty($server['updated_at']) && strtotime($server['updated_at']) > strtotime($clientTime)) {
                $conflicts[] = ['id' => $change['id'], 'entidad_id' => $change['entidad_id'], 'resolucion' => 'server_gana', 'registro_servidor' => $server];
                $this->logSync($user['id'], $change, 'conflicto');
                continue;
            }

            if ($change['operacion'] === 'eliminar') {
                $this->softDelete($table, $change['entidad_id'], $clientTime);
            } elseif ($change['operacion'] === 'crear' || $change['operacion'] === 'editar') {
                $this->upsert($table, $change['entidad_id'], $payload, $clientTime, $user['id']);
            }
            $this->logSync($user['id'], $change, 'aplicado');
            $applied[] = $change['id'];
        }

        Response::json(['aplicados' => $applied, 'conflictos' => $conflicts]);
    }

    public function pull(): void
    {
        $this->requireUser();
        $since = iso_to_mysql($_GET['since'] ?? null) ?? '1970-01-01 00:00:00';
        $changes = [];
        foreach (array_keys($this->syncTables) as $table) {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE updated_at > ? ORDER BY updated_at ASC LIMIT 500");
            $stmt->execute([$since]);
            foreach ($stmt->fetchAll() as $row) {
                $changes[] = [
                    'entidad' => $table,
                    'entidad_id' => $row['id'],
                    'operacion' => $row['deleted_at'] ? 'eliminar' : 'editar',
                    'payload' => $row,
                    'server_timestamp' => $row['updated_at'],
                ];
            }
        }
        Response::json(['changes' => $changes, 'server_time' => gmdate('c')]);
    }

    private function findRecord(string $table, string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function softDelete(string $table, string $id, string $time): void
    {
        $stmt = $this->db->prepare("UPDATE {$table} SET deleted_at = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$time, $time, $id]);
    }

    private function upsert(string $table, string $id, array $payload, string $time, string $userId): void
    {
        $columns = array_values(array_filter($this->syncTables[$table], fn($column) => array_key_exists($column, $payload) || $column === 'id'));
        if (!in_array('created_at', $columns, true) && in_array('created_at', $this->syncTables[$table], true)) {
            $columns[] = 'created_at';
            $payload['created_at'] = $time;
        }
        if (!in_array('updated_at', $columns, true)) {
            $columns[] = 'updated_at';
        }
        $payload['id'] = $id;
        $payload['updated_at'] = $time;
        if ($table === 'tasks' && empty($payload['creador_id'])) {
            $payload['creador_id'] = $userId;
            $columns[] = 'creador_id';
        }
        if ($table === 'leadership_interviews' && empty($payload['creador_id'])) {
            $payload['creador_id'] = $userId;
            $columns[] = 'creador_id';
        }
        $columns = array_values(array_unique($columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updates = implode(', ', array_map(fn($column) => "{$column} = VALUES({$column})", array_filter($columns, fn($column) => $column !== 'id')));
        $stmt = $this->db->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}");
        $stmt->execute(array_map(fn($column) => $payload[$column] ?? null, $columns));
    }

    private function logSync(string $userId, array $change, string $state): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sync_queue (id, user_id, entidad, entidad_id, operacion, payload, client_timestamp, server_timestamp, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), server_timestamp = VALUES(server_timestamp)'
        );
        $stmt->execute([
            $change['id'],
            $userId,
            $change['entidad'],
            $change['entidad_id'],
            $change['operacion'] ?? 'editar',
            json_encode($change['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            iso_to_mysql($change['client_timestamp'] ?? null) ?? now_utc(),
            now_utc(),
            $state,
        ]);
    }
}
