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

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.username = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmailBlindIndex(string $blindIndex, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email_blind_index = ? AND u.status != 'deleted'
        ");
        $stmt->execute([$blindIndex]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId = 0, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ["u.status != 'deleted'"];
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[]            = 'u.role_id = :role_id';
            $params[':role_id'] = $filters['role_id'];
        }

        if (!empty($filters['status'])) {
            $where[]           = 'u.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]                   = '(u.first_name_blind_index = :search_index OR u.last_name_blind_index = :search_index OR u.email_blind_index = :search_index)';
            $params[':search_index']   = $filters['search_blind_index'];
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
                (role_id, username, email, email_blind_index, password_hash,
                 first_name, first_name_blind_index, last_name, last_name_blind_index,
                 phone, status)
            VALUES
                (:role_id, :username, :email, :email_blind_index, :password_hash,
                 :first_name, :first_name_blind_index, :last_name, :last_name_blind_index,
                 :phone, :status)
        ');
        $stmt->execute([
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

    public function update(int $id, int $tenantId = 0, array $data = []): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET
                first_name = :first_name, first_name_blind_index = :first_name_blind_index,
                last_name  = :last_name,  last_name_blind_index  = :last_name_blind_index,
                phone = :phone, status = :status, role_id = :role_id
            WHERE id = :id
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
        ]);
    }

    public function updatePassword(int $id, int $tenantId = 0, string $passwordHash = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = ?, must_change_password = FALSE WHERE id = ?"
        );
        return $stmt->execute([$passwordHash, $id]);
    }

    public function softDelete(int $id, int $tenantId = 0): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
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

    public function resetLockout(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    public function setMustChangePassword(int $id, int $tenantId = 0, bool $value = true): void
    {
        $stmt = $this->db->prepare('UPDATE users SET must_change_password = ? WHERE id = ?');
        $stmt->execute([$value ? 1 : 0, $id]);
    }
}
