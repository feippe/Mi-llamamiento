<?php

class InterviewController extends Controller
{
    public function pairs(string $areaId): void
    {
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT mp.*, u.nombre AS responsable_nombre
             FROM ministering_pairs mp
             LEFT JOIN users u ON u.id = mp.responsable_id
             WHERE mp.area_instance_id = ? AND mp.deleted_at IS NULL
             ORDER BY mp.integrante_1, mp.integrante_2'
        );
        $stmt->execute([$areaId]);
        Response::json(array_map(fn($pair) => $this->withPairStatus($pair), $stmt->fetchAll()));
    }

    public function createPair(string $areaId): void
    {
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $input = Request::json();
        if (empty($input['integrante_1']) || empty($input['integrante_2'])) {
            Response::error('VALIDATION_ERROR', 'Los dos integrantes son obligatorios.', 422);
            return;
        }
        $id = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO ministering_pairs (id, area_instance_id, integrante_1, integrante_2, responsable_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $areaId, $input['integrante_1'], $input['integrante_2'], $input['responsable_id'] ?? null, $now, $now]);
        Response::json($this->pair($id), 201);
    }

    public function updatePair(string $id): void
    {
        $pair = $this->pair($id);
        if (!$pair || !$this->canAccessArea($pair['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes editar esta pareja.', 403);
            return;
        }
        $input = Request::json();
        $stmt = $this->db->prepare('UPDATE ministering_pairs SET integrante_1 = ?, integrante_2 = ?, responsable_id = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$input['integrante_1'] ?? $pair['integrante_1'], $input['integrante_2'] ?? $pair['integrante_2'], array_key_exists('responsable_id', $input) ? $input['responsable_id'] : $pair['responsable_id'], now_utc(), $id]);
        Response::json($this->pair($id));
    }

    public function deletePair(string $id): void
    {
        $pair = $this->pair($id);
        if (!$pair || !$this->canAccessArea($pair['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes eliminar esta pareja.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE ministering_pairs SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), now_utc(), $id]);
        Response::json(null, 204);
    }

    public function assignPair(string $id): void
    {
        $input = Request::json();
        $this->updatePair($id);
    }

    public function schedulePair(string $id): void
    {
        $user = $this->requireUser();
        $pair = $this->pair($id);
        if (!$pair || !$this->canAccessArea($pair['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes agendar esta entrevista.', 403);
            return;
        }
        $input = Request::json();
        if (empty($input['agendada_para'])) {
            Response::error('VALIDATION_ERROR', 'La fecha y hora son obligatorias.', 422);
            return;
        }
        $interviewId = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO ministering_interviews (id, pair_id, estado, agendada_para, agendada_por, created_at, updated_at)
             VALUES (?, ?, "agendada", ?, ?, ?, ?)'
        );
        $stmt->execute([$interviewId, $id, iso_to_mysql($input['agendada_para']), $user['id'], $now, $now]);
        Response::json(['estado_pareja' => 'agendada', 'agendada_para' => $input['agendada_para']], 201);
    }

    public function recordPairInterview(string $id): void
    {
        $pair = $this->pair($id);
        if (!$pair || !$this->canAccessArea($pair['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes registrar esta entrevista.', 403);
            return;
        }
        $input = Request::json();
        $date = $input['fecha_realizada'] ?? gmdate('Y-m-d');
        $interviewId = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO ministering_interviews (id, pair_id, estado, fecha_realizada, trimestre, created_at, updated_at)
             VALUES (?, ?, "realizada", ?, ?, ?, ?)'
        );
        $stmt->execute([$interviewId, $id, $date, quarter_label($date), $now, $now]);
        Response::json(['estado_pareja' => 'verde', 'trimestre' => quarter_label($date)], 201);
    }

    public function compliance(string $areaId): void
    {
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM ministering_pairs WHERE area_instance_id = ? AND deleted_at IS NULL');
        $stmt->execute([$areaId]);
        $pairs = array_map(fn($pair) => $this->withPairStatus($pair), $stmt->fetchAll());
        $done = count(array_filter($pairs, fn($pair) => $pair['estado_semaforo'] === 'verde'));
        $total = count($pairs);
        Response::json([
            'trimestre_actual' => [
                'etiqueta' => quarter_label(),
                'total' => $total,
                'al_dia' => $done,
                'porcentaje' => $total ? round(($done / $total) * 100) : 0,
                'parejas' => $pairs,
            ],
        ]);
    }

    public function leadership(string $areaId): void
    {
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM leadership_interviews WHERE area_instance_id = ? AND deleted_at IS NULL ORDER BY estado, fecha_objetivo IS NULL, fecha_objetivo');
        $stmt->execute([$areaId]);
        Response::json($stmt->fetchAll());
    }

    public function createLeadership(string $areaId): void
    {
        $user = $this->requireUser();
        if (!$this->canAccessArea($areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $input = Request::json();
        if (empty($input['persona'])) {
            Response::error('VALIDATION_ERROR', 'La persona es obligatoria.', 422, ['persona' => 'obligatorio']);
            return;
        }
        $id = $input['id'] ?? uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO leadership_interviews (id, area_instance_id, creador_id, persona, motivo, fecha_objetivo, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $areaId, $user['id'], $input['persona'], $input['motivo'] ?? null, iso_to_mysql($input['fecha_objetivo'] ?? null), $input['estado'] ?? 'pendiente', $now, $now]);
        Response::json($this->leadershipOne($id), 201);
    }

    public function updateLeadership(string $id): void
    {
        $row = $this->leadershipOne($id);
        if (!$row || !$this->canAccessArea($row['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes editar esta entrevista.', 403);
            return;
        }
        $input = Request::json();
        $stmt = $this->db->prepare('UPDATE leadership_interviews SET persona = ?, motivo = ?, fecha_objetivo = ?, estado = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$input['persona'] ?? $row['persona'], $input['motivo'] ?? $row['motivo'], iso_to_mysql($input['fecha_objetivo'] ?? $row['fecha_objetivo']), $input['estado'] ?? $row['estado'], now_utc(), $id]);
        Response::json($this->leadershipOne($id));
    }

    public function completeLeadership(string $id): void
    {
        $row = $this->leadershipOne($id);
        if (!$row || !$this->canAccessArea($row['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes completar esta entrevista.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE leadership_interviews SET estado = "realizada", updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), $id]);
        Response::json($this->leadershipOne($id));
    }

    public function deleteLeadership(string $id): void
    {
        $row = $this->leadershipOne($id);
        if (!$row || !$this->canAccessArea($row['area_instance_id'])) {
            Response::error('FORBIDDEN', 'No puedes eliminar esta entrevista.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE leadership_interviews SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), now_utc(), $id]);
        Response::json(null, 204);
    }

    private function pair(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ministering_pairs WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function leadershipOne(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM leadership_interviews WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function withPairStatus(array $pair): array
    {
        $quarter = quarter_label();
        $stmt = $this->db->prepare(
            'SELECT * FROM ministering_interviews
             WHERE pair_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$pair['id']]);
        $last = $stmt->fetch();
        $pair['ultima_entrevista'] = $last ?: null;
        $pair['estado_semaforo'] = ($last && $last['estado'] === 'realizada' && $last['trimestre'] === $quarter) ? 'verde' : (($last && $last['estado'] === 'agendada') ? 'amarillo' : 'rojo');
        return $pair;
    }
}
