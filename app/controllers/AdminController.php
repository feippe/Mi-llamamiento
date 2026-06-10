<?php

class AdminController extends Controller
{
    public function catalog(): void
    {
        $this->requireStakeAdmin();
        Response::json([
            'units' => $this->db->query(
                'SELECT u.*, parent.name AS parent_name
                 FROM units u
                 LEFT JOIN units parent ON parent.id = u.parent_unit_id
                 ORDER BY FIELD(u.type, "stake", "ward", "branch"), u.name'
            )->fetchAll(),
            'organizations' => $this->db->query('SELECT * FROM organizations ORDER BY scope_type, sort_order, name')->fetchAll(),
            'callings' => $this->db->query(
                'SELECT c.*, o.name AS organization_name, o.scope_type
                 FROM callings c
                 JOIN organizations o ON o.id = c.organization_id
                 ORDER BY o.scope_type, o.sort_order, c.sort_order, c.name'
            )->fetchAll(),
            'work_area_templates' => $this->db->query(
                'SELECT wat.*, o.name AS organization_name
                 FROM work_area_templates wat
                 LEFT JOIN organizations o ON o.id = wat.organization_id
                 ORDER BY wat.scope_type, wat.sort_order, wat.name'
            )->fetchAll(),
            'rules' => $this->db->query(
                'SELECT cwar.*, c.name AS calling_name, o.name AS organization_name, wat.name AS area_name, wat.scope_type
                 FROM calling_work_area_rules cwar
                 JOIN callings c ON c.id = cwar.calling_id
                 JOIN organizations o ON o.id = c.organization_id
                 JOIN work_area_templates wat ON wat.id = cwar.work_area_template_id
                 ORDER BY o.scope_type, o.sort_order, c.sort_order, wat.sort_order'
            )->fetchAll(),
        ]);
    }

    public function createUnit(): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? '';
        if ($name === '' || !in_array($type, ['stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Tipo y nombre son obligatorios.', 422);
            return;
        }
        $parentId = $type === 'stake' ? null : $this->nullableInt($input['parent_unit_id'] ?? null);
        if ($type !== 'stake' && !$parentId) {
            Response::error('VALIDATION', 'Los barrios y ramas deben tener una estaca asociada.', 422);
            return;
        }
        if ($type !== 'stake' && !$this->activeStakeExists($parentId)) {
            Response::error('VALIDATION', 'La unidad superior debe ser una estaca activa.', 422);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO units (type, name, parent_unit_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $name, $parentId, !empty($input['active']) ? 1 : 0, now_utc(), now_utc()]);
        Response::json(['id' => $this->db->lastInsertId()], 201);
    }

