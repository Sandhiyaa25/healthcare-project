<?php

namespace App\Models;

use Core\Database;
use PDO;

class AuditLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(array $data): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO audit_logs
                (tenant_id, user_id, action, severity, status, resource_type, resource_id,
                 old_values, new_values, ip_address, user_agent)
            VALUES
                (:tenant_id, :user_id, :action, :severity, :status, :resource_type, :resource_id,
                 :old_values, :new_values, :ip_address, :user_agent)
        ');
        $stmt->execute([
            ':tenant_id'     => $data['tenant_id'],
            ':user_id'       => $data['user_id'] ?? null,
            ':action'        => $data['action'],
            ':severity'      => $data['severity'] ?? 'info',
            ':status'        => $data['status'] ?? 'success',
            ':resource_type' => $data['resource_type'] ?? null,
            ':resource_id'   => $data['resource_id'] ?? null,
            ':old_values'    => isset($data['old_values']) ? json_encode($data['old_values']) : null,
            ':new_values'    => isset($data['new_values']) ? json_encode($data['new_values']) : null,
            ':ip_address'    => $data['ip_address'] ?? null,
            ':user_agent'    => $data['user_agent'] ?? null,
        ]);
    }

    public function getByTenant(int $tenantId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where  = ['tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['action'])) {
            $where[]            = 'action = :action';
            $params[':action']  = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where[]              = 'user_id = :user_id';
            $params[':user_id']   = $filters['user_id'];
        }

        if (!empty($filters['severity'])) {
            $where[]               = 'severity = :severity';
            $params[':severity']   = $filters['severity'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = 'SELECT * FROM audit_logs WHERE ' . implode(' AND ', $where)
                . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
