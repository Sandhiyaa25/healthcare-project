<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;

class BillingService
{
    private Invoice           $invoiceModel;
    private Payment           $paymentModel;
    private Patient           $patientModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    // Encrypt notes on invoices; reference_number on payments
    private const INVOICE_ENCRYPTED = ['notes'];
    private const PAYMENT_ENCRYPTED = ['reference_number', 'notes'];

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
        $this->paymentModel = new Payment();
        $this->patientModel = new Patient();
        $this->auditLog     = new AuditLog();
        $this->encryption   = new EncryptionService();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $invoices = $this->invoiceModel->getAll($tenantId, $filters, $page, $perPage);
        return array_map([$this, 'decryptInvoice'], $invoices);
    }

    /**
     * Return only the invoices belonging to the logged-in patient user.
     */
    public function getAllForPatient(int $tenantId, int $userId, int $page, int $perPage): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            return [];
        }
        $invoices = $this->invoiceModel->getAll($tenantId, ['patient_id' => $patient['id']], $page, $perPage);
        return array_map([$this, 'decryptInvoice'], $invoices);
    }

    /**
     * Verify the invoice belongs to the patient user — throws if not.
     */
    public function assertPatientOwnsInvoice(array $invoice, int $userId, int $tenantId): void
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient || (int)$invoice['patient_id'] !== (int)$patient['id']) {
            throw new ValidationException('You can only view your own invoices');
        }
    }

    public function getById(int $id, int $tenantId): array
    {
        $invoice = $this->invoiceModel->findById($id, $tenantId);
        if (!$invoice) {
            throw new ValidationException('Invoice not found');
        }
        $invoice = $this->decryptInvoice($invoice);
        $payments = $this->paymentModel->getByInvoice($id, $tenantId);
        $invoice['payments'] = array_map([$this, 'decryptPayment'], $payments);
        return $invoice;
    }

    public function create(array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $errors = [];
        if (empty($data['patient_id'])) {
            $errors['patient_id'] = 'Patient ID required';
        }
        if (empty($data['amount'])) {
            $errors['amount'] = 'Amount required';
        } elseif (!is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors['amount'] = 'Amount must be a positive number';
        }
        if (isset($data['tax']) && (!is_numeric($data['tax']) || (float)$data['tax'] < 0)) {
            $errors['tax'] = 'Tax must be a non-negative number';
        }
        if (isset($data['discount'])) {
            if (!is_numeric($data['discount']) || (float)$data['discount'] < 0) {
                $errors['discount'] = 'Discount must be a non-negative number';
            } elseif (!empty($data['amount']) && is_numeric($data['amount']) && (float)$data['discount'] > (float)$data['amount']) {
                $errors['discount'] = 'Discount cannot exceed the invoice amount';
            }
        }
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        // Verify patient belongs to tenant (Bug 9)
        $patient = $this->patientModel->findById((int)$data['patient_id'], $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found in this tenant');
        }

        $data['total_amount'] = $data['amount'] + ($data['tax'] ?? 0) - ($data['discount'] ?? 0);

        // Encrypt notes before storing
        if (!empty($data['notes'])) {
            $data['notes'] = $this->encryption->encryptField($data['notes']);
        }

        $invoiceId = $this->invoiceModel->create(array_merge($data, ['tenant_id' => $tenantId]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'INVOICE_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'invoice',
            'resource_id'  => $invoiceId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->getById($invoiceId, $tenantId);
    }

    public function recordPayment(int $invoiceId, array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $invoice = $this->invoiceModel->findById($invoiceId, $tenantId);
        if (!$invoice) {
            throw new ValidationException('Invoice not found');
        }

        if ($invoice['status'] === 'paid') {
            throw new ValidationException('Invoice is already paid');
        }

        // Validate payment amount (Bug 15)
        if (empty($data['amount'])) {
            throw new ValidationException('Validation failed', ['amount' => 'Amount required']);
        }
        if (!is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            throw new ValidationException('Validation failed', ['amount' => 'Amount must be a positive number']);
        }

        // Validate payment covers the invoice total (Bug 7)
        if ((float)$data['amount'] < (float)$invoice['total_amount']) {
            throw new ValidationException(
                'Payment amount must cover the invoice total of ' . $invoice['total_amount']
            );
        }

        // Validate payment_method (Bug 17)
        $allowedMethods = ['cash', 'card', 'UPI', 'bank_transfer', 'insurance'];
        if (empty($data['payment_method']) || !in_array($data['payment_method'], $allowedMethods)) {
            throw new ValidationException('Validation failed', [
                'payment_method' => 'payment_method is required. Allowed: ' . implode(', ', $allowedMethods),
            ]);
        }

        // Encrypt reference_number and notes before storing
        $paymentData = $data;
        foreach (self::PAYMENT_ENCRYPTED as $field) {
            if (!empty($paymentData[$field])) {
                $paymentData[$field] = $this->encryption->encryptField((string)$paymentData[$field]);
            }
        }

        $this->paymentModel->create(array_merge($paymentData, [
            'tenant_id'  => $tenantId,
            'invoice_id' => $invoiceId,
            'patient_id' => $invoice['patient_id'],
        ]));

        $this->invoiceModel->updateStatus($invoiceId, $tenantId, 'paid');

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'PAYMENT_RECORDED',
            'severity'     => 'info',
            'resource_type'=> 'invoice',
            'resource_id'  => $invoiceId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->getById($invoiceId, $tenantId);
    }

    public function getSummary(int $tenantId): array
    {
        return $this->invoiceModel->getSummary($tenantId);
    }

    // ─── AES helpers ─────────────────────────────────────────────────

    private function decryptInvoice(array $invoice): array
    {
        foreach (self::INVOICE_ENCRYPTED as $field) {
            if (!empty($invoice[$field])) {
                $invoice[$field] = $this->encryption->decryptField($invoice[$field]);
            }
        }

        // Decode line_items JSON consistently (fixes Bug 16)
        if (is_string($invoice['line_items'] ?? null)) {
            $invoice['line_items'] = json_decode($invoice['line_items'], true) ?? [];
        }

        // Decrypt and assemble patient_name from individual encrypted columns (fixes Bug 2)
        $pf = $this->encryption->decryptField($invoice['patient_first_name'] ?? null) ?? '';
        $pl = $this->encryption->decryptField($invoice['patient_last_name']  ?? null) ?? '';
        $invoice['patient_name'] = trim($pf . ' ' . $pl);
        unset($invoice['patient_first_name'], $invoice['patient_last_name']);

        return $invoice;
    }

    private function decryptPayment(array $payment): array
    {
        foreach (self::PAYMENT_ENCRYPTED as $field) {
            if (!empty($payment[$field])) {
                $payment[$field] = $this->encryption->decryptField($payment[$field]);
            }
        }
        return $payment;
    }
}
