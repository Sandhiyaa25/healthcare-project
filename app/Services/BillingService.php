 
    <?php 
 
namespace App\Services; 
 
use App\Models\Invoice; 
use App\Models\Payment; 
use App\Models\AuditLog; 
use App\Exceptions\ValidationException; 
 
class BillingService 
{ 
    private Invoice           $invoiceModel; 
    private Payment           $paymentModel; 
    private AuditLog          $auditLog; 
    private EncryptionService $encryption; 
 
    // Encrypt notes on invoices; reference_number on payments 
    private const INVOICE_ENCRYPTED = ['notes']; 
    private const PAYMENT_ENCRYPTED = ['reference_number', 'notes']; 
 
    public function __construct() 
    { 
        $this->invoiceModel = new Invoice(); 
        $this->paymentModel = new Payment(); 
        $this->auditLog     = new AuditLog(); 
        $this->encryption   = new EncryptionService(); 
    } 
 
    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array 
    { 
        $invoices = $this->invoiceModel->getAll($tenantId, $filters, $page, $perPage); 
        return array_map([$this, 'decryptInvoice'], $invoices); 
    } 
 
    public function getById(int $id, int $tenantId): array 
    { 
        $invoice = $this->invoiceModel->findById($id, $tenantId); 
        if (!$invoice) { 
            throw new ValidationException('Invoice not found'); 
        } 
        if (is_string($invoice['line_items'])) { 
            $invoice['line_items'] = json_decode($invoice['line_items'], true); 
        } 
        $invoice = $this->decryptInvoice($invoice); 
        $payments = $this->paymentModel->getByInvoice($id, $tenantId); 
        $invoice['payments'] = array_map([$this, 'decryptPayment'], $payments); 
        return $invoice; 
    } 
 
    public function create(array $data, int $tenantId, int $userId, string $ip, string $userAgent): 
array 
    { 
        if (empty($data['patient_id']) || empty($data['amount'])) { 
            throw new ValidationException('Validation failed', [ 
                'patient_id' => empty($data['patient_id']) ? 'Patient ID required' : null, 
                'amount'     => empty($data['amount'])     ? 'Amount required' : null, 
            ]); 
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
 
    public function recordPayment(int $invoiceId, array $data, int $tenantId, int $userId, string $ip, 
string $userAgent): array 
    { 
        $invoice = $this->invoiceModel->findById($invoiceId, $tenantId); 
        if (!$invoice) { 
            throw new ValidationException('Invoice not found'); 
        } 
 
        if ($invoice['status'] === 'paid') { 
            throw new ValidationException('Invoice is already paid'); 
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
 
    // ─── AES helpers 
───────────────────────────────────────────────── 
 
    private function decryptInvoice(array $invoice): array 
    { 
        foreach (self::INVOICE_ENCRYPTED as $field) { 
            if (!empty($invoice[$field])) { 
                $invoice[$field] = $this->encryption->decryptField($invoice[$field]); 
            } 
        } 
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