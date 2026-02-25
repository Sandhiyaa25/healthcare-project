<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;
use Core\Env;

/**
 * AiService — Gemini 2.5 Flash integration.
 *
 * SECURITY PRINCIPLES:
 *  1. De-identification: Patient names, DOB, email, phone, address are NEVER
 *     sent to the external Gemini API. Only clinical data (diagnosis, symptoms,
 *     blood group, allergies, vitals) is transmitted. The patient is referred
 *     to as "the patient" with age + gender only.
 *  2. Audit trail: Every AI request is logged to audit_logs so there is a full
 *     record of who accessed what AI feature, for which patient, and when.
 *  3. HTTPS only: curl uses the default CA bundle; Gemini requires HTTPS.
 */
class AiService
{
    private Patient           $patientModel;
    private MedicalRecord     $recordModel;
    private Prescription      $prescriptionModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    private const MODEL   = 'gemini-2.5-flash';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent';

    // Fields AES-encrypted in the DB that must be decrypted locally before use
    private const PATIENT_ENCRYPTED     = [
        'first_name', 'last_name', 'email', 'phone',
        'address', 'date_of_birth', 'allergies', 'medical_notes',
        'emergency_contact_name', 'emergency_contact_phone',
    ];
    private const RECORD_ENCRYPTED      = ['chief_complaint', 'diagnosis', 'treatment', 'notes'];
    private const RECORD_JSON_ENCRYPTED = ['vital_signs', 'lab_results'];

    // Fields that are PII/direct identifiers — NEVER included in Gemini prompts
    private const PII_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'address',
                                 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone'];

    public function __construct()
    {
        $this->patientModel      = new Patient();
        $this->recordModel       = new MedicalRecord();
        $this->prescriptionModel = new Prescription();
        $this->auditLog          = new AuditLog();
        $this->encryption        = new EncryptionService();
    }

    // ─── 1. Patient Summary ──────────────────────────────────────────────────

