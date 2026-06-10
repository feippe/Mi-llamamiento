<?php

class CatalogController extends Controller
{
    public function units(): void
    {
        $this->requireUser();
        $stmt = $this->db->query('SELECT * FROM units WHERE active = 1 ORDER BY type, name');
        Response::json($stmt->fetchAll());
    }

    public function organizations(): void
    {
        $this->requireUser();
        $scope = $_GET['scope_type'] ?? null;
        $sql = 'SELECT * FROM organizations WHERE active = 1';
        $params = [];
        if ($scope) {
            $sql .= ' AND scope_type = ?';
            $params[] = $scope;
        }
        $sql .= ' ORDER BY scope_type, sort_order, name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::json($stmt->fetchAll());
    }

    public function callings(): void
    {
        $this->requireUser();
        $organizationId = (int) ($_GET['organization_id'] ?? 0);
        $stmt = $this->db->prepare('SELECT * FROM callings WHERE organization_id = ? AND active = 1 ORDER BY sort_order, name');
        $stmt->execute([$organizationId]);
        Response::json($stmt->fetchAll());
    }
}
