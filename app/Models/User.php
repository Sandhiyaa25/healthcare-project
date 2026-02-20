<?php

namespace App\Models;

use Core\Database;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = ? AND u.tenant_id = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$email, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.username = ? AND u.tenant_id = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$username, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmailBlindIndex(string $blindIndex, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email_blind_index = ? AND u.tenant_id = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$blindIndex, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ["u.tenant_id = :tenant_id", "u.status != 'deleted'"];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['role_id'])) {
            $where[]              = 'u.role_id = :role_id';
            $params[':role_id']   = $filters['role_id'];
        }

        if (!empty($filters['status'])) {
            $where[]              = 'u.status = :status';
            $params[':status']    = $filters['status'];
        }

        if (!empty($filters['search'])) {
            // Use blind index for email, name search
            $where[]              = '(u.first_name_blind_index = :search_index OR u.last_name_blind_index = :search_index OR u.email_blind_index = :search_index)';
            $params[':search_index'] = $filters['search_blind_index'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = 'SELECT u.*, r.name AS role_name, r.slug AS role_slug
                   FROM users u JOIN roles r ON r.id = u.role_id
                   WHERE ' . implode(' AND ', $where) . '
                   ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO users
                (tenant_id, role_id, username, email, email_blind_index, password_hash,
                 first_name, first_name_blind_index, last_name, last_name_blind_index,
                 phone, status)
            VALUES
                (:tenant_id, :role_id, :username, :email, :email_blind_index, :password_hash,
                 :first_name, :first_name_blind_index, :last_name, :last_name_blind_index,
                 :phone, :status)
        ');
        $stmt->execute([
            ':tenant_id'               => $data['tenant_id'],
            ':role_id'                 => $data['role_id'],
            ':username'                => $data['username'],
            ':email'                   => $data['email'],
            ':email_blind_index'       => $data['email_blind_index'],
            ':password_hash'           => $data['password_hash'],
            ':first_name'              => $data['first_name'] ?? null,
            ':first_name_blind_index'  => $data['first_name_blind_index'] ?? null,
            ':last_name'               => $data['last_name'] ?? null,
            ':last_name_blind_index'   => $data['last_name_blind_index'] ?? null,
            ':phone'                   => $data['phone'] ?? null,
            ':status'                  => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET
                first_name = :first_name, first_name_blind_index = :first_name_blind_index,
                last_name  = :last_name,  last_name_blind_index  = :last_name_blind_index,
                phone = :phone, status = :status, role_id = :role_id
            WHERE id = :id AND tenant_id = :tenant_id
        ');
        return $stmt->execute([
            ':first_name'             => $data['first_name'] ?? null,
            ':first_name_blind_index' => $data['first_name_blind_index'] ?? null,
            ':last_name'              => $data['last_name'] ?? null,
            ':last_name_blind_index'  => $data['last_name_blind_index'] ?? null,
            ':phone'                  => $data['phone'] ?? null,
            ':status'                 => $data['status'] ?? 'active',
            ':role_id'                => $data['role_id'],
            ':id'                     => $id,
            ':tenant_id'              => $tenantId,
        ]);
    }

    public function updatePassword(int $id, int $tenantId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = ?, must_change_password = FALSE WHERE id = ? AND tenant_id = ?"
        );
        return $stmt->execute([$passwordHash, $id, $tenantId]);
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET status = 'deleted' WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public function updateLoginMeta(int $id, string $ip, ?int $failCount = null): void
    {
        if ($failCount !== null) {
            $stmt = $this->db->prepare('UPDATE users SET failed_login_attempts = ?, last_login_ip = ? WHERE id = ?');
            $stmt->execute([$failCount, $ip, $id]);
        } else {
            $stmt = $this->db->prepare('UPDATE users SET last_login = NOW(), last_login_ip = ?, failed_login_attempts = 0 WHERE id = ?');
            $stmt->execute([$ip, $id]);
        }
    }

    public function lockAccount(int $id, int $minutes = 15): void
    {
        $stmt = $this->db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
        $stmt->execute([$minutes, $id]);
    }

    public function setMustChangePassword(int $id, int $tenantId, bool $value): void
    {
        $stmt = $this->db->prepare('UPDATE users SET must_change_password = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$value ? 1 : 0, $id, $tenantId]);
    }
}
