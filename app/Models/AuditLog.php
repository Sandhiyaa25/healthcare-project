<?php

namespace App\Models;

use Core\Database;
use App\Services\EncryptionService;
use PDO;

class AuditLog
{
    private PDO               $db;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->encryption = new EncryptionService();
    }

    public function log(array $data): void
    {
        // Encrypt old_values and new_values before storing
        $oldValues = isset($data['old_values']) ? json_encode($data['old_values']) : null;
        $newValues = isset($data['new_values']) ? json_encode($data['new_values']) : null;

        if ($oldValues) {
            $oldValues = $this->encryption->encryptField($oldValues);
        }
        if ($newValues) {
            $newValues = $this->encryption->encryptField($newValues);
        }

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
            ':old_values'    => $oldValues,
            ':new_values'    => $newValues,
            ':ip_address'    => $data['ip_address'] ?? null,
            ':user_agent'    => $data['user_agent'] ?? null,
        ]);
    }

    public function getByTenant(int $tenantId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where  = ['tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['action'])) {
            $where[]           = 'action = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[]             = 'user_id = :user_id';
            $params[':user_id']  = $filters['user_id'];
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

        $rows = $stmt->fetchAll();

        // Decrypt old_values and new_values on read
        return array_map(function ($row) {
            if (!empty($row['old_values'])) {
                $decrypted = $this->encryption->decryptField($row['old_values']);
                $row['old_values'] = json_decode($decrypted, true) ?? $decrypted;
            }
            if (!empty($row['new_values'])) {
                $decrypted = $this->encryption->decryptField($row['new_values']);
                $row['new_values'] = json_decode($decrypted, true) ?? $decrypted;
            }
            return $row;
        }, $rows);
    }
}