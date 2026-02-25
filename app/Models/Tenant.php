<?php

namespace App\Models;

use Core\Database;
use PDO;

class Tenant
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getMaster();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySubdomain(string $subdomain): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int

    {
        $stmt = $this->db->prepare('
            INSERT INTO tenants (name, subdomain, db_name, contact_email, contact_phone, subscription_plan, status, max_users)
            VALUES (:name, :subdomain, :db_name, :contact_email, :contact_phone, :subscription_plan, :status, :max_users)
        ');
        $stmt->execute([
            ':name'              => $data['name'],
            ':subdomain'         => $data['subdomain'],
            ':db_name'           => $data['db_name'],
            ':contact_email'     => $data['contact_email'] ?? null,
            ':contact_phone'     => $data['contact_phone'] ?? null,
            ':subscription_plan' => $data['subscription_plan'] ?? 'trial',
            ':status'            => $data['status'] ?? 'inactive',
            ':max_users'         => $data['max_users'] ?? 10,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE tenants SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]) && $stmt->rowCount() > 0;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE tenants SET name = :name, contact_email = :contact_email,
                contact_phone = :contact_phone, subscription_plan = :subscription_plan,
                max_users = :max_users
            WHERE id = :id
        ');
        return $stmt->execute([
            ':name'              => $data['name'],
            ':contact_email'     => $data['contact_email'] ?? null,
            ':contact_phone'     => $data['contact_phone'] ?? null,
            ':subscription_plan' => $data['subscription_plan'] ?? 'trial',
            ':max_users'         => $data['max_users'] ?? 10,
            ':id'                => $id,
        ]);
    }

    public function getAll(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $offset = ($page - 1) * $perPage;

        if ($status) {
            $stmt = $this->db->prepare(
                'SELECT * FROM tenants WHERE status = :status ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM tenants ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
            );
        }

        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count active users for a tenant by connecting to their isolated DB.
     */
    public function countUsers(int $tenantId): int
    {
        $tenant = $this->findById($tenantId);
        if (!$tenant || empty($tenant['db_name'])) {
            return 0;
        }

        $tenantDb = Database::getTenant($tenant['db_name']);
        $stmt     = $tenantDb->prepare("SELECT COUNT(*) FROM users WHERE status != 'deleted'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
