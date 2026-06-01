<?php

class AreaController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare(
            'SELECT sai.*, am.rol, am.estado, am.origen, sa.nivel, sa.orden
             FROM area_members am
             JOIN service_area_instances sai ON sai.id = am.area_instance_id
             LEFT JOIN service_areas sa ON sa.id = sai.service_area_id
             WHERE am.user_id = ? AND am.deleted_at IS NULL AND sai.deleted_at IS NULL
             ORDER BY sai.es_personal DESC, am.estado, sa.orden, sai.nombre'
        );
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll());
    }

    public function show(string $id): void
    {
        if (!$this->canAccessArea($id)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM service_area_instances WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $area = $stmt->fetch();
        Response::json(['area' => $area, 'members' => $this->membersForArea($id)]);
    }

    public function delete(string $id): void
    {
        if (!$this->canAccessArea($id, true)) {
            Response::error('FORBIDDEN', 'Solo un propietario puede eliminar el área.', 403);
            return;
        }
        $stmt = $this->db->prepare('SELECT es_personal FROM service_area_instances WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 1) {
            Response::error('VALIDATION_ERROR', 'El área Personal no puede eliminarse.', 422);
            return;
        }
        $now = now_utc();
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('UPDATE service_area_instances SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$now, $now, $id]);
        $stmt = $this->db->prepare('UPDATE area_members SET deleted_at = ?, updated_at = ? WHERE area_instance_id = ?');
        $stmt->execute([$now, $now, $id]);
        $stmt = $this->db->prepare(
            'UPDATE tasks t
             JOIN service_area_instances p ON p.owner_user_id = t.responsable_id AND p.es_personal = 1
             SET t.area_instance_id = p.id, t.updated_at = ?
             WHERE t.area_instance_id = ? AND t.deleted_at IS NULL'
        );
        $stmt->execute([$now, $id]);
        $this->db->commit();
        Response::json(null, 204);
    }

    public function members(string $id): void
    {
        if (!$this->canAccessArea($id)) {
            Response::error('FORBIDDEN', 'No tienes acceso activo a esta área.', 403);
            return;
        }
        Response::json($this->membersForArea($id));
    }

    public function promote(string $id, string $userId): void
    {
        if (!$this->canAccessArea($id, true)) {
            Response::error('FORBIDDEN', 'Solo un propietario puede promover miembros.', 403);
            return;
        }
        $stmt = $this->db->prepare('UPDATE area_members SET rol = "propietario", updated_at = ? WHERE area_instance_id = ? AND user_id = ? AND estado = "activo"');
        $stmt->execute([now_utc(), $id, $userId]);
        Response::json(['user_id' => $userId, 'rol' => 'propietario']);
    }

    public function removeMember(string $id, string $userId): void
    {
        $current = $this->requireUser();
        if (!$this->canAccessArea($id, true)) {
            Response::error('FORBIDDEN', 'Solo un propietario puede remover miembros.', 403);
            return;
        }
        if ($current['id'] === $userId) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM area_members WHERE area_instance_id = ? AND rol = "propietario" AND estado = "activo" AND deleted_at IS NULL');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() <= 1) {
                Response::error('VALIDATION_ERROR', 'No puedes remover al único propietario del área.', 422);
                return;
            }
        }
        $now = now_utc();
        $stmt = $this->db->prepare('UPDATE area_members SET deleted_at = ?, updated_at = ? WHERE area_instance_id = ? AND user_id = ?');
        $stmt->execute([$now, $now, $id, $userId]);
        $stmt = $this->db->prepare('UPDATE tasks SET responsable_id = NULL, updated_at = ? WHERE area_instance_id = ? AND responsable_id = ?');
        $stmt->execute([$now, $id, $userId]);
        Response::json(null, 204);
    }

    public function accessRequests(): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare(
            'SELECT sar.*, sai.nombre AS area_nombre, u.nombre AS usuario_nombre, c.nombre AS calling_nombre
             FROM service_access_requests sar
             JOIN service_area_instances sai ON sai.id = sar.area_instance_id
             JOIN users u ON u.id = sar.user_id
             JOIN user_callings uc ON uc.id = sar.user_calling_id
             JOIN callings c ON c.id = uc.calling_id
             JOIN area_members approver ON approver.area_instance_id = sar.area_instance_id
             WHERE sar.estado = "pendiente" AND approver.user_id = ? AND approver.estado = "activo"
             AND approver.rol = "propietario" AND approver.deleted_at IS NULL'
        );
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll());
    }

    public function approveRequest(string $id): void
    {
        $this->resolveRequest($id, 'aprobada', 'activo');
    }

    public function rejectRequest(string $id): void
    {
        $this->resolveRequest($id, 'rechazada', 'rechazado');
    }

    private function resolveRequest(string $id, string $requestState, string $memberState): void
    {
        $user = $this->requireUser();
        $stmt = $this->db->prepare('SELECT * FROM service_access_requests WHERE id = ? AND estado = "pendiente"');
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        if (!$request || !$this->canAccessArea($request['area_instance_id'], true)) {
            Response::error('FORBIDDEN', 'No puedes resolver esta solicitud.', 403);
            return;
        }
        $now = now_utc();
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('UPDATE service_access_requests SET estado = ?, resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$requestState, $user['id'], $now, $id]);
        $stmt = $this->db->prepare('UPDATE area_members SET estado = ?, updated_at = ? WHERE area_instance_id = ? AND user_id = ? AND user_calling_id = ?');
        $stmt->execute([$memberState, $now, $request['area_instance_id'], $request['user_id'], $request['user_calling_id']]);
        $this->db->commit();
        Response::json(['id' => $id, 'estado' => $requestState]);
    }

    private function membersForArea(string $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT am.*, u.nombre, u.email, u.foto_url
             FROM area_members am
             JOIN users u ON u.id = am.user_id
             WHERE am.area_instance_id = ? AND am.deleted_at IS NULL
             ORDER BY am.rol DESC, u.nombre'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