    public function patientSummary(int $patientId, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $patient = $this->patientModel->findById($patientId, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found');
        }
        $patient = $this->decryptPatient($patient);

        // Last 10 records and prescriptions for context
        $records       = $this->recordModel->getByPatient($patientId, $tenantId, 1, 10);
        $decRecords    = array_map([$this, 'decryptRecord'], $records);
        $prescriptions = $this->prescriptionModel->getAll($tenantId, ['patient_id' => $patientId], 1, 10);

        // Build de-identified prompt — NO name/DOB/contact sent to Gemini
        $prompt  = $this->buildPatientSummaryPrompt($patient, $decRecords, $prescriptions);
        $summary = $this->callGemini($prompt);

        // Audit log — records who accessed AI summary for which patient
        $this->auditLog->log([
            'user_id'       => $userId,
            'action'        => 'AI_PATIENT_SUMMARY',
            'severity'      => 'info',
            'resource_type' => 'patient',
            'resource_id'   => $patientId,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);

        return [
            'patient_id'   => $patientId,
            'summary'      => $summary,
            'records_used' => count($decRecords),
            'rx_used'      => count($prescriptions),
            'disclaimer'   => 'AI-generated summary. Always verify against source records. Patient identifiers were not transmitted to the AI service.',
        ];
    }

    // ─── 2. Prescription Suggest ─────────────────────────────────────────────

    public function prescriptionSuggest(array $data, int $userId, string $ip, string $userAgent): array
    {
        if (empty($data['diagnosis'])) {
            throw new ValidationException('diagnosis is required');
        }

        $result = $this->callGemini($this->buildPrescriptionPrompt($data));

        $this->auditLog->log([
            'user_id'       => $userId,
            'action'        => 'AI_PRESCRIPTION_SUGGEST',
            'severity'      => 'info',
            'resource_type' => 'prescription',
            'resource_id'   => null,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'new_values'    => ['diagnosis' => $data['diagnosis']],
        ]);

        return [
            'diagnosis'   => $data['diagnosis'],
            'symptoms'    => $data['symptoms'] ?? null,
            'suggestions' => $result,
            'disclaimer'  => 'AI suggestions are for reference only. Always apply clinical judgment before prescribing.',
        ];
    }

    // ─── 3. Symptom Analyzer ─────────────────────────────────────────────────

    public function symptomAnalyze(array $data, int $userId, string $ip, string $userAgent): array
    {
        if (empty($data['symptoms'])) {
            throw new ValidationException('symptoms is required');
        }

        $result = $this->callGemini($this->buildSymptomPrompt($data));

        $this->auditLog->log([
            'user_id'       => $userId,
            'action'        => 'AI_SYMPTOM_ANALYZE',
            'severity'      => 'info',
            'resource_type' => null,
            'resource_id'   => null,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);

        return [
            'symptoms'   => $data['symptoms'],
            'vitals'     => $data['vitals'] ?? null,
            'analysis'   => $result,
            'disclaimer' => 'AI-assisted analysis only. Always confirm with clinical examination and tests.',
        ];
    }

    // ─── 4. Explain Diagnosis ────────────────────────────────────────────────

    public function explainDiagnosis(array $data, int $userId, string $ip, string $userAgent): array
    {
        if (empty($data['diagnosis'])) {
            throw new ValidationException('diagnosis is required');
        }

        // Safe: diagnosis text only — no patient identifiers in this prompt
        $prompt = "You are a helpful medical assistant explaining a diagnosis to a patient in simple, non-medical language.\n\n"
                . "Diagnosis: {$data['diagnosis']}\n"
                . (!empty($data['treatment']) ? "Treatment: {$data['treatment']}\n" : '')
                . "\nExplain clearly:\n"
                . "1. What this condition is (in plain language)\n"
                . "2. Common causes\n"
                . "3. What the treatment involves\n"
                . "4. What the patient should expect during recovery\n"
                . "5. When to seek emergency help\n\n"
                . "Keep it simple, reassuring, and under 300 words. Avoid medical jargon.";

        $result = $this->callGemini($prompt);

        $this->auditLog->log([
            'user_id'       => $userId,
            'action'        => 'AI_EXPLAIN_DIAGNOSIS',
            'severity'      => 'info',
            'resource_type' => null,
            'resource_id'   => null,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);

        return [
            'diagnosis'   => $data['diagnosis'],
            'explanation' => $result,
            'disclaimer'  => 'This explanation is for general understanding only. Consult your doctor for personalised medical advice.',
        ];
    }

    // ─── Gemini API ──────────────────────────────────────────────────────────

    private function callGemini(string $prompt): string
    {
        $apiKey = Env::get('GEMINI_API_KEY', '');
        if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
            throw new ValidationException('Gemini API key not configured. Set GEMINI_API_KEY in your .env file.');
        }

        $url  = self::API_URL . '?key=' . $apiKey;
        $body = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1024],
        ]);

       $ch      = curl_init($url);
       $caInfo  = Env::get('CURL_CAINFO', '');   // set path in .env for local dev only

        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

if (!empty($caInfo)) {
    $curlOpts[CURLOPT_CAINFO] = $caInfo;
}

curl_setopt_array($ch, $curlOpts);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new ValidationException('AI service connection failed: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $msg     = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new ValidationException('Gemini API error: ' . $msg);
        }

