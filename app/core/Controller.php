<?php

abstract class Controller
{
    protected PDO $db;
    protected ?array $user = null;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function requireUser(): array
    {
        if ($this->user) {
            return $this->user;
        }

        $token = Request::bearerToken();
        if (!$token) {
            Response::error('UNAUTHORIZED', 'La sesión es obligatoria.', 401);
            exit;
        }

        $stmt = $this->db->prepare(
            'SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.revoked_at IS NULL AND u.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::error('UNAUTHORIZED', 'La sesión no es válida.', 401);
            exit;
        }

        $this->user = $user;
        return $user;
    }

    protected function canAccessArea(string $areaId, bool $owner = false): ?array
    {
        $user = $this->requireUser();
        $sql = 'SELECT * FROM area_members
                WHERE area_instance_id = ? AND user_id = ? AND estado = "activo" AND deleted_at IS NULL';
        if ($owner) {
            $sql .= ' AND rol = "propietario"';
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute([$areaId, $user['id']]);
        $member = $stmt->fetch();
        return $member ?: null;
    }
}
