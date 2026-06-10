<?php

class AccessService
{
    public function __construct(private PDO $db)
    {
    }

    public function ensurePersonalArea(array $user): void
    {
        $stmt = $this->db->prepare('SELECT id FROM work_area_templates WHERE is_personal = 1 LIMIT 1');
        $stmt->execute();
        $templateId = (int) $stmt->fetchColumn();
        $stmt = $this->db->prepare('SELECT id FROM work_area_instances WHERE template_id = ? AND owner_user_id = ? LIMIT 1');
        $stmt->execute([$templateId, $user['id']]);
        $areaId = $stmt->fetchColumn();
        if (!$areaId) {
            $areaId = uuid();
            $stmt = $this->db->prepare('INSERT INTO work_area_instances (id, template_id, owner_user_id, name, created_at, updated_at) VALUES (?, ?, ?, "Personal", ?, ?)');
            $stmt->execute([$areaId, $templateId, $user['id'], now_utc(), now_utc()]);
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM work_area_members WHERE work_area_id = ? AND user_id = ?');
        $stmt->execute([$areaId, $user['id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $stmt = $this->db->prepare('INSERT INTO work_area_members (id, work_area_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, ?, "owner", "active", ?, ?)');
            $stmt->execute([uuid(), $areaId, $user['id'], now_utc(), now_utc()]);
        }
    }

    public function requestCalling(string $userId, int $callingId, int $unitId): array
    {
        $calling = $this->calling($callingId);
        $unit = $this->unit($unitId);
        if (!$calling || !$unit) {
            throw new RuntimeException('Llamamiento o unidad inválidos.');
        }
        if ($calling['scope_type'] === 'stake' && $unit['type'] !== 'stake') {
            throw new RuntimeException('Los llamamientos de estaca deben asignarse a una estaca.');
        }
        if ($calling['scope_type'] !== 'stake' && !in_array($unit['type'], ['ward', 'branch'], true)) {
            throw new RuntimeException('Los llamamientos de barrio/rama deben asignarse a una unidad.');
        }

        $this->db->beginTransaction();
        $userCallingId = uuid();
        $stmt = $this->db->prepare('INSERT INTO user_callings (id, user_id, calling_id, unit_id, status, requested_at) VALUES (?, ?, ?, ?, "pending", ?)');
        $stmt->execute([$userCallingId, $userId, $callingId, $unitId, now_utc()]);

        $areas = $this->createPendingAreaMemberships($userId, $userCallingId, $callingId, $unitId);
        $stmt = $this->db->prepare('INSERT INTO access_requests (id, user_calling_id, status, requested_at) VALUES (?, ?, "pending", ?)');
        $requestId = uuid();
        $stmt->execute([$requestId, $userCallingId, now_utc()]);
        $autoApproved = false;
        if ($this->requesterCanSelfApproveCalling($userId, $unitId) || $this->shouldBootstrapStakePresidency($callingId, $userCallingId)) {
            $this->activateRequest($requestId, $userCallingId, $userId);
            $autoApproved = true;
        }
        $this->db->commit();

        return [
            'request_id' => $requestId,
            'user_calling_id' => $userCallingId,
            'areas' => $areas,
            'status' => $autoApproved ? 'approved' : 'pending',
            'auto_approved' => $autoApproved,
        ];
    }

    public function approveRequest(string $requestId, string $approverId): void
    {
        $request = $this->accessRequest($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('Solicitud inválida.');
        }
        if (!$this->canApprove($approverId, $request['user_calling_id'])) {
            throw new RuntimeException('No tienes permiso para aprobar esta solicitud.');
        }
        $this->db->beginTransaction();
        $this->activateRequest($requestId, $request['user_calling_id'], $approverId);
        $this->db->commit();
    }

    public function rejectRequest(string $requestId, string $approverId): void
    {
        $request = $this->accessRequest($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('Solicitud inválida.');
        }
        if (!$this->canApprove($approverId, $request['user_calling_id'])) {
            throw new RuntimeException('No tienes permiso para rechazar esta solicitud.');
        }
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('UPDATE access_requests SET status = "rejected", resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$approverId, now_utc(), $requestId]);
        $stmt = $this->db->prepare('UPDATE user_callings SET status = "rejected", resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$approverId, now_utc(), $request['user_calling_id']]);
        $stmt = $this->db->prepare('UPDATE work_area_members SET status = "rejected", updated_at = ? WHERE user_calling_id = ?');
        $stmt->execute([now_utc(), $request['user_calling_id']]);
        $this->db->commit();
    }

    public function canAccessArea(string $userId, string $areaId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM work_area_members WHERE user_id = ? AND work_area_id = ? AND status = "active"');
        $stmt->execute([$userId, $areaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function canApprove(string $approverId, string $userCallingId): bool
    {
        $target = $this->userCalling($userCallingId);
        if (!$target) {
            return false;
        }
        $areaIds = $this->areaIdsForUserCalling($userCallingId);
        if ($areaIds) {
            $placeholders = implode(',', array_fill(0, count($areaIds), '?'));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM work_area_members WHERE user_id = ? AND status = 'active' AND work_area_id IN ({$placeholders})");
            $stmt->execute([$approverId, ...$areaIds]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }
        return $this->hasAuthorityScope($approverId, 'stake', null)
            || $this->hasAuthorityScope($approverId, 'unit', (int) $target['unit_id']);
    }

    public function hasAuthorityScope(string $userId, string $scope, ?int $unitId): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM user_callings uc
                JOIN callings c ON c.id = uc.calling_id
                WHERE uc.user_id = ? AND uc.status = "active" AND c.authority_scope = ?';
        $params = [$userId, $scope];
        if ($scope === 'unit' && $unitId) {
            $sql .= ' AND uc.unit_id = ?';
            $params[] = $unitId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function bootstrapFirstStakePresidencyRequestForUser(string $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->lockStakeBootstrap();
            $autoMode = $this->stakePresidencyAutoMode($userId);
            if (!$autoMode) {
                $this->db->commit();
                return false;
            }

            $sql = 'SELECT ar.id AS request_id, uc.id AS user_calling_id, uc.user_id
                 FROM access_requests ar
                 JOIN user_callings uc ON uc.id = ar.user_calling_id
                 JOIN callings c ON c.id = uc.calling_id
                 JOIN organizations o ON o.id = c.organization_id
                 WHERE ar.status = "pending"
                   AND uc.status = "pending"
                   AND c.authority_scope = "stake"
                   AND o.scope_type = "stake"
                   AND o.name = "Presidencia de Estaca"';
            if ($autoMode === 'self') {
                $sql .= ' AND uc.user_id = ' . $this->db->quote($userId);
            }
            $sql .= ' ORDER BY uc.requested_at ASC, ar.requested_at ASC LIMIT 1 FOR UPDATE';
            $stmt = $this->db->query($sql);
            $request = $stmt->fetch();
            if (!$request || $request['user_id'] !== $userId) {
                $this->db->commit();
                return false;
            }

            $this->activateRequest($request['request_id'], $request['user_calling_id'], $userId);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approveSelfApprovablePendingRequestsForUser(string $userId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT ar.id AS request_id, uc.id AS user_calling_id, uc.unit_id
                 FROM access_requests ar
                 JOIN user_callings uc ON uc.id = ar.user_calling_id
                 WHERE ar.status = "pending"
                   AND uc.status = "pending"
                   AND uc.user_id = ?
                 ORDER BY uc.requested_at ASC
                 FOR UPDATE'
            );
            $stmt->execute([$userId]);
            $approved = 0;
            foreach ($stmt->fetchAll() as $request) {
                if (!$this->requesterCanSelfApproveCalling($userId, (int) $request['unit_id'])) {
                    continue;
                }
                $this->activateRequest($request['request_id'], $request['user_calling_id'], $userId);
                $approved++;
            }
            $this->db->commit();
            return $approved;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function createPendingAreaMemberships(string $userId, string $userCallingId, int $callingId, int $unitId): array
    {
        $stmt = $this->db->prepare(
            'SELECT wat.*, cwar.access_role
             FROM calling_work_area_rules cwar
             JOIN work_area_templates wat ON wat.id = cwar.work_area_template_id
             WHERE cwar.calling_id = ? AND wat.active = 1'
        );
        $stmt->execute([$callingId]);
        $areas = [];
        foreach ($stmt->fetchAll() as $template) {
            $area = $this->ensureAreaInstance($template, $unitId);
            $stmt = $this->db->prepare('INSERT INTO work_area_members (id, work_area_id, user_id, user_calling_id, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "pending", ?, ?)');
            $stmt->execute([uuid(), $area['id'], $userId, $userCallingId, $template['access_role'], now_utc(), now_utc()]);
            $areas[] = $area;
        }
        return $areas;
    }

    private function requesterCanSelfApproveCalling(string $userId, int $unitId): bool
    {
        return $this->hasAuthorityScope($userId, 'stake', null)
            || $this->hasAuthorityScope($userId, 'unit', $unitId);
    }

    private function shouldBootstrapStakePresidency(int $callingId, string $userCallingId): bool
    {
        $this->lockStakeBootstrap();

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM callings c
             JOIN organizations o ON o.id = c.organization_id
             WHERE c.id = ?
               AND c.authority_scope = "stake"
               AND o.scope_type = "stake"
               AND o.name = "Presidencia de Estaca"'
        );
        $stmt->execute([$callingId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return false;
        }

        $userCalling = $this->userCalling($userCallingId);
        $autoMode = $userCalling ? $this->stakePresidencyAutoMode($userCalling['user_id']) : null;
        if (!$autoMode) {
            return false;
        }
        if ($autoMode === 'self') {
            return true;
        }

        $stmt = $this->db->query(
            'SELECT uc.id
             FROM user_callings uc
             JOIN callings c ON c.id = uc.calling_id
             JOIN organizations o ON o.id = c.organization_id
             WHERE uc.status = "pending"
               AND c.authority_scope = "stake"
               AND o.scope_type = "stake"
               AND o.name = "Presidencia de Estaca"
             ORDER BY uc.requested_at ASC, uc.id ASC
             LIMIT 1'
        );
        return $stmt->fetchColumn() === $userCallingId;
    }

    private function stakePresidencyAutoMode(string $userId): ?string
    {
        $activeUserIds = $this->activeStakePresidencyUserIds();
        if (count($activeUserIds) === 0) {
            return 'bootstrap';
        }
        if (count($activeUserIds) === 1 && $activeUserIds[0] === $userId) {
            return 'self';
        }
        return null;
    }

    private function activeStakePresidencyUserIds(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT uc.user_id
             FROM user_callings uc
             JOIN callings c ON c.id = uc.calling_id
             JOIN organizations o ON o.id = c.organization_id
             WHERE uc.status = "active"
               AND c.authority_scope = "stake"
               AND o.scope_type = "stake"
               AND o.name = "Presidencia de Estaca"'
        );
        return array_column($stmt->fetchAll(), 'user_id');
    }

    private function lockStakeBootstrap(): void
    {
        $this->db->query('SELECT id FROM units WHERE type = "stake" ORDER BY id ASC LIMIT 1 FOR UPDATE');
    }

    private function activateRequest(string $requestId, string $userCallingId, string $resolverId): void
    {
        $now = now_utc();
        $stmt = $this->db->prepare('UPDATE access_requests SET status = "approved", resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$resolverId, $now, $requestId]);
        $stmt = $this->db->prepare('UPDATE user_callings SET status = "active", resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$resolverId, $now, $userCallingId]);
        $stmt = $this->db->prepare('UPDATE work_area_members SET status = "active", updated_at = ? WHERE user_calling_id = ?');
        $stmt->execute([$now, $userCallingId]);
    }

    private function ensureAreaInstance(array $template, int $unitId): array
    {
        $instanceUnitId = $template['scope_type'] === 'stake' ? $this->stakeIdForUnit($unitId) : $unitId;
        $stmt = $this->db->prepare('SELECT * FROM work_area_instances WHERE template_id = ? AND unit_id = ? LIMIT 1');
        $stmt->execute([$template['id'], $instanceUnitId]);
        $area = $stmt->fetch();
        if ($area) {
            return $area;
        }
        $id = uuid();
        $stmt = $this->db->prepare('INSERT INTO work_area_instances (id, template_id, unit_id, name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $template['id'], $instanceUnitId, $template['name'], now_utc(), now_utc()]);
        return ['id' => $id, 'template_id' => $template['id'], 'unit_id' => $instanceUnitId, 'name' => $template['name']];
    }

    private function stakeIdForUnit(int $unitId): int
    {
        $unit = $this->unit($unitId);
        return $unit['type'] === 'stake' ? (int) $unit['id'] : (int) $unit['parent_unit_id'];
    }

    private function calling(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT c.*, o.scope_type FROM callings c JOIN organizations o ON o.id = c.organization_id WHERE c.id = ? AND c.active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function unit(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM units WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function accessRequest(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM access_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function userCalling(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_callings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function areaIdsForUserCalling(string $userCallingId): array
    {
        $stmt = $this->db->prepare('SELECT work_area_id FROM work_area_members WHERE user_calling_id = ?');
        $stmt->execute([$userCallingId]);
        return array_column($stmt->fetchAll(), 'work_area_id');
    }
}
