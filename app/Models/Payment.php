<?php

namespace App\Models;

use Core\Database;
use PDO;

class Payment
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO payments (invoice_id, patient_id, amount, payment_method, reference_number, notes)
            VALUES (:invoice_id, :patient_id, :amount, :payment_method, :reference_number, :notes)
        ');
        $stmt->execute([
            ':invoice_id'       => $data['invoice_id'],
            ':patient_id'       => $data['patient_id'],
            ':amount'           => $data['amount'],
            ':payment_method'   => $data['payment_method'] ?? 'cash',
            ':reference_number' => $data['reference_number'] ?? null,
            ':notes'            => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getByInvoice(int $invoiceId, int $tenantId = 0): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }
}
