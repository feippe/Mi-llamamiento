<?php

class CatalogController extends Controller
{
    public function profile(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare(
            'SELECT u.*, s.nombre AS stake_nombre, un.nombre AS unit_nombre, un.tipo AS unit_tipo
             FROM users u
             LEFT JOIN stakes s ON s.id = u.stake_id
             LEFT JOIN units un ON un.id = u.unit_id
             WHERE u.id = ?'
        );
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        Response::json(['profile' => $profile, 'callings' => $this->loadUserCallings($user['id'])]);
    }

    public function updateProfile(): void
    {
        $user = $this->requireUser();
        $input = Request::json();
        $stmt = $this->db->prepare('UPDATE users SET stake_id = ?, unit_id = ?, timezone = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$input['stake_id'] ?? $user['stake_id'], $input['unit_id'] ?? $user['unit_id'], $input['timezone'] ?? $user['timezone'], now_utc(), $user['id']]);
        $this->profile();
    }

    public function stakes(): void
    {
        $this->requireUser();
        Response::json($this->db->query('SELECT * FROM stakes ORDER BY nombre')->fetchAll());
    }

    public function createStake(): void
    {
        $user = $this->requireUser();
        $input = Request::json();
        if (empty($input['nombre'])) {
            Response::error('VALIDATION_ERROR', 'El nombre de la estaca es obligatorio.', 422, ['nombre' => 'obligatorio']);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO stakes (tipo, nombre, created_by, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$input['tipo'] ?? 'estaca', $input['nombre'], $user['id'], now_utc()]);
        Response::json(['id' => (int) $this->db->lastInsertId(), 'tipo' => $input['tipo'] ?? 'estaca', 'nombre' => $input['nombre']], 201);
    }

    public function units(string $stakeId): void
    {
        $this->requireUser();
        $stmt = $this->db->prepare('SELECT * FROM units WHERE stake_id = ? ORDER BY tipo, nombre');
        $stmt->execute([$stakeId]);
        Response::json($stmt->fetchAll());
    }

    public function createUnit(string $stakeId): void
    {
        $user = $this->requireUser();
        $input = Request::json();
        if (empty($input['nombre'])) {
            Response::error('VALIDATION_ERROR', 'El nombre de la unidad es obligatorio.', 422, ['nombre' => 'obligatorio']);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO units (stake_id, tipo, nombre, created_by, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$stakeId, $input['tipo'] ?? 'barrio', $input['nombre'], $user['id'], now_utc()]);
        Response::json(['id' => (int) $this->db->lastInsertId(), 'stake_id' => (int) $stakeId, 'tipo' => $input['tipo'] ?? 'barrio', 'nombre' => $input['nombre']], 201);
    }

    public function serviceAreas(): void
    {
        $this->requireUser();
        $nivel = $_GET['nivel'] ?? null;
        if ($nivel) {
            $stmt = $this->db->prepare('SELECT * FROM service_areas WHERE nivel = ? ORDER BY orden, nombre');
            $stmt->execute([$nivel]);
            Response::json($stmt->fetchAll());
            return;
        }
        Response::json($this->db->query('SELECT * FROM service_areas ORDER BY orden, nombre')->fetchAll());
    }

    public function callings(string $serviceAreaId): void
    {
        $this->requireUser();
        $stmt = $this->db->prepare('SELECT * FROM callings WHERE service_area_id = ? ORDER BY id');
        $stmt->execute([$serviceAreaId]);
        Response::json($stmt->fetchAll());
    }

    public function userCallings(): void
    {
        $user = $this->requireUser();
        Response::json($this->loadUserCallings($user['id']));
    }

    public function createUserCalling(): void
    {
        $user = $this->requireUser();
        $input = Request::json();
        if (empty($input['calling_id'])) {
            Response::error('VALIDATION_ERROR', 'El llamamiento es obligatorio.', 422, ['calling_id' => 'obligatorio']);
            return;
        }

        $calling = $this->calling((int) $input['calling_id']);
        if (!$calling) {
            Response::error('NOT_FOUND', 'El llamamiento no existe.', 404);
            return;
        }

        $id = $input['id'] ?? uuid();
        $stakeId = $input['stake_id'] ?? ($calling['nivel'] === 'estaca' ? $user['stake_id'] : null);
        $unitId = $input['unit_id'] ?? ($calling['nivel'] !== 'estaca' ? $user['unit_id'] : null);
        $now = now_utc();

        $this->db->beginTransaction();
        $stmt = $this->db->prepare('INSERT INTO user_callings (id, user_id, calling_id, unit_id, stake_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $user['id'], $calling['id'], $unitId, $stakeId, $now]);

        $areas = [$calling['service_area_id'] => 'llamamiento'];
        $stmt = $this->db->prepare('SELECT service_area_id FROM calling_overlaps WHERE calling_id = ?');
        $stmt->execute([$calling['id']]);
        foreach ($stmt->fetchAll() as $overlap) {
            $areas[(int) $overlap['service_area_id']] = 'solapamiento';
        }

        $createdAccess = [];
        foreach ($areas as $serviceAreaId => $origin) {
            $area = $this->ensureAreaInstance((int) $serviceAreaId, $stakeId, $unitId);
            $autoApprove = !$this->hasApprover((int) $calling['id'], $area['id'], $user['id']);
            $role = ((int) $calling['es_presidente'] === 1 && $origin === 'llamamiento') ? 'propietario' : 'miembro';
            $state = $autoApprove ? 'activo' : 'pendiente';

            $stmt = $this->db->prepare(
                'INSERT INTO area_members (id, area_instance_id, user_id, rol, estado, origen, user_calling_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE estado = VALUES(estado), rol = IF(rol = "propietario", rol, VALUES(rol)), deleted_at = NULL, updated_at = VALUES(updated_at)'
            );
            $stmt->execute([uuid(), $area['id'], $user['id'], $role, $state, $origin, $id, $now, $now]);

            if (!$autoApprove) {
                $stmt = $this->db->prepare('INSERT INTO service_access_requests (id, user_id, area_instance_id, user_calling_id, estado, created_at) VALUES (?, ?, ?, ?, "pendiente", ?)');
                $stmt->execute([uuid(), $user['id'], $area['id'], $id, $now]);
            }
            $createdAccess[] = ['area_instance_id' => $area['id'], 'nombre' => $area['nombre'], 'estado' => $state, 'origen' => $origin];
        }

        $this->db->commit();
        Response::json(['user_calling' => ['id' => $id, 'calling_id' => (int) $calling['id']], 'accesos_creados' => $createdAccess], 201);
    }

    public function deleteUserCalling(string $id): void
    {
        $user = $this->requireUser();
        $now = now_utc();
        $stmt = $this->db->prepare('UPDATE user_callings SET deleted_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$now, $id, $user['id']]);
        $stmt = $this->db->prepare('UPDATE area_members SET deleted_at = ?, updated_at = ? WHERE user_calling_id = ? AND user_id = ?');
        $stmt->execute([$now, $now, $id, $user['id']]);
        Response::json(null, 204);
    }

    private function loadUserCallings(string $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT uc.*, c.nombre AS calling_nombre, sa.nombre AS area_nombre, sa.nivel
             FROM user_callings uc
             JOIN callings c ON c.id = uc.calling_id
             JOIN service_areas sa ON sa.id = c.service_area_id
             WHERE uc.user_id = ? AND uc.deleted_at IS NULL
             ORDER BY uc.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private function calling(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT c.*, sa.nivel, sa.nombre AS area_nombre FROM callings c JOIN service_areas sa ON sa.id = c.service_area_id WHERE c.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function ensureAreaInstance(int $serviceAreaId, ?int $stakeId, ?int $unitId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM service_areas WHERE id = ?');
        $stmt->execute([$serviceAreaId]);
        $catalog = $stmt->fetch();
        $where = $catalog['nivel'] === 'estaca'
            ? 'service_area_id = ? AND stake_id = ? AND unit_id IS NULL AND deleted_at IS NULL'
            : 'service_area_id = ? AND unit_id = ? AND deleted_at IS NULL';
        $stmt = $this->db->prepare("SELECT * FROM service_area_instances WHERE {$where} LIMIT 1");
        $stmt->execute($catalog['nivel'] === 'estaca' ? [$serviceAreaId, $stakeId] : [$serviceAreaId, $unitId]);
        $area = $stmt->fetch();
        if ($area) {
            return $area;
        }
        $id = uuid();
        $now = now_utc();
        $stmt = $this->db->prepare(
            'INSERT INTO service_area_instances (id, service_area_id, unit_id, stake_id, es_personal, nombre, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?)'
        );
        $stmt->execute([$id, $serviceAreaId, $catalog['nivel'] === 'estaca' ? null : $unitId, $stakeId, $catalog['nombre'], $now, $now]);
        return ['id' => $id, 'nombre' => $catalog['nombre']];
    }

    private function hasApprover(int $callingId, string $areaId, string $requestingUserId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM area_members am
             JOIN user_callings uc ON uc.user_id = am.user_id AND uc.deleted_at IS NULL
             JOIN callings c ON c.id = uc.calling_id
             WHERE am.area_instance_id = ? AND am.estado = "activo" AND am.deleted_at IS NULL
             AND am.user_id <> ? AND (am.rol = "propietario" OR c.es_aprobador_unidad = 1)'
        );
        $stmt->execute([$areaId, $requestingUserId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
