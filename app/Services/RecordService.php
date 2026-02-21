namespace App\Services;


use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;


class RecordService
{
    private MedicalRecord     $recordModel;
    private Patient           $patientModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;


    // AES-encrypted text fields in medical_records table
    private const ENCRYPTED_FIELDS = [
        'chief_complaint', 'diagnosis', 'treatment', 'notes',
    ];


    public function __construct()
    {
        $this->recordModel  = new MedicalRecord();
        $this->patientModel = new Patient();
        $this->auditLog     = new AuditLog();
        $this->encryption   = new EncryptionService();
    }


    public function getByPatient(int $patientId, int $tenantId, int $page, int $perPage): array
    {
        // Verify patient belongs to this tenant
        $patient = $this->patientModel->findById($patientId, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found in your tenant');
        }


        $records = $this->recordModel->getByPatient($patientId, $tenantId, $page, $perPage);


        if (empty($records)) {
            throw new ValidationException('No medical records found for this patient');
        }


        return array_map([$this, 'decryptRecord'], $records);
    }


    public function getById(int $id, int $tenantId): array
    {
        $record = $this->recordModel->findById($id, $tenantId);
        if (!$record) {
            throw new ValidationException('Medical record not found in your tenant');
        }
        return $this->decryptRecord($record);
    }


    public function create(array $data, int $tenantId, int $userId, string $role, string $ip, string $userAgent): array
    {
        // Only doctor and nurse can create
        if (!in_array($role, ['doctor', 'nurse'])) {
            throw new ValidationException('Only doctors and nurses can create medical records');
        }


        if (empty($data['patient_id'])) {
            throw new ValidationException('patient_id is required');
        }


        // Verify patient belongs to THIS tenant
        $patient = $this->patientModel->findById((int) $data['patient_id'], $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found in your tenant. You can only create records for patients in your own tenant.');
        }


        // Encrypt text fields before storing
        $data = $this->encryptFields($data);


        $recordId = $this->recordModel->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'doctor_id' => $userId,
        ]));


        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'MEDICAL_RECORD_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'medical_record',
            'resource_id'  => $recordId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);


        return $this->getById($recordId, $tenantId);
    }


    public function update(int $id, array $data, int $tenantId, int $userId, string $role, string $ip, string $userAgent): array
    {
        // Only doctor and nurse can update
        if (!in_array($role, ['doctor', 'nurse'])) {
            throw new ValidationException('Only doctors and nurses can update medical records');
        }


        $record = $this->recordModel->findById($id, $tenantId);
        if (!$record) {
            throw new ValidationException('Medical record not found in your tenant');
        }


        // Encrypt text fields before storing
        $data = $this->encryptFields($data);


        $this->recordModel->update($id, $tenantId, $data);


        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'MEDICAL_RECORD_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'medical_record',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);


        return $this->getById($id, $tenantId);
    }


    // ─── AES helpers ─────────────────────────────────────────────────


    private function encryptFields(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->encryption->encryptField((string) $data[$field]);
            }
        }
        return $data;
    }


    private function decryptRecord(array $record): array
    {
        // Decrypt AES fields
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($record[$field])) {
                $record[$field] = $this->encryption->decryptField($record[$field]);
            }
        }
        // Decode JSON fields
        if (is_string($record['vital_signs'])) {
            $record['vital_signs'] = json_decode($record['vital_signs'], true);
        }
        if (isset($record['lab_results']) && is_string($record['lab_results'])) {
            $record['lab_results'] = json_decode($record['lab_results'], true);
        }
        return $record;
    }
}