        $decoded = json_decode($response, true);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new ValidationException('Unexpected response format from Gemini API');
        }

        return trim($text);
    }

    // ─── Prompt Builders ─────────────────────────────────────────────────────

    /**
     * Build a de-identified patient summary prompt.
     * SECURITY: Name, DOB, email, phone, address are EXCLUDED.
     * Only non-identifying clinical data is sent: age, gender, blood group,
     * allergies, diagnoses, treatments, vitals, prescription medicines.
     */
    private function buildPatientSummaryPrompt(array $patient, array $records, array $prescriptions): string
    {
        // Derive age from DOB (numeric age only — not the actual DOB)
        $age = 'Unknown';
        if (!empty($patient['date_of_birth'])) {
            try {
                $age = (new \DateTime($patient['date_of_birth']))->diff(new \DateTime())->y . ' years';
            } catch (\Throwable) {}
        }

        $p  = "You are a clinical assistant. Summarize the following patient's medical history for a doctor before a consultation.\n\n";
        // De-identified header — NO name, NO DOB, NO contact info
        $p .= "PATIENT: [Anonymised], {$patient['gender']}, {$age}\n";
        $p .= "Blood Group : " . ($patient['blood_group'] ?? 'Unknown') . "\n";
        $p .= "Allergies   : " . ($patient['allergies']   ?? 'None reported') . "\n\n";

        if (!empty($records)) {
            $p .= "MEDICAL RECORDS (most recent first):\n";
            foreach ($records as $i => $r) {
                $p .= ($i + 1) . ". [{$r['record_type']}] {$r['created_at']}\n";
                if (!empty($r['chief_complaint'])) $p .= "   Complaint : {$r['chief_complaint']}\n";
                if (!empty($r['diagnosis']))       $p .= "   Diagnosis : {$r['diagnosis']}\n";
                if (!empty($r['treatment']))       $p .= "   Treatment : {$r['treatment']}\n";
                if (!empty($r['vital_signs']))     $p .= "   Vitals    : " . json_encode($r['vital_signs']) . "\n";
            }
        } else {
            $p .= "MEDICAL RECORDS: None on file.\n";
        }

        if (!empty($prescriptions)) {
            $p .= "\nPRESCRIPTIONS:\n";
            foreach ($prescriptions as $i => $rx) {
                $meds = is_string($rx['medicines']) ? $rx['medicines'] : json_encode($rx['medicines']);
                $p .= ($i + 1) . ". [{$rx['status']}] {$rx['created_at']}: {$meds}\n";
            }
        } else {
            $p .= "\nPRESCRIPTIONS: None on file.\n";
        }

        $p .= "\nWrite a concise clinical summary (under 400 words) covering: overall health status, key conditions, current medications, allergies, and any important notes for the consulting doctor.";

        return $p;
    }

    private function buildPrescriptionPrompt(array $data): string
    {
        $p  = "You are an AI assistant helping a doctor with prescription suggestions.\n\n";
        $p .= "Diagnosis : {$data['diagnosis']}\n";
        if (!empty($data['symptoms']))  $p .= "Symptoms   : {$data['symptoms']}\n";
        if (!empty($data['age']))       $p .= "Patient Age: {$data['age']}\n";
        if (!empty($data['gender']))    $p .= "Gender     : {$data['gender']}\n";
        if (!empty($data['allergies'])) $p .= "Allergies  : {$data['allergies']}\n";
        if (!empty($data['notes']))     $p .= "Notes      : {$data['notes']}\n";

        $p .= "\nSuggest a standard prescription. For each medicine provide:\n"
            . "- Generic name\n- Dosage\n- Frequency\n- Duration\n- Special instructions\n\n"
            . "Also note any important precautions or contraindications.\n"
            . "Keep the response concise and clearly formatted.";

        return $p;
    }

    private function buildSymptomPrompt(array $data): string
    {
        $vitals = '';
        if (!empty($data['vitals'])) {
            $vitals = is_array($data['vitals']) ? json_encode($data['vitals']) : $data['vitals'];
        }

        $p  = "You are a clinical decision support assistant.\n\n";
        $p .= "Symptoms         : {$data['symptoms']}\n";
        if ($vitals)                    $p .= "Vital Signs      : {$vitals}\n";
        if (!empty($data['age']))       $p .= "Patient Age      : {$data['age']}\n";
        if (!empty($data['gender']))    $p .= "Patient Gender   : {$data['gender']}\n";
        if (!empty($data['duration']))  $p .= "Symptom Duration : {$data['duration']}\n";
        if (!empty($data['history']))   $p .= "Medical History  : {$data['history']}\n";

        $p .= "\nProvide:\n"
            . "1. Top 3 possible diagnoses (with likelihood %)\n"
            . "2. Recommended diagnostic tests\n"
            . "3. Red flags / when to escalate\n"
            . "4. Immediate management steps\n\n"
            . "Be concise and clinical.";

        return $p;
    }

    // ─── Decrypt helpers ─────────────────────────────────────────────────────

    private function decryptPatient(array $patient): array
    {
        foreach (self::PATIENT_ENCRYPTED as $field) {
            if (!empty($patient[$field])) {
                $patient[$field] = $this->encryption->decryptField($patient[$field]);
            }
        }
        return $patient;
    }

    private function decryptRecord(array $record): array
    {
        foreach (self::RECORD_ENCRYPTED as $field) {
            if (!empty($record[$field])) {
                $record[$field] = $this->encryption->decryptField($record[$field]);
            }
        }
        foreach (self::RECORD_JSON_ENCRYPTED as $field) {
            if (!empty($record[$field])) {
                $dec            = $this->encryption->decryptField($record[$field]);
                $record[$field] = json_decode($dec, true) ?? null;
            }
        }
        return $record;
    }
}
