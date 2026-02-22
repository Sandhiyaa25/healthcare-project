<?php

namespace App\Models;

use Core\Database;
use PDO;

class Invoice
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT i.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name
            FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id
            WHERE i.id = ? AND i.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['i.tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['patient_id'])) {
            $where[]               = 'i.patient_id = :patient_id';
            $params[':patient_id'] = $filters['patient_id'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT i.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name
                   FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id
                   WHERE " . implode(' AND ', $where) . " ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";

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
            INSERT INTO invoices (tenant_id, patient_id, appointment_id, amount, tax, discount, total_amount, status, due_date, notes, line_items)
            VALUES (:tenant_id, :patient_id, :appointment_id, :amount, :tax, :discount, :total_amount, :status, :due_date, :notes, :line_items)
        ');
        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':patient_id'     => $data['patient_id'],
            ':appointment_id' => $data['appointment_id'] ?? null,
            ':amount'         => $data['amount'],
            ':tax'            => $data['tax'] ?? 0,
            ':discount'       => $data['discount'] ?? 0,
            ':total_amount'   => $data['total_amount'],
            ':status'         => 'pending',
            ':due_date'       => $data['due_date'] ?? null,
            ':notes'          => $data['notes'] ?? null,
            ':line_items'     => json_encode($data['line_items'] ?? []),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE invoices SET status = ? WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$status, $id, $tenantId]);
    }

    public function getSummary(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM invoices WHERE tenant_id = ? GROUP BY status
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function getSummaryByDateRange(int $tenantId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM invoices
            WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$tenantId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
}