<?php

class MinisteringController extends Controller
{
    public function pairs(): void
    {
        $user = $this->requireUser();
        $areaId = $_GET['work_area_id'] ?? '';
        if (!$areaId || !(new AccessService($this->db))->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $quarter = $_GET['quarter'] ?? $this->currentQuarter();
        $stmt = $this->db->prepare(
            'SELECT mp.id, mp.minister1, mp.minister2, mp.assigned_to,
                    mi.id AS interview_id, mi.scheduled_date, mi.scheduled_time,
                    mi.interviewer_id, mi.notes, mi.completed,
                    u.name AS interviewer_name
             FROM ministering_pairs mp
             LEFT JOIN ministering_interviews mi
               ON mi.pair_id = mp.id AND mi.quarter = ? AND mi.deleted_at IS NULL
             LEFT JOIN users u ON u.id = mi.interviewer_id
             WHERE mp.work_area_id = ? AND mp.active = 1 AND mp.deleted_at IS NULL
             ORDER BY COALESCE(mi.completed, 0) ASC, mp.minister1 ASC'
        );
        $stmt->execute([$quarter, $areaId]);
        Response::json($stmt->fetchAll());
    }

    public function createPair(): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $input = Request::json();
        $areaId = $input['work_area_id'] ?? '';
        $minister1 = trim($input['minister1'] ?? '');
        $assignedTo = trim($input['assigned_to'] ?? '');
        if (!$areaId || $minister1 === '' || $assignedTo === '') {
            Response::error('VALIDATION', 'Datos incompletos.', 422);
            return;
        }
        if (!(new AccessService($this->db))->canAccessArea($user['id'], $areaId)) {
            Response::error('FORBIDDEN', 'No tienes acceso al área.', 403);
            return;
        }
        $id = uuid();
        $now = now_utc();
        $this->db->prepare(
            'INSERT INTO ministering_pairs
             (id, work_area_id, minister1, minister2, assigned_to, active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)'
        )->execute([
            $id, $areaId, $minister1,
            trim($input['minister2'] ?? '') ?: null,
            $assignedTo, $user['id'], $now, $now,
        ]);
        Response::json(['id' => $id], 201);
    }

    public function updatePair(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $pair = $this->findPair($id);
        if (!$pair || !(new AccessService($this->db))->canAccessArea($user['id'], $pair['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso.', 403);
            return;
        }
        $input = Request::json();
        $minister1 = trim($input['minister1'] ?? '');
        $assignedTo = trim($input['assigned_to'] ?? '');
        if ($minister1 === '' || $assignedTo === '') {
            Response::error('VALIDATION', 'Datos incompletos.', 422);
            return;
        }
        $this->db->prepare(
            'UPDATE ministering_pairs SET minister1=?, minister2=?, assigned_to=?, updated_at=? WHERE id=?'
        )->execute([
            $minister1,
            trim($input['minister2'] ?? '') ?: null,
            $assignedTo,
            now_utc(),
            $id,
        ]);
        Response::json(['ok' => true]);
    }

    public function deletePair(string $id): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $pair = $this->findPair($id);
        if (!$pair || !(new AccessService($this->db))->canAccessArea($user['id'], $pair['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso.', 403);
            return;
        }
        $now = now_utc();
        $this->db->prepare(
            'UPDATE ministering_pairs SET active=0, deleted_at=?, updated_at=? WHERE id=?'
        )->execute([$now, $now, $id]);
        Response::json(['ok' => true]);
    }

    public function saveInterview(string $pairId): void
    {
        $this->requireCsrf();
        $user = $this->requireUser();
        $pair = $this->findPair($pairId);
        if (!$pair || !(new AccessService($this->db))->canAccessArea($user['id'], $pair['work_area_id'])) {
            Response::error('FORBIDDEN', 'No tienes acceso.', 403);
            return;
        }
        $input = Request::json();
        $quarter = $input['quarter'] ?? $this->currentQuarter();
        $interviewerId = ($input['interviewer_id'] ?? '') ?: null;
        $scheduledDate = ($input['scheduled_date'] ?? '') ?: null;
        $scheduledTime = ($input['scheduled_time'] ?? '') ?: null;
        $notes = trim($input['notes'] ?? '') ?: null;
        $completed = (int)($input['completed'] ?? 0);
        $now = now_utc();

        $stmt = $this->db->prepare(
            'SELECT id, completed_at FROM ministering_interviews
             WHERE pair_id=? AND quarter=? AND deleted_at IS NULL'
        );
        $stmt->execute([$pairId, $quarter]);
        $existing = $stmt->fetch();

        $completedAt = $completed
            ? ($existing && $existing['completed_at'] ? $existing['completed_at'] : $now)
            : null;

        if ($existing) {
            $this->db->prepare(
                'UPDATE ministering_interviews
                 SET interviewer_id=?, scheduled_date=?, scheduled_time=?, notes=?,
                     completed=?, completed_at=?, updated_by=?, updated_at=?
                 WHERE id=?'
            )->execute([
                $interviewerId, $scheduledDate, $scheduledTime, $notes,
                $completed, $completedAt, $user['id'], $now, $existing['id'],
            ]);
            Response::json(['id' => $existing['id']]);
        } else {
            $id = uuid();
            $this->db->prepare(
                'INSERT INTO ministering_interviews
                 (id, work_area_id, pair_id, quarter, scheduled_date, scheduled_time,
                  interviewer_id, notes, completed, completed_at, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $id, $pair['work_area_id'], $pairId, $quarter,
                $scheduledDate, $scheduledTime, $interviewerId, $notes,
                $completed, $completedAt, $user['id'], $user['id'], $now, $now,
            ]);
            Response::json(['id' => $id], 201);
        }
    }

    private function findPair(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ministering_pairs WHERE id=? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function currentQuarter(): string
    {
        return date('Y') . '-Q' . (int)ceil((int)date('n') / 3);
    }
}