    public function updateUnit(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? '';
        if ($name === '' || !in_array($type, ['stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Tipo y nombre son obligatorios.', 422);
            return;
        }
        $parentId = $type === 'stake' ? null : $this->nullableInt($input['parent_unit_id'] ?? null);
        if ($type !== 'stake' && !$parentId) {
            Response::error('VALIDATION', 'Los barrios y ramas deben tener una estaca asociada.', 422);
            return;
        }
        if ($type !== 'stake' && $parentId === (int) $id) {
            Response::error('VALIDATION', 'Una unidad no puede ser su propia unidad superior.', 422);
            return;
        }
        if ($type !== 'stake' && !$this->activeStakeExists($parentId)) {
            Response::error('VALIDATION', 'La unidad superior debe ser una estaca activa.', 422);
            return;
        }
        $current = $this->unit((int) $id);
        if (!$current) {
            Response::error('NOT_FOUND', 'Unidad no encontrada.', 404);
            return;
        }
        $willDeactivate = empty($input['active']);
        if ($current['type'] === 'stake' && ($type !== 'stake' || $willDeactivate) && $this->hasActiveChildUnits((int) $id)) {
            Response::error('VALIDATION', 'No puedes desactivar o cambiar una estaca con barrios o ramas activas.', 422);
            return;
        }
        if ($current['type'] === 'stake' && $willDeactivate && $this->activeStakeCount() <= 1) {
            Response::error('VALIDATION', 'Debe quedar al menos una estaca activa.', 422);
            return;
        }
        $stmt = $this->db->prepare('UPDATE units SET type = ?, name = ?, parent_unit_id = ?, active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$type, $name, $parentId, !empty($input['active']) ? 1 : 0, now_utc(), (int) $id]);
        Response::json(['ok' => true]);
    }

    public function deleteUnit(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $unit = $this->unit((int) $id);
        if (!$unit) {
            Response::error('NOT_FOUND', 'Unidad no encontrada.', 404);
            return;
        }
        if ($unit['type'] === 'stake' && $this->hasActiveChildUnits((int) $id)) {
            Response::error('VALIDATION', 'No puedes desactivar una estaca con barrios o ramas activas.', 422);
            return;
        }
        if ($unit['type'] === 'stake' && $this->activeStakeCount() <= 1) {
            Response::error('VALIDATION', 'Debe quedar al menos una estaca activa.', 422);
            return;
        }
        $stmt = $this->db->prepare('UPDATE units SET active = 0, updated_at = ? WHERE id = ?');
        $stmt->execute([now_utc(), (int) $id]);
        Response::json(['ok' => true]);
    }

    public function createOrganization(): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $scope = $input['scope_type'] ?? '';
        if ($name === '' || !in_array($scope, ['stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Ámbito y nombre son obligatorios.', 422);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO organizations (scope_type, name, sort_order, active) VALUES (?, ?, ?, ?)');
        $stmt->execute([$scope, $name, (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0]);
        Response::json(['id' => $this->db->lastInsertId()], 201);
    }

    public function updateOrganization(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $scope = $input['scope_type'] ?? '';
        if ($name === '' || !in_array($scope, ['stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Ámbito y nombre son obligatorios.', 422);
            return;
        }
        $stmt = $this->db->prepare('UPDATE organizations SET scope_type = ?, name = ?, sort_order = ?, active = ? WHERE id = ?');
        $stmt->execute([$scope, $name, (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0, (int) $id]);
        Response::json(['ok' => true]);
    }

    public function deleteOrganization(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $stmt = $this->db->prepare('UPDATE organizations SET active = 0 WHERE id = ?');
        $stmt->execute([(int) $id]);
        Response::json(['ok' => true]);
    }

    public function createCalling(): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            Response::error('VALIDATION', 'El nombre es obligatorio.', 422);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO callings (organization_id, name, authority_scope, sort_order, active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([(int) $input['organization_id'], $name, $this->authorityScope($input['authority_scope'] ?? 'none'), (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0]);
        Response::json(['id' => $this->db->lastInsertId()], 201);
    }

    public function updateCalling(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            Response::error('VALIDATION', 'El nombre es obligatorio.', 422);
            return;
        }
        $stmt = $this->db->prepare('UPDATE callings SET organization_id = ?, name = ?, authority_scope = ?, sort_order = ?, active = ? WHERE id = ?');
        $stmt->execute([(int) $input['organization_id'], $name, $this->authorityScope($input['authority_scope'] ?? 'none'), (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0, (int) $id]);
        Response::json(['ok' => true]);
    }

    public function deleteCalling(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $stmt = $this->db->prepare('UPDATE callings SET active = 0 WHERE id = ?');
        $stmt->execute([(int) $id]);
        Response::json(['ok' => true]);
    }

    public function createAreaTemplate(): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $scope = $input['scope_type'] ?? '';
        if ($name === '' || !in_array($scope, ['personal', 'stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Ámbito y nombre son obligatorios.', 422);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO work_area_templates (scope_type, organization_id, name, is_personal, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$scope, $this->nullableInt($input['organization_id'] ?? null), $name, !empty($input['is_personal']) ? 1 : 0, (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0]);
        Response::json(['id' => $this->db->lastInsertId()], 201);
    }

    public function updateAreaTemplate(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $name = trim($input['name'] ?? '');
        $scope = $input['scope_type'] ?? '';
        if ($name === '' || !in_array($scope, ['personal', 'stake', 'ward', 'branch'], true)) {
            Response::error('VALIDATION', 'Ámbito y nombre son obligatorios.', 422);
            return;
        }
        $stmt = $this->db->prepare('UPDATE work_area_templates SET scope_type = ?, organization_id = ?, name = ?, is_personal = ?, sort_order = ?, active = ? WHERE id = ?');
        $stmt->execute([$scope, $this->nullableInt($input['organization_id'] ?? null), $name, !empty($input['is_personal']) ? 1 : 0, (int) ($input['sort_order'] ?? 99), !empty($input['active']) ? 1 : 0, (int) $id]);
        Response::json(['ok' => true]);
    }

    public function deleteAreaTemplate(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $stmt = $this->db->prepare('UPDATE work_area_templates SET active = 0 WHERE id = ?');
        $stmt->execute([(int) $id]);
        Response::json(['ok' => true]);
    }

    public function createRule(): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $role = in_array($input['access_role'] ?? 'member', ['member', 'manager'], true) ? $input['access_role'] : 'member';
        $stmt = $this->db->prepare('INSERT IGNORE INTO calling_work_area_rules (calling_id, work_area_template_id, access_role) VALUES (?, ?, ?)');
        $stmt->execute([(int) $input['calling_id'], (int) $input['work_area_template_id'], $role]);
        Response::json(['ok' => true], 201);
    }

    public function updateRule(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $input = Request::json();
        $role = in_array($input['access_role'] ?? 'member', ['member', 'manager'], true) ? $input['access_role'] : 'member';
        $stmt = $this->db->prepare('UPDATE calling_work_area_rules SET calling_id = ?, work_area_template_id = ?, access_role = ? WHERE id = ?');
        $stmt->execute([(int) $input['calling_id'], (int) $input['work_area_template_id'], $role, (int) $id]);
        Response::json(['ok' => true]);
    }

    public function deleteRule(string $id): void
    {
        $this->requireCsrf();
        $this->requireStakeAdmin();
        $stmt = $this->db->prepare('DELETE FROM calling_work_area_rules WHERE id = ?');
        $stmt->execute([(int) $id]);
        Response::json(['ok' => true]);
    }

    private function requireStakeAdmin(): array
    {
        $user = $this->requireUser();
        if (!(new AccessService($this->db))->hasAuthorityScope($user['id'], 'stake', null)) {
            Response::error('FORBIDDEN', 'Solo la presidencia de estaca puede administrar configuraciones.', 403);
            exit;
        }
        return $user;
    }

    private function authorityScope(string $value): string
    {
        return in_array($value, ['none', 'area', 'unit', 'stake'], true) ? $value : 'none';
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function unit(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM units WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function activeStakeExists(?int $id): bool
    {
        if (!$id) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM units WHERE id = ? AND type = "stake" AND active = 1');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasActiveChildUnits(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM units WHERE parent_unit_id = ? AND active = 1');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function activeStakeCount(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM units WHERE type = "stake" AND active = 1')->fetchColumn();
    }
}
